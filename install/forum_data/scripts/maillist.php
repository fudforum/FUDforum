#!/usr/local/bin/php -q
<?php
/**
* copyright            : (C) 2001-2010 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id$
*
* This program is free software; you can redistribute it and/or modify it 
* under the terms of the GNU General Public License as published by the 
* Free Software Foundation; version 2 of the License. 
**/

class fud_emsg
{
	var $subject, $body, $headers, $to, $from_email, $from_name, $ip, $msg_id;
	var $subject_cleanup_rgx, $body_cleanup_rgx, $subject_cleanup_rep, $body_cleanup_rep;
	var $user_id;
	var $reply_to_msg_id;
	var $reply_to, $thread_id;
	var $raw_msg;
	var $body_s, $body_sc;
	var $attachments  = array(), $inline_files = array();

	function read_data($data='')
	{
		$this->raw_msg = !$data ? file_get_contents('php://stdin') : $data;
	}

	function split_hdr_body()
	{
		if (!preg_match("!^(.*?)\r?\n\r?\n(.*)!s", $this->raw_msg, $m)) {
			return;
		}

		$this->body = $m[2];
		$this->headers = $m[1];
	}

	function format_header()
	{
		$this->headers = str_replace("\r\n", "\n", $this->headers);
		// Cleanup multiline headers.
		$this->headers = preg_replace("!\n(\t| )+!", ' ', $this->headers);
		$hdr = explode("\n", trim($this->headers));
		$this->headers = array();
		foreach ($hdr as $v) {
			$hk = substr($v, 0, ($p = strpos($v, ':')));
			// Skip non-valid header lines.
			if (!$hk || ++$p == strlen($v) || ($v{$p} != ' ' && $v{$p} != "\t")) {
				continue;
			}

			$hv = substr($v, $p);
			$hk = strtolower(trim($hk));

			if (!isset($this->headers[$hk])) {
				$this->headers[$hk] = decode_header_value($hv);
			} else {
				$this->headers[$hk] .= ' '. decode_header_value($hv);
			}
		}
	}

	function parse_multival_headers($val, $key)
	{
		if (($p = strpos($val, ';')) !== false) {
			$this->headers[$key] = strtolower(trim(substr($val, 0, $p)));
			$val = ltrim(substr($val, $p+1));
			if (!empty($val) && preg_match_all('!([-A-Za-z]+)="?(.*?)"?\s*(?:;|$)!', $val, $m)) {
				$c = count($m[0]);
				for ($i=0; $i<$c; $i++) {
					$this->headers['__other_hdr__'][$key][strtolower($m[1][$i])] = $m[2][$i];
				}
			}
		} else {
			$this->headers[$key] = strtolower(trim($val));
		}
	}

	function handle_content_headers()
	{
		// This functions performs special handling needed for parsing message data.

		if (isset($this->headers['content-type'])) {
			$this->parse_multival_headers($this->headers['content-type'], 'content-type');
		} else {
			$this->headers['content-type'] = 'text/plain';
			$this->headers['__other_hdr__']['content-type']['charset'] = 'utf-8';
		}

		if (isset($this->headers['content-disposition'])) {
			$this->parse_multival_headers($this->headers['content-disposition'], 'content-disposition');
		} else {
			$this->headers['content-disposition'] = 'inline';
		}
		if (isset($this->headers['content-transfer-encoding'])) {
			$this->parse_multival_headers($this->headers['content-transfer-encoding'], 'content-transfer-encoding');
		} else {
			$this->headers['content-transfer-encoding'] = '7bit';
		}
	}

	function boudry_split($boundry, $html)
	{
		// Isolate boundry sections.
		$this->body_sc = 0;
		foreach (explode('--'.$boundry, $this->body) as $p) {
			if (!trim($p)) continue;
			// Parse inidividual body sections.
			$this->body_s[$this->body_sc] = new fud_emsg;
			$this->body_s[$this->body_sc++]->parse_input($html, $p);
		}
	}

