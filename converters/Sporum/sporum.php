<?php
/***************************************************************************
* copyright            : (C) 2001-2007 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id$
*
* This program is free software; you can redistribute it and/or modify it 
* under the terms of the GNU General Public License as published by the 
* Free Software Foundation; version 2 of the License. 
***************************************************************************/

	/* Sporum 1.X - FUDforum Conversion script - Brief Instructions */

	/* If you intend to run this script via the console, make sure to UNLOCK the 
	 * FUDforum 1st. I recommend running this script via the web unless the forum
	 * you are importing is very large.
	 */

	/* Specify the FULL path to the Sporum's SmallPigVars.pm file */
	$SPR_CFG = "/path/to/SmallPigVars.pm";

/* DO NOT MODIFY BEYOND THIS POINT */

function print_msg($msg)
{
	if (__WEB__) {
		echo nl2br(str_replace("\t", "&nbsp;&nbsp;&nbsp;&nbsp;", $msg)) . "<br />\n";
	} else {
		echo $msg . "\n";
	}
}

function bbq($q, $err=0)
{
	$r = mysql_query($q, phpsql);
	if ($r) {
		return $r;
	}
	if (!$err) {
		die(mysql_error(phpsql));
	}
}

	define('__WEB__', (isset($_SERVER["REMOTE_ADDR"]) === FALSE ? 0 : 1));

	/* prevent session initialization */
	unset($_SERVER['REMOTE_ADDR']);
	define('forum_debug', 1);

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

	$gl = @file_get_contents($SPR_CFG);
	if (!$gl) {
		exit("Unable to open Sporum configuration at '{$SPR_CFG}'.\n");
	}

	/* parse the config */
	if (!preg_match('!\$config =\s+{(.*)};!sm', $gl, $match)) {
		exit("Invalid Sporum configuration at '{$SPR_CFG}'.\n");
	}
	$match = preg_replace(array('!\s+#.*!', "!\n+!sm"), array('',"\n"), $match[1]);
	eval(' $config = array'.$match.'; ');

	if (!($sp = mysql_connect($config['dbhost'], $config['dbuser'], $config['dbpass']))) {
		exit("Failed to connect to database containing Sporum settings using MySQL information inside '{$SPR_CFG}'.\n");
	}
	define('phpsql', $sp);

	$spf = $config['dbname'].'.';

	/* include all the necessary FUDforum includes */
	fud_use('err.inc');
	fud_use('db.inc');
	fud_use('users.inc');
	fud_use('users_reg.inc');
	fud_use('replace.inc');
	fud_use('smiley.inc');
	fud_use('post_proc.inc');
	fud_use('wordwrap.inc');
	fud_use('thread_notify.inc');
	fud_use('forum_notify.inc');
	fud_use('cat.inc', true);
	fud_use('groups.inc');
	fud_use('imsg_edt.inc');
	fud_use('th.inc');
	fud_use('th_adm.inc');
	fud_use('rev_fmt.inc');
	fud_use('fileio.inc');
	fud_use('isearch.inc');
	fud_use('attach.inc');
	fud_use('forum_adm.inc', true);
	fud_use('groups_adm.inc', true);
	fud_use('glob.inc', true);

	$start_time = time();

