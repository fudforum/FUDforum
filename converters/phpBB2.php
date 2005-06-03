<?php
/***************************************************************************
* copyright            : (C) 2001-2004 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: phpBB2.php,v 1.22 2005/06/03 19:39:47 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it 
* under the terms of the GNU General Public License as published by the 
* Free Software Foundation; either version 2 of the License, or 
* (at your option) any later version.
***************************************************************************/

	/* PHPBB 2 (2.0.X) - FUDforum Conversion script - Brief Instructions */

	/* If you intend to run this script via the console, make sure to UNLOCK the 
	 * FUDforum 1st. I recommend running this script via the web unless the forum
	 * you are importing is very large.
	 */

	/* Specify the FULL path to the phpBB2 config.php file */
	$PHPBB_CFG = "/path/to/config.php";

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
	$str = preg_replace('!\[code\:([^\]]+)\]!is', '[code]', $str);
	$str = preg_replace('!\[/code\:([^\]]+)\]!is', '[/code]', $str);
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

	$gl = @include($PHPBB_CFG);
	if ($gl === FALSE) {
		exit("Unable to open phpBB2 configuration at '{$PHPBB_CFG}'.\n");
	}

	if (!($ib = mysql_connect($dbhost, $dbuser, $dbpasswd))) {
		exit("Failed to connect to database containing phpBB2 settings using MySQL information inside '{$PHPBB_CFG}'.\n");
	}
	define('phpsql', $ib);

	$bb2 = $dbname . '.' . $table_prefix;

	$PHPBB_INSTALL_ROOT = dirname($PHPBB_CFG) . '/';
	$IMG_ROOT_DISK = $WWW_ROOT_DISK . 'images/';
	require($PHPBB_INSTALL_ROOT."includes/constants.php");

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

	print_msg('Beginning Conversion Process');
	$board_config = array();
	print_msg('Reading phpBB2 config');
	$r = bbq("SELECT * FROM {$bb2}config");
	while (list($k,$v) = db_rowarr($r)) {
		$board_config[$k] = $v;
	}
	unset($r);
	print_msg('Done: Reading phpBB2 config');

/* Import phpBB smilies */
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."smiley");
	
	$old_umask = umask(0111);
	$r = bbq("SELECT * FROM {$bb2}smilies");
	print_msg('Importing Smilies: '.db_count($r));
	$i = 1;
	while ($obj = db_rowobj($r)) {
		if (!q_singleval("SELECT id FROM ".$DBHOST_TBL_PREFIX."smiley WHERE img='".$obj->smile_url."'")) {
			if (!copy($PHPBB_INSTALL_ROOT.$board_config['smilies_path'].'/'.$obj->smile_url, $IMG_ROOT_DISK.'smiley_icons/'.$obj->smile_url)) {
				print_msg("Coulnd't copy smiley image (".$PHPBB_INSTALL_ROOT.$board_config['smilies_path'].$obj->smile_url.") to (".$IMG_ROOT_DISK.'smiley_icons/'.$obj->smile_url.")");
				exit;
			}
			q("INSERT INTO ".$DBHOST_TBL_PREFIX."smiley (img,code,descr,vieworder) VALUES('{$obj->smile_url}','".addslashes($obj->code)."','".addslashes($obj->emoticon)."', '{$i}')");
		} else {
			q("UPDATE ".$DBHOST_TBL_PREFIX."smiley SET code=".__FUD_SQL_CONCAT__."(code, '~', '".addslashes($obj->code)."') WHERE img='{$obj->smile_url}'");
		}
	}
	umask($old_umask);
	unset($r);
	print_msg('Finished Importing Smilies');

/* Import phpBB avatar galleries */

