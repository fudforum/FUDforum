<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: phpBB2.php,v 1.5 2002/07/08 23:21:26 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

	@set_time_limit(6000);
/*
 *	Usage Instructions
 *
 *	1) Copy this script into the main web directory of FUDforum 2.
 *	2) Change the value of the value of the $PHPBB_INSTALL_ROOT variable
 *	   to the full path of the directory where phpBB2 was installed.
 *	3) Run this script via the shell or the web.
 *	4) Once the script successfuly runs, run the consitency checker.
 *	5) Voila, you're done.
*/
	$PHPBB_INSTALL_ROOT = "/home/forum/phpBB2/";

/* DO NOT MODIFY BEYOND THIS POINT */

	define('IN_PHPBB', 1);

	include_once "GLOBALS.php";
	$IMG_ROOT_DISK = $WWW_ROOT_DISK.'images/';
	fud_use('rev_fmt.inc');
	fud_use('post_proc.inc');
	fud_use('db.inc');
	fud_Use('smiley.inc');
	
	if( empty($PHPBB_INSTALL_ROOT) ) 
		exit("PHPBB_INSTALL_ROOT is blank, cannot proceed!\n Change the value of the value of the \$PHPBB_INSTALL_ROOT variable to the full path of the directory where phpBB2 was installed.\n");

	switch( substr($PHPBB_INSTALL_ROOT, -1) )
	{
		case '/':
		case '\\':
			break;
		default:
			$PHPBB_INSTALL_ROOT .='/'; 
	}

	if( !file_exists($PHPBB_INSTALL_ROOT.'config.php') && !file_exists($PHPBB_INSTALL_ROOT.'includes/constants.php') )
		exit("PHPBB_INSTALL_ROOT ($PHPBB_INSTALL_ROOT) does not contain valid path to phpBB2\n");

	include_once $PHPBB_INSTALL_ROOT.'config.php';
	include_once $PHPBB_INSTALL_ROOT.'includes/constants.php';
	
	$phpbbdb = mysql_connect($dbhost,$dbuser,$dbpasswd);
	
	if( isset($HTTP_SERVER_VARS['REMOTE_ADDR']) ) echo '<pre>';
	if( !isset($DBHOST_TBL_PREFIX) ) $DBHOST_TBL_PREFIX = $MYSQL_TBL_PREFIX;
	$start_time = time();

function Q2($str)
{
	$r= mysql_db_query($GLOBALS['dbname'], $str, $GLOBALS['phpbbdb']);
	if( !$r ) exit(mysql_error($GLOBALS['phpbbdb'])."\n");
	return $r; 
}

function Q_W()
{
	mysql_select_db($GLOBALS['MYSQL_DB'],$GLOBALS['__DB_INC__']['SQL_LINK']);
}

function filetomem($fn)
{
        $fp = fopen($fn, 'rb');
        $st = fstat($fp);
	$size = isset($st['size']) ? $st['size'] : $st[7];
	$str = fread($fp, $size);
        fclose($fp);
                                
	return $str;
}

function phpbb_decode_ip($int_ip)
{
	$hexipbang = explode('.', chunk_split($int_ip, 2, '.'));
	return hexdec($hexipbang[0]). '.' . hexdec($hexipbang[1]) . '.' . hexdec($hexipbang[2]) . '.' . hexdec($hexipbang[3]);
}

function bbcode2fudcode($str)
{
	define('no_char', 1);
	$str = preg_replace('!\[(.+?)\:([a-z0-9]+)?\]!s', '[\1]', $str);
	$str = preg_replace('!\[quote\:([a-z0-9]+?)="(.*?)"\]!is', '[quote=\2]', $str);
	$str = smiley_to_post(tags_to_html($str));	

	return $str;
}

function INT_yn($s)
{
	return (empty($s)?'N':'Y');
}

function print_status($str)
{
	echo $str."\n";
	flush();
}

	print_status('Beginning Conversion Process');
	$board_config = array();
	$r = Q2("SELECT * FROM ".$table_prefix."config");
	while( list($k,$v) = db_rowarr($r) ) {
		$board_config[$k] = $v;
	}
	qf($r);
	print_status('Reading phpBB2 config');

