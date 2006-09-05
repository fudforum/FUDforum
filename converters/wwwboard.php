<?php
/***************************************************************************
* copyright            : (C) 2001-2006 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: wwwboard.php,v 1.9 2006/09/05 13:48:43 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it 
* under the terms of the GNU General Public License as published by the 
* Free Software Foundation; either version 2 of the License. 
***************************************************************************/

	/* WWWBoard (Version 2.0 ALPHA 2.1) - FUDforum Conversion script - Brief Instructions */

	/* If you intend to run this script via the console, make sure to UNLOCK the 
	 * FUDforum 1st. I recommend running this script via the web unless the forum
	 * you are importing is very large.
	 */

	/* Specify the FULL path to the WWWBoard messages directory */
	$WWWB_MSG = "/path/to/messages";

/**** DO NOT EDIT BEYOND THIS POINT ****/

function print_msg($msg)
{
	if (__WEB__) {
		echo $msg . "<br />\n";	
	} else {
		echo $msg . "\n";
	}
}

	set_time_limit(-1);
	ini_set('memory_limit', '128M');
	define('__WEB__', (isset($_SERVER["REMOTE_ADDR"]) === FALSE ? 0 : 1));

	/* prevent session initialization */
	define('forum_debug', 1);
	unset($_SERVER['REMOTE_ADDR']);

	$gl = @include("./GLOBALS.php"); 
	if ($gl === FALSE) {
		exit("This script must be placed in FUDforum's main web directory.\n");
	}

	if ($FUD_OPT_2 & 8388608 && !__WEB__) {
		exit("Since you are running conversion script via the console you must UNLOCK forum's files first.\n");
	}

	if (!is_dir($WWWB_MSG) || !is_readable($WWWB_MSG)) {
		print_msg("Cannot open WWWBoard messages directory ({WWWB_MSG})");
		exit;	
	}

	/* include all the necessary FUDforum includes */
	fud_use('err.inc');
	fud_use('db.inc');
	fud_use('users.inc');
	fud_use('users_reg.inc');
	fud_use('replace.inc');
	fud_use('smiley.inc');
	fud_use('post_proc.inc');
	fud_use('wordwrap.inc');
	fud_use('cat.inc', true);
	fud_use('groups.inc');
	fud_use('imsg_edt.inc');
	fud_use('th.inc');
	fud_use('th_adm.inc');
	fud_use('rev_fmt.inc');
	fud_use('fileio.inc');
	fud_use('isearch.inc');
	fud_use('attach.inc');
	fud_use('ipoll.inc');
	fud_use('private.inc');
	fud_use('forum_adm.inc', true);
	fud_use('groups_adm.inc', true);
	fud_use('glob.inc', true);


	/* get a list of all WWWBoard messages */
	$d = opendir($WWWB_MSG);
	readdir($d); readdir($d);
	while (($f = readdir($d))) {
		$files[basename($f, '.html')] = $WWWB_MSG . '/' . $f;
	}
	closedir($d);
	ksort($files, SORT_NUMERIC);

	/* create a destination forum & category */
	$_POST = array('cat_name' => 'WWWBoard', 'cat_cat_opt' => 3, 'cat_description' => 'WWWBoard');
	$cat = new fud_cat;
	$cat->add(0);

	$_POST = array('frm_cat_id' => $cat->id, 'frm_name' => 'WWWBoard', 'frm_descr' => 'WWWBoard', 'frm_forum_opt' => 16);
	$frm = new fud_forum;
	$frm->add(0);

	rebuild_forum_cat_order();

	/* begin import process */

	/* common settings for all users */
	$u = new fud_user_reg;
	$u->plaintext_passwd = rand();
	$u->users_opt = -1;

	$m = new fud_msg_edit;
	$m->msg_opt = 0;

	foreach ($files as $k => $f) {
		$data = file_get_contents($f);
		$msg = array('id' => $k);

		/* fetch subject */
		$p = strpos($data, '<title>') + strlen('<title>');
		$msg['subject'] = substr($data, $p, (strpos($data, '</title>', $p) - $p));

		/* try to fetch the user */
		if (($p = strpos($data, 'Posted by <a href="')) !== false) {
			$tmp = substr($data, $p, (strpos($data, ":<p>", $p) - $p));
			preg_match('!Posted by \<a href="mailto:([^"]+)"\>(.*)</a> on (.*)!', $tmp, $res);
			$msg['user'] = $res[2]; 
			$msg['email'] = $res[1];
			$msg['time'] = strtotime($res[3]);
		} else { /* we still need date of post and 'anon' user */
			$p = strpos($data, 'Posted by ') + strlen('Posted by ');
			$msg['user'] = substr($data, $p, (strpos($data, "\n", $p) - $p));
			$p = strpos($data, "\non ", $p) + strlen("\non ");
			$msg['time'] = strtotime(substr($data, $p, (strpos($data, ':<p>', $p) - $p)));
		}

		/* fetch reply info */
		if (($p = strpos($data, 'In Reply to: <a href="')) !== false) {
			$p += strlen('In Reply to: <a href="');
			$msg['reply_to'] = (int) substr($data, $p, (strpos($data, '.', $p) - $p));
		}

		/* fetch message body */
		$p = strpos($data, ':<p>', $p) + strlen(':<p>');
		$e = strpos($data, '<br><hr size=7 width=75%><p>', $p);
		$msg['body'] = str_replace(array('<br>', '<p>'), array("\n", "\n\n"), strip_tags(substr($data, $p, ($e - $p)), '<a><br><p>'));
		if (strpos($msg['body'], '<a href="') !== false) {
			$msg['body'] = preg_replace('!<a href="([^"]+)">([^>]+)</a>!', '[url=\1]\2[/url]', $msg['body']);
		}
		$msg['body'] = trim($msg['body']);
		fud_wordwrap($msg['body']);

		/* try to identify the user and if possible create a new user */
		if (isset($msg['email'])) {
			$id = q_singleval("SELECT id FROM {$DBHOST_TBL_PREFIX}users WHERE email='".addslashes($msg['email'])."'");
			if (!$id) {
				$id = (int) q_singleval("SELECT id FROM {$DBHOST_TBL_PREFIX}users WHERE login='".addslashes($msg['user'])."'");
			}
			if (!$id) { /* add user */
				$u->login = $u->name = $msg['user'];
				$u->email = $msg['email'];
				$id = $u->add_user();
			}
		} else {
			$id = (int) q_singleval("SELECT id FROM {$DBHOST_TBL_PREFIX}users WHERE login='".addslashes($msg['user'])."'");
		}

		$m->subject = htmlspecialchars($msg['subject']);
		$m->ip_addr = '0.0.0.0';
		$m->poster_id = $id;
		$m->post_stamp = $msg['time'];
		$m->body = tags_to_html($msg['body'], 0);

		if (isset($msg['reply_to']) && isset($ml[$msg['reply_to']])) {
			$m->reply_to = $ml[$msg['reply_to']][0];
			$m->thread_id = $ml[$msg['reply_to']][1];
		} else {
			$m->reply_to = $m->thread_id = 0;
		}

		$m->add($frm->id, 0, 2, 0);

		$ml[$msg['id']] = array($m->id, $m->thread_id);
	}

	print_msg("Conversion Process Complete!");
	print_msg("");
	print_msg("");
	print_msg("------------------------------------------------------------------------------");
	print_msg("                                IMPORTANT!!!");
	print_msg("------------------------------------------------------------------------------");
	print_msg("To complete the conversion process run the consistency checker at:");
	print_msg("{$WWW_ROOT}adm/consist.php");
	print_msg("You will need to login using the administrator account from the forum you've");
	print_msg("just imported.");	
	print_msg("");
	print_msg("If you want the imported messages to be searcheable, rebuild the search index");
	print_msg("Rebuild Search Index admin control panel.");
	print_msg("------------------------------------------------------------------------------");
	print_msg("");
?>