<?php
/***************************************************************************
* copyright            : (C) 2001-2006 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: ubb.php,v 1.11 2005/12/07 18:07:45 hackie Exp $
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
	$UBB_CFG = "/path/to/ubbthreads_config.txt";

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

	$start_time = time();

/* Import ubb users */

	$old_umask = umask(0111);
	$IMG_ROOT_DISK = $WWW_ROOT_DISK . 'images/';
	$dupe_list = array();

	q("DELETE FROM ".$DBHOST_TBL_PREFIX."users WHERE id>1");
	$theme = q_singleval("SELECT id FROM ".$DBHOST_TBL_PREFIX."themes WHERE (theme_opt & 3) >= 3 LIMIT 1");

	$r = bbq("SELECT * FROM {$ubb}Users WHERE U_Number>1 ORDER BY U_Number");
	
	print_msg('Importing Users '.db_count($r));
	
	while ($obj = db_rowobj($r)) {
		if (($dupe = q_singleval("SELECT id FROM ".$DBHOST_TBL_PREFIX."users WHERE login='".addslashes($obj->U_Username)."'"))) {
			$dupe_list[$obj->U_Number] = $dupe;
			print_msg("\tuser: ".$obj->U_Username);
			print_msg("\t\tWARNING: Cannot import user ".$obj->U_Username.", user with this login already exists");
			continue;
		} else if (($dupe = q_singleval("SELECT id FROM ".$DBHOST_TBL_PREFIX."users WHERE email='".addslashes($obj->U_Email)."'"))) {
			$dupe_list[$obj->U_Number] = $dupe;
			print_msg("\temail: ".$obj->U_Email);
			print_msg("\t\tWARNING: Cannot import user ".$obj->U_Email.", user with this email already exists");
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

/* XXX: uncomment continue to disable avatar fetching continue; */

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

// Import ubb Categories

	q("DELETE FROM ".$DBHOST_TBL_PREFIX."cat");
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."group_resources");
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."groups WHERE id>2");
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."forum");
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."group_members");
	
	$r = bbq("select * from {$ubb}Category ORDER BY Cat_Number");
	print_msg('Importing Categories '.db_count($r));
	$i = 1;
	while( $obj = db_rowobj($r) ) {
		q("INSERT INTO ".$DBHOST_TBL_PREFIX."cat (id, name, description, view_order, cat_opt) VALUES(".(int)$obj->Cat_Number.",'".addslashes($obj->Cat_Title)."', '".addslashes($obj->Cat_Description)."', ".$i++.", 3)");
	}
	unset($r);
	print_msg('Finished Importing Categories');

// Import ubb Forums
	$group_map = array(
		'Bo_Read_Perm'=> 1 | 262144 | 512 | 2 | 1024 | 16384 | 32768,
		'Bo_Write_Perm'=> 4 | 512 | 128,
		'Bo_Reply_Perm'=> 8
	);
	if (!$config['allowimages']) {
		$group_map['Bo_Read_Perm'] = $group_map['Bo_Read_Perm'] &~ 32768;
	}

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
		$_POST['frm_max_attach_size'] = round((int) $config['filesize'] / 1024);

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

// Import ubb moderators

	q("DELETE FROM ".$DBHOST_TBL_PREFIX."mod");
	print_msg('Importing Moderators');

	$r = bbq("SELECT u.U_Number, f.Bo_Number FROM {$ubb}Moderators m 
			INNER JOIN {$ubb}Users u ON u.U_Username=m.Mod_Username
			INNER JOIN {$ubb}Boards f ON f.Bo_Keyword=m.Mod_Board");

	while ($obj = db_rowobj($r)) {
		if (isset($dupe_list[$obj->U_Number])) {
			$obj->U_Number = $dupe_list[$obj->U_Number];		
		}
		q("INSERT IGNORE INTO ".$DBHOST_TBL_PREFIX."mod (user_id, forum_id) VALUES(".(int)$obj->U_Number.", ".(int)$obj->Bo_Number.")");
	}

	unset($r);
	print_msg('Finished Importing Moderators');

// Import ubb Topics & Messages

	q("DELETE FROM ".$DBHOST_TBL_PREFIX."thread");
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."msg");
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."attach");
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."poll");
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."poll_opt");
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."poll_opt_track");

	$old_umask = umask(0111);

	print_msg('Importing Messages');	
	$j = $i = 0; 
	$tmp = bbq("SELECT MAX(B_Number) as mp FROM {$ubb}Posts");
	$tmp = db_rowobj($tmp);
	$max = $tmp->mp;

