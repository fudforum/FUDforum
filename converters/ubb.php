<?php
/***************************************************************************
* copyright            : (C) 2001-2004 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: ubb.php,v 1.1 2004/02/18 14:46:14 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it 
* under the terms of the GNU General Public License as published by the 
* Free Software Foundation; either version 2 of the License, or 
* (at your option) any later version.
***************************************************************************/

	/* UBB.threads 6.1 - FUDforum Conversion script - Brief Instructions */

	/* If you intend to run this script via the console, make sure to UNLOCK the 
	 * FUDforum 1st. I recommend running this script via the web unless the forum
	 * you are importing is very large.
	 */

	/* Specify the FULL path to the UBB.threads ubbthreads_config.txt file */
	$UBB_CFG = "/work2/src/ubbthreads_config.txt";

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

function bbcode2fudcode($str)
{
	$str = preg_replace('!\[(.+?)\:([a-z0-9]+)?\]!s', '[\1]', $str);
	$str = preg_replace('!\[quote\:([a-z0-9]+?)="(.*?)"\]!is', '[quote=\2]', $str);
	$str = preg_replace("#(^|[\n ])((www|ftp)\.[\w\-]+\.[\w\-.\~]+(?:/[^ \"\t\n\r<]*)?)#is", "\\1http://\\2", $str);
	$str = smiley_to_post(tags_to_html($str, 1, 1));

	return $str;
}

function phpbb_decode_ip($int_ip)
{
	if ($int_ip == '00000000') {
		return '0.0.0.0';
	} else {
		return long2ip("0x{$int_ip}");
	}
}

	define('IN_PHPBB', 1);
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

	$gl = @include($UBB_CFG);
	if ($gl === FALSE) {
		exit("Unable to open UBB.threads configuration at '{$UBB_CFG}'.\n");
	}

	if (!($ib = mysql_connect($config['dbserver'], $config['dbuser'], $config['dbpass']))) {
		exit("Failed to connect to database containing UBB.threads settings using MySQL information inside '{$UBB_CFG}'.\n");
	}
	define('phpsql', $ib);

	$ubb = $config['dbname'] . '.' . $config['tbprefix'];

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
	fud_use('imsg.inc');
	fud_use('imsg_edt.inc');
	fud_use('th.inc');
	fud_use('th_adm.inc');
	fud_use('rev_fmt.inc');
	fud_use('forum.inc');
	fud_use('fileio.inc');
	fud_use('isearch.inc');
	fud_use('attach.inc');
	fud_use('ipoll.inc');
	fud_use('private.inc');
	fud_use('forum_adm.inc', true);
	fud_use('groups_adm.inc', true);
	fud_use('glob.inc', true);

	$start_time = time();