	function decode_body($html=0)
	{
		switch ($this->headers['content-type']) {
			case 'text/plain':
				$this->decode_message_body();
				break;

			case 'text/html':
				$this->decode_message_body();
				if (!$html) {
					$this->body = str_ireplace('<p>', "\n\n", $this->body);
					$this->body = str_ireplace(array('<br />', '<br>', '</p>'), "\n", $this->body);
					$this->body = strip_tags($this->body);
				}
				break;

			case 'multipart/parallel': // Apparently same as multipart/mixed but order of body parts does not matter
	                case 'multipart/report': // RFC1892 ( 1st part is human readable, identical to multipart/mixed )
        	        case 'multipart/signed': // PGP or OpenPGP (appear same) ( 1st part is human readable )
			case 'multipart/alternative': // various alternate formats of message most common html or text
			case 'multipart/related': // ignore those, contains urls/links to 'stuff' on the net
 			case 'multipart/mixed':
 			case 'message/rfc822': // *scary*

				if (!isset($this->headers['__other_hdr__']['content-type']['boundary'])) {
					$this->body = '';
					return;
				}
				$this->boudry_split($this->headers['__other_hdr__']['content-type']['boundary'], $html);
				// In some cases in multi-part messages there will only be 1 body,
				// in those situations we assing that body and info to the primary message
				// and hide the fact this was multi-part message.
				if ($this->body_sc == 1) {
					$this->body = $this->body_s[0]->body;
					$this->headers['__other_hdr__'] = $this->body_s[0]->headers['__other_hdr__'];
				} else if ($this->body_sc > 1) {
					// We got many bodies to pick from, Yey!. Lets find something we can use,
					// preference given to 'text/plain' or if not found go for 'text/html'.
					$final_id = $html_id = array();

					for ($i = 0; $i < $this->body_sc; $i++) {
						switch ($this->body_s[$i]->headers['content-type']) {
							case 'text/html':
								$html_id[] = $i;
								break;

							case 'text/plain':
								$final_id[] = $i;
								break;
						}

						// Look if message has any attached files.
						if ($this->body_s[$i]->headers['content-disposition'] == 'attachment' || 
								$this->body_s[$i]->headers['content-disposition'] == 'inline' ||
								isset($this->body_s[$i]->headers['content-id'])) {
							// Determine the file name.
							if (isset($this->body_s[$i]->headers['__other_hdr__']['content-disposition']['filename'])) {
								$file_name = $this->body_s[$i]->headers['__other_hdr__']['content-disposition']['filename'];
							} else if (isset($this->body_s[$i]->headers['__other_hdr__']['content-type']['name'])) {
								$file_name = $this->body_s[$i]->headers['__other_hdr__']['content-type']['name'];
							} else { // No name for file, skipping.
								continue;
							}

							$this->attachments[$file_name] = $this->body_s[$i]->body;
							if (isset($this->body_s[$i]->headers['content-id']) && $this->body_s[$i]->headers['content-disposition'] == 'inline') {
								$this->inline_files[$file_name] = trim($this->body_s[$i]->headers['content-id'], ' <>');
							}
						}
					}
					if (($html || !$final_id) && $html_id) {
						$final_id = $html_id;
					}
					if ($final_id) {
						$this->body = '';
						foreach ($final_id as $fid) {
							$this->body .= $this->body_s[$fid]->body;
							foreach ($this->body_s[$fid]->attachments as $k => $v) {
								$this->attachments[$k] = $v;
							}
							foreach ($this->body_s[$fid]->inline_files as $k => $v) {
								$this->inline_files[$k] = $v;
							}
						}
						if (isset($this->body_s[$final_id[0]]->headers['__other_hdr__'])) {
							$this->headers['__other_hdr__'] = $this->body_s[$final_id[0]]->headers['__other_hdr__'];
						}
						$this->headers['content-type'] = $this->body_s[$final_id[0]]->headers['content-type'];
					} else {
						$this->body = '';
					}
				} else { // Bad mail client didn't format message properly.
					$this->body = '';
				}
				break;

			default:
				$this->decode_message_body();
				break;

			// case 'multipart/digest':  will/can contain many messages, ignore for our perpouse
		}
	}

	function decode_message_body()
	{
		$this->body = decode_string($this->body, $this->headers['content-transfer-encoding'], $this->headers['__other_hdr__']['content-type']['charset']);
	}

	function parse_input($html=0, $data='')
	{
		$this->read_data($data);
		$this->split_hdr_body();
		$this->format_header();
		$this->handle_content_headers();
		$this->decode_body($html);
	}