function import_av_gal($dirn)
{
	print_msg("\tfrom: $dirn");
	
	$odir = getcwd();
	chdir($dirn);
	$dir = opendir('.');
	readdir($dir); readdir($dir);
	while ($file = readdir($dir)) {
		if (@is_dir($file)) {
			import_av_gal($file);
			continue;
		}

		$file_ext = strrchr($file, '.');
		switch (substr(strrchr($file, '.'), 1)) {
			case 'jpg':
			case 'jpeg':
			case 'png':
			case 'gif':
				if( q_singleval("SELECT id FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."avatar WHERE img='".addslashes($file)."'") ) {
					/* dupe avatar */
					continue;	
				}
			
				if( !copy($file, $GLOBALS['IMG_ROOT_DISK'].'avatars/'.$file) ) {
					print_msg("Couldn't copy avatar (".getcwd().'/'.$file.") to (".$GLOBALS['IMG_ROOT_DISK'].'avatars/'.$file.")");
					exit;				
				}
				q("INSERT INTO ".$GLOBALS['DBHOST_TBL_PREFIX']."avatar (img,descr) VALUES('".addslashes($file)."','".addslashes($dirn.' '.$file)."')");
				$GLOBALS["av_gal"]++;
				break;
		}
	}
	closedir($dir);
	chdir($odir);
}
	
	print_msg('Importing Avatar Galleries');
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."avatar");
	$old_umask = umask(0111);
	$GLOBALS["av_gal"] = 0;
	import_av_gal($PHPBB_INSTALL_ROOT.$board_config['avatar_gallery_path'].'/');
	umask($old_umask);
	print_msg('Finished Importing Avatar Galleries, <b>'.$GLOBALS["av_gal"].'</b> avatars improted');

