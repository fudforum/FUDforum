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
	$err_msg_cpy = $GLOBALS['ERROR_PATH'] .'.nntp/'. time() .'_'. md5($msg_data);
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
	fud_logerror($error . $err_msg_cpy, 'nntp_errors');

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
	ini_set('memory_limit', '128M');
	define('forum_debug', 1);
	unset($_SERVER['REMOTE_ADDR']);

	if (!ini_get('register_argc_argv')) {
		exit("Please enable the 'register_argc_argv' php.ini directive.\n");
	}
	if ($_SERVER['argc'] < 2) {
		exit("Please specify the NNTP identifier parameter.\n");
	}

	if (strncmp($_SERVER['argv'][0], '.', 1)) {
		require (dirname($_SERVER['argv'][0]) .'/GLOBALS.php');
	} else {
		require (getcwd() .'/GLOBALS.php');
	}

	if (!($FUD_OPT_1 & 1)) {
		exit("Forum is currently disabled.\n");
	}

	/* Disable MODERATE_USER_REGS and FILE_LOCK. */
	$FUD_OPT_2 |= 1024|8388608;
	$FUD_OPT_2 ^= 1024|8388608;

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
	fud_use('rhost.inc');
	fud_use('attach.inc');
	fud_use('fileio.inc');
	fud_use('alt_var.inc');
	fud_use('smiley.inc');
	fud_use('nntp.inc', true);
	fud_use('mime_decode.inc', true);
	fud_use('scripts_common.inc', true);

	define('sql_p', $GLOBALS['DBHOST_TBL_PREFIX']);

	if (is_numeric($_SERVER['argv'][1])) {
		$config = db_sab('SELECT * FROM '. sql_p .'nntp WHERE id='. $_SERVER['argv'][1]);
	} else {
		$config = db_sab('SELECT * FROM '. sql_p .'nntp WHERE newsgroup='. _esc($_SERVER['argv'][1]));
	}
	if (!$config) {
		exit('Invalid NNTP identifier.');
	}

	/* Set language & locale. */
	$GLOBALS['usr'] = new stdClass();
	list($GLOBALS['usr']->lang, $locale) = db_saq('SELECT lang, locale FROM '. sql_p .'themes WHERE theme_opt='. (1|2) .' LIMIT 1');
	$GLOBALS['good_locale'] = setlocale(LC_ALL, $locale);

	/* Try to increase DB timeout to prevent "MySQL server has gone away" errors. */
	if (__dbtype__ == 'mysql') {
		$db_timeout = q_singleval('select @@session.wait_timeout');
		if ($db_timeout < $nntp->timeout) {
			echo 'WARNING: MySQL timeout is smaller than the NNTP Timeout. Will try to increase database timeout.';
			q('SET SESSION wait_timeout = '. $nntp->timeout);
		}
	}

	/* Fetch forum options. */
	$frm = db_sab('SELECT id, forum_opt, message_threshold, (max_attach_size * 1024) AS max_attach_size, max_file_attachments FROM '. sql_p .'forum WHERE id='. $config->forum_id);
		
	$FUD_OPT_2 |= 128;	// Disable USE_ALIASES.

	$nntp = new fud_nntp;
	$nntp->server 		= $config->server;
	$nntp->newsgroup 	= $config->newsgroup;
	$nntp->port 		= $config->port;
	$nntp->timeout 		= $config->timeout;
	$nntp->nntp_opt 	= $config->nntp_opt;
	$nntp->user 		= $config->login;
	$nntp->pass 		= $config->pass;
	
	// Lock, connect and fetch group_first and group_last (message counters).
	$lock = $nntp->get_lock();
	if (!$nntp->connect()) {
		$nntp->exit_handler();
	}

	$nntp->group_last++;
	if ($config->tracker && $config->tracker > $nntp->group_first) {
		$nntp->group_first = $config->tracker;
	}

	$counter = 1;

	for ($i = $nntp->group_first; $i < $nntp->group_last; $i++) {
		echo 'Importing '. $nntp->newsgroup .' message '. $i ."\n";
		if (!$nntp->get_message($i)) {
			$nntp->error = null;
			continue;
		}

		$emsg = new fud_mime_msg();
		$emsg->parse_message($nntp->raw_msg, $frm->forum_opt & 16);

		$emsg->fetch_useful_headers();
		// $emsg->clean_up_data();	// We may want to add subject & body mangling later.

		$msg_post = new fud_msg_edit;

		// Handler for our own messages, which do not need to be imported.
		if (isset($emsg->headers['x-fudforum']) && preg_match('!([A-Za-z0-9]{32}) <([0-9]+)>!', $emsg->headers['x-fudforum'], $m)) {
			if ($m[1] == md5($GLOBALS['WWW_ROOT'])) {
				q('UPDATE '. sql_p .'msg SET mlist_msg_id='. _esc($emsg->msg_id) .' WHERE id='. intval($m[2]) .' AND mlist_msg_id IS NULL');
				if (db_affected()) {
					continue;
				}
			}
		}

		// Handle NNTP cancellation messages.
		if (isset($emsg->headers['control']) && preg_match('!cancel!', $emsg->headers['control'])) {
			log_script_error('Ignore NNTP cancellation message (not implemented).', $emsg->raw_msg);
			// For future implementation. Anyone brave enough to test it for us?
			// q('DELETE FROM '.sql_p.'msg WHERE mlist_msg_id='._esc($emsg->msg_id));
			// if (db_affected()) {
				continue;
			// }
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
			$msg_post->poster_id = match_user_to_post($emsg->from_email, $emsg->from_name, $config->nntp_opt & 32, $emsg->user_id, $msg_post->post_stamp);
		}

		/* Check if matching user and if not, skip if necessary. */
		if (!$msg_post->poster_id && $config->nntp_opt & 256) {
			continue;
		}

		$attach_list = array();
		// Handle NNTP (UUEncoded and Base64) attachments.
		$msg_post->body = $nntp->parse_attachments($msg_post->body);
		if (isset($nntp->attachments) && is_array($nntp->attachments)) {
			foreach($nntp->attachments as $key => $val) {
				if (!($config->nntp_opt & 8) && (strlen($val) > $frm->max_attach_size || (isset($attach_list) && count($attach_list) > $frm->max_file_attachments) || filter_ext($key))) {
					continue;
				}
				$id = add_attachment($key, $val, $msg_post->poster_id);
				$attach_list[$id] = $id;
				unset($nntp->attachments[$k]);
			}
		}

		/* Handle inlined attachments. */
		if ($config->nntp_opt & 8) {
			foreach ($emsg->inline_files as $key => $val) {
				if (strpos($emsg->body, 'cid:'. $val) !== false) {
					$id = add_attachment($key, $emsg->attachments[$key], $msg_post->poster_id);
					$attach_list[$id] = $id;
					$emsg->body = str_replace('cid:'. $val, $WWW_ROOT .'index.php?t=getfile&amp;id='. $id, $emsg->body);
				}
				unset($emsg->attachments[$key]);
			}
		}

		$msg_post->body = trim($emsg->body);

		/* For anonymous users prefix 'contact' link. */
		if (!$msg_post->poster_id) {
			if ($frm->forum_opt & 16) {
				$msg_post->body = '[b]Originally posted by:[/b] [email='. $emsg->from_email .']'. (!empty($emsg->from_name) ? $emsg->from_name : $emsg->from_email) ."[/email]\n\n". $msg_post->body;
			} else {
				$msg_post->body = 'Originally posted by: '. str_replace('@', '&#64', $emsg->from_email) ."\n\n". $msg_post->body;
			}
		}

		$msg_post->body = apply_custom_replace($msg_post->body);
		if ($frm->forum_opt & 16) {
			$msg_post->body = tags_to_html($msg_post->body, 0);
		} else {
			$msg_post->body = nl2br($msg_post->body);
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
		list($msg_post->reply_to, $msg_post->thread_id) = get_fud_reply_id(($config->nntp_opt & 16), $frm->id, $msg_post->subject, $emsg->reply_to_msg_id);

		$msg_post->add($frm->id, $frm->message_threshold, 0, 0, false);

		// Handle file attachments.
		if ($config->nntp_opt & 8) {
			foreach($emsg->attachments as $key => $val) {
				$id = add_attachment($key, $val, $msg_post->poster_id);			
				$attach_list[$id] = $id;
			}
		}
		if ($attach_list) {
			attach_finalize($attach_list, $msg_post->id);
		}

		if (!($config->nntp_opt & 1)) {
			fud_msg_edit::approve($msg_post->id);
		}

		unset($emsg);
		unset($msg_post);

		/* Message import limit reached. */
		if ($config->imp_limit && $counter++ >= $config->imp_limit) {
			break;
		}
	}

	// Store current position.
	$nntp->set_tracker_end($config->id, $i); // We use $i so we stop in the right place if limit is reached.
 	$nntp->exit_handler();

	$nntp->release_lock($lock);
	$nntp->close_connection();
?>