/* Import phpBB smilies */
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."smiley");
	
	$old_umask = umask(0);
	$r = Q2("SELECT * FROM ".$table_prefix."smilies");
	print_status('Importing Smilies: '.db_count($r));
	while( $obj = db_rowobj($r) ) {
		if( !bq("SELECT id FROM ".$DBHOST_TBL_PREFIX."smiley WHERE img='".$obj->smile_url."'") ) {
			if( !copy($PHPBB_INSTALL_ROOT.$board_config['smilies_path'].'/'.$obj->smile_url, $IMG_ROOT_DISK.'smiley_icons/'.$obj->smile_url) ) {
				print_status("Coulnd't copy smiley image (".$PHPBB_INSTALL_ROOT.$board_config['smilies_path'].$obj->smile_url.") to (".$IMG_ROOT_DISK.'smiley_icons/'.$obj->smile_url.")");
				exit;
			}
			@chmod($IMG_ROOT_DISK.'smiley_icons/'.$obj->smile_url, 0666);
			q("INSERT INTO ".$DBHOST_TBL_PREFIX."smiley (img,code,descr) VALUES('".$obj->smile_url."','".addslashes($obj->code)."','".addslashes($obj->emoticon)."')");
		}
		else {
			q("UPDATE ".$DBHOST_TBL_PREFIX."smiley SET code=CONCAT(code, '~', '".addslashes($obj->code)."') WHERE img='".$obj->smile_url."'");
		}
	}
	umask($old_umask);
	qf($r);
	print_status('Finished Importing Smilies');

/* Import phpBB avatar galleries */

function import_av_gal($dir)
{
	print_status("\tfrom: $dir");
	
	$odir = getcwd();
	chdir($dir);
	$dir = opendir('.');
	readdir($dir); readdir($dir);
	while( $file = readdir($dir) ) {
		if( @is_dir($file) ) import_av_gal($file);
		
		$file_ext = strrchr($file, '.');
		switch( substr(strrchr($file, '.'),1) ) 
		{
			case 'jpg':
			case 'jpeg':
			case 'png':
			case 'gif':
				if( !copy($file, $GLOBALS['IMG_ROOT_DISK'].'avatars/'.$file) ) {
					print_status("Couldn't copy avatar (".getcwd().'/'.$file.") to (".$GLOBALS['IMG_ROOT_DISK'].'avatars/'.$file.")");
					exit;				
				}
				@chmod($GLOBALS['IMG_ROOT_DISK'].'avatars/'.$file, 0666);
				q("INSERT INTO ".$GLOBALS['DBHOST_TBL_PREFIX']."avatar (img,descr) VALUES('".addslashes($file)."','".addslashes($dir.' '.$file)."')");
				$GLOBALS["av_gal"]++;
				break;
		}
	}
	closedir($dir);
	chdir($odir);
}
	
	print_status('Importing Avatar Galleries');
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."avatar");
	$old_umask=umask(0);
	$GLOBALS["av_gal"] = 0;
	import_av_gal($PHPBB_INSTALL_ROOT.$board_config['avatar_gallery_path'].'/');
	umask($old_umask);
	print_status('Finished Importing Avatar Galleries, <b>'.$GLOBALS["av_gal"].'</b> avatars improted');

