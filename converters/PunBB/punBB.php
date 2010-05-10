<?php
/***************************************************************************
* copyright            : (C) 2001-2009 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id$
*
* This program is free software; you can redistribute it and/or modify it 
* under the terms of the GNU General Public License as published by the 
* Free Software Foundation; version 2 of the License. 
***************************************************************************/

	/* PunBB 1 (1.x) to FUDforum Conversion script - Brief Instructions */

	/* If you intend to run this script via the console, make sure to UNLOCK FUDforum's  
	 * files first. We recommend running this script via the web unless the forum
	 * you are importing is very large.
	 */

	/* Specify the FULL path to the punBB config.php file. */
	$PUNBB_CFG = "C:/web/xampplite/htdocs/punbb/config.php";

/* DO NOT MODIFY BEYOND THIS POINT */

function print_msg($msg)
{
	if (__WEB__) {
		echo nl2br(str_replace("\t", "&nbsp;&nbsp;&nbsp;&nbsp;", $msg)) . "<br />\n";
	} else {
		echo $msg . "\n";
	}
}

function puncode2fudcode($str)
{
	$str = preg_replace('!\[(.+?)\:([a-z0-9]+)?\]!s', '[\1]', $str);
	$str = preg_replace('!\[quote\:([a-z0-9]+?)="(.*?)"\]!is', '[quote=\2]', $str);
	$str = preg_replace('!\[code\:([^\]]+)\]!is', '[code]', $str);
	$str = preg_replace('!\[/code\:([^\]]+)\]!is', '[/code]', $str);
	$str = preg_replace("#(^|[\n ])((www|ftp)\.[\w\-]+\.[\w\-.\~]+(?:/[^ \"\t\n\r<]*)?)#is", "\\1http://\\2", $str);

	$str = smiley_to_post(tags_to_html($str, 1, 1));

	return $str;
}

function decode_ip($int_ip)
{
	if ($int_ip == '00000000') {
		return '0.0.0.0';
	} else {
		return long2ip("0x{$int_ip}");
	}
}

	define('__WEB__', (isset($_SERVER["REMOTE_ADDR"]) === FALSE ? 0 : 1));

	/* Prevent session initialization. */
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

	if (strncasecmp('win', PHP_OS, 3) && $FUD_OPT_2 & 8388608 && !__WEB__) {
		exit("Since you are running conversion script via the console you must UNLOCK forum's files first.\n");
	}

	$gl = @include($PUNBB_CFG);
	if ($gl === FALSE) {
		exit("Unable to open pubBB configuration at '{$PUNBB_CFG}'.\n");
	}

	if (!($ib = mysql_connect($db_host, $db_username, $db_password))) {
		exit("Failed to connect to database containing punBB data using MySQL information inside '{$PUNBB_CFG}'.\n");
	}
	define('phpsql', $ib);

	$bb2 = $db_name . '.' . $db_prefix;
	$PUNBB_INSTALL_ROOT = dirname($PUNBB_CFG) . '/';
	$IMG_ROOT_DISK = $WWW_ROOT_DISK . 'img/';

	/* Include all the necessary FUDforum includes. */
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

	print_msg('Start the conversion process...');
	$board_config = array();
	print_msg('Reading punBB\'s config file...');
	$r = q("SELECT * FROM {$bb2}config");
	while (list($k,$v) = db_rowarr($r)) {
		$board_config[$k] = $v;
	}
	unset($r);
	print_msg('Done reading the config...');

/* Add DB column for password salt. */
$exists = false;
$r = q("show columns from ". $DBHOST_TBL_PREFIX ."users");
while ($obj = db_rowobj($r)) {
	if ($obj->Field == 'salt'){
		print_msg('Salt column found in database.');
		$exists = true;
		break;
	}
}
if(!$exists){
	print_msg('Add salt column to database.');
	q("ALTER TABLE ". $DBHOST_TBL_PREFIX ."users ADD salt varchar(12) DEFAULT NULL");
}
q("ALTER TABLE ". $DBHOST_TBL_PREFIX ."users MODIFY passwd varchar(40) NOT NULL DEFAULT ''");

