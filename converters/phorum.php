<?php
/***************************************************************************
* copyright            : (C) 2001-2006 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: phorum.php,v 1.20 2006/09/05 13:48:43 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it 
* under the terms of the GNU General Public License as published by the 
* Free Software Foundation; either version 2 of the License. 
***************************************************************************/

/*
 *	Usage Instructions
 *
 *	1) Copy this script into the main web directory of FUDforum 2.
 *	2) Change the value of the value of the $PH_SETTINGS_PATH variable
 *	   to the full path of the phorum's settings directory
 *	3) Run this script via the shell or the web.
 *	4) Once the script successfuly runs, run the consitency checker.
 *	5) Voila, you're done.
*/

	$PH_SETTINGS_PATH="/home/phorum/admin/settings";
	// Make accounts for users who posted on Phorum without actually registering
	$MAKE_ANON_ACCOUNTS = FALSE;

/* DO NOT MODIFY BEYOND THIS POINT */

function print_msg($msg)
{
	if (__WEB__) {
		echo nl2br(str_replace("\t", "&nbsp;&nbsp;&nbsp;&nbsp;", $msg)) . "<br />\n";
	} else {
		echo $msg . "\n";
	}
}

function phorum2fudcode($str)
{
	$str = preg_replace("!\[center\](.*)\[/center\]!i", "[align=center]\1[/align]", $str);
	return smiley_to_post(tags_to_html($str, 1, 1));
}

	define('__WEB__', (isset($_SERVER["REMOTE_ADDR"]) === FALSE ? 0 : 1));

	/* prevent session initialization */
	define('forum_debug', 1);
	unset($_SERVER['REMOTE_ADDR']);

	set_time_limit(-1);
	error_reporting(E_ALL);
	ini_set('memory_limit', '128M');
	ini_set('default_socket_timeout', 10);

	$gl = @include("./GLOBALS.php"); 
	if ($gl === FALSE) {
		exit("This script must be placed in FUDforum's main web directory.\n");
	}

	if ($FUD_OPT_2 & 8388608 && !__WEB__) {
		exit("Since you are running conversion script via the console you must UNLOCK forum's files first.\n");
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
	fud_use('forum_adm.inc', true);
	fud_use('groups_adm.inc', true);
	fud_use('glob.inc', true);
	
	$PH_SETTINGS_PATH = realpath($PH_SETTINGS_PATH);
	
	$ph = include($PH_SETTINGS_PATH.'/forums.php');
	if ($ph === FALSE) {
		exit("Unable to open phorum configuration at '{$PH_SETTINGS_PATH}'.\n");
	}
	$PHORUM['auth_table'] = $PHORUM['main_table'].'_auth';
	$PHORUM['mod_table'] = $PHORUM['main_table'].'_moderators';
	$PHORUM['user2group_table'] = $PHORUM['main_table'].'_user2group';
	$PHORUM['forum2group_table'] = $PHORUM['main_table'].'_forum2group';
	$PHORUM['group_table'] = $PHORUM['main_table'].'_groups';

	if ($PHORUM['dbtype'] == 'mysql') {
		$phdb = mysql_connect($GLOBALS['PHORUM']['DatabaseServer'], $GLOBALS['PHORUM']['DatabaseUser'], $GLOBALS['PHORUM']['DatabasePassword']);
		if (!$phdb) {
			exit("Failed to connect to database containing phorum settings using MySQL information inside '{$PH_SETTINGS_PATH}'.\n");
		}
		mysql_select_db($PHORUM['DatabaseName'], $phdb);
		function q2($qry)
		{
			$res = mysql_query($qry, ph_sql) or die("SQL error: ".mysql_error());
			return $res;
		}
		function db_count2($res)
		{
			return mysql_num_rows($res);
		}
		function rob($res)
		{
			return mysql_fetch_object($res);
		}
	} else { /* PostgreSQL */
		$phdb = pg_connect("host={$PHORUM['DatabaseServer']} dbname={$PHORUM['DatabaseName']} user={$PHORUM['DatabaseUser']} password={$PHORUM['DatabasePassword']}");
		function q2($qry)
		{
			$res = pg_query(ph_sql, $qry) or die("SQL error: ".pg_last_error(ph_sql));
			return $res;
		}
		function db_count2($res)
		{
			return pg_num_rows($res);
		}
		function rob($res)
		{
			return pg_fetch_object($res);
		}
	}
	define('ph_sql', $phdb);

	$start_time = time();
	print_msg('Beginning Conversion Process');

/* Import phorum users */
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."users WHERE id>1");
	$theme = q_singleval("SELECT id FROM ".$DBHOST_TBL_PREFIX."themes WHERE (theme_opt & 3) >= 3 LIMIT 1");

	$r = q2("SELECT a.*, m.user_id as is_admin FROM {$PHORUM['auth_table']} a LEFT JOIN {$PHORUM['mod_table']} m ON a.id=m.user_id AND m.forum_id=0");

	print_msg('Importing Users');

	while ($obj = rob($r)) {
		if (q_singleval("SELECT id FROM ".$DBHOST_TBL_PREFIX."users WHERE login='".addslashes($obj->username)."' OR email='".addslashes($obj->email)."'")) {
			print_msg("\tuser: ".$obj->username);
			print_msg("\t\tWARNING: Cannot import user ".$obj->username.", user with this email and/or login already exists");
			continue;
		}

		$users_opt = 4 | 4194304 | 16 | 32 | 128 | 256 | 512 | 4096 | 8192 | 16384 | 2 | 64 | 32768 | 131072;
		if ($obj->is_admin) {
			$users_opt |= 1048576;
		}
		if ($obj->hide_email) {
			$users_opt |= 1;
		}

		$users[(int)$obj->id] = db_qid("INSERT INTO ".$DBHOST_TBL_PREFIX."users 
			(login, alias, passwd, email, icq, sig, aim, yahoo, msnm, jabber, users_opt, home_page, theme)
			VALUES (
				'".addslashes($obj->username)."',
				'".addslashes(htmlspecialchars($obj->username))."',
				'".$obj->password."',
				'".addslashes($obj->email)."',
				".($obj->icq ? (int)$obj->icq : 'NULL').",
				'".addslashes(phorum2fudcode($obj->signature))."',
				'".addslashes($obj->aol)."',
				'".addslashes($obj->yahoo)."',
				'".addslashes($obj->msn)."',
				'".addslashes($obj->jabber)."',
				".$users_opt.",
				'".addslashes($obj->webpage)."',
				".$theme."
				)");

	}
	unset($r);
	print_msg('Finished Importing Users');

/* Import phorum forums & categories */
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."cat");
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."group_resources");
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."groups WHERE id>2");
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."forum");
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."group_members");

	print_msg('Importing Categories');
	$r = q2("SELECT * FROM {$PHORUM['main_table']} WHERE folder=1 ORDER BY id");
	$i = 1;
	$cat_count = 0;
	while ($obj = rob($r)) {
		q("INSERT INTO ".$DBHOST_TBL_PREFIX."cat (id, name, view_order, cat_opt) VALUES(".(int)$obj->id.",'".addslashes($obj->name)."',".$i++.", 3)");
		$cat_count++;
	}
	unset($r);
	
	/* check if top level is needed */
	$r = q2("SELECT id FROM ".$PHORUM['main_table']." WHERE folder=0 AND parent=0");
	if (db_count2($r)) {
		$TOP_LEVEL_CATID = db_qid("INSERT INTO ".$DBHOST_TBL_PREFIX."cat (name, view_order, cat_opt) VALUES('Imported phorum forums w/category', {$i}, 3)");
		$cat_count++;
	}
	unset($r);
	print_msg('Finished Importing ('.$cat_count.') Categories');

	$r = q2("select * from {$PHORUM['main_table']} WHERE folder=0 ORDER BY parent, id");
	print_msg('Importing Forums '.db_count2($r));
	while ($obj = rob($r)) {
		$_POST['frm_cat_id'] = $obj->parent ? $obj->parent : $TOP_LEVEL_CATID;
		$_POST['frm_name'] = $obj->name;
		$_POST['frm_descr'] = $obj->description;
		$_POST['frm_forum_opt'] = 16 | (strtolower($obj->moderation) == 'y' ? 2 : 0);
		$_POST['frm_max_file_attachments'] = (int) $obj->allow_uploads;
		$_POST['frm_max_attach_size'] = 1024;

		$frm = new fud_forum();
		$id = $frm->add('LAST');

		q("UPDATE {$DBHOST_TBL_PREFIX}forum SET id={$obj->id} WHERE id=".$id);
		q("UPDATE {$DBHOST_TBL_PREFIX}groups SET forum_id={$obj->id} WHERE forum_id=".$id);
		q("UPDATE {$DBHOST_TBL_PREFIX}group_resources SET resource_id={$obj->id} WHERE resource_id=".$id);

		$perms_reg = $perms_anon = 65536 | 262144 | 1 | 2;
		$perms_reg |= 4 | 8 | 128 | ($obj->allow_uploads ? 256 : 0) | 512 | 1024 | 16384 | 32768;
		if ($obj->security < 2) {
			$perms_anon = $perms_reg;
		}

		$gid = q_singleval("SELECT id FROM {$DBHOST_TBL_PREFIX}groups WHERE forum_id=".$obj->id);
		q("UPDATE {$DBHOST_TBL_PREFIX}group_members SET group_members_opt={$perms_anon} WHERE group_id={$gid} AND user_id=0");
		q("UPDATE {$DBHOST_TBL_PREFIX}group_members SET group_members_opt={$perms_reg} WHERE group_id={$gid} AND user_id=2147483647");
	}
	unset($r);
	print_msg('Finished Importing Forums');

	rebuild_forum_cat_order();