/* Import phpBB2 users */

	fud_use('util.inc');
	$old_umask=umask(0);
	
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."users");
	$r = Q2("SELECT * FROM ".$table_prefix."users WHERE user_id>0 ORDER BY user_id");
	
	print_status('Importing Users '.db_count($r));
	
	while ( $obj = db_rowobj($r) ) {
		
		if( bq("SELECT id FROM ".$DBHOST_TBL_PREFIX."users WHERE login='".addslashes($obj->username)."' OR email='".$obj->user_email."'") ) {
			print_status("\tuser: ".$obj->username);
			print_status("\t\tWARNING: Cannot import user ".$obj->username.", user with this email and/or login already exists");
			continue;
		}
		
		switch( $obj->user_level )
		{
			case USER:
				$is_mod = 'N';
				break;
			case ADMIN:
				$is_mod = 'A';
				break;
			case MOD:
				$is_mod = 'Y';
				break;
		}
		
		q("INSERT INTO ".$DBHOST_TBL_PREFIX."users 
			(
				id,
				login,
				alias,
				passwd,
				last_visit,
				join_date,
				display_email,
				append_sig,
				notify,
				email,
				icq,
				location,
				sig,
				aim,
				yahoo,
				msnm,
				occupation,
				interests,
				conf_key,
				invisible_mode,
				is_mod,
				email_conf,
				coppa
			)
			VALUES (
				'".$obj->user_id."',
				'".addslashes($obj->username)."',
				'".addslashes($obj->username)."',
				'".$obj->user_password."',
				".intzero($obj->user_lastvisit).",
				".intzero($obj->user_regdate).",
				'".INT_yn($obj->user_viewemail)."',
				'".INT_yn($obj->user_attachsig)."',
				'".INT_yn($obj->user_notify)."',
				'".$obj->user_email."',
				".intnull($obj->user_icq).",
				'".addslashes($obj->user_from)."',
				'".addslashes(bbcode2fudcode($obj->user_sig))."',
				'".addslashes($obj->user_aim)."',
				'".addslashes($obj->user_yim)."',
				'".addslashes($obj->user_msnm)."',
				'".addslashes($obj->user_occ)."',
				'".addslashes($obj->user_interests)."',
				'".$obj->user_actkey."',
				'".INT_yn(!$obj->user_allow_viewonline)."',
				'".$is_mod."',
				'Y',
				'N'
				)"
			);

		switch( $obj->user_avatar_type )
		{
			case USER_AVATAR_NONE:
				$avatar_loc = '';
				$avatar_approved = 'NO';
				$avatar = 0;
				break;
			case USER_AVATAR_UPLOAD: 
				if( !@file_exists($PHPBB_INSTALL_ROOT.$board_config['avatar_path']."/".$obj->user_avatar) ) {
					print_status("\tuser: ".$obj->username);
					print_status("\t\tWARNING: missing avatar file: ".$PHPBB_INSTALL_ROOT.$board_config['avatar_path']."/".$obj->user_avatar);
					$avatar_loc = '';
					$avatar_approved = 'NO';
					$avatar = 0;
					break;	
				}

				if( !@copy($PHPBB_INSTALL_ROOT.$board_config['avatar_path']."/".$obj->user_avatar, $IMG_ROOT_DISK."custom_avatars/".$obj->user_id) ) {
					print_status("Couldn't copy avatar ".$PHPBB_INSTALL_ROOT.$board_config['avatar_path']."/".$obj->user_avatar." to ".$IMG_ROOT_DISK."custom_avatars/".$obj->user_id);
					print_status("Please ensure the script has access to perform this action and run it again");
					exit;
				}	
				else {
					@chmod($IMG_ROOT_DISK.'custom_avatars/'.$obj->user_id,0666);
					$avatar_loc = '';
					$avatar = 0;
					$avatar_approved='Y';
				}
				break;
			case USER_AVATAR_REMOTE:
				$avatar_loc = $obj->user_avatar;
				$avatar_approved='Y';
				$avatar = 0;
				break;
			case USER_AVATAR_GALLERY:
				$avatar_loc = '';
				$avatar_approved = 'Y';
				$img = substr(strrchr($obj->user_avatar, '/'), 1);
				$avatar = q_singleval("SELECT id FROM ".$DBHOST_TBL_PREFIX."avatar WHERE img='".$img."'");
				break;
		}
		
		q("UPDATE ".$DBHOST_TBL_PREFIX."users SET home_page='".addslashes($obj->user_website)."', avatar=".intzero($avatar).", avatar_approved='".$avatar_approved."', avatar_loc='".$avatar_loc."' WHERE id=".$obj->user_id);
	}
	qf($r);
	umask($old_umask);
	print_status('Finished Importing Users');

/* Import phpBB2 Categories */
	
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."cat");
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."group_resources");
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."groups WHERE id>2");
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."forum");
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."group_members");
	
	$r = Q2("select * from ".$table_prefix."categories ORDER BY cat_order");
	print_status('Importing Categories '.db_count($r));
	$i=1;
	while( $obj = db_rowobj($r) ) {
		q("INSERT INTO ".$DBHOST_TBL_PREFIX."cat (id,name,view_order) VALUES(".$obj->cat_id.",'".addslashes($obj->cat_title)."',".$i++.")");
	}
	qf($r);
	print_status('Finished Importing Categories');

/* Import phpBB2 Forums */
	
	fud_use('forum.inc');
	fud_use('forum_adm.inc');
	fud_use('groups.inc');

function append_perm_str($perm, $who)
{
	return INT_yn(($perm==$who)?1:0);
}
	