/* Import phpBB2 users */

	$old_umask = umask(0111);

	q("DELETE FROM ".$DBHOST_TBL_PREFIX."users WHERE id>1");
	$theme = q_singleval("SELECT id FROM ".$DBHOST_TBL_PREFIX."themes WHERE (theme_opt & 3) >= 3 LIMIT 1");

	$r = bbq("SELECT * FROM {$ubb}Users WHERE U_Number>0 ORDER BY U_Number");
	
	print_msg('Importing Users '.db_count($r));
	
	while ($obj = db_rowobj($r)) {
		if (q_singleval("SELECT id FROM ".$DBHOST_TBL_PREFIX."users WHERE login='".addslashes($obj->U_Username)."' OR email='".addslashes($obj->U_Email)."'")) {
			print_msg("\tuser: ".$obj->U_Username);
			print_msg("\t\tWARNING: Cannot import user ".$obj->U_Username.", user with this email and/or login already exists");
			continue;
		}

		$users_opt = 4 | 4194304 | 16 | 32 | 512 | 8192 | 16384 | 2048 | 64 | 2 | 131072;

		if (strpos($obj->U_Groups, '-1-') !== false) {
			$users_opt |= 1048576;
		} else if (strpos($obj->U_Groups, '-2-') !== false) {
			$users_opt |= 524288;
		}

		if ($obj->Visible != 'yes') {
			$users_opt |= 32768;
		}
		if ($obj->U_AdminEmails != 'yes') {
			$users_opt |= 8;
		}
		if ($obj->U_Display == 'flat') {
			$users_opt |= 128;
		}
		if ($obj->U_View == 'collapsed') {
			$users_opt |= 256;
		}
		if ($obj->U_ShowSigs == 'yes') {
			$users_opt |= 4096;
		}
		if ($obj->U_Approved != 'yes') {
			$users_opt |= 2097152;
		}

		q("INSERT INTO ".$DBHOST_TBL_PREFIX."users 
			(id, login, alias, passwd, last_visit, join_date, email, location, sig,
			occupation, interests, users_opt, home_page, theme, last_read)
			VALUES (
				".(int)$obj->U_Number.",
				'".addslashes($obj->U_Username)."',
				'".addslashes(htmlspecialchars($obj->U_Username))."',
				'".$obj->U_Password."',
				".(int)$obj->U_Laston.",
				".(int)$obj->U_Registered.",
				'".addslashes($obj->U_Email)."',
				'".addslashes($obj->U_Location)."',
				'".addslashes($obj->U_Signature)."',
				'".addslashes($obj->U_Occupation)."',
				'".addslashes($obj->U_Hobbies)."',
				".$users_opt.",
				'".addslashes($obj->U_Homepage)."',
				".$theme.",
				".(int)$obj->U_Laston."
				)");

		if (!$obj->U_Picture || $obj->U_Picture == 'http://') {
			continue;
		}

		if (!($im = @getimagesize($obj->U_Picture))) {
			print_msg($obj->U_Picture." is invalid image");
			continue;
		}

		switch ($im[2]) {
			case IMAGETYPE_GIF:
				$ext = ".gif";
				break;
			case IMAGETYPE_JPEG:
				$ext = ".jpg";
				break;
			case IMAGETYPE_PNG:
				$ext = ".png";
				break;
			default:
				print_msg("Unsupported imagetype");
				continue;
			break;
		}

		$dest = $IMG_ROOT_DISK . "custom_avatars/". $obj->U_Number . $ext;

		if (!($fp = fopen($dest, "wb"))) {
			print_msg("Couldn't create avatar inside ".$dest);
			print_msg("Please ensure the script has access to perform this action and run it again");
			exit;
		}

		fwrite($fp, file_get_contents($obj->U_Picture));
		fclose($fp);

		$avatar = 0;

		$avatar_loc = '<img src="'.str_replace($WWW_ROOT_DISK, $WWW_ROOT, $dest).'" '.$im[3].'>';

		q("UPDATE {$DBHOST_TBL_PREFIX}users SET avatar={$avatar}, users_opt=(users_opt & ~ 4194304)|8388608, avatar_loc='{$avatar_loc}' WHERE id=".$obj->U_Number);
	}
	unset($r);
	umask($old_umask);
	print_msg('Finished Importing Users');

/* Import ubb Categories */
	
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."cat");
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."group_resources");
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."groups WHERE id>2");
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."forum");
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."group_members");
	
	$r = bbq("select * from {$ubb}w3t_Category ORDER BY Cat_Number");
	print_msg('Importing Categories '.db_count($r));
	$i = 1;
	while( $obj = db_rowobj($r) ) {
		q("INSERT INTO ".$DBHOST_TBL_PREFIX."cat (id, name, description, view_order, cat_opt) VALUES(".(int)$obj->Cat_Number.",'".addslashes($obj->Cat_Title)."', '".addslashes($obj->Cat_Description)."', ".$i++.", 3)");
	}
	unset($r);
	print_msg('Finished Importing Categories');