	function fetch_useful_headers()
	{
		$this->subject = $this->headers['subject'];

		// Attempt to Get Poster's IP from fields commonly used to store it.
		if (isset($this->headers['x-posted-by'])) {
			$this->ip = parse_ip($this->headers['x-posted-by']);
		} else if (isset($this->headers['x-originating-ip'])) {
			$this->ip = parse_ip($this->headers['x-originating-ip']);
		} else if (isset($this->headers['x-senderip'])) {
			$this->ip = parse_ip($this->headers['x-senderip']);
		} else if (isset($this->headers['x-mdremoteip'])) {
			$this->ip = parse_ip($this->headers['x-mdremoteip']);
		} else if (isset($this->headers['received'])) {
			$this->ip = parse_ip($this->headers['received']);
		}

		// Fetch From email and Possible name.
		if (preg_match('!(.*?)<(.*?)>!', $this->headers['from'], $matches)) {
			$this->from_email = trim($matches[2]);

			if (!empty($matches[1])) {
				$matches[1] = trim($matches[1]);
				if ($matches[1][0] == '"' && substr($matches[1], -1) == '"') {
					$this->from_name = substr($matches[1], 1, -1);
				} else {
					$this->from_name = $matches[1];
				}
			} else {
				$this->from_name = $this->from_email;
			}

			if (preg_match('![^A-Za-z0-9\-_ ]!', $this->from_name)) {
				$this->from_name = substr($this->from_email, 0, strpos($this->from_email, '@'));
			}
		} else {
			$this->from_email = trim($this->headers['from']);
			$this->from_name = substr($this->from_email, 0, strpos($this->from_email, '@'));
		}

		if (empty($this->from_email) || empty($this->from_name)) {
			mlist_error_log('No name or email for '.$this->headers['from'], $this->raw_msg);
		}

		if (isset($this->headers['message-id'])) {
			$this->msg_id = substr(trim($this->headers['message-id']), 1, -1);
		} else if (isset($this->headers['x-qmail-scanner-message-id'])) {
			$this->msg_id = substr(trim($this->headers['x-qmail-scanner-message-id']), 1, -1);
		} else {
			mlist_error_log('No message id', $this->raw_msg);
		}

		// This fetches the id of the message if this is a reply to an existing message.
		if (!empty($this->headers['in-reply-to']) && preg_match('!<([^>]+)>$!', trim($this->headers['in-reply-to']), $match)) {
			$this->reply_to_msg_id = $match[1];
		} else if (!empty($this->headers['references']) && preg_match('!<([^>]+)>$!', trim($this->headers['references']), $match)) {
			$this->reply_to_msg_id = $match[1];
		}
	}

	function clean_up_data()
	{
		if ($this->subject_cleanup_rgx) {
			$this->subject = trim(preg_replace($this->subject_cleanup_rgx, $this->subject_cleanup_rep, $this->subject));
		}
		if ($this->body_cleanup_rgx) {
			$this->body = trim(preg_replace($this->body_cleanup_rgx, $this->body_cleanup_rep, $this->body));
		}
	}
}

function mlist_error_log($error, $msg_data, $level='WARNING')
{
	$error_log_path = $GLOBALS['ERROR_PATH'].'.mlist/error_log';
	$err_msg_cpy = $GLOBALS['ERROR_PATH'].'.mlist/'.time().'_'.md5($msg_data);

	if ($level != 'LOG') {
		if (!($fp = fopen($err_msg_cpy, 'wb'))) {
			exit('No perms to write '.$err_msg_cpy."\n");
		}
		fwrite($fp, $msg_data);
		fclose($fp);
	} else {
		$err_msg_cpy = '';
	}

	if (!($fp = fopen($error_log_path, 'ab'))) {
		exit('No perms to write '.$error_log_path."\n");
	}
	fwrite($fp, $level.' :: '.date('r').' :: '.$error.' :: '.$err_msg_cpy."\n");
	fclose($fp);

	if ($level == 'ERROR') {
		exit;
	}
}

function add_attachment($name, $data, $pid)
{
	$tmpfname = tempnam($GLOBALS['TMP'], 'FUDf_');
	$fp = fopen($tmpfname, 'wb');
	$len = fwrite($fp, $data);
	fclose($fp);

	return attach_add(array('name' => basename($name), 'size' => $len, 'tmp_name' => $tmpfname), $pid, 0, 1);
}

