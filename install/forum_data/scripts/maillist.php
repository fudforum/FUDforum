#!/usr/bin/php -q
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

function log_script_error($error, $msg_data='', $level='WARNING')
{
	// Make copy of message for later investigation.
	$err_msg_cpy = $GLOBALS['ERROR_PATH'] .'.mlist/'. time() .'_'. md5($msg_data);
	if (!empty($msg_data) && $level != 'LOG') {
		$u = umask(0111);
		if (!($fp = fopen($err_msg_cpy, 'wb'))) {
			exit('No perms to write '. $err_msg_cpy ."\n");
		}
		fwrite($fp, $msg_data);
		fclose($fp);
		umask($u);
		$err_msg_cpy = ' @ '. $err_msg_cpy;
	} else {
		$err_msg_cpy = '';
	}

	// Log error message.
	fud_logerror($error . $err_msg_cpy, 'mlist_errors');

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
		exit("Please enable the 'register_argc_argv' php.ini directive.\n");
	}
	if ($_SERVER['argc'] < 2) {
		exit("Please specify the Mailing List identifier parameter.\n");
	}

	if (strncmp($_SERVER['argv'][0], '.', 1)) {
		require (dirname($_SERVER['argv'][0]) .'/GLOBALS.php');
	} else {
		require (getcwd() .'/GLOBALS.php');
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
	fud_use('mime_decode.inc', true);
	fud_use('scripts_common.inc', true);

	define('sql_p', $DBHOST_TBL_PREFIX);

	if (is_numeric($_SERVER['argv'][1])) {
		$config = db_sab('SELECT * FROM '. sql_p .'mlist WHERE id='. $_SERVER['argv'][1]);
	} else {
		$config = db_sab('SELECT * FROM '. sql_p .'mlist WHERE name='. _esc($_SERVER['argv'][1]));
	}
	if (!$config) {
		exit('Invalid mailing list identifier.');
	}

	$CREATE_NEW_USERS = $config->mlist_opt & 64;
	$FUD_OPT_2 |= $FUD_OPT_2 &~ (1024|8388608);
	$FUD_OPT_2 |= 128;

	/* Set language, locale and time zone. */
	$GLOBALS['usr'] = new stdClass();
	list($GLOBALS['usr']->lang, $locale) = db_saq('SELECT lang, locale FROM '. sql_p .'themes WHERE theme_opt='. (1|2) .' LIMIT 1');
	$GLOBALS['good_locale'] = setlocale(LC_ALL, $locale);
	date_default_timezone_set($GLOBALS['SERVER_TZ']);

	$frm = db_sab('SELECT id, forum_opt, message_threshold, (max_attach_size * 1024) AS max_attach_size, max_file_attachments FROM '. sql_p .'forum WHERE id='. $config->forum_id);

	/* Fetch messaged form IMAP of POP3 inbox. */
	if ($config->mbox_server && $config->mbox_user) {
		if (!function_exists('imap_open')) {
			exit('PHP\'s IMAP extension was not detected, mail cannot be fetched.');
		}

		// Setup protocol, port and flags.
		if (!$config->mbox_type) {	// Unsecure POP3 mailbox.
			$protocol = 'POP3';
			$port     = 110;
			$flags    = '';
		} else if ($config->mbox_type & 1) {	// Unsecure IMAP mailbox.
			$protocol = 'IMAP';
			$port     = 143;
			$flags    = '/novalidate-cert';
		} else if ($config->mbox_type & 2) {	// POP3, TLS mode.
			$protocol = 'POP3';
			$port     = 110;
			$flags    = '/tls/novalidate-cert';
		} else if ($config->mbox_type & 4) {	// IMAP, TLS mode.
			$protocol = 'IMAP';
			$port     = 143;
			$flags    = '/tls/novalidate-cert';
		} else if ($config->mbox_type & 8) {	// POP3, SSL mode.
			$protocol = 'POP3';
			$port     = 995;
			$flags    = '/ssl/novalidate-cert';
		} else if ($config->mbox_type & 16) {	// IMAP, SSL mode.
			$protocol = 'IMAP';
			$port     = 993;
			$flags    = '/ssl/novalidate-cert';
		}

		// Add default port if the user didn't specify it.
		$config->mbox_server .= (strpos($config->mbox_server, ':') === FALSE) ? ':'. $port : '';

		// Connect and search for e-mail messages.
		$inbox = '{'. $config->mbox_server .'/'. $protocol . $flags .'}INBOX';
		echo "Connecting to mailbox $inbox\n";
		$mbox = @imap_open($inbox, $config->mbox_user, $config->mbox_pass) or die('Can\'t connect to mailbox: '. imap_last_error());
		// $emails = @imap_search($mbox, 'RECENT');
		$emails = array_reverse(@imap_sort($mbox, 'SORTDATE', 0));
	}

	$done = 0;
	while (!$done) {
		$emsg = new fud_mime_msg();
		$emsg->subject_cleanup_rgx = $config->subject_regex_haystack;
		$emsg->subject_cleanup_rep = $config->subject_regex_needle;
		$emsg->body_cleanup_rgx = $config->body_regex_haystack;
		$emsg->body_cleanup_rep = $config->body_regex_needle;

		if ($config->mbox_server && $config->mbox_user) {
			/* Fetch message from mailbox and load into the forum. */
			if (empty($emails)) {
				echo "No more mails to process.\n";
				$done = 1;
				continue;
			}
			$email_number = array_pop($emails);
			$email_message = imap_fetchbody($mbox, $email_number, '');
			echo 'Load message '. $email_number;
			$emsg->parse_message($email_message, $config->mlist_opt & 16);
			echo ". Done. Delete message.\n";
			imap_delete($mbox, $email_number);
		} else {
			/* Read single message from pipe (stdin) and load into the forum. */
			$email_message = file_get_contents('php://stdin');
			$emsg->parse_message($email_message, $config->mlist_opt & 16);
			$done = 1;
		}

		$emsg->fetch_useful_headers();
		$emsg->clean_up_data();

		$msg_post = new fud_msg_edit;

		/* Check if message was already imported. */
		if ($emsg->msg_id && q_singleval('SELECT m.id FROM '. sql_p .'msg m
						INNER JOIN '. sql_p .'thread t ON t.id=m.thread_id
						WHERE mlist_msg_id='. _esc($emsg->msg_id) .' AND t.forum_id='. $frm->id)) {
			continue;
		}

		/* Handler for our own messages, which do not need to be imported. */
		if (isset($emsg->headers['x-fudforum']) && preg_match('!'. md5($GLOBALS['WWW_ROOT']) .' <([0-9]+)>!', $emsg->headers['x-fudforum'], $m)) {
			q('UPDATE '. sql_p .'msg SET mlist_msg_id='. _esc($emsg->msg_id) .' WHERE id='. (int)$m[1] .' AND mlist_msg_id IS NULL');
			continue;
		}

		$msg_post->post_stamp = !empty($emsg->headers['date']) ? strtotime($emsg->headers['date']) : 0;
		if ($msg_post->post_stamp < 1 || $msg_post->post_stamp > __request_timestamp__) {
			log_script_error('Invalid date', $emsg->raw_msg);
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
			$msg_post->poster_id = match_user_to_post($emsg->from_email, $emsg->from_name, $config->mlist_opt & 64, $emsg->user_id, $msg_post->post_stamp);
		}

		/* Check if matching user and if not, skip if necessary. */
		if (!$msg_post->poster_id && $config->mlist_opt & 128) {
			continue;
		}

		$attach_list = array();
		/* Handle inlined attachments. */
		if ($config->mlist_opt & 8) {
			foreach ($emsg->inline_files as $k => $v) {
				if (strpos($emsg->body, 'cid:'. $v) !== false) {
					$id = add_attachment($k, $emsg->attachments[$k], $msg_post->poster_id);
					$attach_list[$id] = $id;
					$emsg->body = str_replace('cid:'. $v, $WWW_ROOT .'index.php?t=getfile&amp;id='. $id, $emsg->body);
				}
				unset($emsg->attachments[$k]);
			}
		}

		$msg_post->body = $emsg->body;

		/* For anonymous users prefix 'contact' link. */
		if (!$msg_post->poster_id) {
			if ($frm->forum_opt & 16) {
				$msg_post->body = '[b]Originally posted by:[/b] [email='. $emsg->from_email .']'. (!empty($emsg->from_name) ? $emsg->from_name : $emsg->from_email) ."[/email]\n\n". $msg_post->body;
			} else {
				$msg_post->body = 'Originally posted by: '. str_replace('@', '&#64', $emsg->from_email) ."\n\n". $msg_post->body;
			}
		}

		$msg_post->body = apply_custom_replace($msg_post->body);
		if (!($config->mlist_opt & 16)) {
			if ($frm->forum_opt & 16) {
				$msg_post->body = tags_to_html($msg_post->body, 0);
			} else {
				$msg_post->body = nl2br($msg_post->body);
			}
		}

		fud_wordwrap($msg_post->body);
		$msg_post->subject = htmlspecialchars(apply_custom_replace($emsg->subject));
		if (!strlen($msg_post->subject)) {
			log_script_error('Blank subject', $emsg->raw_msg);
			$msg_post->subject = '(no subject)';
		}

		$msg_post->ip_addr = $emsg->ip;
		$msg_post->mlist_msg_id = $emsg->msg_id;
		$msg_post->attach_cnt = 0;
		$msg_post->poll_id = 0;
		$msg_post->msg_opt = 1|2;

		// Try to determine whether this message is a reply or a new thread.
		list($msg_post->reply_to, $msg_post->thread_id) = get_fud_reply_id($config->mlist_opt & 32, $frm->id, $msg_post->subject, $emsg->reply_to_msg_id);

		$msg_post->add($frm->id, $frm->message_threshold, 0, 0, false);

		// Handle file attachments.
		if ($config->mlist_opt & 8) {
			foreach($emsg->attachments as $key => $val) {
				$id = add_attachment($key, $val, $msg_post->poster_id);			
				$attach_list[$id] = $id;
			}
		}
		if ($attach_list) {
			attach_finalize($attach_list, $msg_post->id);
		}

		if (!($config->mlist_opt & 1)) {
			$msg_post->approve($msg_post->id);
		}
	}

	// Close the mailbox.
	if ($config->mbox_server && $config->mbox_user) {
		@imap_expunge($mbox);
		@imap_close($mbox);
	}
?>