/* Import ubb Forums */
	$group_map = array(
		'Bo_Read_Perm'=> 1 | 262144 | 512 | 2 | 1024 | 16384 | 32768,
		'Bo_Write_Perm'=> 4 | 512 | 128,
		'Bo_Reply_Perm'=> 8
	);

	$r = bbq("select * from {$ubb}Boards ORDER BY Bo_Cat, Bo_Sorter");
	print_msg('Importing Forums '.db_count($r));
	while ($obj = db_rowobj($r)) {
		$_POST['frm_cat_id'] = $obj->Bo_Cat;
		$_POST['frm_name'] = $obj->Bo_Title;
		$_POST['frm_descr'] = $obj->Bo_Description;
		$_POST['frm_forum_opt'] = 0;
		if ($obj->Bo_Markup == 'On') {
			$_POST['frm_forum_opt'] |= 16;
		} else if ($obj->Bo_HTML != 'On') {
			$_POST['frm_forum_opt'] |= 8;
		}
		if ($obj->Bo_Moderated != 'no') {
			$_POST['frm_forum_opt'] |= 2;
		}

		$_POST['frm_max_file_attachments'] = 1;
		$_POST['frm_max_attach_size'] = 1024;

		$frm = new fud_forum();
		$id = $frm->add('LAST');

		q("UPDATE {$DBHOST_TBL_PREFIX}forum SET id={$obj->Bo_Number} WHERE id=".$id);
		q("UPDATE {$DBHOST_TBL_PREFIX}groups SET forum_id={$obj->Bo_Number} WHERE forum_id=".$id);
		q("UPDATE {$DBHOST_TBL_PREFIX}group_resources SET resource_id={$obj->Bo_Number} WHERE resource_id=".$id);

		$perms_reg = $perms_anon = 65536;

		foreach ($group_map as $k => $perm) {
			if (strpos($obj->{$k}, '-3-') !== false) {
				 $perms_reg |= $perm;
			}
			if (strpos($obj->{$k}, '-4-') !== false) {
				 $perms_anon |= $perm;
			}
		}

		$gid = q_singleval("SELECT id FROM {$DBHOST_TBL_PREFIX}groups WHERE forum_id=".$obj->Bo_Number);
		q("UPDATE {$DBHOST_TBL_PREFIX}group_members SET group_members_opt={$perms_anon} WHERE group_id={$gid} AND user_id=0");
		q("UPDATE {$DBHOST_TBL_PREFIX}group_members SET group_members_opt={$perms_reg} WHERE group_id={$gid} AND user_id=2147483647");
	}
	unset($r);
	print_msg('Finished Importing Forums');

/* Import ubb moderators */
	
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."mod");
	print_msg('Importing Moderators');

	$r = bbq("SELECT u.U_Number, f.Bo_Number FROM {$ubb}Moderators m 
			INNER JOIN {$ubb}Users u ON u.U_Username=m.Mod_Username
			INNER JOIN {$ubb}Boards f ON f.Bo_Keyword=m.Mod_Board");

	while ($obj = db_rowobj($r)) {
		q("INSERT INTO ".$DBHOST_TBL_PREFIX."mod (user_id, forum_id) VALUES(".(int)$obj->U_Number.", ".(int)$obj->Bo_Number.")");
	}

	unset($r);
	print_msg('Finished Importing Moderators');

/* Import ubb Topics & Messages */

	q("DELETE FROM ".$DBHOST_TBL_PREFIX."thread");
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."msg");
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."attach");
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."poll");
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."poll_opt");
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."poll_opt_track");

	$old_umask = umask(0111);

	$r = bbq("SELECT f.Bo_Number, m.*, u.U_Number AS edit_id
		FROM {$ubb}Posts m
		INNER JOIN {$ubb}Boards f N f.Bo_Keyword=m.B_Board
		LEFT JOIN {$ubb}Users u ON u.U_Username=m.B_LastEditBy
		ORDER BY B_Board, B_Main, B_Parent");

	print_msg('Importing Messages '.db_count($r));

	while ($obj = db_rowobj($r)) {
		/* new topic */
		if (!$obj->parent) {
			if ($obj->B_Sticky) {
				$thread_opt = 4;
				$orderexpiry = $obj->B_Sticky;
			} else {
				$orderexpiry = $thread_opt = 0;
			}
			q("INSERT INTO ".$DBHOST_TBL_PREFIX."thread (id, forum_id, n_rating, n_rating) VALUES(".(int)$obj->B_Number.", ".(int)$obj->Bo_Number.", ".(int)$obj->B_Rating.", ".(int)$obj->B_Rates.")");
		}
		$fileid = write_body($obj->B_Body, $len, $off);

		q("INSERT INTO ".$DBHOST_TBL_PREFIX."msg
			(id, thread_id, poster_id, post_stamp, update_stamp, updated_by, subject,
			 ip_addr, foff, length, file_id, msg_opt, apr, reply_to
		) VALUES (
			".(int)$obj->B_Number.",
			".(int)$obj->B_Main.",
			".(int)$obj->B_PosterId.",
			".(int)$obj->B_Posted.",
			".(int)$obj->B_LastEdit.",
			".(int)$obj->edit_id.",
			'".addslashes($obj->B_Subject)."',
			'".addslashes($obj->B_IP)."',
			".$off.",
			".$len.",
			".$fileid.",
			".(int)($obj->B_Signature ? 1 : 0).",
			".(int)($obj->B_Status == 'yes').",
			".(int)$obj->B_Parent."
			)"
		);

		/* handle any file attachments */
		if ($obj->B_File && $obj->B_File != 'http://') {
			if (!@file_exists($config['files'].$obj->B_File)) {
				print_msg("\tWARNING: file attachment ".$config['files'].$obj->B_File." doesn't exist");
				continue;
			}

			$mime = q_singleval("SELECT id FROM ".$DBHOST_TBL_PREFIX."mime WHERE fl_ext='".substr(strrchr($obj->B_File, '.'), 1)."'");

			$attach_id = db_qid("INSERT INTO ".$DBHOST_TBL_PREFIX."attach 
				(original_name, owner, message_id, dlcount, mime_type, fsize)
				VALUES (
					'".addslashes($obj->B_File)."',
					".(int)$obj->B_PosterId.",
					".(int)$obj->B_Number.",
					".(int)$obj->B_FileCounter.",
					".(int)$mime.",
					".(int)filesize($config['files'].$obj->B_File).")"
				);

			if (!copy($config['files'].$obj->B_File, $FILE_STORE.$attach_id.'.atch')) {
				print_msg("Couldn't copy file attachment (".$config['files'].$obj->B_File.") to (".$FILE_STORE.$attach_id.'.atch'.")");
				exit;
			}
			q("UPDATE ".$DBHOST_TBL_PREFIX."attach SET location='".$FILE_STORE.$attach_id.'.atch'."' WHERE id=".$attach_id);		
		}		

		/* handle polls */
		if ($obj->B_Poll) {
			$r2 = bbq("SELECT * FROM {$ubb}Polls WHERE P_Name='".addslashes($obj->B_Poll)."' ORDER BY P_Number");
			$row = db_rowobj($r2);
			$pid = db_qid("INSERT INTO ".$DBHOST_TBL_PREFIX."poll (name, owner, creation_date, expiry_date, forum_id)
			VALUES(
				'".addslashes($row->P_Title)."',
				".(int)$obj->B_PosterId.",
				".(int)$obj->B_Posted.",
				0,
				".(int)$obj->Bo_Number.")"
			);
			q("UPDATE ".$DBHOST_TBL_PREFIX."msg SET poll_id=".$pid." WHERE id=".$obj->B_Number);
			
			$opts = array();
			do {
				$opts[$obj->P_Number] = db_qid("INSERT INTO ".$DBHOST_TBL_PREFIX."poll_opt VALUES(poll_id, name) VALUES(".$pid.", '".addslashes($row->P_Option)."')");
			} while (($row = db_rowobj($r2)));
			
			/* handle poll voters */
			
		}
	}	
	unset($r);
	umask($old_umask);
	print_msg('Finished Importing Messages');

/* Import phpBB Thread Subscriptions */
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."thread_notify");
	$r = bbq("SELECT * FROM {$ubb}topics_watch WHERE notify_status=" . TOPIC_WATCH_NOTIFIED);
	print_msg('Importing Thread Subscriptions '.db_count($r));
	while ($obj = db_rowobj($r)) {
		q("INSERT INTO ".$DBHOST_TBL_PREFIX."thread_notify (user_id, thread_id) VALUES(".(int)$obj->user_id.", ".(int)$obj->topic_id.")");
	}
	unset($r);
	print_msg('Finished Importing Thread Subscriptions');