$group_map = array(
'auth_view'=>'up_VISIBLE',
'auth_read'=>'up_READ',
'auth_post'=>'up_POST',
'auth_reply'=>'up_REPLY',
'auth_edit'=>'up_EDIT',
'auth_delete'=>'up_DEL',
'auth_sticky'=>'up_STICKY',
'auth_vote'=>'up_VOTE',
'auth_pollcreate'=>'up_POLL',
'auth_attachments'=>'up_FILE'
);

	$r = Q2("select * from ".$table_prefix."forums ORDER BY forum_id");
	print_status('Importing Forums '.db_count($r));
	$i=1;
	$cat_id=0;
	while( $obj = db_rowobj($r) ) {
		if( $cat_id != $obj->cat_id ) {
			$i=1;
			$cat_id=$obj->cat_id;
		}
		
		$frm = new fud_forum_adm;
		$frm->cat_id = $obj->cat_id;
		$frm->name = addslashes($obj->forum_name);
		$frm->descr = addslashes($obj->forum_desc);
		$id = $frm->add($i);
		q("UPDATE ".$DBHOST_TBL_PREFIX."forum SET id=$obj->forum_id, view_order=".($obj->forum_order/10)." WHERE id=$id");
		$gid = q_singleval("SELECT id FROM ".$DBHOST_TBL_PREFIX."groups WHERE res='forum' AND res_id=$id");
		q("UPDATE ".$DBHOST_TBL_PREFIX."groups SET res_id=$obj->forum_id WHERE id=$gid");
		q("UPDATE ".$DBHOST_TBL_PREFIX."group_resources SET resource_id=$obj->forum_id WHERE group_id=$gid");
		
		reset($group_map);
		
		$str_a = '';
		$str_r = '';
		while ( list($k, $v) = each($group_map) ) {
			$str_a .= $v."='".append_perm_str($obj->{$k}, AUTH_ALL)."',";
			$str_r .= $v."='".append_perm_str($obj->{$k}, AUTH_REG)."',";
		}
		$str_a = substr($str_a, 0, -1);
		$str_r = substr($str_r, 0, -1);
		
		q("UPDATE ".$DBHOST_TBL_PREFIX."group_members SET $str_a WHERE group_id=$gid AND user_id=0");
		q("UPDATE ".$DBHOST_TBL_PREFIX."group_members SET $str_r WHERE group_id=$gid AND user_id=4294967295");
	}
	qf($r);
	print_status('Finished Importing Forums');

/* Import phpBB moderators */
	
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."mod");
	print_status('Importing Moderators');
		
	$r = Q2("SELECT ".$table_prefix."user_group.user_id, ".$table_prefix."forums.forum_id 
			FROM ".$table_prefix."auth_access 
			INNER JOIN ".$table_prefix."groups 
				ON ".$table_prefix."auth_access.group_id=".$table_prefix."groups.group_id 
			INNER JOIN ".$table_prefix."user_group 
				ON ".$table_prefix."user_group.group_id=".$table_prefix."auth_access.group_id 
			INNER JOIN ".$table_prefix."users 
				ON ".$table_prefix."users.user_id=".$table_prefix."user_group.user_id 
			INNER JOIN ".$table_prefix."forums 
				ON ".$table_prefix."forums.forum_id=".$table_prefix."auth_access.forum_id 
			WHERE
				auth_mod=1 AND 
				".$table_prefix."users.user_level=".MOD." 
			GROUP BY ".$table_prefix."auth_access.forum_id, ".$table_prefix."user_group.user_id");	

	while( $obj = db_rowobj($r) ) {
		q("INSERT INTO ".$DBHOST_TBL_PREFIX."mod (user_id,forum_id) VALUES(".$obj->user_id.", ".$obj->forum_id.")");
	}
	qf($r);
	print_status('Finished Importing Moderators');

/* Import phpBB threads */
	
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."thread");	
	$r = Q2("SELECT * FROM ".$table_prefix."topics");
	print_status('Importing Threads '.db_count($r));
	while( $obj = db_rowobj($r) ) {
		
		switch( $obj->topic_type )
		{
			case POST_NORMAL:
				$ordertype='NONE';
				break;
			case POST_STICKY:
				$ordertype='STICKY';
				break;	
			case POST_ANNOUNCE:
			case POST_GLOBAL_ANNOUNCE:
				$ordertype='ANNOUNCE';
				break;		
		}
		
		switch( $obj->topic_status )
		{
			case TOPIC_UNLOCKED:
				$locked='N';
				break;
			case TOPIC_LOCKED:
				$locked='Y';
				break;	
		}
		
		q("INSERT INTO ".$DBHOST_TBL_PREFIX."thread (
			id,
			forum_id,
			root_msg_id,
			last_post_id,
			views,
			ordertype,
			locked,
			moved_to
			)
			
			VALUES(
			".$obj->topic_id.",
			".$obj->forum_id.",
			".$obj->topic_first_post_id.",
			".$obj->topic_last_post_id.",
			".$obj->topic_views.",
			'".$ordertype."',
			'".$locked."',
			".$obj->topic_moved_id."
			)
		");	
	}
	qf($r);
	print_status('Finished Importing Threads');
	
