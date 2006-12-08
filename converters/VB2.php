<?php
/***************************************************************************
* copyright            : (C) 2001-2006 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: VB2.php,v 1.30 2006/12/08 15:14:40 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it 
* under the terms of the GNU General Public License as published by the 
* Free Software Foundation; version 2 of the License. 
***************************************************************************/

	@set_time_limit(6000);
/*
 *	Usage Instructions
 *
 *	1) Copy this script into the main web directory of FUDforum 2.
 *	2) Change the value of the value of the $VB2_CONFIG_PATH variable
 *	   to the full path of the Vbulletin's config.php file.
 *	3) Run this script via the shell or the web.
 *	4) Once the script successfuly runs, run the consitency checker.
 *	5) Voila, you're done.
*/
	$VB2_CONFIG_PATH = "/home/forum/F/vb/admin/config.php";

/* DO NOT MODIFY BEYOND THIS POINT */

	if( empty($VB2_CONFIG_PATH) ) 
		exit("VB2_CONFIG_PATH is blank, cannot proceed!\n Change the value of the value of the \$VB2_CONFIG_PATH variable to the full path full path of the Vbulletin's config.php file.\n");
	else if( !@file_exists($VB2_CONFIG_PATH) || !@is_readable($VB2_CONFIG_PATH) ) 
		exit("FATAL ERROR: The Vbulletin configuration file ".$VB2_CONFIG_PATH." does not exist, or cannot be opened by the conversion script\n");
	
	include_once $VB2_CONFIG_PATH;
	define ("VB2DIR", realpath(dirname($VB2_CONFIG_PATH).'/../'));
	define ("SHOWSIGNATURES", 1);
	define ("SHOWAVATARS", 2);
	define ("SHOWIMAGES", 4);
	define ("SHOWVBCODE", 8);
	
	$vb2db = mysql_connect($servername,$dbusername,$dbpassword);

	/* prevent session initialization */
	unset($_SERVER['REMOTE_ADDR']);
	define('forum_debug', 1);
	$gl = @include("./GLOBALS.php"); 
	if ($gl === FALSE) {
		exit("This script must be placed in FUDforum's main web directory.\n");
	}

	$IMG_ROOT_DISK = $WWW_ROOT_DISK.'images/';
	fud_use('rev_fmt.inc');
	fud_use('post_proc.inc');
	fud_use('db.inc');
	fud_Use('smiley.inc');
	fud_use('forum_adm.inc', true);
	fud_use('groups.inc');
	fud_use('imsg_edt.inc');
	fud_use('post_proc.inc');
	fud_use('rhost.inc');
	fud_use('private.inc');
	fud_use('glob.inc', true);
	


	if( isset($HTTP_SERVER_VARS['REMOTE_ADDR']) ) echo '<pre>';
	if( !isset($DBHOST_TBL_PREFIX) ) $DBHOST_TBL_PREFIX = $MYSQL_TBL_PREFIX;
	$start_time = time();