/* main */
	define('forum_debug', 1);
	unset($_SERVER['REMOTE_ADDR']);

	if (!ini_get('register_argc_argv')) {
		exit("Enable the 'register_argc_argv' php.ini directive.\n");
	}
	if ($_SERVER['argc'] < 2) {
		exit("Missing Forum ID Parameter.\n");
	}

	if (strncmp($_SERVER['argv'][0], '.', 1)) {
		require (dirname($_SERVER['argv'][0]) . '/GLOBALS.php');
	} else {
		require (getcwd() . '/GLOBALS.php');
	}

	if (!($FUD_OPT_1 & 1)) {
		exit("Forum is currently disabled.\n");
	}

	fud_use('err.inc');
	fud_use('db.inc');
	fud_use('imsg_edt.inc');
	fud_use('th.inc');
	fud_use('th_adm.inc');
	fud_use('wordwrap.inc');
	fud_use('isearch.inc');
	fud_use('replace.inc');
	fud_use('rev_fmt.inc');
	fud_use('iemail.inc');
	fud_use('post_proc.inc');
	fud_use('is_perms.inc');
	fud_use('users.inc');
	fud_use('users_reg.inc');
	fud_use('attach.inc');
	fud_use('rhost.inc');
	fud_use('smiley.inc');
	fud_use('fileio.inc');
	fud_use('mlist.inc', true);
	fud_use('scripts_common.inc', true);

	define('sql_p', $DBHOST_TBL_PREFIX);

	if (is_numeric($_SERVER['argv'][1])) {
		$mlist = db_sab('SELECT * FROM '.sql_p.'mlist WHERE id='.$_SERVER['argv'][1]);
	} else {
		$mlist = db_sab('SELECT * FROM '.sql_p.'mlist WHERE name='._esc($_SERVER['argv'][1]));
	}
	if (!$mlist) {
		exit('Invalid list identifier');
	}

	$CREATE_NEW_USERS = $mlist->mlist_opt & 64;
	$FUD_OPT_2 |= $FUD_OPT_2 &~ (1024|8388608);
	$FUD_OPT_2 |= 128;
	
	/* Set language & locale. */
	$GLOBALS['usr'] = new stdClass();
	list($GLOBALS['usr']->lang, $locale) = db_saq('SELECT lang, locale FROM '.sql_p.'themes WHERE theme_opt=1|2 LIMIT 1');
	$GLOBALS['good_locale'] = setlocale(LC_ALL, $locale);

	$frm = db_sab('SELECT id, forum_opt, message_threshold, (max_attach_size * 1024) AS max_attach_size, max_file_attachments FROM '.sql_p.'forum WHERE id='.$mlist->forum_id);

	/* Fetch messaged form IMAP of POP3 inbox. */
	if ($mlist->mbox_server && $mlist->mbox_user) {
		if (!function_exists('imap_open')) {
			exit('PHP\'s IMAP extension was not detected, mail cannot be fetched.');
		}

		// Get protocol and ensure a port is specified.
		if ($mlist->mbox_type & 1 || $mlist->mbox_type & 4) {
			$protocol = 'IMAP';
			$mlist->mbox_server .= (strpos($mlist->mbox_server, ':') === FALSE) ? ':143' : '';
		} else {
			$protocol = 'POP3';
			$mlist->mbox_server .= (strpos($mlist->mbox_server, ':') === FALSE) ? ':110' : '';
		}

		// Connect and serch for e-mail messages.
		$tls = ($mlist->mbox_type >= 2) ? '/tls/novalidate-cert' : '/novalidate-cert';
		$inbox = '{'. $mlist->mbox_server .'/'. $protocol . $tls . '}INBOX';
		echo "Connecting to mailbox $inbox\n";
		$mbox = @imap_open($inbox, $mlist->mbox_user, $mlist->mbox_pass) or die('Can\'t connect to mailbox: '. imap_last_error());
		$emails = @imap_search($mbox, 'RECENT');
	}

	$done = 0;
	while (!$done) {
		$emsg = new fud_emsg();
		$emsg->subject_cleanup_rgx = $mlist->subject_regex_haystack;
		$emsg->subject_cleanup_rep = $mlist->subject_regex_needle;
		$emsg->body_cleanup_rgx = $mlist->body_regex_haystack;
		$emsg->body_cleanup_rep = $mlist->body_regex_needle;

		if ($mlist->mbox_server && $mlist->mbox_user) {
			/* Fetch message from mailbox and load into the forum. */
			if (empty($emails)) {
				echo "No more mails to process.\n";
				$done = 1;
				continue;
			}
			$email_number = array_pop($emails);
			$email_message = imap_fetchbody($mbox, $email_number, '');
			echo "Laoding message $email_number.";
			$emsg->parse_input($mlist->mlist_opt & 16, $email_message);
			echo " Done. Deleting message.\n";
			imap_delete($mbox, $email_number);
		} else {
			/* Read single message from pipe (stdin) and load into the forum. */
			$done = 1;
			$emsg->parse_input($mlist->mlist_opt & 16);
		}
		
		$emsg->fetch_useful_headers();
		$emsg->clean_up_data();

		$msg_post = new fud_msg_edit;

		/* Check if message was already imported. */
		if ($emsg->msg_id && q_singleval('SELECT m.id FROM '.sql_p.'msg m
						INNER JOIN '.sql_p.'thread t ON t.id=m.thread_id
						WHERE mlist_msg_id='._esc($emsg->msg_id).' AND t.forum_id='.$frm->id)) {
			continue;
		}

		/* Handler for our own messages, which do not need to be imported. */
		if (isset($emsg->headers['x-fudforum']) && preg_match('!'.md5($GLOBALS['WWW_ROOT']).' <([0-9]+)>!', $emsg->headers['x-fudforum'], $m)) {
			q('UPDATE '.sql_p.'msg SET mlist_msg_id='._esc($emsg->msg_id).' WHERE id='.(int)$m[1].' AND mlist_msg_id IS NULL');
			continue;
		}

		$msg_post->post_stamp = !empty($emsg->headers['date']) ? strtotime($emsg->headers['date']) : 0;
		if ($msg_post->post_stamp < 1 || $msg_post->post_stamp > __request_timestamp__) {
			mlist_error_log('Invalid Date', $emsg->raw_msg);
			if (($p = strpos($emsg->headers['received'], '; ')) !== false) {
				$p += 2;
				$msg_post->post_stamp = strtotime(substr($emsg->headers['received'], $p, (strpos($emsg->headers['received'], '00 ', $p) + 2 - $p)));
			}
			if ($msg_post->post_stamp < 1 || $msg_post->post_stamp > __request_timestamp__) {
				$msg_post->post_stamp = __request_timestamp__;
			}
		}

		if (!$emsg->from_email || !$emsg->from_name) {
			$msg_post->poster_id = 0;
		} else {
			$msg_post->poster_id = match_user_to_post($emsg->from_email, $emsg->from_name, $mlist->mlist_opt & 64, $emsg->user_id, $msg_post->post_stamp);
		}

		/* Check if matching user and if not, skip if necessary. */
		if (!$msg_post->poster_id && $mlist->mlist_opt & 128) {
			continue;
		}

		$attach_list = array();
		/* Handle inlined attachments. */
		if ($mlist->mlist_opt & 8) {
			foreach ($emsg->inline_files as $k => $v) {
				if (strpos($emsg->body, 'cid:'.$v) !== false) {
					$id = add_attachment($k, $emsg->attachments[$k], $msg_post->poster_id);
					$attach_list[$id] = $id;
					$emsg->body = str_replace('cid:'.$v, $WWW_ROOT.'index.php?t=getfile&amp;id='.$id, $emsg->body);
				}
				unset($emsg->attachments[$k]);
			}
		}

		$msg_post->body = $emsg->body;

		/* For anonymous users prefix 'contact' link. */
		if (!$msg_post->poster_id) {
			if ($frm->forum_opt & 16) {
				$msg_post->body = '[b]Originally posted by:[/b] [email='.$emsg->from_email.']'.(!empty($emsg->from_name) ? $emsg->from_name : $emsg->from_email)."[/email]\n\n".$msg_post->body;
			} else {
				$msg_post->body = 'Originally posted by: '.str_replace('@', '&#64', $emsg->from_email)."\n\n".$msg_post->body;
			}
		}

		$msg_post->body = apply_custom_replace($msg_post->body);
		if (!($mlist->mlist_opt & 16)) {
			if ($frm->forum_opt & 16) {
				$msg_post->body = tags_to_html($msg_post->body, 0);
			} else {
				$msg_post->body = nl2br($msg_post->body);
			}
		}

		fud_wordwrap($msg_post->body);
		$msg_post->subject = htmlspecialchars(apply_custom_replace($emsg->subject));
		if (!strlen($msg_post->subject)) {
			mlist_error_log('Blank Subject', $emsg->raw_msg);
			$msg_post->subject = '(no subject)';
		}

		$msg_post->ip_addr = $emsg->ip;
		$msg_post->mlist_msg_id = $emsg->msg_id;
		$msg_post->attach_cnt = 0;
		$msg_post->poll_id = 0;
		$msg_post->msg_opt = 1|2;

		// Try to determine whether this message is a reply or a new thread.
		list($msg_post->reply_to, $msg_post->thread_id) = get_fud_reply_id($mlist->mlist_opt & 32, $frm->id, $msg_post->subject, $emsg->reply_to_msg_id);

		$msg_post->add($frm->id, $frm->message_threshold, 0, 0, false);

		// Handle file attachments.
		if ($mlist->mlist_opt & 8) {
			foreach($emsg->attachments as $key => $val) {
				$id = add_attachment($key, $val, $msg_post->poster_id);			
				$attach_list[$id] = $id;
			}
		}
		if ($attach_list) {
			attach_finalize($attach_list, $msg_post->id);
		}

		if (!($mlist->mlist_opt & 1)) {
			$msg_post->approve($msg_post->id);
		}
	}

	if ($mlist->mbox_server && $mlist->mbox_user) {
		@imap_expunge($mbox);
		@imap_close($mbox);
	}
?>
