#!/usr/local/bin/php -q
<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: nntp.php,v 1.15 2003/09/26 18:49:02 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

	set_time_limit(600);
	define('forum_debug', 1);

	if (!ini_get("register_argc_argv")) {
		exit("Enable the 'register_argc_argv' php.ini directive\n");	
	}
	if ($_SERVER['argc'] < 2) {
		exit("Missing Forum ID Paramater\n");	
	}
	if (!($fid = (int)$_SERVER['argv'][1])) {
		exit("Missing Forum ID Paramater\n");
	}	
	
	if (strncmp($_SERVER['argv'][0], '.', 1)) {
		require (dirname($_SERVER['argv'][0]) . '/GLOBALS.php');
	} else {
		require (getcwd() . '/GLOBALS.php');
	}

	$GLOBALS['FILE_LOCK'] = 'N';
	$GLOBALS['MODERATE_USER_REGS'] = 'N';

	fud_use('err.inc');
	fud_use('db.inc');
	fud_use('imsg.inc');
	fud_use('imsg_edt.inc');
	fud_use('th.inc');
	fud_use('th_adm.inc');
	fud_use('wordwrap.inc');
	fud_use('isearch.inc');
	fud_use('replace.inc');
	fud_use('forum.inc');
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

	$nntp_adm = db_sab('SELECT * FROM '.sql_p.'nntp WHERE id='.$fid);

	$nntp = new fud_nntp;

	$nntp->server 		= $nntp_adm->server;
	$nntp->newsgroup 	= $nntp_adm->newsgroup;
	$nntp->port 		= $nntp_adm->port;
	$nntp->timeout 		= $nntp_adm->timeout;
	$nntp->nntp_opt 	= $nntp_adm->nntp_opt;
	$nntp->user 		= $nntp_adm->login;
	$nntp->pass 		= $nntp_adm->pass;

	$frm = db_sab('SELECT id, forum_opt, message_threshold, (max_attach_size * 1024) AS max_attach_size, max_file_attachments FROM '.sql_p.'forum WHERE id='.$nntp_adm->forum_id);
	
	$lock = $nntp->get_lock();
	$nntp->parse_msgs($frm, $nntp_adm, $nntp->read_start());
	$nntp->release_lock($lock);
	
	$nntp->close_connection();		
?>