/* Import phpBB2 users */

	$old_umask = umask(0111);

	q("DELETE FROM ".$DBHOST_TBL_PREFIX."users WHERE id>1");
	$theme = q_singleval("SELECT id FROM ".$DBHOST_TBL_PREFIX."themes WHERE (theme_opt & 3) >= 3 LIMIT 1");

	$r = bbq("SELECT * FROM {$bb2}users WHERE user_id>0 ORDER BY user_id");
	
	print_msg('Importing Users '.db_count($r));
	
	while ($obj = db_rowobj($r)) {
		if (q_singleval("SELECT id FROM ".$DBHOST_TBL_PREFIX."users WHERE login='".addslashes($obj->username)."' OR email='".addslashes($obj->user_email)."'")) {
			print_msg("\tuser: ".$obj->username);
			print_msg("\t\tWARNING: Cannot import user ".$obj->username.", user with this email and/or login already exists");
			continue;
		}

		$users_opt = 4 | 4194304 | 16 | 32 | 128 | 256 | 512 | 4096 | 8192 | 16384;

		if ($obj->user_level == ADMIN) {
			$users_opt |= 1048576;
		} else if ($obj->user_level == MOD) {
			$users_opt |= 524288;
		}

		if ($obj->user_viewemail) {
			$users_opt |= 1;
		}
		if ($obj->user_attachsig) {
			$users_opt |= 2048;
		}
		if ($obj->user_notify) {
			$users_opt |= 2;
		}
		if ($obj->user_notify_pm) {
			$users_opt |= 64;
		}
		if (!$obj->user_allow_viewonline) {
			$users_opt |= 32768;
		}
		if ($obj->user_active) {
			$users_opt |= 131072;
		}

		q("INSERT INTO ".$DBHOST_TBL_PREFIX."users 
			(id, login, alias, passwd, last_visit, join_date, email, icq, location,
			 sig, aim, yahoo, msnm, occupation, interests, conf_key, users_opt, home_page, theme, last_read)
			VALUES (
				".(int)$obj->user_id.",
				'".addslashes($obj->username)."',
				'".addslashes(htmlspecialchars($obj->username))."',
				'".$obj->user_password."',
				".(int)$obj->user_lastvisit.",
				".(int)$obj->user_regdate.",
				'".addslashes($obj->user_email)."',
				".($obj->user_icq ? $obj->user_icq : 'NULL').",
				'".addslashes($obj->user_from)."',
				'".addslashes(bbcode2fudcode($obj->user_sig))."',
				'".addslashes($obj->user_aim)."',
				'".addslashes($obj->user_yim)."',
				'".addslashes($obj->user_msnm)."',
				'".addslashes($obj->user_occ)."',
				'".addslashes($obj->user_interests)."',
				'".$obj->user_actkey."',
				".$users_opt.",
				'".addslashes($obj->user_website)."',
				".$theme.",
				".(int)$obj->user_lastvisit."
				)");

		if ($obj->user_avatar_type == USER_AVATAR_NONE) {
			continue;
		}

		switch ($obj->user_avatar_type) {
			case USER_AVATAR_UPLOAD: 
				if (!file_exists($PHPBB_INSTALL_ROOT.$board_config['avatar_path']."/".$obj->user_avatar)) {
					print_msg("\tuser: ".$obj->username);
					print_msg("\t\tWARNING: missing avatar file: ".$PHPBB_INSTALL_ROOT.$board_config['avatar_path']."/".$obj->user_avatar);
					continue;
					break;	
				}

				if (!($im = getimagesize($PHPBB_INSTALL_ROOT.$board_config['avatar_path']."/".$obj->user_avatar))) {
					print_msg($PHPBB_INSTALL_ROOT.$board_config['avatar_path']."/".$obj->user_avatar." is invalid image");
					continue;
				}

				$dest = $IMG_ROOT_DISK . "custom_avatars/". $obj->user_id . strrchr($obj->user_avatar, '.');

				if (!copy($PHPBB_INSTALL_ROOT.$board_config['avatar_path']."/".$obj->user_avatar, $dest)) {
					print_msg("Couldn't copy avatar ".$PHPBB_INSTALL_ROOT.$board_config['avatar_path']."/".$obj->user_avatar." to ".$dest);
					print_msg("Please ensure the script has access to perform this action and run it again");
					exit;
				} else {
					$avatar = 0;
				}
				break;
			case USER_AVATAR_REMOTE:
				if (!($im = @getimagesize($obj->user_avatar))) {
					print_msg($obj->user_avatar." is invalid image");
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

				$dest = $IMG_ROOT_DISK . "custom_avatars/". $obj->user_id . $ext;

				if (!($fp = fopen($dest, "wb"))) {
					print_msg("Couldn't create avatar inside ".$dest);
					print_msg("Please ensure the script has access to perform this action and run it again");
					exit;
				}
				fwrite($fp, file_get_contents($obj->user_avatar));
				fclose($fp);

				$avatar = 0;
				break;
			case USER_AVATAR_GALLERY:
				$avatar = q_singleval("SELECT id FROM {$DBHOST_TBL_PREFIX}avatar WHERE img='".addslashes(basename($obj->user_avatar))."'");
				if (!$avatar) {
					continue;
				}
				$dest = $IMG_ROOT_DISK.'avatars/'.basename($obj->user_avatar);
				$im = getimagesize($dest);
				break;
		}
		$avatar_loc = '<img src="'.str_replace($WWW_ROOT_DISK, $WWW_ROOT, $dest).'" '.$im[3].'>';
		q("UPDATE {$DBHOST_TBL_PREFIX}users SET avatar={$avatar}, users_opt=(users_opt & ~ 4194304)|8388608, avatar_loc='{$avatar_loc}' WHERE id=".$obj->user_id);
	}
	unset($r);
	umask($old_umask);
	print_msg('Finished Importing Users');

/* Import phpBB2 Categories */
	
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."cat");
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."group_resources");
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."groups WHERE id>2");
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."forum");
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."group_members");
	
	$r = bbq("select * from {$bb2}categories ORDER BY cat_order");
	print_msg('Importing Categories '.db_count($r));
	$i = 1;
	while( $obj = db_rowobj($r) ) {
		q("INSERT INTO ".$DBHOST_TBL_PREFIX."cat (id, name, view_order, cat_opt) VALUES(".(int)$obj->cat_id.",'".addslashes($obj->cat_title)."',".$i++.", 3)");
	}
	unset($r);
	print_msg('Finished Importing Categories');

/* Import phpBB2 Forums */

function append_perm_str($perm, $who)
{
	return INT_yn(($perm==$who)?1:0);
}
	
