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
		require (dirname($_SERVER['argv'][0]) . '/GLOBALS.php');
	} else {
		require (getcwd() . '/GLOBALS.php');
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
	fud_use('nntp_adm.inc', true);
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

	$nntp = new fud_nntp;
	$nntp->rule_id 		= $config->id;
	$nntp->server 		= $config->server;
	$nntp->newsgroup 	= $config->newsgroup;
	$nntp->port 		= $config->port;
	$nntp->timeout 		= $config->timeout;
	$nntp->nntp_opt 	= $config->nntp_opt;
	$nntp->user 		= $config->login;
	$nntp->pass 		= $config->pass;
	$nntp->imp_limit	= $config->imp_limit;
	$nntp->tracker		= $config->tracker;

	$frm = db_sab('SELECT id, forum_opt, message_threshold, (max_attach_size * 1024) AS max_attach_size, max_file_attachments FROM '. sql_p .'forum WHERE id='. $config->forum_id);

	/* Set language & locale. */
	$GLOBALS['usr'] = new stdClass();
	list($GLOBALS['usr']->lang, $locale) = db_saq('SELECT lang, locale FROM '. sql_p .'themes WHERE theme_opt=1|2 LIMIT 1');
	$GLOBALS['good_locale'] = setlocale(LC_ALL, $locale);

	/* Try to increase DB timeout to prevent "MySQL server has gone away" errors. */
	if (__dbtype__ == 'mysql') {
		$db_timeout = q_singleval('select @@session.wait_timeout');
		if ($db_timeout < $nntp->timeout) {
			echo 'WARNING: MySQL timeout is smaller than the NNTP Timeout. Will try to increase database timeout.';
			q('SET SESSION wait_timeout = '. $nntp->timeout);
		}
	}

	$FUD_OPT_2 |= 128;	// Disable USE_ALIASES.

	$lock = $nntp->get_lock();
	$nntp->parse_msgs($frm, $config, $nntp->tracker);
	$nntp->release_lock($lock);

	$nntp->close_connection();
?>