/* Import ubb banns */
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."blocked_logins");
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."ip_block");
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."email_block");
	
	$r = bbq("SELECT B_Username, B_Hostname FROM {$ubb}Banned");
	print_msg('Importing Bans '.db_count($r));
	while ($obj = db_rowobj($r)) {
		if ($obj->B_Hostname) {
			list($ca,$cb,$cc,$cd) = explode('.', $obj->B_Hostname);
			q("INSERT INTO ".$DBHOST_TBL_PREFIX."ip_block (ca,cb,cc,cd) VALUES($ca,$cb,$cc,$cd)");
		} else {
			q("INSERT INTO ".$DBHOST_TBL_PREFIX."blocked_logins (login) VALUES('".addslashes($obj->B_Username)."')");
			q("UPDATE {$DBHOST_TBL_PREFIX}users SET users_opt=users_opt|65536 WHERE login='".addslashes($obj->B_Username)."'");
		}
	}
	unset($r);
	print_msg('Finished Importing Bans');

/* Import phpBB private messages */
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."pmsg");

	$r = bbq("SELECT p.*, pt.privmsgs_text, u.username FROM {$ubb}privmsgs p INNER JOIN {$ubb}privmsgs_text pt ON p.privmsgs_id=pt.privmsgs_text_id INNER JOIN {$ubb}users u ON u.user_id=p.privmsgs_to_userid");
	print_msg('Importing Private Messages '.db_count($r));
	while ($obj = db_rowobj($r)) {
		if ($obj->privmsgs_type != PRIVMSGS_READ_MAIL && $obj->privmsgs_type != PRIVMSGS_NEW_MAIL && $obj->privmsgs_type != PRIVMSGS_SENT_MAIL) {
			continue;
		}

		list($off, $len) = write_pmsg_body(bbcode2fudcode($obj->privmsgs_text));
		$pmsg_opt = ($obj->privmsgs_attach_sig ? 1 : 0) | ($obj->privmsgs_enable_smilies ? 0 : 2);
		$read_stamp = $obj->privmsgs_type != PRIVMSGS_NEW_MAIL ? $obj->privmsgs_date : 0;
		$folder = $obj->privmsgs_type != PRIVMSGS_SENT_MAIL ? 1 : 3;

		q("INSERT INTO ".$DBHOST_TBL_PREFIX."pmsg 
			(ouser_id, duser_id, ip_addr, post_stamp, read_stamp, fldr, subject, pmsg_opt, foff, length, to_list)
			VALUES(
				".(int)$obj->privmsgs_from_userid.",
				".(int)$obj->privmsgs_to_userid.",
				'".phpbb_decode_ip($obj->privmsgs_ip)."',
				".(int)$obj->privmsgs_date.",
				".$read_stamp.",
				".$folder.",
				'".addslashes($obj->privmsgs_subject)."',
				".$pmsg_opt.",
				".$off.",
				".$len.",
				'".addslashes($obj->username)."')"
		);
	}
	unset($r);
	print_msg('Finished Importing Private Messages');