/* Import sporum users */

	$old_umask = umask(0111);
	$IMG_ROOT_DISK = $WWW_ROOT_DISK . 'images/';
	$ut = array();

	q("DELETE FROM ".$DBHOST_TBL_PREFIX."users WHERE id>1");
	$theme = q_singleval("SELECT id FROM ".$DBHOST_TBL_PREFIX."themes WHERE (theme_opt & 3) >= 3 LIMIT 1");

	$r = bbq("SELECT * FROM {$spf}Users ORDER BY uid");
	
	print_msg('Importing Users '.db_count($r));
	
	while ($obj = db_rowobj($r)) {
		if (($dupe = q_singleval("SELECT id FROM ".$DBHOST_TBL_PREFIX."users WHERE login='".addslashes($obj->username)."'"))) {
			$ut[$obj->uid] = $dupe;
			print_msg("\tuser: ".$obj->username);
			print_msg("\t\tWARNING: Cannot import user ".$obj->username.", user with this login already exists");
			continue;
		} else if (($dupe = q_singleval("SELECT id FROM ".$DBHOST_TBL_PREFIX."users WHERE email='".addslashes($obj->realemail)."'"))) {
			$ut[$obj->uid] = $dupe;
			print_msg("\temail: ".$obj->realemail);
			print_msg("\t\tWARNING: Cannot import user ".$obj->realemail.", user with this email already exists");
			continue;
		}

		$obj->nickname = htmlspecialchars($obj->nickname);
		if (q_singleval("SELECT id FROM ".$DBHOST_TBL_PREFIX."users WHERE alias='".$obj->nickname."'")) {
			$obj->nickname = htmlspecialchars($obj->username);
		}

		$users_opt = 4 | 4194304 | 16 | 32 | 512 | 8192 | 16384 | 2048 | 64 | 2 | 131072 | 32768 | 8 | 4096;

		if ($obj->isadmin) {
			$users_opt |= 1048576;
		}
		if ($obj->display == 'flat') {
			$users_opt |= 128 | 256;
		}
		if (!$obj->active) {
			$users_opt |= 2097152;
		}

		$ut[$obj->uid] = db_qid("INSERT INTO ".$DBHOST_TBL_PREFIX."users 
			(login, alias, passwd, last_visit, join_date, email, location, sig,
			bio, users_opt, home_page, theme, last_read, user_image)
			VALUES (
				'".addslashes($obj->username)."',
				'".addslashes(htmlspecialchars($obj->nickname))."',
				'',
				".strtotime($obj->lastlogon).",
				".strtotime($obj->registered).",
				'".addslashes($obj->realemail)."',
				'".addslashes($obj->location)."',
				'".addslashes($obj->sig)."',
				'".addslashes($obj->bio)."',
				".$users_opt.",
				'".addslashes($obj->homepage)."',
				".$theme.",
				".strtotime($obj->lastresp).",
				'".addslashes($obj->photourl)."'
				)");
	}
	unset($r);
	umask($old_umask);
	print_msg('Finished Importing Users');
	print_msg('
!!! IMPORTANT !!!
Because Sporum uses incompatible one-way password encryption scheme the passwords cannot be imported. Consequently users will need to through "lost password" procedure to have the forum send them their password.
!!! IMPORTANT !!!
');

// Import sporum Categories

	q("DELETE FROM ".$DBHOST_TBL_PREFIX."cat");
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."group_resources");
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."groups WHERE id>2");
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."forum");
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."group_members");
	
	$r = bbq("select * from {$spf}Cats ORDER BY catorder");
	print_msg('Importing Categories '.db_count($r));
	$i = 1;
	$ct = array();
	while( $obj = db_rowobj($r) ) {
		$ct[$obj->catid] = db_qid("INSERT INTO ".$DBHOST_TBL_PREFIX."cat (name, view_order, cat_opt) VALUES('".addslashes($obj->cattitle)."', ".$i++.", 3)");
	}
	unset($r);
	print_msg('Finished Importing Categories');

// Import sporum Forums
	$r = bbq("SELECT b.*,j.catid FROM {$spf}BoardBelongToCat j INNER JOIN {$spf}Boards b ON b.sid=j.sid ORDER BY j.catid, j.sorder");
	print_msg('Importing Forums '.db_count($r));
	while ($obj = db_rowobj($r)) {
		$_POST['frm_cat_id'] = $ct[$obj->catid];
		$_POST['frm_name'] = $obj->title;
		$_POST['frm_descr'] = $obj->introtext;

		if ($obj->markup) {
			$_POST['frm_forum_opt'] = 16;
		} else if (!$obj->html) {
			$_POST['frm_forum_opt'] = 8;
		} else {
			$_POST['frm_forum_opt'] = 0;
		}

		if ($obj->fileattach) {
			$_POST['frm_max_file_attachments'] = 1;
			$_POST['frm_max_attach_size'] = round((int) $config['file_size'] / 1024);
		} else {
			$_POST['frm_max_attach_size'] = $_POST['frm_max_file_attachments'] = 0;
		}

		$frm = new fud_forum();
		$ft[$obj->sid] = $frm->add('LAST');
	}
	unset($r);
	print_msg('Finished Importing Forums');

// Import sporum moderators

	q("DELETE FROM ".$DBHOST_TBL_PREFIX."mod");
	print_msg('Importing Moderators');
	$r = bbq("SELECT m.sid, m.uid FROM {$spf}Moderators m INNER JOIN {$spf}Users u ON u.uid=m.uid INNER JOIN {$spf}Boards f ON f.sid=m.sid GROUP BY m.sid, m.uid");
	while ($obj = db_rowobj($r)) {
		if (isset($ft[$obj->sid], $ut[$obj->uid])) {
			q("INSERT INTO ".$DBHOST_TBL_PREFIX."mod (user_id, forum_id) VALUES(".$ut[$obj->uid].", ".$ft[$obj->sid].")");
		}
	}
	unset($r);
	print_msg('Finished Importing Moderators');

