<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: openbb.php,v 1.1.1.1 2002/06/17 23:00:09 hackie Exp $
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
 *	2) Change the value of the value of the $OPENBB_INSTALL_ROOT variable
 *	   to the full path of the directory where openBB was installed.
 *	3) Run this script via the shell or the web.
 *	4) Once the script successfuly runs, run the consitency checker.
 *	5) Voila, you're done.
*/
	$OPENBB_INSTALL_ROOT = "";

/* DO NOT MODIFY BEYOND THIS POINT */

	$OPENBB_INSTALL_ROOT = realpath($OPENBB_INSTALL_ROOT);

	if( empty($OPENBB_INSTALL_ROOT) ) 
		exit("OPENBB_INSTALL_ROOT is blank, cannot proceed!\n Change the value of the value of the \$OPENBB_INSTALL_ROOT variable to the full path of the directory where openBB was installed.\n");

	if( !file_exists($OPENBB_INSTALL_ROOT.'/lib/sqldata.php') )
		exit("OPENBB_INSTALL_ROOT ($OPENBB_INSTALL_ROOT) does not contain valid path to openBB\n");

	include_once $OPENBB_INSTALL_ROOT.'/lib/sqldata.php';
	
	$dbname = $database_server['database'];
	$phpbbdb = mysql_connect($database_server['hostname'],$database_server['username'],$database_server['password']);

	include_once "GLOBALS.php";
	$IMG_ROOT_DISK = $WWW_ROOT_DISK.'images/';
	
	fud_use('rev_fmt.inc');
	fud_use('post_proc.inc');
	fud_use('db.inc');
	fud_Use('smiley.inc');
	fud_use('forum.inc');
	fud_use('forum_adm.inc');
	fud_use('groups.inc');
	fud_use('util.inc');
	fud_use('imsg.inc');
	fud_use('imsg_edt.inc');
	fud_use('post_proc.inc');
	fud_use('rhost.inc');
	fud_use('static/glob.inc');

	if( isset($HTTP_SERVER_VARS['REMOTE_ADDR']) ) echo '<pre>';
	
	$start_time = time();

function Q2($str)
{
	$r= mysql_db_query($GLOBALS['dbname'], $str, $GLOBALS['phpbbdb']);
	if( !$r ) exit(mysql_error($GLOBALS['phpbbdb'])."\n");
	return $r; 
}

function INT_YN($s)
{
	return (intval($s)?'Y':'N');
}