while (1) {
	$r = bbq("SELECT f.Bo_Number, m.*, u.U_Number AS edit_id
		FROM {$ubb}Posts m
		INNER JOIN {$ubb}Boards f ON f.Bo_Keyword=m.B_Board
		LEFT JOIN {$ubb}Users u ON u.U_Username=m.B_LastEditBy
		WHERE B_Number BETWEEN ".$i." AND ".($i + 99)."
		ORDER BY B_Board, B_Main, B_Parent");

	while ($obj = db_rowobj($r)) {
		// new topic
		if (!$obj->B_Parent) {
			if ($obj->B_Sticky) {
				$thread_opt = 4;
				$orderexpiry = $obj->B_Sticky;
			} else {
				$orderexpiry = $thread_opt = 0;
			}
			q("INSERT IGNORE INTO ".$DBHOST_TBL_PREFIX."thread (id, forum_id) VALUES(".(int)$obj->B_Number.", ".(int)$obj->Bo_Number.")");
		}
		$fileid = write_body($obj->B_Body, $len, $off, (int)$obj->Bo_Number);

		if (isset($dupe_list[$obj->B_PosterId])) {
			$obj->B_PosterId = $dupe_list[$obj->B_PosterId];		
		}
		if ($obj->edit_id && isset($dupe_list[$obj->edit_id])) {
			$obj->edit_id = $dupe_list[$obj->edit_id];	
		}

		q("INSERT IGNORE INTO ".$DBHOST_TBL_PREFIX."msg
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
			".(int)($obj->B_Status == 'O').",
			".(int)$obj->B_Parent."
			)"
		);

		// handle any file attachments
		if ($obj->B_File && $obj->B_File != 'http://') {
			if (!@file_exists($config['files']."/".$obj->B_File)) {
				print_msg("\tWARNING: file attachment ".$config['files']."/".$obj->B_File." doesn't exist");
				continue;
			}

			$mime = q_singleval("SELECT id FROM ".$DBHOST_TBL_PREFIX."mime WHERE fl_ext='".substr(strrchr($obj->B_File, '.'), 1)."'");

			if (($pos = strpos($obj->B_File, '-')) !== false) {
				$realname = substr($obj->B_File, $pos + 1);
			} else {
				$realname = $obj->B_File;
			}

			$attach_id = db_qid("INSERT INTO ".$DBHOST_TBL_PREFIX."attach 
				(original_name, owner, message_id, dlcount, mime_type, fsize)
				VALUES (
					'".addslashes($obj->B_File)."',
					".(int)$obj->B_PosterId.",
					".(int)$obj->B_Number.",
					".(int)$obj->B_FileCounter.",
					".(int)$mime.",
					".(int)filesize($config['files']."/".$obj->B_File).")"
				);

			if (!copy($config['files']."/".$obj->B_File, $FILE_STORE.$attach_id.'.atch')) {
				print_msg("Couldn't copy file attachment (".$config['files']."/".$obj->B_File.") to (".$FILE_STORE.$attach_id.'.atch'.")");
				exit;
			}
			q("UPDATE ".$DBHOST_TBL_PREFIX."attach SET location='".$FILE_STORE.$attach_id.'.atch'."' WHERE id=".$attach_id);
		}		

		// handle polls
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
				$opts[$row->P_Number] = db_qid("INSERT INTO ".$DBHOST_TBL_PREFIX."poll_opt (poll_id, name) VALUES(".$pid.", '".addslashes($row->P_Option)."')");
			} while (($row = db_rowobj($r2)));
			
			// handle poll voters
			$r2 = bbq("select count(*) AS cnt, P_Number FROM {$ubb}PollData WHERE P_Name='".addslashes($obj->B_Poll)."' GROUP BY P_Number");
			while ($row = db_rowobj($r2)) {
				q("INSERT INTO ".$DBHOST_TBL_PREFIX."poll_opt_track 
					(poll_id, poll_opt, user_id) SELECT ".$pid.", ".$opts[$row->P_Number].", id 
					FROM ".$DBHOST_TBL_PREFIX."users ORDER BY RAND() LIMIT ".$row->cnt);
			}
			unset($r2, $opts);
		}
	}

	unset($r, $r2);

	if ($i > $max) {
		var_dump($i, $max);
		break;
	}

	$i += 100;
	echo "pos: " . $i . "\n";
}
	umask($old_umask);
	print_msg('Finished Importing Messages');