/* Import punBB users. */
	$have_admin = 0;
	$old_umask = umask(0111);

	q("DELETE FROM ".$DBHOST_TBL_PREFIX."users WHERE id>1");
	$theme = q_singleval("SELECT id FROM ".$DBHOST_TBL_PREFIX."themes WHERE (theme_opt & 3) >= 3 LIMIT 1");

	$r = q("SELECT * FROM {$bb2}users WHERE id>0 ORDER BY id");
	
	print_msg('Importing '.db_count($r).' users...');

	while ($obj = db_rowobj($r)) {
		if (q_singleval("SELECT id FROM ".$DBHOST_TBL_PREFIX."users WHERE login='".addslashes($obj->username)."' OR email='".addslashes($obj->email)."'")) {
			print_msg("\tuser: ".$obj->username);
			print_msg("\t\tWARNING: Cannot import user ".$obj->username.", user with this email and/or login already exists");
			continue;
		}
		$users_opt = 2 | 4 | 4194304 | 16 | 32 | 128 | 256 | 512 | 4096 | 8192 | 16384 | 32768 | 131072;

		if ($obj->group_id == 1) {	// ADMIN
			$users_opt |= 1048576;
			$have_admin = 1;
		} else if ($obj->group_id == 4) { // MODERATOR
			$users_opt |= 524288;
		}
		if ($obj->email_setting == 0) {	// Show e-mail address to other users.
			$users_opt |= 1;
		}
		if ($obj->show_sig) {	// Show signatures.
			$users_opt |= 2048;
		}

		/* Hack for user id of 1, since this id is reserved for anon user in FUDforum. */
		if ($obj->id == 1) {
			$obj->id = $hack_id = q_singleval("SELECT MAX(id) FROM {$bb2}users") + 1;
		}

		q("INSERT INTO ".$DBHOST_TBL_PREFIX."users 
			(id, login, alias, name, passwd, salt, last_visit, join_date, email, icq, location,
			 sig, aim, yahoo, msnm, users_opt, theme, last_read)
			VALUES (
				".(int)$obj->id.",
				'".addslashes($obj->username)."',
				'".addslashes(htmlspecialchars($obj->username))."',
				'".($obj->realname ? addslashes($obj->realname) : '')."',
				'".$obj->password."',
				'".(isset($obj->salt) ? addslashes($obj->salt) : '')."',
				".(int)$obj->last_visit.",
				".(int)$obj->registered.",
				'".addslashes($obj->email)."',
				".($obj->icq ? $obj->icq : 'NULL').",
				'".addslashes($obj->location)."',
				'".addslashes(puncode2fudcode($obj->signature))."',
				'".addslashes($obj->aim)."',
				'".addslashes($obj->yahoo)."',
				'".addslashes($obj->msn)."',
				".$users_opt.",
				".$theme.",
				".(int)$obj->last_visit."
				)");
	}
	unset($r);
	umask($old_umask);
	print_msg('Finished Importing Users');

	if (!$have_admin) {
		$users_opt |= 1048576;
		q("INSERT INTO ".$DBHOST_TBL_PREFIX."users 
			(login, alias, passwd, users_opt, theme)
			VALUES ('admin', 'admin', '".md5('fudforum')."', $users_opt, $theme)");
		print_msg('!!! New admin account created. Please login with admin/fudforum !!!');
	}

/* Import punBB Categories. */
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."cat");
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."group_resources");
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."groups WHERE id>2");
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."forum");
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."group_members");

	$r = q("select * from {$bb2}categories ORDER BY disp_position");
	print_msg('Importing Categories '.db_count($r));
	$i = 1;
	while( $obj = db_rowobj($r) ) {
		q("INSERT INTO ".$DBHOST_TBL_PREFIX."cat (id, name, view_order, cat_opt) VALUES(".(int)$obj->id.",'".addslashes($obj->cat_name)."',".$i++.", 3)");
	}
	unset($r);
	print_msg('Finished Importing Categories');