$group_map = array(
'auth_view'=> 1 | 262144,
'auth_read'=> 2 | 1024 | 16384 | 32768,
'auth_post'=> 4,
'auth_reply'=> 8,
'auth_edit'=> 0,
'auth_delete'=> 0,
'auth_sticky'=> 64,
'auth_vote'=> 512,
'auth_pollcreate'=> 128,
'auth_attachments'=> 256
);

	$r = bbq("select * from {$bb2}forums ORDER BY forum_order");
	print_msg('Importing Forums '.db_count($r));
	while ($obj = db_rowobj($r)) {
		$_POST['frm_cat_id'] = $obj->cat_id;
		$_POST['frm_name'] = $obj->forum_name;
		$_POST['frm_descr'] = $obj->forum_desc;
		$_POST['frm_forum_opt'] = 16;
		$_POST['frm_max_file_attachments'] = 1;
		$_POST['frm_max_attach_size'] = 1024;

		$frm = new fud_forum();
		$id = $frm->add('LAST');

		q("UPDATE {$DBHOST_TBL_PREFIX}forum SET id={$obj->forum_id} WHERE id=".$id);
		q("UPDATE {$DBHOST_TBL_PREFIX}groups SET forum_id={$obj->forum_id} WHERE forum_id=".$id);
		q("UPDATE {$DBHOST_TBL_PREFIX}group_resources SET resource_id={$obj->forum_id} WHERE resource_id=".$id);

		$perms_reg = $perms_anon = 65536;

		foreach ($group_map as $k => $v) {
			if ($obj->{$k} == AUTH_ALL) {
				$perms_reg |= $v;
				$perms_anon |= $v;
			} else if ($obj->{$k} == AUTH_REG) {
				$perms_reg |= $v;
			}
		}

		$gid = q_singleval("SELECT id FROM {$DBHOST_TBL_PREFIX}groups WHERE forum_id=".$obj->forum_id);
		q("UPDATE {$DBHOST_TBL_PREFIX}group_members SET group_members_opt={$perms_anon} WHERE group_id={$gid} AND user_id=0");
		q("UPDATE {$DBHOST_TBL_PREFIX}group_members SET group_members_opt={$perms_reg} WHERE group_id={$gid} AND user_id=2147483647");
	}
	unset($r);
	print_msg('Finished Importing Forums');

