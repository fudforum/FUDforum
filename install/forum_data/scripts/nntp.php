#!/usr/local/bin/php -q -d register_argc_argv=1
<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: nntp.php,v 1.7 2002/10/07 20:42:19 hackie Exp $
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

	if( $HTTP_SERVER_VARS['argc'] < 2 ) exit("Missing Forum ID Paramater\n");	
	if( !is_numeric($HTTP_SERVER_VARS['argv'][1]) ) exit("Missing Forum ID Paramater\n");	
	
	/* Switch to the scripts directory */
	chdir(dirname($HTTP_SERVER_VARS['argv'][0]));

	include_once "GLOBALS.php";
	
	$GLOBALS['FILE_LOCK'] = 'N';
	fud_use('err.inc');
	fud_use('db.inc');
	fud_use('imsg.inc');
	fud_use('imsg_edt.inc');
	fud_use('th.inc');
	fud_use('wordwrap.inc');
	fud_use('isearch.inc');
	fud_use('replace.inc');
	fud_use('forum.inc');
	fud_use('rev_fmt.inc');
	fud_use('iemail.inc');
	fud_use('allperms.inc');
	fud_use('post_proc.inc');
	fud_use('is_perms.inc');
	fud_use('users.inc');
	fud_use('users_reg.inc');
	fud_use('rhost.inc');
	fud_use('attach.inc');
	fud_use('mime.inc');
	fud_use('nntp.inc', true);
	fud_use('nntp_adm.inc', true);
	
	if( !isset($HTTP_SERVER_VARS['argv'][1]) || !is_numeric($HTTP_SERVER_VARS['argv'][1]) ) 
		exit("Missing Forum ID Paramater\n");	
	
	$nntp_adm = new fud_nntp_adm;
	$nntp_adm->get($HTTP_SERVER_VARS['argv'][1]);
	
	$nntp = new fud_nntp;
	
	$nntp->server = $nntp_adm->server;
	$nntp->newsgroup = $nntp_adm->newsgroup;
	$nntp->port = $nntp_adm->port;
	$nntp->timeout = $nntp_adm->timeout;
	$nntp->auth = $nntp_adm->auth;
	$nntp->user = $nntp_adm->login;
	$nntp->pass = $nntp_adm->pass;
	$nntp->create_users = $nntp_adm->create_users;
	
	$frm = new fud_forum;
	$frm->get($nntp_adm->forum_id);
	
	$lock = $nntp->get_lock();
	$nntp->parse_msgs($frm, $nntp_adm, $nntp->read_start());
	$nntp->release_lock($lock);
	
	$nntp->close_connection();		
?>