/* Import punBB Forums. */
$forum_map = array();
	// Remove all lock & view tables.
	foreach (get_fud_table_list() as $v) {
		if (strncmp($v, "{$DBHOST_TBL_PREFIX}fl_", strlen("{$DBHOST_TBL_PREFIX}fl_")) && 
			strncmp($v, "{$DBHOST_TBL_PREFIX}tv_", strlen("{$DBHOST_TBL_PREFIX}tv_"))
		) {
			continue;
		}
		if ($v == "{$DBHOST_TBL_PREFIX}fl_pm") {
			continue;
		}
		q("DROP TABLE {$v}");
	}

	$r = q("select * from {$bb2}forums ORDER BY disp_position");
	print_msg('Importing Forums '.db_count($r));
	while ($obj = db_rowobj($r)) {
		$_POST['frm_cat_id'] = $obj->cat_id;
		$_POST['frm_name'] = $obj->forum_name;
		$_POST['frm_descr'] = $obj->forum_desc;
		$_POST['url_redirect'] = $obj->redirect_url;
		$_POST['frm_forum_opt'] = 16;
		$_POST['frm_max_file_attachments'] = 1;
		$_POST['frm_max_attach_size'] = 1024;

		$frm = new fud_forum();
		$id = $frm->add('LAST');
		$forum_map[(int) $obj->id] = $id;

		$perms_reg = 378767;
		$perms_anon = 327683;
		$gid = q_singleval("SELECT id FROM {$DBHOST_TBL_PREFIX}groups WHERE forum_id=".$id);
		q("UPDATE {$DBHOST_TBL_PREFIX}group_members SET group_members_opt={$perms_anon} WHERE group_id={$gid} AND user_id=0");
		q("UPDATE {$DBHOST_TBL_PREFIX}group_members SET group_members_opt={$perms_reg} WHERE group_id={$gid} AND user_id=2147483647");

	}
	unset($r);
	print_msg('Finished Importing Forums');

/* Import punBB moderators. */
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."mod");
	print_msg('Importing Moderators');
	$r = q("SELECT * FROM {$bb2}users WHERE group_id = 1");
	while ($obj = db_rowobj($r)) {
		if (isset($forum_map[(int)$obj->id])) {
			if ($obj->id == 1) {
				$obj->id = $hack_id;
			}
			foreach($forum_map as $forum_id) {
				q("INSERT INTO ".$DBHOST_TBL_PREFIX."mod (user_id, forum_id) VALUES(".(int)$obj->id.", ".$forum_id.")");
			}
		}
	}
	unset($r);
	print_msg('Finished Importing Moderators');

/* Import punBB Topics. */
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."thread");
	
	// NOTE: some versions use "first_post_id" instead of "last_post_id".
	$r = q("show columns from {$bb2}topics");
	while ($obj = db_rowobj($r)) {
		if ($obj->Field == 'first_post_id'){
			$last_post_id = 0;
			$r = q("SELECT t.*,p.posted FROM {$bb2}topics t INNER JOIN {$bb2}posts p ON t.first_post_id=p.id");
			break;
		} else if ($obj->Field == 'last_post_id') {
			$last_post_id = 1;
			$r = q("SELECT t.*,p.posted FROM {$bb2}topics t INNER JOIN {$bb2}posts p ON t.last_post_id=p.id");
			break;
		}
	}

	print_msg('Importing Topics '.db_count($r));
	while ($obj = db_rowobj($r)) {
		if ($last_post_id) {
			// echo "Convert last ".$obj->last_post_id." to first ".$obj->first_post_id."<br>";	
			$obj->first_post_id = q_singleval("SELECT min(id) FROM {$bb2}posts WHERE topic_id=".$obj->id);
		}
		
		$orderexpiry = $thread_opt = 0;
		if ($obj->sticky == 1) {	// 1 = Topic is a sticky topic.
			$thread_opt |= 4;
			$orderexpiry = 1000000000;
		}
		if ($obj->closed) {	// 0 = Topic is open. 1 = Topic is closed.
			$thread_opt |= 1;
		}		
		if (!isset($forum_map[(int)$obj->forum_id])) {
			continue;
		}
		q("INSERT INTO ".$DBHOST_TBL_PREFIX."thread (
			id, forum_id, root_msg_id, views, thread_opt, orderexpiry, moved_to, replies
			) VALUES(
			".(int)$obj->id.",
			".$forum_map[(int)$obj->forum_id].",
			".(int)$obj->first_post_id.",
			".(int)$obj->num_views.",
			".$thread_opt.",
			".$orderexpiry.",
			".(int)$obj->moved_to.",
			".(int)$obj->num_replies.")
		");
	}
	unset($r);
	print_msg('Finished Importing Topics');