/* Import phpBB moderators */
	
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."mod");
	print_msg('Importing Moderators');

	$r = bbq("SELECT ug.user_id, f.forum_id 
			FROM {$bb2}auth_access ua
			INNER JOIN {$bb2}groups g ON ua.group_id=g.group_id 
			INNER JOIN {$bb2}user_group ug ON ug.group_id=ua.group_id 
			INNER JOIN {$bb2}users u ON u.user_id=ug.user_id 
			INNER JOIN {$bb2}forums f ON f.forum_id=ua.forum_id 
			WHERE auth_mod=1 GROUP BY ua.forum_id, ug.user_id");

	while ($obj = db_rowobj($r)) {
		q("INSERT INTO ".$DBHOST_TBL_PREFIX."mod (user_id, forum_id) VALUES(".(int)$obj->user_id.", ".(int)$obj->forum_id.")");
	}
	unset($r);
	print_msg('Finished Importing Moderators');

/* Import phpBB Topics */

	q("DELETE FROM ".$DBHOST_TBL_PREFIX."thread");	
	$r = bbq("SELECT t.*,p.post_time FROM {$bb2}topics t INNER JOIN {$bb2}posts p ON t.topic_first_post_id=p.post_id");
	print_msg('Importing Topics '.db_count($r));
	while ($obj = db_rowobj($r)) {
		$orderexpiry = $thread_opt = 0;
		if ($obj->topic_type == POST_STICKY) {
			$thread_opt |= 4;
			$orderexpiry = 1000000000;
		} else if ($obj->topic_type == POST_ANNOUNCE || $obj->topic_type == POST_GLOBAL_ANNOUNCE) {
			$thread_opt |= 2;
			$orderexpiry = 1000000000;
		}
		if ($obj->topic_status == TOPIC_LOCKED) {
			$thread_opt |= 1;
		}

		q("INSERT INTO ".$DBHOST_TBL_PREFIX."thread (
			id, forum_id, root_msg_id, last_post_id, views, thread_opt, orderexpiry, moved_to, last_post_date, 
			replies
			) VALUES(
			".(int)$obj->topic_id.",
			".(int)$obj->forum_id.",
			".(int)$obj->topic_first_post_id.",
			".(int)$obj->topic_last_post_id.",
			".(int)$obj->topic_views.",
			".$thread_opt.",
			".$orderexpiry.",
			".(int)$obj->topic_moved_id.",
			".(int)$obj->post_time.",
			".(int)$obj->topic_replies.")
		");
	}
	unset($r);
	print_msg('Finished Importing Topics');

/* Import phpBB messages */

	q("DELETE FROM ".$DBHOST_TBL_PREFIX."msg");
	$r = bbq("SELECT t.topic_title, p.*, pt.post_subject, pt.post_text FROM {$bb2}posts p INNER JOIN {$bb2}posts_text pt ON p.post_id=pt.post_id INNER JOIN {$bb2}topics t ON t.topic_id=p.topic_id");
	print_msg('Importing Messages '.db_count($r));
	while( $obj = db_rowobj($r) ) {
		if (!$obj->post_subject) {
			$obj->post_subject = $obj->topic_title;
		}

		$fileid = write_body(bbcode2fudcode($obj->post_text), $len, $off);
		$updated_by = $obj->post_edit_time ? $obj->poster_id : 0;
		$msg_opt = ($obj->enable_sig ? 1 : 0) | ($obj->enable_smilies ? 0 : 2);
		if ($obj->poster_id == -1) {
			$obj->poster_id = 0;
		}

		q("INSERT INTO ".$DBHOST_TBL_PREFIX."msg
			(id, thread_id, poster_id, post_stamp, update_stamp, updated_by, subject,
			 ip_addr, foff, length, file_id, msg_opt, apr
		) VALUES (
			".(int)$obj->post_id.",
			".(int)$obj->topic_id.",
			".(int)$obj->poster_id.",
			".(int)$obj->post_time.",
			".(int)$obj->post_edit_time.",
			".$updated_by.",
			'".addslashes($obj->post_subject)."',
			'".phpbb_decode_ip($obj->poster_ip)."',
			".$off.",
			".$len.",
			".$fileid.",
			".$msg_opt.",
			1)"
		);
	}
	unset($r);
	print_msg('Finished Importing Messages');

/* Import phpBB polls */

	q("DELETE FROM ".$DBHOST_TBL_PREFIX."poll");
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."poll_opt");
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."poll_opt_track");
	
	$r = bbq("SELECT vd.*, p.forum_id, p.post_id, p.poster_id FROM {$bb2}vote_desc vd INNER JOIN {$bb2}topics t ON t.topic_id=vd.topic_id INNER JOIN {$bb2}posts p ON p.post_id=t.topic_first_post_id");

	print_msg('Importing Polls '.db_count($r));

	while ($obj = db_rowobj($r)) {
		$vote_length = $obj->vote_length ? $obj->vote_start + $obj->vote_length : 0;
		q("INSERT INTO ".$DBHOST_TBL_PREFIX."poll (id, name, owner, creation_date, expiry_date, forum_id)
			VALUES(
				".(int)$obj->vote_id.",
				'".addslashes($obj->vote_text)."',
				".(int)$obj->poster_id.",
				".(int)$obj->vote_start.",
				".(int)$vote_length.",
				".(int)$obj->forum_id.")"
		);
		q("UPDATE ".$DBHOST_TBL_PREFIX."msg SET poll_id={$obj->vote_id} WHERE id=".$obj->post_id);

		$r2 = bbq("SELECT * FROM {$bb2}vote_voters");
		while ($o = db_rowobj($r2)) {
			if (q_singleval("SELECT id FROM {$DBHOST_TBL_PREFIX}poll_opt_track WHERE poll_id={$o->vote_id} AND user_id=".$o->vote_user_id)) {
				continue;
			}
			q("INSERT INTO ".$DBHOST_TBL_PREFIX."poll_opt_track (poll_id, user_id) VALUES(".(int)$o->vote_id.", ".(int)$o->vote_user_id.")");
		}
		unset($r2);

		$r2 = bbq("SELECT * FROM {$bb2}vote_results WHERE vote_id=".$obj->vote_id);
		while ($o = db_rowobj($r2)) {
			$id = db_qid("INSERT INTO ".$DBHOST_TBL_PREFIX."poll_opt (poll_id, name, count) VALUES(".(int)$o->vote_id.", '".addslashes($o->vote_option_text)."', ".(int)$o->vote_result.")");
			q("UPDATE ".$DBHOST_TBL_PREFIX."poll_opt_track SET poll_opt={$id} WHERE poll_id={$o->vote_id} AND poll_opt=0 LIMIT ".(int)$o->vote_result);
		}
		unset($r2);
	}
	unset($r);
	print_msg('Finished Importing Polls');