/* Import phpBB messages */
	fud_use('imsg.inc');
	fud_use('imsg_edt.inc');
	fud_use('post_proc.inc');
	fud_use('rhost.inc');

	q("DELETE FROM ".$DBHOST_TBL_PREFIX."msg");
	$r = Q2("SELECT ".$table_prefix."topics.topic_title, ".$table_prefix."posts.*, ".$table_prefix."posts_text.post_subject, ".$table_prefix."posts_text.post_text FROM ".$table_prefix."posts INNER JOIN ".$table_prefix."posts_text ON ".$table_prefix."posts.post_id=".$table_prefix."posts_text.post_id INNER JOIN ".$table_prefix."topics ON ".$table_prefix."topics.topic_id=".$table_prefix."posts.topic_id");
	print_status('Importing Messages '.db_count($r));
	while( $obj = db_rowobj($r) ) {
		if ( !strlen($obj->post_subject) ) $obj->post_subject = $obj->topic_title;
		
		$poster_ip = phpbb_decode_ip($obj->poster_ip);
		$host = NULL;

		$obj->post_text = bbcode2fudcode($obj->post_text);

		$fileid = write_body($obj->post_text, $len, $off);
		$updated_by = ( $obj->post_edit_time ) ? $obj->poster_id : 0;
		q("INSERT INTO ".$DBHOST_TBL_PREFIX."msg
			(
				id,
				thread_id,
				poster_id,
				post_stamp,
				update_stamp,
				updated_by,
				subject,
				approved,
				show_sig,
				smiley_disabled,
				ip_addr,
				host_name,
				foff
				length,
				file_id
			)
			VALUES
			(
				$obj->post_id,
				$obj->topic_id,
				$obj->poster_id,
				$obj->post_time,
				".intzero($obj->post_edit_time).",
				$updated_by,
				'".addslashes($obj->post_subject)."',
				'Y',
				'".INT_yn($obj->enable_sig)."',
				'".INT_yn($obj->enable_smilies)."',
				'$poster_ip',
				".strnull($host).",
				".intzero($off).",
				".intzero($len).",
				$fileid
			)");
	}
	qf($r);
	/* Handle bug found in phpBB2, which caused message posting date equal zero 
	 * our handler assings the time when the upgrade script was ran at to those messages
	*/
	q("UPDATE ".$DBHOST_TBL_PREFIX."msg SET post_stamp=".$start_time." WHERE post_stamp=0");
	
	print_status('Finished Importing Messages');

/* Import phpBB polls */

	q("DELETE FROM ".$DBHOST_TBL_PREFIX."poll");
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."poll_opt");
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."poll_opt_track");
	
	$r = Q2("SELECT ".$table_prefix."vote_desc.*, ".$table_prefix."posts.post_id, ".$table_prefix."posts.poster_id FROM 
			".$table_prefix."vote_desc 
			INNER JOIN ".$table_prefix."topics 
				ON ".$table_prefix."topics.topic_id=".$table_prefix."vote_desc.topic_id 
			INNER JOIN ".$table_prefix."posts 
				ON ".$table_prefix."posts.post_id=".$table_prefix."topics.topic_first_post_id
	");
	print_status('Importing Polls '.db_count($r));
		
	while ( $obj = db_rowobj($r) ) {
		$vote_length = ( $obj->vote_length ) ? $obj->vote_start+$obj->vote_length : 0;
		q("INSERT INTO ".$DBHOST_TBL_PREFIX."poll
			(
				id,
				name,
				owner,
				creation_date,
				expiry_date
			)
			VALUES(
				$obj->vote_id,
				'".addslashes($obj->vote_text)."',
				$obj->poster_id,
				$obj->vote_start,
				$vote_length
			)");
		q("UPDATE ".$DBHOST_TBL_PREFIX."msg SET poll_id=$obj->vote_id WHERE id=$obj->post_id");
		
		$r2 = Q2("SELECT * FROM ".$table_prefix."vote_results WHERE vote_id=$obj->vote_id");
		while ( $o = db_rowobj($r2) ) {
			q("INSERT INTO ".$DBHOST_TBL_PREFIX."poll_opt
				(
					poll_id,
					name,
					count
				)
				VALUES
				(
					$o->vote_id,
					'".addslashes($o->vote_option_text)."',
					$o->vote_result
				)");
		}
		qf($r2);
		
		$r2 = Q2("SELECT * FROM ".$table_prefix."vote_voters");
		while ( $o = db_rowobj($r2) ) {
			if ( bq("SELECT id FROM ".$DBHOST_TBL_PREFIX."poll_opt_track WHERE poll_id=$o->vote_id AND user_id=$o->vote_user_id") ) continue;
			
				q("INSERT INTO ".$DBHOST_TBL_PREFIX."poll_opt_track 
				(
					poll_id,
					user_id
				)
				VALUES(
					$o->vote_id,
					$o->vote_user_id
				)");
		}
		qf($r2);
	}
	qf($r);
	print_status('Finished Importing Polls');

/* Import phpBB Thread Subscriptions */
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."thread_notify");
	$r = Q2("SELECT * FROM ".$table_prefix."topics_watch WHERE notify_status=1");
	print_status('Importing Thread Subscriptions '.db_count($r));
	while ( $obj = db_rowobj($r) ) {
		q("INSERT INTO ".$DBHOST_TBL_PREFIX."thread_notify
			(
				user_id,
				thread_id
			) 
			VALUES(
				$obj->user_id,
				$obj->topic_id
			)");
	}
	qf($r);
	print_status('Finished Importing Thread Subscriptions');

/* Import phpBB user ranks */
	
	// Post based ranks
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."level");
	$r = Q2("SELECT * FROM ".$table_prefix."ranks WHERE rank_special=0");
	print_status('Importing User Ranks (post count based) '.db_count($r));
	while ( $obj = db_rowobj($r) ) {
		if( !empty($obj->rank_image) ) {
			$file_name = substr(strrchr($obj->rank_image, '/'), 1);
			if( !copy($PHPBB_INSTALL_ROOT.$obj->rank_image, $IMG_ROOT_DISK.$file_name) ) {
				print_status("Couldn't copy user rank image from (".$PHPBB_INSTALL_ROOT.$obj->rank_image.") to (".$IMG_ROOT_DISK.$file_name.")");
				exit;
			}
		}
	
		q("INSERT INTO ".$DBHOST_TBL_PREFIX."level (name,post_count,img) VALUES('".addslashes($obj->rank_title)."',".$obj->rank_min.",".strnull($file_name).")");
	}
	qf($r);
	print_status('Finished Importing User Ranks (post count based)');
	
	// Custom tags 
	q("DELETE FROm ".$DBHOST_TBL_PREFIX."custom_tags");
	$r = Q2("SELECT * FROM ".$table_prefix."ranks WHERE rank_special=1");
	print_status('Importing Custom Tags '.db_count($r));
	while ( $obj = db_rowobj($r) ) {
		$r2 = Q2("SELECT user_id FROM ".$table_prefix."users WHERE user_rank=".$obj->rank_id);
		while( $o = db_rowobj($r2) ) {
			q("INSERT INTO ".$DBHOST_TBL_PREFIX."custom_tags (name,user_id) VALUES('".addslashes($obj->rank_title)."',".$o->user_id.")");
		}	
		qf($r2);	
	}
	qf($r);
	print_status('Finished Importing Custom Tags');

/* Import phpBB blocked words */
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."replace");
	
	$r = Q2("SELECT * FROM ".$table_prefix."words");
	print_status('Importing Blocked Words '.db_count($r));
	while( $obj = db_rowobj($r) ) {
		q("INSERT INTO ".$DBHOST_TBL_PREFIX."replace (replace_str,with_str) VALUES('".addslashes($obj->word)."','".addslashes($obj->replacement)."')");
	}
	qf($r);
	print_status('Finished Importing Blocked Words');

/* Import phpBB dissalowed logins */
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."blocked_logins");
	$r = Q2("SELECT * FROM ".$table_prefix."disallow");
	print_status('Importing Disallowed Logins '.db_count($r));
	while( $obj = db_rowobj($r) ) {
	 	q("INSERT INTO ".$DBHOST_TBL_PREFIX."blocked_logins (login) VALUES('".addslashes($obj->disallow_username)."'");
	}
	qf($r);
	print_status('Finished Importing Disallowed Logins');

/* Import phpBB banned users */
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."ip_block");
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."email_block");
	
	$r = Q2("SELECT * FROM ".$table_prefix."banlist");
	print_status('Importing Banned Users '.db_count($r));
	while( $obj = db_rowobj($r) ) {
		if( !empty($obj->ban_userid) ) 
			q("UPDATE ".$DBHOST_TBL_PREFIX."users SET blocked='Y' WHERE id=".$obj->ban_userid);

		if( !empty($obj->ban_ip) ) {
			list($ca,$cb,$cc,$cd) = explode('.', phpbb_decode_ip($obj->ban_ip));
			q("INSERT INTO ".$DBHOST_TBL_PREFIX."ip_block (ca,cb,cc,cd) VALUES($ca,$cb,$cc,$cd)");
		}
		
		if( !empty($obj->ban_email) ) 
			q("INSERT INTO ".$DBHOST_TBL_PREFIX."email_block (string) VALUES('".addslashes($obj->ban_email)."'");	
	}
	qf($r);
	print_status('Finished Importing Banned Users');