// Import sporum Topics & Messages

	q("DELETE FROM ".$DBHOST_TBL_PREFIX."thread");
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."msg");
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."attach");
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."poll");
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."poll_opt");
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."poll_opt_track");

	$old_umask = umask(0111);

	print_msg('Importing Topics and Messages');	

	$tt = array();
	$r = bbq("SELECT p.*,a.fname FROM {$spf}Posts p LEFT JOIN {$spf}FileAttach a ON a.cid=p.cid ORDER BY p.cid");
	while ($obj = db_rowobj($r)) {
		$dupe = isset($tt[$obj->cid]);
		if (!$dupe) {
			if (!$obj->pid) { // new topic
				$thread_id = $tt[$obj->cid] = db_qid("INSERT INTO ".$DBHOST_TBL_PREFIX."thread (forum_id, thread_opt) VALUES(".$ft[$obj->sid].", ".($obj->closed ? 1 : 0).")");
			} else {
				$thread_id = $tt[$obj->cid] = $tt[$obj->pid];
			}
			$fileid = write_body(smiley_to_post(tags_to_html($obj->bodytext, 1, 1)), $len, $off, $ft[$obj->sid]);
		} else {
			$thread_id = $tt[$obj->cid];
		}

		if (!$obj->uid || empty($ut[$obj->uid])) {
			$poster = 0;
		} else {
			$poster = $ut[$obj->uid];
		}

		if (!$dupe) {
			q("INSERT INTO ".$DBHOST_TBL_PREFIX."msg
				(id, thread_id, poster_id, post_stamp, subject,
				 ip_addr, foff, length, file_id, apr, reply_to
			) VALUES (
				".(int)$obj->cid.",
				".$thread_id.",
				".$poster.",
				".strtotime($obj->date).",
				'".addslashes($obj->subject)."',
				'".addslashes($obj->ip)."',
				".$off.",
				".$len.",
				".$fileid.",
				".(int)$obj->approved.",
				".(int)$obj->pid.")"
			);
			// handle subscription
			if ($poster && $obj->emailreply) {
				thread_notify_add($poster, $thread_id);
			}
		}

		// handle any file attachments
		if ($obj->fname) {
			$file_name = $config['file_path']."/".$obj->fname;
			if (!@file_exists($file_name)) {
				$file_name = $obj->fname;
			}
		
			if (!@file_exists($file_name)) {
				print_msg("\tWARNING: file attachment ".$file_name." doesn't exist");
				continue;
			}

			$mime = q_singleval("SELECT id FROM ".$DBHOST_TBL_PREFIX."mime WHERE fl_ext='".substr(strrchr($obj->fname, '.'), 1)."'");
			$attach_id = db_qid("INSERT INTO ".$DBHOST_TBL_PREFIX."attach 
				(original_name, owner, message_id, dlcount, mime_type, fsize)
				VALUES (
					'".addslashes($obj->fname)."',
					".$poster_id.",
					".(int)$obj->cid.",
					0,
					".(int)$mime.",
					".(int)filesize($file_name).")"
				);

			if (!copy($file_name, $FILE_STORE.$attach_id.'.atch')) {
				print_msg("Couldn't copy file attachment (".$file_name.") to (".$FILE_STORE.$attach_id.'.atch'.")");
				exit;
			}
			q("UPDATE ".$DBHOST_TBL_PREFIX."attach SET location='".$FILE_STORE.$attach_id.'.atch'."' WHERE id=".$attach_id);		
		}		
	}

	unset($r, $r2);
	umask($old_umask);
	print_msg('Finished Importing Messages');

// Import sporum Forum Subscriptions
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."forum_notify");
	$r = bbq("SELECT sb.sid, sb.uid FROM {$spf}SubscribeBoard sb INNER JOIN {$spf}Users u ON u.uid=sb.uid INNER JOIN {$spf}Boards b ON b.sid=sb.sid");
	print_msg('Importing Forum Subscriptions '.db_count($r));
	while ($obj = db_rowobj($r)) {
		if (isset($ut[$obj->uid], $ft[$obj->sid])) {
			forum_notify_add($ut[$obj->uid], $ft[$obj->sid]);
		}
	}
	unset($r);
	print_msg('Finished Importing Forum Subscriptions');

/* Import sporum settings */
	print_msg('Importing Forum Settings');
	$list = array();

	$list['FORUM_TITLE'] = $config['title'];
	$list['ADMIN_EMAIL'] = $list['NOTIFY_FROM'] = $config['adminaddr'];
	$list['PRIVATE_ATTACH_SIZE'] = (int) $config['file_size'];
	$list['THREADS_PER_PAGE'] = (int) $config['postsper'];
	$list['FUD_OPT_2'] = $FUD_OPT_2 | 512;

	change_global_settings($list);

	q("DELETE FROM ".$DBHOST_TBL_PREFIX."ses");

	print_msg('Finished Importing Forum Settings');

	$time_taken = time() - $start_time;
	if ($time_taken > 120) {
		$time_taken .= ' seconds';
	} else {
		$m = floor($time_taken/60);
		$s = $time_taken - $m * 60;
		$time_taken = $m." minutes ".$s." seconds";
	}	

	print_msg("\n\nConversion of Sporum to FUDforum2 has been completed\n Time Taken: ".$time_taken."\n");
	print_msg("To complete the process run the consistency checker at:");
	print_msg($WWW_ROOT."adm/consist.php");
?>