function Q2($str)
{
	$r= mysql_db_query($GLOBALS['dbname'], $str, $GLOBALS['vb2db']);
	if( !$r ) exit(mysql_error($GLOBALS['vb2db'])."<br>\n");
	return $r; 
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

function vb2tofudcode($str)
{
	return smiley_to_post(tags_to_html($str));	
}

function INT_yn($s)
{
	return (empty($s)?'N':'Y');
}

function print_status($str)
{
	echo $str."\n";
}

function append_perm_str($perm, $who)
{
	return INT_yn(($perm==$who)?1:0);
}

	print_status('Beginning Conversion Process');
	
	print_status('Read VBulletin Settings');
	$VB2SET = array();
	$r = Q2("SELECT varname,value FROM setting");
	while( $obj = db_rowobj($r) ) {
		$VB2SET[$obj->varname] = $obj->value;
	}
	unset($r);

/* Import Avatar Gallery */
	q("DELETE FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."avatar");

	$r = Q2("SELECT * FROM avatar");
	print_status('Importing '.db_count($r).' Avatars From Avatar Gallery');
	$umask = umask(0177);
	while( $obj = db_rowobj($r) ) {
		if( !@file_exists(VB2DIR.'/'.$obj->avatarpath) ) {
			print_status("\tAvatar ".VB2DIR.'/'.$obj->avatarpath." does not exist, skipping");
			continue;			
		}
		
		q("INSERT INTO ".$GLOBALS['DBHOST_TBL_PREFIX']."avatar (
			id,
			descr,
			img
			)
			VALUES(
			".$obj->avatarid.",
			'".addslashes($obj->title)."',
			'".addslashes(basename($obj->avatarpath))."'
			)
		");
		
		if( !copy(VB2DIR.'/'.$obj->avatarpath, $IMG_ROOT_DISK.'avatars/'.basename($obj->avatarpath)) ) {
			print_status("Couldn't copy avatar (".VB2DIR.'/'.$obj->avatarpath.") to (".$IMG_ROOT_DISK.'avatars/'.basename($obj->avatarpath).")");
			exit;
		}
	}
	unset($r);
	umask($umask);
	print_status('Finished Importing Avatars');

/* Import Users */
	q("DELETE FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."users");
	q("DELETE FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."buddy");
	q("DELETE FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."user_ignore");
	q("DELETE FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."custom_tags");
	
	$r = Q2("SELECT *,user.userid AS userid FROM user LEFT JOIN userfield ON user.userid=userfield.userid LEFT JOIN customavatar ON user.userid=customavatar.userid");
	print_status('Importing '.db_count($r).' Users');
	while( $obj = db_rowobj($r) ) {
		if( q_singleval("SELECT id FROM ".$DBHOST_TBL_PREFIX."users WHERE login='".addslashes($obj->username)."' OR email='".addslashes($obj->email)."'") ) {
			print_status("\t\tWARNING: Cannot import user ".$obj->username.", user with this email and/or login already exists");
			continue;
		}
	
		$ppg = $obj->maxposts > 0 ? $obj->maxposts : $VB2SET['maxposts'];
	
		$avatar_approved = 4194304;
		if( $obj->avatarid && q_singleval("SELECT id FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."avatar WHERE id=".$obj->avatarid) ) {
			$avatar = $obj->avatarid;
			$avatar_approved = 8388608;
		} else if( !empty($obj->avatardata) ) {
			$fp = fopen($IMG_ROOT_DISK.'custom_avatars/'.$obj->userid, "wb");
			fwrite($fp, $obj->avatardata);
			fclose($fp);
			$avatar = 0;
			$avatar_approved = 8388608;
		} else {
			$avatar = 0;
		}
		
		$users_opt = 131072|262144|$avatar_approved;
		if ($obj->showemail) $users_opt |= 1;
		if ($obj->emailnotification) $users_opt |= 2|4;
		if (!$obj->adminemail) $users_opt |= 8;
		if ($obj->invisible) $users_opt |= 32768;
		if ($obj->options&SHOWSIGNATURES) $users_opt |= 4096;
		if ($obj->options&SHOWAVATARS) $users_opt |= 8192;

		q("INSERT INTO ".$GLOBALS['DBHOST_TBL_PREFIX']."users (
			id,
			login,
			alias,
			passwd,
			email,
			home_page,
			icq,
			aim,
			yahoo,
			join_date,
			bday,
			posts_ppg,
			location,
			interests,
			occupation,
			bio,
			sig,
			last_visit,
			referer_id,
			avatar,
			users_opt,
			conf_key
			)
			VALUES(
			".$obj->userid.",
			'".addslashes($obj->username)."',
			'".addslashes($obj->username)."',
			'".addslashes($obj->password)."',
			'".addslashes($obj->email)."',
			".ssn($obj->homepage).",
			".intval($obj->icq).",
			'".addslashes($obj->aim)."',
			'".addslashes($obj->yahoo)."',
			".intval($obj->joindate).",
			".intval(str_replace('-', '', $obj->birthday)).",
			".intval($ppg).",
			'".addslashes($obj->field2)."',
			'".addslashes($obj->field3)."',
			'".addslashes($obj->field4)."',
			'".addslashes($obj->field1)."',
			'".addslashes(vb2tofudcode($obj->signature))."',
			".intval($obj->lastvisit).",
			".intval($obj->referrerid).",
			".$avatar.",
			".$users_opt.",
			'".md5(get_random_value(64))."'
			)
		");
		
		/* Deal with the user's buddies & ignores */
		if( !empty($obj->buddylist) ) {
			$bl = array();
			$bl = explode(' ', $obj->buddylist);
			while( list(,$v) = each($bl) ) {
				$v = intval(trim($v));
				q("INSERT INTO ".$GLOBALS['DBHOST_TBL_PREFIX']."buddy (bud_id,user_id) VALUES(".$v.", ".$obj->userid.")");
			}
		}
		
		if( !empty($obj->ignorelist) ) {
			$il = array();
			$il = explode(' ', $obj->ignorelist);
			while( list(,$v) = each($il) ) {
				$v = intval(trim($v));
				q("INSERT INTO ".$GLOBALS['DBHOST_TBL_PREFIX']."user_ignore (ignore_id,user_id) VALUES(".$v.", ".$obj->userid.")");
			}
		}
		
		/* Deal with custom titles */
		if( $obj->customtitle ) 
			q("INSERT INTO ".$GLOBALS['DBHOST_TBL_PREFIX']."custom_tags (name,user_id) VALUES('".addslashes($obj->usertitle)."',".$obj->userid.")");
	}
	unset($r);
	print_status('Finished Importing Users');

/* Import Global Permissions for unregged & regged users */

$group_map = array(
'canview'=>1|262144,
'canview'=>2,
'canpostnew'=>4,
'canreplyothers'=>8,
'caneditpost'=>16,
'candeletethread'=>32,
'auth_sticky'=>64,
'canvote'=>512,
'canpostpoll'=>128,
'canopenclose'=>4096,
'canmove'=>8192,
'canthreadrate'=>1024,
'canpostattachment'=>256
);

	q("DELETE FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."groups WHERE id>2");
	q("DELETE FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."group_cache");
	q("DELETE FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."group_members");
	q("DELETE FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."group_resources");
	
	print_status('Importing Permissions for all anonymous & registered users');
	$r = Q2("select * from usergroup where usergroupid<3 ORDER BY usergroupid");
	while( $obj = db_rowobj($r) ) {
		$opt = 0;
		foreach ($group_map as $k => $v) {
			if ($obj->$k) {
				$opt |= $v;
			}
		}
		q("UPDATE ".$GLOBALS['DBHOST_TBL_PREFIX']."groups SET groups_opt=".$opt." WHERE id=".$obj->usergroupid);
	}
	unset($r);

/* Import Categories */
	q("DELETE FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."cat");

	$r = Q2("SELECT * FROM forum WHERE parentid=-1 ORDER BY displayorder");
	print_status('Importing '.db_count($r).' Categories');
	$i=1;
	while( $obj = db_rowobj($r) ) {
		q("INSERT INTO ".$GLOBALS['DBHOST_TBL_PREFIX']."cat (
			id,
			name,
			description,
			view_order			
			)
			VALUES(
			".$obj->forumid.",
			'".addslashes($obj->title)."',
			'".addslashes($obj->description)."',
			".$i++."
			)
		");	
	}
	unset($r);
	print_status('Finished Importing Categories');
	
/* Import Forums */
	q("DELETE FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."forum");
	$r = Q2("SELECT * FROM forum ORDER BY displayorder,forumid");
	print_status('Importing '.db_count($r).' Forums');
	$i=1;
	while( $obj = db_rowobj($r) ) {
		$pa = explode(',', $obj->parentlist);
		$pid = $pa[count($pa)-2];
		$opt = 0;
		
		if ($obj->allowhtml)  {

		} else if ($obj->allowbbcode) {
			$opt |= 16;
		} else {
			$opt |= 8;
		}

		if ($obj->moderatenew) {
			$opt |= 2;
		}

		$frm = new fud_forum;
		$frm->cat_id = $pid;
		$frm->name = addslashes($obj->title);
		$frm->descr = addslashes($obj->description);
		$frm->tag_style = $code;
		$frm->forum_opt = $opt;
		$id = $frm->add($i);
				
		$gid = q_singleval("SELECT id FROM ".$DBHOST_TBL_PREFIX."groups WHERE res='forum' AND res_id=".$id);
		q("UPDATE ".$DBHOST_TBL_PREFIX."groups SET res_id=".$obj->forumid." WHERE id=".$gid);
		q("UPDATE ".$DBHOST_TBL_PREFIX."group_resources SET resource_id=".$obj->forumid." WHERE group_id=".$gid);
		q("UPDATE ".$DBHOST_TBL_PREFIX."forum SET id=".$obj->forumid." WHERE id=".$id);
	}
	unset($r);
	print_status('Finished Importing Forums');

/* Import Moderators */
	q("DELETE FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."mod");
	$r = Q2("select userid,forumid from moderator");
	print_status('Importing '.db_count($r).' Moderators');
	while( $obj = db_rowobj($r) ) {
		q("INSERT INTO ".$GLOBALS['DBHOST_TBL_PREFIX']."mod (user_id,forum_id) VALUES(".$obj->userid.",".$obj->forumid.")");
	}
	unset($r);
	print_status('Finished Importing Moderators');

/* Import Threads */
	q("DELETE FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."thread");
	$r = Q2("SELECT * FROM thread");
	print_status('Importing '.db_count($r).' Threads');
	while( $obj = db_rowobj($r) ) {
		$opt = $obj->sticky ? 2 : 0;
		if (!$obj->open) {
			$opt |= 1;
		}
		
		q("INSERT INTO ".$DBHOST_TBL_PREFIX."thread (
			id,
			forum_id,
			last_post_id,
			views,
			thread_opt
			)
			VALUES(
			".$obj->threadid.",
			".$obj->forumid.",
			".$obj->lastpost.",
			".$obj->views.",
			".$thread_opt."
			)
		");
	}
	unset($r);
	print_status('Finished Importing Threads');
	
/* Import Messages */
	$ffid = q_singleval("SELECT id FROM ".$DBHOST_TBL_PREFIX."forum LIMIT 1");	

	q("DELETE FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."msg");
	$r = Q2("SELECT * FROM post ORDER BY threadid,postid");
	print_status('Importing '.db_count($r).' Messages');
	$th_id = 0;
	while( $obj = db_rowobj($r) ) {
		if( $th_id != $obj->threadid ) {
			q("UPDATE ".$GLOBALS['DBHOST_TBL_PREFIX']."thread SET root_msg_id=".$obj->postid." WHERE id=".$obj->threadid);
			$th_id = $obj->threadid;
		}
		
		$opt = $obj->showsignature ? 1 : 0;

		$obj->pagetext = tags_to_html($obj->pagetext);
		if ($obj->allowsmilie) {
			$obj->pagetext = smiley_to_post($obj->pagetext);
		} else {
			$opt |= 2;
		}
		$fileid = write_body($obj->pagetext, $len, $off, $ffid);
		
		q("INSERT INTO ".$DBHOST_TBL_PREFIX."msg (
			id,
			thread_id,
			poster_id,
			post_stamp,
			update_stamp,
			updated_by,
			subject,
			apr,
			msg_opt,
			ip_addr,
			foff,
			length,
			file_id
			)
			VALUES(
			".intval($obj->postid).",
			".intval($obj->threadid).",
			".intval($obj->userid).",
			".intval($obj->dateline).",
			".(int)$obj->editdate.",
			".(int)$obj->edituserid.",
			'".addslashes($obj->title)."',
			1,
			".$opt.",
			'".$obj->ipaddress."',
			".(int)$off.",
			".(int)$len.",
			".$fileid."
			)
		");
	}
	unset($r);
	print_status('Finished Importing Messages');

	
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."poll");
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."poll_opt");
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."poll_opt_track");
	
	$r = Q2("SELECT poll.*,thread.threadid,postuserid FROM thread INNER JOIN poll ON thread.pollid=poll.pollid");
	
	print_status('Importing '.db_count($r).' Polls');
	
	while ( $obj = db_rowobj($r) ) {	
		$vote_length = ( $obj->active ) ? 0 : $obj->vote_start;
		
		q("INSERT INTO ".$DBHOST_TBL_PREFIX."poll (
			id,
			name,
			owner,
			creation_date,
			expiry_date
			)
			VALUES(
			".$obj->pollid.",
			'".addslashes($obj->question)."',
			".$obj->postuserid.",
			".$obj->dateline.",
			".$vote_length."
		)");
		
		$mid = q_singleval("SELECT root_msg_id FROM ".$DBHOST_TBL_PREFIX."thread WHERE id=".$obj->threadid);
		q("UPDATE ".$DBHOST_TBL_PREFIX."msg SET poll_id=".$obj->pollid." WHERE id=".$mid);
		
		$opts = array();
		$vots = array();
		
		$opts = explode('|||', $obj->options);
		$vots = explode('|||', $obj->votes);
		
		$n_opt = count($opts);
		
		while ( list($k,$v) = each($opts) ) {
			q("INSERT INTO ".$DBHOST_TBL_PREFIX."poll_opt (
				poll_id,
				name,
				count
				)
				VALUES(
				".$obj->pollid.",
				'".addslashes($v)."',
				".$vots[$k]."
			)");
		}
	}
	unset($r);
	
	$r = Q2("SELECT * FROM pollvote");
	while( $obj = db_rowobj($r) ) {
		q("INSERT INTO ".$DBHOST_TBL_PREFIX."poll_opt_track (
			poll_id,
			user_id
			)
			VALUES(
			".$obj->pollid.",
			".$obj->userid."
		)");
	}
	unset($r);
	print_status('Finished Importing Polls');
	
/* Import File Attachments */

	q("DELETE FROM ".$DBHOST_TBL_PREFIX."attach");	
	
	$r = Q2("SELECT attachment.*, post.postid FROM post INNER JOIN attachment ON post.attachmentid=attachment.attachmentid");
	print_status('Importing '.db_count($r).' File Attachments');
	$umask = umask(0177);
	while ( $obj = db_rowobj($r) ) {
		q("INSERT INTO ".$DBHOST_TBL_PREFIX."attach (
			id,
			original_name,
			owner,
			attach_opt,
			message_id,
			dlcount,
			mime_type,
			location,
			fsize
			)
			VALUES(
			".$obj->attachmentid.",
			'".addslashes($obj->filename)."',
			".intval($obj->userid).",
			0,
			".$obj->postid.",
			".intval($obj->counter).",
			".intval(get_mime_by_ext(substr(strrchr($obj->filename, '.'), 1))).",
			'".$FILE_STORE.$obj->attachmentid.".atch',
			".filesize($FILE_STORE.$obj->attachmentid.'.atch')."
		)");
		
		$fp = fopen($FILE_STORE.$obj->attachmentid.'.atch', "wb");
		fwrite($fp, $obj->filedata);
		fclose($fp);
	}
	unset($r);
	print_status('Finished Importing File Attachments');
	
/* Import Thread Subscriptions */
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."thread_notify");
	$r = Q2("SELECT * FROM subscribethread");	
	print_status('Importing '.db_count($r).' Thread Subscriptions');
	while ( $obj = db_rowobj($r) ) {
		q("INSERT INTO ".$DBHOST_TBL_PREFIX."thread_notify (
			user_id,
			thread_id
			) 
			VALUES(
			".$obj->userid.",
			".$obj->threadid."
		)");
	}
	unset($r);
	print_status('Finished Importing Thread Subscriptions');
	
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."forum_notify");
	$r = Q2("SELECT * FROM subscribeforum");	
	print_status('Importing '.db_count($r).' Forum Subscriptions');
	while ( $obj = db_rowobj($r) ) {
		q("INSERT INTO ".$DBHOST_TBL_PREFIX."forum_notify (
			user_id,
			forum_id
			) 
			VALUES(
			".$obj->userid.",
			".$obj->forumid."
		)");
	}
	unset($r);
	print_status('Finished Importing Forum Subscriptions');
	
/* Importing User Ranks */
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."level");
	$r = Q2("select * from usertitle");
	print_status('Importing '.db_count($r).' User Ranks');
	while ( $obj = db_rowobj($r) ) {
		q("INSERT INTO ".$DBHOST_TBL_PREFIX."level (
			name,
			post_count
			)
			VALUES(
			'".addslashes($obj->title)."',
			".intval($obj->minposts)."
		)");	
	}
	unset($r);
	print_status('Finished Importing User Ranks');

/* Import Private Messages */
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."pmsg");
	$r = Q2("select * from privatemessage");
	print_status('Importing '.db_count($r).' Private Messages');
	while( $obj = db_rowobj($r) ) {
		list($off, $len) = write_pmsg_body(vb2tofudcode($obj->message));
	
		$folder = $obj->fromuserid == $obj->touserid ? 3 : 1;
		$opt = 16;
		if ($obj->showsignature) $opt |= 1;
		if ($obj->receipt) $opt |= 4;
	
		q("INSERT INTO ".$DBHOST_TBL_PREFIX."pmsg (
			ouser_id,
			duser_id,
			post_stamp,
			read_stamp,
			mailed,
			fldr,
			subject,
			foff,
			length,
			pmsg_opt
			)
			VALUES(
			".intval($obj->fromuserid).",
			".intval($obj->touserid).",		
			".intval($obj->dateline).",
			".intval($obj->readtime).",
			'Y',
			".$folder.",
			'".addslashes($obj->title)."',
			".$off.",
			".$len.",
			".$opt."
		)");
	}
	unset($r);
	print_status('Finished Importing Private Messages');
	
/* Import Allowed File Extensions */

	q("DELETE FROM ".$DBHOST_TBL_PREFIX."ext_block");
	$ext = explode(' ', $VB2SET['attachextensions']);
	print_status('Importing '.count($ext).' Allowed File Extensions');
	while( list(,$v) = each($ext) )
		q("INSERT INTO ".$DBHOST_TBL_PREFIX."ext_block (ext) VALUES('".addslashes($v)."')");
	unset($ext);
	print_status('Finished Importing Allowed File Extensions');
	
/* Import Banned Email Address' */
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."email_block");
	$email = explode(' ', $VB2SET['banemail']);
	print_status('Importing '.count($email).' Banned E-mail address\'');
	while( list(,$v) = each($email) ) 
		q("INSERT INTO ".$DBHOST_TBL_PREFIX."email_block (string) VALUES('".addslashes($v)."')");		
	unset($email);
	print_status('Finished Importing Banned E-mail address\'');
	
/* Import Banned IP Maskes */
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."ip_block");
	$ip = explode(' ', $VB2SET['banip']);
	print_status('Importing '.count($ip).' Banned IP masks');
	while( list(,$v) = each($ip) ) {
		$tmp = array();
		$tmp = explode('.', $v);
		for( $i=0; $i<4; $i++ ) 
			if( empty($tmp[$i]) ) $tmp[$i] = 255;
			
		q("INSERT INTO ".$DBHOST_TBL_PREFIX."ip_block (
			ca,
			cb,
			cc,
			cd
			)
			VALUES(
			".$tmp[0].",
			".$tmp[1].",	
			".$tmp[2].",
			".$tmp[3]."
		)");		
	}	
	unset($ip);
	print_status('Finished Importing Banned IP masks');

/* Import Blocked Words */
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."replace");
	$rp_words = explode(' ', $VB2SET['censorwords']);
	print_status('Importing '.count($rp_words).' Censored Words');
	while( list(,$v) = each($rp_words) ) {
		$r = str_repeat($VB2SET['censorchar'], strlen($v));
		q("INSERT INTO ".$DBHOST_TBL_PREFIX."replace (
			type,
			replace_str,
			with_str
			)
			VALUES(
			'PERL',
			'/".addslashes(preg_quote($v))."/i',
			'".addslashes($r)."'
		)");
		
	}
	print_status('Finished Importing Censored Words');
	unset($rp_words);

/* Import Administrators */
	$r = Q2("SELECT userid FROM usergroup INNER JOIN user ON user.usergroupid=usergroup.usergroupid WHERE cancontrolpanel=1");
	print_status('Importing '.db_count($r).' Administrators');
	while( list($uid) = db_rowarr($r) ) 
		q("UPDATE ".$DBHOST_TBL_PREFIX."users SET users_opt=users_opt|1048576 WHERE id=".$uid);
	unset($r);
	print_status('Finished Importing Administrators');

/* Import VBulletin Settings */
	print_status('Importing Forum Settings');
	
	$global_config = read_global_config();
	
	change_global_val('FORUM_ENABLED', INT_yn($VB2SET['bbactive']), $global_config);
	change_global_val('FORUM_TITLE', $VB2SET['bbtitle'], $global_config);
	
	if( $VB2SET['avatarenabled'] ) {
		if( $VB2SET['avatarallowupload'] && $VB2SET['avatarallowwebsite'] ) 
			$avtr = 'ALL';
		else if ( $VB2SET['avatarallowupload'] ) 
			$avtr = 'BUILT_UPLOAD';
		else if( $VB2SET['avatarallowwebsite'] )
			$avtr = 'BUILT_URL';
		else
			$avtr = 'BUILT';
	}	
	else
		$avtr = 'OFF';
	
	change_global_val('CUSTOM_AVATARS', $avtr, $global_config);
	change_global_val('CUSTOM_AVATAR_MAX_DIM', $VB2SET['avatarmaxdimension'].'x'.$VB2SET['avatarmaxdimension'], $global_config);
	change_global_val('CUSTOM_AVATAR_MAX_SIZE', $VB2SET['avatarmaxsize'], $global_config);
	
	change_global_val('SESSION_TIMEOUT', $VB2SET['cookietimeout'], $global_config);
	
	change_global_val('ADMIN_EMAIL', $VB2SET['webmasteremail'], $global_config);
	change_global_val('NOTIFY_FROM', $VB2SET['webmasteremail'], $global_config);
	
	change_global_val('PM_ENABLED', INT_yn($VB2SET['enablepms']), $global_config);
	change_global_val('PRIVATE_MSG_SMILEY', INT_yn($VB2SET['privallowsmilies']), $global_config);
	change_global_val('PRIVATE_IMAGES', INT_yn($VB2SET['privallowbbimagecode']), $global_config);
	change_global_val('MAX_PMSG_FLDR_SIZE', ($VB2SET['pmquota']*1024), $global_config);
	
	if( $VB2SET['privallowhtml'] ) 
		$code = 'HTML';
	else if( $VB2SET['privallowbbcode'] ) 
		$code = 'ML';
	else
		$code = 'N';	
	
	change_global_val('PRIVATE_TAGS', $code, $global_config);
	
	change_global_val('ALLOW_SIGS', INT_yn($VB2SET['allowsignatures']), $global_config);
	change_global_val('MAX_SMILIES_SHOWN', $VB2SET['smtotal'], $global_config);
	change_global_val('EMAIL_CONFIRMATION', INT_yn($VB2SET['verifyemail']), $global_config);
	change_global_val('DISABLED_REASON', $VB2SET['bbclosedreason'], $global_config);
	change_global_val('COPPA', INT_yn($VB2SET['usecoppa']), $global_config);
	change_global_val('POSTS_PER_PAGE', $VB2SET['maxposts'], $global_config);
	change_global_val('THREADS_PER_PAGE', $VB2SET['maxthreads'], $global_config);
	change_global_val('WORD_WRAP', $VB2SET['wordwrap'], $global_config);
	change_global_val('FLOOD_CHECK_TIME', $VB2SET['floodchecktime'], $global_config);
	change_global_val('ALLOW_EMAIL', INT_yn($VB2SET['enableemail']), $global_config);
	change_global_val('MEMBER_SEARCH_ENABLED', INT_yn($VB2SET['enablememberlist']), $global_config);
	change_global_val('FORUM_SEARCH', INT_yn($VB2SET['enablesearches']), $global_config);
	change_global_val('ACTION_LIST_ENABLED', INT_yn($VB2SET['showforumusers']), $global_config);
	change_global_val('LOGEDIN_LIST', INT_yn($VB2SET['showforumusers']), $global_config);
	change_global_val('MEMBERS_PER_PAGE', $VB2SET['searchperpage'], $global_config);
	change_global_val('THREAD_MSG_PAGER', $VB2SET['pagenavpages'], $global_config);
	change_global_val('SHOW_EDITED_BY', INT_yn($VB2SET['showeditedby']), $global_config);
	change_global_val('EDITED_BY_MOD', INT_yn($VB2SET['showeditedbyadmin']), $global_config);
	change_global_val('EDIT_TIME_LIMIT', $VB2SET['edittimelimit'], $global_config);
	change_global_val('SITE_HOME_PAGE', $VB2SET['homeurl'], $global_config);
	
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
	
	print_status("\n\nConversion of Vbulletin to FUDforum2 has been completed\n Time Taken: ".$time_taken."\n");
	print_status("To complete the process run the consistency checker at:");
	print_status($GLOBALS['WWW_ROOT']."adm/consist.php");
	if( isset($HTTP_SERVER_VARS['REMOTE_ADDR']) ) echo '</pre>';
?>