/* Import phpBB private messages */
	fud_use('private.inc');

	q("DELETE FROM ".$DBHOST_TBL_PREFIX."pmsg");
	$r = Q2("SELECT ".$table_prefix."privmsgs.*, ".$table_prefix."privmsgs_text.privmsgs_text FROM ".$table_prefix."privmsgs INNER JOIN ".$table_prefix."privmsgs_text ON ".$table_prefix."privmsgs.privmsgs_id=".$table_prefix."privmsgs_text.privmsgs_text_id");
	print_status('Importing Private Messages '.db_count($r));
	while( $obj = db_rowobj($r) ) {
		
		list($off, $len) = write_pmsg_body(bbcode2fudcode($obj->privmsgs_text));
		
		switch ( $obj->privmsgs_type ) 
		{
			case PRIVMSGS_READ_MAIL:
			case PRIVMSGS_NEW_MAIL:
			
				$read_stamp = ( $obj->privmsgs_type==PRIVMSGS_READ_MAIL ) ? $obj->privmsgs_date : 0;
			
				q("INSERT INTO ".$DBHOST_TBL_PREFIX."pmsg (
					ouser_id,
					duser_id,
					ip_addr,
					post_stamp,
					read_stamp,
					folder_id,
					subject,
					show_sig,
					smiley_disabled,
					foff
					length
					)
					VALUES(
						$obj->privmsgs_from_userid,
						$obj->privmsgs_to_userid,
						'".phpbb_decode_ip($obj->privmsgs_ip)."',
						$obj->privmsgs_date,
						$read_stamp,
						'INBOX',
						'".addslashes($obj->privmsgs_subject)."',
						'".INT_yn($obj->privmsgs_attach_sig)."',
						'".INT_yn($obj->privmsgs_enable_smilies)."',
						".intzero($off).",
						".intzero($len)."
					)");
				break;
			case PRIVMSGS_SENT_MAIL:
				q("INSERT INTO ".$DBHOST_TBL_PREFIX."pmsg (
					ouser_id,
					duser_id,
					ip_addr,
					post_stamp,
					read_stamp,
					folder_id,
					subject,
					show_sig,
					smiley_disabled,
					foff
					length
					)
					VALUES(
						$obj->privmsgs_from_userid,
						$obj->privmsgs_from_userid,
						'".$obj->privmsgs_ip."',
						$obj->privmsgs_date,
						$obj->privmsgs_date,
						'SENT',
						'".addslashes($obj->privmsgs_subject)."',
						'".INT_yn($obj->privmsgs_attach_sig)."',
						'".INT_yn($obj->privmsgs_enable_smilies)."',
						".intzero($off).",
						".intzero($len)."
					)");			
				break;	
		}
	}
	qf($r);
	print_status('Finished Importing Private Messages');