/* Import phorum moderators */
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."mod");
	print_msg('Importing Moderators');

	$r = q2("SELECT * FROM {$PHORUM['mod_table']} WHERE forum_id!=0");
	while ($obj = rob($r)) {
		if (!isset($users[(int)$obj->user_id])) {
			continue;
		}
		q("INSERT INTO ".$DBHOST_TBL_PREFIX."mod (user_id, forum_id) VALUES(".$users[(int)$obj->user_id].", ".(int)$obj->forum_id.")");
	}
	unset($r);
	print_msg('Finished Importing Moderators');

/* Import messages & topics */
	print_msg('Importing Topics & Messages');

	q("DELETE FROM ".$DBHOST_TBL_PREFIX."thread");
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."msg");
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."thread_notify");
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."poll");
	q("DELETE FROm ".$DBHOST_TBL_PREFIX."custom_tags");
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."poll_opt");
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."poll_opt_track");
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."pmsg");
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."attach");

	$r = q2("SELECT id, table_name FROM {$PHORUM['main_table']} WHERE folder=0");
	while ($obj = rob($r)) {
		$phorums[$obj->id] = $obj->table_name;
	}
	unset($r);

	/* import data 1 forum at a time */
	$m = new fud_msg_edit();
	$m->msg_opt = 0;
	foreach ($phorums as $fid => $pfx) {
		$th = array();
		$r = q2("SELECT m.*, b.body FROM {$pfx} m LEFT JOIN {$pfx}_bodies b ON m.id=b.id ORDER BY m.thread, m.id");
		print_msg("\t\tforum: {$pfx} ".db_count2($r));
		while ($obj = rob($r)) {
			$m->subject = htmlspecialchars($obj->subject);
			$m->ip_addr = '0.0.0.0';
			$m->poster_id = isset($users[(int)$obj->userid]) ? $users[(int)$obj->userid] : 0;
			$m->post_stamp = (int) strtotime($obj->datestamp);
			$m->body = phorum2fudcode($obj->body);
			$m->update_stamp = (int) $obj->modifystamp;
			if (isset($th[(int)$obj->thread])) {
				$m->thread_id = $th[(int)$obj->thread][0];
				$m->reply_to = isset($mid[(int)$obj->parent]) ? $mid[(int)$obj->parent] : $th[(int)$obj->thread][1];
				$new = 0;
			} else {
				$m->thread_id = $m->reply_to = 0;
				$new = 1;
			}
			$m->apr = (int) (strtolower($obj->approved) == 'y');
			$m->msg_opt = 1;
			if ($obj->closed) {
				$_POST['thr_locked'] = 1;
			} else {
				unset($_POST['thr_locked']);
			}

			/* handle anon posters */
			if (!$m->poster_id && $MAKE_ANON_ACCOUNTS !== FALSE) {
				/* try to identify the user and if possible create a new user */
				if ($obj->email) {
					$id = q_singleval("SELECT id FROM {$DBHOST_TBL_PREFIX}users WHERE email='".addslashes($obj->email)."'");
					if (!$id) { /* add user */
						$u = new fud_user_reg();
						$u->login = $u->name = $obj->author;
						$u->alias = htmlspecialchars($obj->author);
						$u->email = $obj->email;
						$u->users_opt = -1;
						$m->poster_id = $u->add_user();
					}
				} else {
					$m->poster_id = (int) q_singleval("SELECT id FROM {$DBHOST_TBL_PREFIX}users WHERE login='".addslashes($obj->author)."'");
				}
			}
			$mid[(int)$obj->id] = $m->add($fid, 0, 0, 4096, false);
			if ($new) {
				$th[(int)$obj->thread] = array($m->thread_id, $m->id);
			}
			if ($m->apr) {
				q("UPDATE {$DBHOST_TBL_PREFIX}msg SET apr=1 WHERE id=".$m->id);
			}
		}
		unset($r);
	}
	print_msg('Done: Importing Topics & Messages');