/* Import phpBB Thread Subscriptions */
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."thread_notify");
	$r = bbq("SELECT * FROM {$bb2}topics_watch WHERE notify_status=" . TOPIC_WATCH_NOTIFIED);
	print_msg('Importing Thread Subscriptions '.db_count($r));
	while ($obj = db_rowobj($r)) {
		q("INSERT INTO ".$DBHOST_TBL_PREFIX."thread_notify (user_id, thread_id) VALUES(".(int)$obj->user_id.", ".(int)$obj->topic_id.")");
	}
	unset($r);
	print_msg('Finished Importing Thread Subscriptions');

/* Import phpBB user ranks */
	
	// Post based ranks
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."level");
	$r = bbq("SELECT * FROM {$bb2}ranks WHERE rank_special=0");
	print_msg('Importing User Ranks (post count based) '.db_count($r));
	while ($obj = db_rowobj($r)) {
		if ($obj->rank_image) {
			$file_name = basename($obj->rank_image);
			if (!copy($PHPBB_INSTALL_ROOT.$obj->rank_image, $IMG_ROOT_DISK.$file_name)) {
				print_msg("Couldn't copy user rank image from (".$PHPBB_INSTALL_ROOT.$obj->rank_image.") to (".$IMG_ROOT_DISK.$file_name.")");
				exit;
			}
		}
		$file_name = '';
		q("INSERT INTO ".$DBHOST_TBL_PREFIX."level (name,post_count,img) VALUES('".addslashes($obj->rank_title)."',".(int)$obj->rank_min.",".strnull($file_name).")");
	}
	unset($r);
	print_msg('Finished Importing User Ranks (post count based)');
	
	// Custom tags 
	q("DELETE FROm ".$DBHOST_TBL_PREFIX."custom_tags");
	$r = bbq("SELECT * FROM {$bb2}ranks WHERE rank_special=1");
	print_msg('Importing Custom Tags '.db_count($r));
	while ($obj = db_rowobj($r)) {
		$r2 = bbq("SELECT user_id FROM {$bb2}users WHERE user_rank=".$obj->rank_id);
		while ($o = db_rowobj($r2)) {
			q("INSERT INTO ".$DBHOST_TBL_PREFIX."custom_tags (name,user_id) VALUES('".addslashes($obj->rank_title)."',".$o->user_id.")");
		}
		unset($r2);
	}
	unset($r);
	print_msg('Finished Importing Custom Tags');

/* Import phpBB blocked words */
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."replace");
	
	$r = bbq("SELECT * FROM {$bb2}words");
	print_msg('Importing Blocked Words '.db_count($r));
	while ($obj = db_rowobj($r)) {
		q("INSERT INTO ".$DBHOST_TBL_PREFIX."replace (replace_str, with_str, replace_opt) VALUES('/".addslashes(preg_quote($obj->word, '/'))."/','".addslashes($obj->replacement)."', 1)");
	}
	unset($r);
	print_msg('Finished Importing Blocked Words');

/* Import phpBB dissalowed logins */
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."blocked_logins");
	$r = bbq("SELECT * FROM {$bb2}disallow");
	print_msg('Importing Disallowed Logins '.db_count($r));
	while( $obj = db_rowobj($r) ) {
	 	q("INSERT INTO ".$DBHOST_TBL_PREFIX."blocked_logins (login) VALUES('".addslashes(str_replace('*', '.*', $obj->disallow_username))."')");
	}
	unset($r);
	print_msg('Finished Importing Disallowed Logins');

/* Import phpBB banned users */
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."ip_block");
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."email_block");
	
	$r = bbq("SELECT * FROM {$bb2}banlist");
	print_msg('Importing Banned Users '.db_count($r));
	while ($obj = db_rowobj($r)) {
		if ($obj->ban_userid) {
			q("UPDATE {$DBHOST_TBL_PREFIX}users SET users_opt=users_opt|65536 WHERE id=".$obj->ban_userid);
		}
		if ($obj->ban_ip) {
			list($ca,$cb,$cc,$cd) = explode('.', phpbb_decode_ip($obj->ban_ip));
			q("INSERT INTO ".$DBHOST_TBL_PREFIX."ip_block (ca,cb,cc,cd) VALUES($ca,$cb,$cc,$cd)");
		}
		if ($obj->ban_email) {
			q("INSERT INTO ".$DBHOST_TBL_PREFIX."email_block (string) VALUES('".addslashes($obj->ban_email)."')");
		}
	}
	unset($r);
	print_msg('Finished Importing Banned Users');