/* Import phpBB file attachments (if person has applied phpbb file attachment mod) */

	if( $r = mysql_db_query($dbname, "SELECT * FROM ".$table_prefix."attach_desc", $GLOBALS['phpbbdb']) ) {
		$ENABLED_FILE_ATTACHMENTS=1;
		print_status('Importing File Attachments '.db_count($r));
	
		fud_use('mime.inc');
		
		q("DELETE FROM ".$DBHOST_TBL_PREFIX."attach");

		list($phpbb_storage) = db_singlearr(Q2("SELECT config_value FROM ".$table_prefix."attach_config WHERE config_name='upload_dir'"));

		$old_umask = umask(0);
		while( $obj = db_rowobj($r) ) {
			
			if( !@file_exists($PHPBB_INSTALL_ROOT.$phpbb_storage.$obj->attach_filename) ) {
				print_status("\tWARNING: file attachment ".$PHPBB_INSTALL_ROOT.$phpbb_storage.$obj->attach_filename." doesn't exist");
				continue;
			}
			
			$owner = q_singleval("SELECT poster_id FROM ".$DBHOST_TBL_PREFIX."msg WHERE id=".$obj->post_id);
			$mime = get_mime_by_ext(substr(strrchr($obj->filename, '.'), 1));
			
			q("INSERT INTO ".$DBHOST_TBL_PREFIX."attach (
				proto,
				original_name,
				owner,
				message_id,
				dlcount,
				mime_type
				)
				VALUES
				(
				'LOCAL',
				'".addslashes($obj->filename)."',
				$owner,
				$obj->post_id,
				$obj->download_count,
				".intzero($mime)."
				)
			");
			
			$attach_id = db_lastid();
			
			if( !copy($PHPBB_INSTALL_ROOT.$phpbb_storage.$obj->attach_filename, $FILE_STORE.$attach_id.'.atch') ) {
				print_status("Couldn't copy file attachment (".$PHPBB_INSTALL_ROOT.$phpbb_storage.$obj->attach_filename.") to (".$FILE_STORE.$attach_id.'.atch'.")");
				exit;
			}
			chmod($FILE_STORE.$attach_id.'.atch', 0666);
			
			q("UPDATE ".$DBHOST_TBL_PREFIX."attach SET location='".$FILE_STORE.$attach_id.'.atch'."' WHERE id=".$attach_id);
		}
		qf($r);
		umask($old_umask);
		print_status('Finished Importing File Attachments');
	}
	