/* Import file attachments */
	print_msg('Importing Attachments');
	if ($PHORUM['AllowAttachments'] && $PHORUM['AttachmentDir']) {
		$r = q2("SELECT table_name FROM forums WHERE folder=0 AND allow_uploads='Y'");
		while ($o = rob($r)) {
			$r2 = q2("SELECT * FROM {$o->table_name}_attachments WHERE message_id > 0");
			while ($obj = rob($r2)) {
				$from = realpath($PHORUM['AttachmentDir'].'/'.$tobj->table_name.'/'.$obj->message_id.'_'.$obj->id.strrchr($obj->filename, '.'));
				if (!$from || !@file_exists($from)) {
					print_msg("\t\tWARNING: file attachment ".$from." doesn't exist");
					continue;
				}
				if (!isset($mid[(int)$obj->message_id])) {
					print_msg("\t\tWARNING: file attachment #{$obj->message_id} doesn't have corresponding message");
					continue;				
				}

				$mime = q_singleval("SELECT id FROM ".$DBHOST_TBL_PREFIX."mime WHERE fl_ext='".substr(strrchr($obj->filename, '.'), 1)."'");
				if (!$mime) {
					$mime = 40;
				}
				$user_id = (int) q_singleval("SELECT poster_id FROM ".$DBHOST_TBL_PREFIX."msg WHERE id=".$mid[(int)$obj->message_id]);
				$attach_id = db_qid("INSERT INTO ".$DBHOST_TBL_PREFIX."attach 
						(original_name, owner, message_id, dlcount, mime_type, fsize)
					VALUES (
						'".addslashes($obj->filename)."',
						".$user_id.",
						".$mid[(int)$obj->message_id].",
						0,
						".(int)$mime.",
						".(int)filesize($from).")"
					);

				if (!copy($from, $FILE_STORE.$attach_id.'.atch')) {
					print_msg("Couldn't copy file attachment (".$from.") to (".$FILE_STORE.$attach_id.'.atch'.")");
					exit;
				}
				q("UPDATE ".$DBHOST_TBL_PREFIX."attach SET location='".$FILE_STORE.$attach_id.'.atch'."' WHERE id=".$attach_id);
			}
			unset($r2);
		}
		unset($r);
	}
	unset($mid, $users);
	print_msg('Done: Importing Attachments');