// import ubb ratings
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."thread_rate_track");
	$r = bbq("SELECT r.R_What, r.R_Rating, u.U_Number
			FROM {$ubb}Ratings r
			INNER JOIN {$ubb}Users u ON u.U_Username=r.R_Rater
			WHERE R_Type='t'");
	print_msg('Importing Topic Ratings '.db_count($r));
	while ($obj = db_rowobj($r)) {
		if (isset($dupe_list[$obj->U_Number])) {
			$obj->U_Number = $dupe_list[$obj->U_Number];		
		}
		q("INSERT IGNORE INTO ".$DBHOST_TBL_PREFIX."thread_rate_track 
			(thread_id, user_id, rating) 
			VALUES (".(int)$obj->R_What.", ".(int)$obj->U_Number.", ".(int)$obj->R_Rating.")");
	}
	unset($r);
	print_msg('Finished Importing Topic Ratings');

// Import ubb forum Subscriptions
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."forum_notify");
	$r = bbq("SELECT u.U_Number, f.Bo_Number 
		FROM {$ubb}Subscribe s
		INNER JOIN {$ubb}Users u ON u.U_Username=s.S_Username
		INNER JOIN {$ubb}Boards f ON f.Bo_Keyword=s.S_Board");
	print_msg('Importing Forum Subscriptions '.db_count($r));
	while ($obj = db_rowobj($r)) {
		if (isset($dupe_list[$obj->U_Number])) {
			$obj->U_Number = $dupe_list[$obj->U_Number];		
		}
		q("INSERT IGNORE INTO ".$DBHOST_TBL_PREFIX."forum_notify (user_id, forum_id) VALUES(".(int)$obj->U_Number.", ".(int)$obj->Bo_Number.")");
	}
	unset($r);
	print_msg('Finished Importing Forum Subscriptions');

// Import ubb topic Subscriptions
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."thread_notify");
	$r = bbq("SELECT u.U_Number, s.F_Thread 
			FROM {$ubb}Favorites s
			INNER JOIN {$ubb}Users u ON u.U_Username=s.F_Owner");
	print_msg('Importing Thread Subscriptions '.db_count($r));
	while ($obj = db_rowobj($r)) {
		if (isset($dupe_list[$obj->U_Number])) {
			$obj->U_Number = $dupe_list[$obj->U_Number];		
		}
		q("INSERT IGNORE INTO ".$DBHOST_TBL_PREFIX."thread_notify (user_id, thread_id) VALUES(".(int)$obj->U_Number.", ".(int)$obj->F_Thread.")");
	}
	unset($r);
	print_msg('Finished Importing Thread Subscriptions');

// Import ubb buddy list
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."buddy");
	$r = bbq("SELECT u1.U_Number AS s, u2.U_Number AS d
			FROM {$ubb}AddressBook a
			INNER JOIN {$ubb}Users u1 ON u1.U_Username=a.Add_Owner
			INNER JOIN {$ubb}Users u2 ON u2.U_Username=a.Add_Member");
	print_msg('Importing Buddy List '.db_count($r));
	while ($obj = db_rowobj($r)) {
		q("INSERT IGNORE INTO ".$DBHOST_TBL_PREFIX."buddy (user_id, bud_id) VALUES(".(int)$obj->d.", ".(int)$obj->s.")");
	}
	unset($r);
	print_msg('Finished Importing Buddy List');

// Import ubb banns
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."blocked_logins");
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."ip_block");
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."email_block");

	$r = bbq("SELECT B_Username, B_Hostname FROM {$ubb}Banned");
	print_msg('Importing Bans '.db_count($r));
	while ($obj = db_rowobj($r)) {
		if ($obj->B_Hostname && $obj->B_Hostname != 'NULL') {
			list($ca,$cb,$cc,$cd) = explode('.', str_replace('%', '256', trim($obj->B_Hostname)));
			$ca = (int) $ca;
			$cb = (int) $cb;
			$cc = (int) $cc;
			$cd = (int) $cd;
			q("INSERT INTO  ".$DBHOST_TBL_PREFIX."ip_block (ca,cb,cc,cd) VALUES($ca,$cb,$cc,$cd)");
		} else {
			q("INSERT INTO ".$DBHOST_TBL_PREFIX."blocked_logins (login) VALUES('".addslashes($obj->B_Username)."')");
			q("UPDATE {$DBHOST_TBL_PREFIX}users SET users_opt=users_opt|65536 WHERE login='".addslashes($obj->B_Username)."'");
		}
	}
	unset($r);
	print_msg('Finished Importing Bans');