/* Import phpBB private messages */
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."pmsg");

	$r = bbq("SELECT p.*, pt.privmsgs_text, u.username FROM {$bb2}privmsgs p INNER JOIN {$bb2}privmsgs_text pt ON p.privmsgs_id=pt.privmsgs_text_id INNER JOIN {$bb2}users u ON u.user_id=p.privmsgs_to_userid");
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

/* Import phpBB file attachments (if person has applied phpbb file attachment mod) */
	$ENABLED_FILE_ATTACHMENTS = 0;
	if (@db_rowarr(bbq("SELECT * FROM '{$bb2}attach_desc' LIMIT 1", 1))) {
		$ENABLED_FILE_ATTACHMENTS = 1;
		print_msg('Importing File Attachments '.db_count($r));

		q("DELETE FROM ".$DBHOST_TBL_PREFIX."attach");

		list($phpbb_storage) = db_rowarr(bbq("SELECT config_value FROM {$bb2}attach_config WHERE config_name='upload_dir'"));

		$old_umask = umask(0111);
		$r = bbq("SELECT a.*, u.user_id FROM {$bb2}attach_desc a INNER JOIN {$bb2}posts p ON a.post_id=p.post_id INNER JOIN {$bb2}users u ON u.user_id=p.poster_id");
		while ($obj = db_rowobj($r)) {
			if (!@file_exists($PHPBB_INSTALL_ROOT.$phpbb_storage.$obj->attach_filename)) {
				print_msg("\tWARNING: file attachment ".$PHPBB_INSTALL_ROOT.$phpbb_storage.$obj->attach_filename." doesn't exist");
				continue;
			}

			$mime = q_singleval("SELECT id FROM ".$DBHOST_TBL_PREFIX."mime WHERE fl_ext='".substr(strrchr($obj->filename, '.'), 1)."'");

			$attach_id = db_qid("INSERT INTO ".$DBHOST_TBL_PREFIX."attach 
				(original_name, owner, message_id, dlcount, mime_type, fsize)
				VALUES (
					'".addslashes($obj->filename)."',
					".(int)$obj->user_id.",
					".(int)$obj->post_id.",
					".(int)$obj->download_count.",
					".(int)$mime.",
					".(int)filesize($PHPBB_INSTALL_ROOT.$phpbb_storage.$obj->attach_filename).")"
				);

			if (!copy($PHPBB_INSTALL_ROOT.$phpbb_storage.$obj->attach_filename, $FILE_STORE.$attach_id.'.atch')) {
				print_msg("Couldn't copy file attachment (".$PHPBB_INSTALL_ROOT.$phpbb_storage.$obj->attach_filename.") to (".$FILE_STORE.$attach_id.'.atch'.")");
				exit;
			}
			q("UPDATE ".$DBHOST_TBL_PREFIX."attach SET location='".$FILE_STORE.$attach_id.'.atch'."' WHERE id=".$attach_id);
		}
		unset($r);
		umask($old_umask);
		print_msg('Finished Importing File Attachments');
	}
	
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

	if ($ENABLED_FILE_ATTACHMENTS) {
		list($max_attach) = db_singlearr(bbq("SELECT config_value FROM {$bb2}attach_config WHERE config_name='max_attachments'"));
		list($max_fsize) = db_singlearr(bbq("SELECT config_value FROM {$bb2}attach_config WHERE config_name='max_filesize'"));
		
		$list['PRIVATE_ATTACHMENTS'] = (int) $max_attach;
		$list['PRIVATE_ATTACH_SIZE'] = (int) $max_fsize;

		q("UPDATE ".$DBHOST_TBL_PREFIX."forum SET max_file_attachments=".(int)$max_attach.", max_attach_size=".(int)$max_fsize);
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