/* Update user profiles with dates */
	print_msg('Finalizing user profiles');
	q("UPDATE ".$DBHOST_TBL_PREFIX."users SET last_visit=".time().", last_read=".time().", join_date=".time());
	$r = q("SELECT poster_id, MAX(post_stamp) AS mx, MIN(post_stamp) as mi FROM {$DBHOST_TBL_PREFIX}msg GROUP BY poster_id");
	while ($obj = db_rowobj($r)) {
		q("UPDATE {$DBHOST_TBL_PREFIX}users SET last_visit={$obj->mx}, last_read={$obj->mx}, join_date={$obj->mi} WHERE id=".$obj->poster_id);
	}
	unset($r);
	print_msg('Done: Finalizing user profiles');

/* import general phorum settings */
	print_msg('Importing Forum Settings');	
	
	$list = array();
	$list['ADMIN_EMAIL'] =  $PHORUM['DefaultEmail'];
	$list['NOTIFY_FROM'] =  $PHORUM['DefaultEmail'];
	$list['POSTS_PER_PAGE'] =  $PHORUM['DefaultDisplay'];
	$list['PRIVATE_ATTACHMENTS'] = $PHORUM['MaximumNumberAttachments'];
	$list['PRIVATE_ATTACH_SIZE'] = $PHORUM['AttachmentSizeLimit'] * 1000;
	change_global_settings($list);
	
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."ext_block");
	if ($PHORUM['AttachmentFileTypes']) {
		$allowed_ext = explode(';', $PHORUM['AttachmentFileTypes']);
		foreach ($allowed_ext as $ext) {
			q("INSERT INTO ".$DBHOST_TBL_PREFIX."ext_block (ext) VALUES('".addslashes(trim($ext))."')");
		}
	}
	
	print_msg('Finished Importing Forum Settings');
	
	$time_taken = time() - $start_time;
	if ($time_taken > 120) {
		$time_taken .= ' seconds';
	} else {
		$m = floor($time_taken / 60);
		$s = $time_taken - $m * 60;
		$time_taken = $m." minutes ".$s." seconds";
	}	
	
	print_msg("\n\nConversion of Phorum to FUDforum2 has been completed\n Time Taken: ".$time_taken."\n");
	print_msg("To complete the process run the consistency checker at:");
	print_msg($GLOBALS['WWW_ROOT']."adm/consist.php");
?>