/* Import punBB messages. */
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."msg");
	$r = q("SELECT t.forum_id, t.subject, p.* FROM {$bb2}posts p INNER JOIN {$bb2}topics t ON t.id=p.topic_id");
	print_msg('Importing Messages '.db_count($r));
	while( $obj = db_rowobj($r) ) {
		if (!isset($forum_map[(int)$obj->forum_id])) {
			continue;
		}

		$fileid = write_body(puncode2fudcode($obj->message), $len, $off, $forum_map[(int)$obj->forum_id]);
		$updated_by = 0;
		if ($obj->edited) {
			$updated_by = q_singleval("SELECT id FROM {$bb2}users WHERE username="._esc($obj->edited_by));
		}
		$updated_by = empty($updated_by) ? 0 : $updated_by;

		$msg_opt = ($obj->hide_smilies ? 2 : 0);
		if ($obj->poster_id == -1) {
			$obj->poster_id = 0;
		} else if ($obj->poster_id == 1) {
			$obj->poster_id = $hack_id;
		}

		q("INSERT INTO ".$DBHOST_TBL_PREFIX."msg
			(id, thread_id, poster_id, post_stamp, update_stamp, updated_by, subject,
			 ip_addr, foff, length, file_id, msg_opt, apr
		) VALUES (
			".(int)$obj->id.",
			".(int)$obj->topic_id.",
			".(int)$obj->poster_id.",
			".(int)$obj->posted.",
			".(int)$obj->edited.",
			".$updated_by.",
			'".addslashes($obj->subject)."',
			'".decode_ip($obj->poster_ip)."',
			".$off.",
			".$len.",
			".$fileid.",
			".$msg_opt.",
			1)"
		);
	}
	unset($r);
	print_msg('Finished Importing Messages');

/* Import punBB Thread Subscriptions. */
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."thread_notify");
	$r = q("SELECT * FROM {$bb2}subscriptions");
	print_msg('Importing thread subscriptions '.db_count($r));
	while ($obj = db_rowobj($r)) {
		q("INSERT INTO ".$DBHOST_TBL_PREFIX."thread_notify (user_id, thread_id) VALUES(".(int)$obj->user_id.", ".(int)$obj->topic_id.")");
	}
	unset($r);
	print_msg('Finished Importing Thread Subscriptions');

/* Import punBB user ranks. */
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."level");
	$r = q("SELECT * FROM {$bb2}ranks");
	print_msg('Importing User Ranks (post count based) '.db_count($r));
	while ($obj = db_rowobj($r)) {
		q("INSERT INTO ".$DBHOST_TBL_PREFIX."level (name,post_count) VALUES('".addslashes($obj->rank)."',".(int)$obj->min_posts.")");
	}
	unset($r);
	print_msg('Finished Importing User Ranks (post count based)');

/* Import pubBB blocked words. */
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."replace");
	$r = q("SELECT * FROM {$bb2}censoring");
	print_msg('Importing Blocked Words '.db_count($r));
	while ($obj = db_rowobj($r)) {
		q("INSERT INTO ".$DBHOST_TBL_PREFIX."replace (replace_str, with_str, replace_opt) VALUES('/".addslashes(preg_quote($obj->search_for, '/'))."/','".addslashes($obj->replaceme_with)."', 1)");
	}
	unset($r);
	print_msg('Finished Importing Blocked Words');

/* Import punBB banned users. */
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."ip_block");
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."email_block");

	$r = q("SELECT * FROM {$bb2}bans");
	print_msg('Importing Banned Users '.db_count($r));
	while ($obj = db_rowobj($r)) {
		q("UPDATE {$DBHOST_TBL_PREFIX}users SET users_opt=users_opt|65536 WHERE login='".addslashes($obj->username)."'");
	}
	unset($r);
	print_msg('Finished Importing Banned Users');

/* Import punBB settings. */
	print_msg('Importing Forum Settings');
	$list = array();
	$list['FORUM_TITLE'] = $board_config['o_board_title'];
	$list['FORUM_DESC'] = $board_config['o_board_desc'];
	$list['DISABLED_REASON'] = $board_config['o_maintenance_message'];
	$list['SESSION_TIMEOUT'] = (int) $board_config['o_timeout_online'];
	if (isset($board_config['o_signatures']) && $board_config['o_signatures']) {
		$FUD_OPT_1 |= 32768;
	}
	if (isset($board_config['o_smilies']) && $board_config['o_smilies']) {
		$FUD_OPT_1 |= 8192 | 262144;
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

	print_msg("\nConversion of punBB to FUDforum has been completed\n Time Taken: ".$time_taken."\n");
	print_msg("To complete the process run the consistency checker at:");
	print_msg($WWW_ROOT."adm/consist.php");
?>