/* Import phpBB settings */
	print_msg('Importing Forum Settings');
	$list = array();
	$list['FORUM_TITLE'] = $board_config['sitename'];
	$list['SESSION_TIMEOUT'] = (int) $board_config['session_length'];
	$list['POSTS_PER_PAGE'] = (int) $board_config['posts_per_page'];
	$list['THREADS_PER_PAGE'] = (int) $board_config['topics_per_page'];
	$list['NOTIFY_FROM'] = $board_config['board_email'];
	$list['FLOOD_CHECK_TIME'] = (int) $board_config['flood_interval'];
	$list['CUSTOM_AVATAR_MAX_DIM'] = $board_config['avatar_max_width'].'x'.$board_config['avatar_max_height'];
	$list['CUSTOM_AVATAR_MAX_SIZE'] = (int) $board_config['avatar_filesize'];
	if (!$board_config['board_disable']) {
		$FUD_OPT_1 |= 1;
	}
	if ($board_config['allow_sig']) {
		$FUD_OPT_1 |= 32768;
	}
	if ($board_config['require_activation']) {
		$FUD_OPT_2 |= 1;
	}
	if ($board_config['board_email_form']) {
		$FUD_OPT_2 |= 1073741824;
	}	
	if (!$board_config['privmsg_disable']) {
		$FUD_OPT_1 |= 1024;
	}
	if ($board_config['allow_smilies']) {
		$FUD_OPT_1 |= 8192 | 262144;
	}

	$FUD_OPT_1 = $FUD_OPT_1 &~ 28;
	if ($board_config['allow_avatar_local']) {
		$FUD_OPT_1 |= 16;
	}
	if ($board_config['allow_avatar_remote']) {
		$FUD_OPT_1 |= 4;
	}
	if ($board_config['allow_avatar_upload']) {
		$FUD_OPT_1 = 8;
	}

	$FUD_OPT_1 = $FUD_OPT_1 &~ (4096 | 2048 | 131072 | 65536);
	if ($board_config['allow_bbcode']) {
		$FUD_OPT_1 |= 4096 | 131072;
	} else if (!$board_config['allow_html']) {
		$FUD_OPT_1 |= 2048 | 65536;
	}

	change_global_settings($list);

	q("DELETE FROM ".$DBHOST_TBL_PREFIX."ses");

	print_msg('Finished Importing Forum Settings');

	$time_taken = time() - $start_time;
	if ($time_taken > 120) {
		$time_taken .= ' seconds';
	} else {
		$m = floor($time_taken/60);
		$s = $time_taken - $m*60;
		$time_taken = $m." minutes ".$s." seconds";
	}	

	print_msg("\n\nConversion of phpBB2 to FUDforum2 has been completed\n Time Taken: ".$time_taken."\n");
	print_msg("To complete the process run the consistency checker at:");
	print_msg($WWW_ROOT."adm/consist.php");
?>