/* Import ubb private messages */
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."pmsg");

	$r = bbq("SELECT u1.U_Number AS sen, u2.U_Number AS rec, m.*
			FROM {$ubb}Messages m
			INNER JOIN {$ubb}Users u1 ON u1.U_Username=m.M_Sender
			INNER JOIN {$ubb}Users u2 ON u2.U_Username=m.M_Username
			ORDER BY m.M_Number");
	print_msg('Importing Private Messages '.db_count($r));
	while ($obj = db_rowobj($r)) {
		list($off, $len) = write_pmsg_body($obj->M_Message);
		if (isset($dupe_list[$obj->sen])) {
			$obj->sen = $dupe_list[$obj->sen];		
		}
		if (isset($dupe_list[$obj->rec])) {
			$obj->rec = $dupe_list[$obj->rec];		
		}

		q("INSERT INTO ".$DBHOST_TBL_PREFIX."pmsg 
			(ouser_id, duser_id, post_stamp, read_stamp, fldr, subject, pmsg_opt, foff, length, to_list)
			VALUES(
				".(int)$obj->sen.",
				".(int)$obj->rec.",
				".(int)$obj->M_Sent.",
				".(int)$obj->M_Sent.",
				1,
				'".addslashes($obj->M_Subject)."',
				1,
				".$off.",
				".$len.",
				'".addslashes($obj->M_Username)."')"
		);
	}
	unset($r);
	print_msg('Finished Importing Private Messages');

/* Import ubb settings */
	print_msg('Importing Forum Settings');
	$list = array();

	$list['FORUM_TITLE'] = $config['title'];
	$list['ADMIN_EMAIL'] = $list['NOTIFY_FROM'] = $config['emailaddy'];
	$list['SESSION_TIMEOUT'] = (int) $config['cookieexp'];
	$list['FORUM_SIG_ML'] = (int) $config['Sig_length'];
	$list['PRIVATE_ATTACH_SIZE'] = (int) $config['filesize'];
	$list['EDIT_TIME_LIMIT'] = (int)$config['edittime'] * 60;

	$FUD_OPT_1 = $FUD_OPT_1 &~ (16384|1048576|134217728|1024|1|2|8388608);
	$FUD_OPT_2 = $FUD_OPT_2 &~ (16384|1073741824);

	if ($config['allowimages']) {
		$FUD_OPT_1 |= 16384;
	}
	if ($config['checkage']) {
		$FUD_OPT_1 |= 1048576;
	}
	if ($config['showip'] == 1) {
		$FUD_OPT_1 |= 134217728;
	}
	if ($config['private']) {
		$FUD_OPT_1 |= 1024;
	}
	if ($config['isclosed']) {
		$FUD_OPT_1 |= 1;
	}
	if (!$config['userreg']) {
		$FUD_OPT_1 |= 2;
	}
	if ($config['userlist']) {
		$FUD_OPT_1 |= 8388608;		
	}
	if ($config['compression']) {
		$FUD_OPT_2 |= 16384;
		$list['PHP_COMPRESSION_LEVEL'] = 9;
	}
	if ($config['mailpost']) {
		$FUD_OPT_2 |= 1073741824;
	}

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

	print_msg("\n\nConversion of UBB.Threads to FUDforum2 has been completed\n Time Taken: ".$time_taken."\n");
	print_msg("To complete the process run the consistency checker at:");
	print_msg($WWW_ROOT."adm/consist.php");
?>