function print_status($str)
{
	echo $str."\n";
	flush();
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

function openBBcode2fudcode($str)
{
	$str = str_replace("\r", "", $str);
	return smiley_to_post(tags_to_html($str));
}

	print_status('Beginning Conversion Process');
	
/* Read openBB config */

	$r = Q2("SELECT * FROM configuration");
	$OPENBB = DB_ROWOBJ($r);
	QF($r);
	
	list($OPENBB_IMG_PATH) = DB_SINGLEARR(Q2("SELECT rep FROM vars WHERE org='imagepath'"));

/* Import openBB Smilies */
	Q("DELETE FROM ".$MYSQL_TBL_PREFIX."smiley");
	
	$curdir = getcwd();
	chdir($OPENBB_INSTALL_ROOT);
	$old_umask = umask(0);
	$r = Q2("SELECT * FROM smilies");
	print_status('Importing Smilies: '.DB_COUNT($r));

	while( $obj = DB_ROWOBJ($r) ) {
		if( !@file_exists($OPENBB_IMG_PATH."/smiley/".$obj->image) ) {
			print_status("\tMissing image (".$OPENBB_IMG_PATH."/smiley/) for ".$obj->smiley.", skipping");
			continue;
		}
		else if( !copy($OPENBB_IMG_PATH."/smiley/".$obj->image, $IMG_ROOT_DISK.'smiley_icons/'.$obj->image) ) {
			print_status("Coulnd't copy smiley image (".$OPENBB_IMG_PATH."/smiley/".$obj->image.") to (".$IMG_ROOT_DISK.'smiley_icons/'.$obj->image.")");
			print_status("Please ensure the script has access to perform this action and run it again");
			exit;
		}
		@chmod($IMG_ROOT_DISK.'smiley_icons/'.$obj->image, 0666);
		Q("INSERT INTO ".$MYSQL_TBL_PREFIX."smiley (img,code) VALUES('".$obj->image."','".addslashes($obj->smiley)."')");
	}

	umask($old_umask);
	chdir($curdir);
	QF($r);	 
	 
	print_status('Finished Importing Smilies');

/* Quickly see which users are admins by checking which usergroups contain admins */
	$ADMIN_GROUPS = array();
	$r = Q2("select id from usergroup WHERE isadmin=1");
	while( list($id) = DB_ROWARR($r) ) {
		$ADMIN_GROUPS[$id] = $id;
	}
	QF($r);

/* Import openBB users */	
	Q("DELETE FROM ".$MYSQL_TBL_PREFIX."users");
	Q("DELETE FROM ".$MYSQL_TBL_PREFIX."custom_tags");
	$r = Q2("SELECT * FROM profiles");
	
	print_status('Importing Users '.DB_COUNT($r));
	$old_umask=umask(0);
	$curdir = getcwd();
	chdir($OPENBB_INSTALL_ROOT);
	
	$unimported_users = array();
	
	while ( $obj = DB_ROWOBJ($r) ) {
		$r3 = Q2("SELECT posts,username FROM profiles WHERE email='".$obj->email."' ORDER BY posts DESC LIMIT 1");
		list($pc,$wuname) = DB_ROWARR($r3);
		QF($r3);
		if( $pc>$obj->posts && !$obj->posts ) {
			print_status("\tuser: ".$obj->username);
			print_status("\t\tWARNING: Dupe email ".$obj->email.", not a top posting account, will use $wuname instead, skipping...");
			continue;
		}
		else if( $pc>$obj->posts ) {
			$unimported_users[$obj->username] = $obj->username;
			print_status("\tuser: ".$obj->username);
			print_status("\t\tWARNING: Dupe email ".$obj->email.", not a top posting account, will use $wuname instead, skipping...");
			continue;
		}
	
		if( BQ("SELECT id FROM ".$MYSQL_TBL_PREFIX."users WHERE login='".addslashes($obj->username)."' OR email='".$obj->email."'") ) {
			$unimported_users[$obj->username] = $obj->username;
			print_status("\tuser: ".$obj->username);
			print_status("\t\tWARNING: Cannot import user ".$obj->username.", user with this email and/or login already exists");
			continue;
		}
		
		if( !is_numeric($obj->icq) ) $obj->icq = NULL;
		
		$is_mod = (!isset($ADMIN_GROUPS[$obj->usergroup]))?'N':'A';
		
		Q("INSERT INTO ".$MYSQL_TBL_PREFIX."users 
			(
				id,
				login,
				passwd,
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
				invisible_mode,
				home_page,
				show_avatars,
				bio,
				email_conf,
				coppa,
				is_mod
			)
			VALUES (
				".$obj->id.",
				'".addslashes($obj->username)."',
				'".$obj->password."',
				".INTZERO($obj->joindate).",
				'".INT_YN(!$obj->showemail)."',
				'".INT_YN(!$obj->showsig)."',
				'".INT_YN(!$obj->autosubscribe)."',
				'".$obj->email."',
				".INTNULL($obj->icq).",
				'".addslashes($obj->location)."',
				'".addslashes(openBBcode2fudcode($obj->sig))."',
				'".addslashes($obj->aim)."',
				'".addslashes($obj->yahoo)."',
				'".addslashes($obj->msn)."',
				'".addslashes($obj->occupation)."',
				'".INT_YN(!$obj->invisible)."',
				'".addslashes($obj->homepage)."',
				'".INT_YN(!$obj->showavatar)."',
				'".addslashes($obj->note)."',
				'Y',
				'N',
				'$is_mod'
				)");

		$uid = DB_LASTID();
			
		/* Import custom tags for the user if there are any */
		if( $obj->custom ) Q("INSERT INTO ".$MYSQL_TBL_PREFIX."custom_tags (user_id,name) VALUES($uid,'".addslashes($obj->custom)."')");
			
		/* Import user avatars */
		if( $obj->avatar && $obj->avatar != 'blank.gif' ) {
			if( preg_match('!^http://!', $obj->avatar) ) { /* URL Avatar */
				$avatar_loc = $obj->avatar;
				$avatar_approved='Y';
			}
			else { /* Uploaded Avatar */
				if( !@file_exists("avatars/".$obj->avatar) ) {
					print_status("\tuser: ".$obj->username);
					print_status("\t\tWARNING: missing avatar file: avatars/".$obj->avatar);
					$avatar_approved = 'NO';
				}
				else if( !@copy("avatars/".$obj->avatar, $IMG_ROOT_DISK."custom_avatars/".$uid) ) {
					print_status("Couldn't copy avatar avatars/".$obj->avatar." to ".$IMG_ROOT_DISK."custom_avatars/".$uid);
					print_status("Please ensure the script has access to perform this action and run it again");
					exit;
				}	
				else {
					@chmod($IMG_ROOT_DISK.'custom_avatars/'.$uid, 0666);
					$avatar_approved='Y';
				}
				$avatar_loc = '';
			}
			Q("UPDATE ".$MYSQL_TBL_PREFIX."users SET avatar_approved='".$avatar_approved."' AND avatar_loc='".addslashes($avatar_loc)."'");
		}
	}
	QF($r);
	umask($old_umask);
	chdir($curdir);
	print_status('Finished Importing Users');

/* Import openBB Categories */

	Q("DELETE FROM ".$MYSQL_TBL_PREFIX."cat");
	Q("DELETE FROM ".$MYSQL_TBL_PREFIX."group_resources");
	Q("DELETE FROM ".$MYSQL_TBL_PREFIX."groups WHERE id>2");
	Q("DELETE FROM ".$MYSQL_TBL_PREFIX."forum");
	Q("DELETE FROM ".$MYSQL_TBL_PREFIX."group_members");
	Q("DELETE FROM ".$MYSQL_TBL_PREFIX."mod");
	
	$r = Q2("SELECT * FROM forum_display WHERE parent=-1 ORDER BY forumorder");
	print_status('Importing Categories '.DB_COUNT($r));
	$i=1;
	while( $obj = DB_ROWOBJ($r) ) {
		Q("INSERT INTO ".$MYSQL_TBL_PREFIX."cat (id,name,description,view_order) VALUES(".$obj->forumid.",'".addslashes($obj->title)."','".addslashes($obj->description)."',".$i++.")");	
	}
	QF($r);
	print_status('Finished Importing Categories');
	
	// Import openBB Forums 
$group_map = array(
'forum_canview'=>'up_VISIBLE',
'thread_canview'=>'up_READ',
'thread_canpost'=>'up_POST',
'thread_canreply'=>'up_REPLY',
'poll_canvote'=>'up_VOTE',
'poll_canvote'=>'up_POLL',
'thread_canreply'=>'up_FILE'
);

	$r = Q2("SELECT * FROM forum_display WHERE parent!=-1 ORDER BY parent,forumorder");
	print_status('Importing Forums '.DB_COUNT($r));
	$pid = 0;
	while( $obj = DB_ROWOBJ($r) ) {
		if( $pid != $obj->parent ) {
			$pid = $obj->parent;
		}	
		
		$frm = new fud_forum_adm;
		$frm->cat_id = $obj->parent;
		$frm->name = addslashes($obj->title);
		$frm->descr = addslashes($obj->description);
		$id = $frm->add('LAST');
		
		// Import openBB Moderators if avaliable 
		
		if( $obj->moderators ) {
			$mods = explode(',',$obj->moderators);
			while( list(,$v) = each($mods) ) {
				if( ($mid = Q_SINGLEVAL("SELECT id FROM ".$MYSQL_TBL_PREFIX."users WHERE login='".addslashes($v)."'")) )
					Q("INSERT INTO ".$MYSQL_TBL_PREFIX."mod (user_id,forum_id) VALUES($mid,$id)");
				else if( ($mid=$unimported_users[$v]) ) {
					/* noop */
				}	
				else	
					print_status("\tCouldn't add '$v' as moderator, user does not exist\n");
			}
		}
		
		Q("UPDATE ".$MYSQL_TBL_PREFIX."forum SET id=".$obj->forumid." WHERE id=".$id);
		$gid = Q_SINGLEVAL("SELECT id FROM ".$MYSQL_TBL_PREFIX."groups WHERE res='forum' AND res_id=$id");
		Q("UPDATE ".$MYSQL_TBL_PREFIX."groups SET res_id=$obj->forumid WHERE id=$gid");
		Q("UPDATE ".$MYSQL_TBL_PREFIX."group_resources SET resource_id=$obj->forumid WHERE group_id=$gid");
		
		/* Import Forum Permissions for all regged & all anon users  */
		$r2 = Q2("SELECT * FROM forum_permissions WHERE forumid=".$obj->forumid);
		while( $obj = DB_ROWOBJ($r2) ) {
			reset($group_map);
			
			if( !$obj->uid ) {
				$str = '';
				while( list($k,$v) = each($group_map) ) $str .= $v."='".INT_YN(!$obj->{$k})."',";
				$str = substr($str, 0, -1);

				Q("UPDATE ".$MYSQL_TBL_PREFIX."group_members SET $str WHERE group_id=$gid AND user_id=4294967295");
			}
			else {
				$str1 = $str2 = '';
				while( list($k,$v) = each($group_map) ) {
					$str1 .= "$v,";
					$str2 .= "'".INT_YN(!$obj->{$k})."',";
				}	
				$str1 = substr($str1, 0, -1);
				$str2 = substr($str2, 0, -1);
			
				Q("INSERT INTO ".$MYSQL_TBL_PREFIX."group_members ($str1,user_id,group_id) VALUES($str2,".$obj->uid.",$gid)");
			}	
		}
		QF($r2);
	}
	QF($r);
	print_status('Finished Importing Forums');	

/* Import openBB Post Icons */
	
	$r = Q2("SELECT image,id FROM topicicons");
	print_status('Importing Post Icons '.DB_COUNT($r));
	
	$IMPORTED_PI = array();
	
	$curdir = getcwd();
	$umask = umask(0);
	chdir($OPENBB_INSTALL_ROOT."/".$OPENBB_IMG_PATH);
	
	while( list($img_p,$pid) = DB_ROWARR($r) ) {
		if( !@file_exists($img_p) ) {
			print_status("\tMissing topic icon $img_p, skipping");
			continue;
		}
		else if( !@copy($img_p, $IMG_ROOT_DISK.'/message_icons/'.$img_p) ) {
			print_status("Couldn't copy post icon ".realpath($img_p)." to ".$IMG_ROOT_DISK.'/message_icons/'.$img_p);
			print_status("Please ensure the script has access to perform this action and run it again");
			exit;
		}
		else {
			@chmod($IMG_ROOT_DISK.'/message_icons/'.$img_p, 0666);
			$IMPORTED_PI[$pid] = $img_p;
		}
	}
	
	umask($umask);
	chdir($curdir);
	print_status('Finished Importing Post Icons');
	
/* Import openBB Threads & Messages */
	
	Q("DELETE FROM ".$MYSQL_TBL_PREFIX."thread");
	Q("DELETE FROM ".$MYSQL_TBL_PREFIX."msg");
	
	$r = Q2("SELECT * FROM topics ORDER BY id");
	print_status('Importing Threads '.DB_COUNT($r));
	while( $obj = DB_ROWOBJ($r) ) {
		Q("INSERT ".$MYSQL_TBL_PREFIX."thread (id,forum_id,locked) VALUES($obj->id, $obj->forumid, '".INT_YN($obj->locked)."')");
		
		// Import openBB Messages
		
		$r2 = Q2("SELECT * FROM posts WHERE threadid=".$obj->id." ORDER BY dateline");
		print_status("\tImporting Messages for this thread #(".$obj->id.") ".DB_COUNT($r2));
		$i=0;
		while( $obj2 = DB_ROWOBJ($r2) ) {
			$icon = '';
			if ( !$i++ ) {
				Q("UPDATE ".$MYSQL_TBL_PREFIX."thread SET root_msg_id=".$obj2->id." WHERE id=".$obj->id);
				if( $obj->icon && $IMPORTED_PI[$obj->icon] ) $icon = $IMPORTED_PI[$obj->icon];
			}	
			
			$obj2->message = openBBcode2fudcode($obj2->message);
			$fileid = write_body($obj2->message, $len, $off);
			
			$poster = INTZERO(Q_SINGLEVAL("SELECT id FROM ".$MYSQL_TBL_PREFIX."users WHERE login='".addslashes($obj2->poster)."'"));
			if( !$poster && $unimported_users[$obj2->poster] ) $poster = $unimported_users[$obj2->poster];
			
			
			if( $obj2->lastupdateby ) 
				$updated_by  = INTZERO(Q_SINGLEVAL("SELECT id FROM ".$MYSQL_TBL_PREFIX."users WHERE login='".addslashes($obj2->lastupdateby)."'"));
			else if( ($updated_by=$unimported_users[$obj2->lastupdateby]) ) {
				/* noop */
			}	
			else	
				$updated_by = 0;			
			
			Q("INSERT INTO ".$MYSQL_TBL_PREFIX."msg
			(
				id,
				thread_id,
				poster_id,
				post_stamp,
				update_stamp,
				updated_by,
				subject,
				approved,
				smiley_disabled,
				ip_addr,
				offset,
				length,
				file_id,
				icon
			)
			VALUES
			(
				$obj2->id,
				$obj2->threadid,
				$poster,
				$obj2->dateline,
				".INTZERO($obj2->lastupdate).",
				$updated_by,
				'".addslashes($obj2->title)."',
				'Y',
				'".INT_YN(!$obj2->dsmiley)."',
				'".$obj2->ip."',
				".INTZERO($off).",
				".INTZERO($len).",
				$fileid,
				'".addslashes($icon)."'
			)");
			
		}
		QF($r2);
	}
	QF($r);
	print_status('Finished Importing Threads');
	
/* Import openBB Polls */

	Q("DELETE FROM ".$MYSQL_TBL_PREFIX."poll");
	Q("DELETE FROM ".$MYSQL_TBL_PREFIX."poll_opt");
	Q("DELETE FROm ".$MYSQL_TBL_PREFIX."poll_opt_track");

	$r = Q2("SELECT polls.*,topics.id AS thread_id,topics.description FROM topics INNER JOIN polls ON topics.pollid=polls.id WHERE topics.pollid!=0");
	print_status('Importing Polls '.DB_COUNT($r));
	while( $obj = DB_ROWOBJ($r) ) {
		list($owner,$mid) = DB_SINGLEARR(Q("SELECT ".$MYSQL_TBL_PREFIX."msg.poster_id,".$MYSQL_TBL_PREFIX."msg.id FROM ".$MYSQL_TBL_PREFIX."thread INNER JOIN ".$MYSQL_TBL_PREFIX."msg ON ".$MYSQL_TBL_PREFIX."thread.root_msg_id=".$MYSQL_TBL_PREFIX."msg.id WHERE ".$MYSQL_TBL_PREFIX."thread.id=".$obj->thread_id));
	
		$owner = INTZERO($owner);
	
		Q("INSERT INTO ".$MYSQL_TBL_PREFIX."poll (id,name,owner) VALUES($obj->id, '".addslashes($obj->description)."', $owner)");
		
		for( $i=1; $i<11; $i++ ) {
			if( !$obj->{'option'.$i} ) break;
			Q("INSERT INTO ".$MYSQL_TBL_PREFIX."poll_opt (poll_id,name,count) VALUES($obj->id, '".addslashes($obj->{'option'.$i})."', ".$obj->{'answer'.$i}.")");
		}
		
		$voters = explode(',', $obj->total);
		while( list(,$v) = each($voters) ) {
			if( ($uid=Q_SINGLEVAL("SELECT id FROM ".$MYSQL_TBL_PREFIX."users WHERE login='".addslashes(trim($v))."'")) )
				Q("INSERT INTO ".$MYSQL_TBL_PREFIX."poll_opt_track (poll_id,user_id) VALUES($obj->id, $uid)");
			else if ( ($uid=$unimported_users[trim($v)]) ) 
				Q("INSERT INTO ".$MYSQL_TBL_PREFIX."poll_opt_track (poll_id,user_id) VALUES($obj->id, $uid)");
				
		}
		unset($voters);
		
		Q("UPDATE ".$MYSQL_TBL_PREFIX."msg SET poll_id=".$obj->id." WHERE id=".$mid);
	}
	QF($r);
	
	print_status('Finished Importing Polls');
	
/* Import openBB Ranks */

	Q("DELETE FROM ".$MYSQL_TBL_PREFIX."level");
	
	$r = Q2("SELECT * FROM usertitles GROUP BY minposts ORDER BY minposts");
	print_status('Importing User Ranks '.DB_COUNT($r));
	while( $obj = DB_ROWOBj($r) ) {
		// Add image copy code
		Q("INSERT INTO ".$MYSQL_TBL_PREFIX."level (name,post_count,img) VALUES('".addslashes($obj->title)."', $obj->minposts, '".addslashes($obj->image)."')");
	}
	QF($r);
	print_status('Finished Importing User Ranks');
	
/* Import openBB Forum Settings */

	print_status('Importing Forum Settings');	

	$global_config = read_global_config();

	$OPENBB->showavatar = (!$OPENBB->showavatar)?'ALL':'NONE';

	change_global_val('FORUM_TITLE', $OPENBB->boardname, $global_config);
	change_global_val('FORUM_ENABLED', INT_YN(!$OPENBB->locked), $global_config);
	change_global_val('DISABLED_REASON', $OPENBB->lockedreason, $global_config);
	change_global_val('ALLOW_SIGS', INT_YN(!$OPENBB->showsig), $global_config);
	change_global_val('CUSTOM_AVATARS', $OPENBB->showavatar, $global_config);
	change_global_val('CUSTOM_AVATAR_MAX_SIZE', $OPENBB->avsize, $global_config);
	change_global_val('CUSTOM_AVATAR_MAX_DIM', $OPENBB->avw.'x'.$OPENBB->avh, $global_config);
	change_global_val('FORUM_SML_SIG', INT_YN($OPENBB->dsmiley), $global_config);
	change_global_val('PRIVATE_MSG_SMILEY', INT_YN($OPENBB->dsmiley), $global_config);
	change_global_val('POSTS_PER_PAGE', $OPENBB->plistperpage, $global_config);
	change_global_val('THREADS_PER_PAGE', $OPENBB->tlistperpage, $global_config);
	change_global_val('MEMBERS_PER_PAGE', $OPENBB->mlistperpage, $global_config);
	
	switch( $OPENBB->regtype )
	{
		case '2':
			change_global_val('EMAIL_CONFIRMATION', 'N', $global_config);
			break;
		default:
			change_global_val('EMAIL_CONFIRMATION', 'Y', $global_config);
			break;	
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
	
	print_status("\n\nConversion of openBB to FUDforum2 has been completed\n Time Taken: ".$time_taken."\n");
	print_status("To complete the process run the consistency checker at:");
	print_status($GLOBALS['WWW_ROOT']."adm/consist.php");
	
	if( isset($HTTP_SERVER_VARS['REMOTE_ADDR']) ) echo '</pre>';
?>