/* Import phpBB settings */
	print_status('Importing Forum Settings');
	fud_use('static/glob.inc');

	$global_config = read_global_config();

	change_global_val('FORUM_ENABLED', INT_yn(!$board_config['board_disable']), $global_config);
	change_global_val('FORUM_TITLE', $board_config['sitename'], $global_config);
	change_global_val('SESSION_TIMEOUT', $board_config['session_length'], $global_config);
	change_global_val('ALLOW_SIGS', INT_yn($board_config['allow_sig']), $global_config);
	change_global_val('POSTS_PER_PAGE', $board_config['posts_per_page'], $global_config);
	change_global_val('THREADS_PER_PAGE', $board_config['topics_per_page'], $global_config);
	change_global_val('NOTIFY_FROM', $board_config['board_email'], $global_config);
	change_global_val('EMAIL_CONFIRMATION', INT_yn($board_config['require_activation']), $global_config);
	change_global_val('FLOOD_CHECK_TIME', $board_config['flood_interval'], $global_config);
	change_global_val('ALLOW_EMAIL', INT_yn($board_config['board_email_form']), $global_config);
	change_global_val('CUSTOM_AVATAR_MAX_SIZE', $board_config['avatar_filesize'], $global_config);
	change_global_val('CUSTOM_AVATAR_MAX_DIM', $board_config['avatar_max_width'].'x'.$board_config['avatar_max_height'], $global_config);
	change_global_val('PM_ENABLED', INT_yn(!$board_config['privmsg_disable']), $global_config);
	
	if( $board_config['allow_avatar_local'] && $board_config['allow_avatar_remote'] && $board_config['allow_avatar_upload'] ) 
		$CUSTOM_AVATARS = 'ALL';
	else if( $board_config['allow_avatar_local'] && $board_config['allow_avatar_remote'] ) 
		$CUSTOM_AVATARS = 'BUILT_URL';
	else if( $board_config['allow_avatar_remote'] && $board_config['allow_avatar_upload'] )
		$CUSTOM_AVATARS = 'URL_UPLOAD';
	else if( $board_config['allow_avatar_local'] && $board_config['allow_avatar_upload'] ) 	
		$CUSTOM_AVATARS = 'BUILT_UPLOAD';
	else if( $board_config['allow_avatar_local'] ) 
		$CUSTOM_AVATARS = 'BUILT';
	else if( $board_config['allow_avatar_remote'] )
		$CUSTOM_AVATARS = 'URL';
	else if( $board_config['allow_avatar_upload'] )
		$CUSTOM_AVATARS = 'UPLOAD';
	else
		$CUSTOM_AVATARS = 'OFF';
	
	change_global_val('CUSTOM_AVATARS', $CUSTOM_AVATARS, $global_config);
	
	change_global_val('PRIVATE_MSG_SMILEY', INT_yn($board_config['allow_smilies']), $global_config);
	change_global_val('FORUM_SML_SIG', INT_yn($board_config['allow_smilies']), $global_config);

	if( $board_config['allow_bbcode'] ) 
		$TAGS = 'ML';
	else if( $board_config['allow_html'] ) 
		$TAGS = 'HTML';
	else
		$TAGS = 'N';

	change_global_val('PRIVATE_TAGS', $TAGS, $global_config);
	change_global_val('FORUM_CODE_SIG', $TAGS, $global_config);

	if( $ENABLED_FILE_ATTACHMENTS ) {
		list($max_attach) = db_singlearr(Q2("SELECT config_value FROM ".$table_prefix."attach_config WHERE config_name='max_attachments'"));
		list($max_fsize) = db_singlearr(Q2("SELECT config_value FROM ".$table_prefix."attach_config WHERE config_name='max_filesize'"));
		
		change_global_val('PRIVATE_ATTACHMENTS', $max_attach, $global_config);
		change_global_val('PRIVATE_ATTACH_SIZE', $max_fsize, $global_config);
		
		q("UPDATE ".$DBHOST_TBL_PREFIX."forum SET max_file_attachments=".intzero($max_attach).", max_attach_size=".intzero($max_fsize));
	}

	write_global_config($global_config);

	print_status('Finished Importing Forum Settings');
	
	$time_taken = time() - $start_time;
	if( $time_taken > 120 ) 
		$time_taken .= ' seconds';
	else {
		$m = floor($time_taken/60);
		$s = $time_taken - $m*60;
		$time_taken = $m." minutes ".$s." seconds";
	}	
	
	print_status("\n\nConversion of phpBB2 to FUDforum2 has been completed\n Time Taken: ".$time_taken."\n");
	print_status("To complete the process run the consistency checker at:");
	print_status($GLOBALS['WWW_ROOT']."adm/consist.php");
	if( isset($HTTP_SERVER_VARS['REMOTE_ADDR']) ) echo '</pre>';
?>