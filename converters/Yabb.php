<?php
/***************************************************************************
* copyright            : (C) 2001-2004 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: Yabb.php,v 1.9 2004/01/04 16:38:24 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it 
* under the terms of the GNU General Public License as published by the 
* Free Software Foundation; either version 2 of the License, or 
* (at your option) any later version.
***************************************************************************/

	@set_time_limit(6000);
/*
 *	Usage Instructions
 *
 *	1) Copy this script into the main web directory of FUDforum 2.
 *	2) Change the value of the value of the $YABB_CONFIG_FILE variable
 *	   to the full path of the YaBB's Settings.pl file
 *	3) Run this script via the shell or the web.
 *	4) Once the script successfuly runs, run the consitency checker.
 *	5) Voila, you're done.
*/

	$YABB_CONFIG_FILE = "";

/* DO NOT MODIFY BEYOND THIS POINT */

	$start_time = time();

	if( isset($HTTP_SERVER_VARS['REMOTE_ADDR']) ) echo '<pre>';
	if( !isset($DBHOST_TBL_PREFIX) ) $DBHOST_TBL_PREFIX = $MYSQL_TBL_PREFIX;

function filetomem($fn)
{
        $fp = fopen($fn, 'rb');
        $st = fstat($fp);
	$size = isset($st['size']) ? $st['size'] : $st[7];
	$str = fread($fp, $size);
        fclose($fp);
                                
	return $str;
}

function print_status($str)
{
	echo $str."\n";
	flush();
}

function parse_yabb_config($path)
{
	$data = filetomem($path);
	$pos = 0;
	while( ($pos = strpos($data, '$', $pos)) !== FALSE ) {
		$pos++;
	
		if( ($epos = strpos($data, '=', $pos)) === FALSE ) continue;
		
		$var_name = trim(substr($data, $pos, $epos-$pos));
		$pos = $epos+1;

		if( ($epos = strpos($data, ';', $pos)) === FALSE ) continue;
		
		$value = ltrim(substr($data, $pos, $epos-$pos));
		if( $value[0] == '"' && $value[strlen($value)-1] == '"' ) $value = substr($value, 1, -1);
		
		$config[$var_name] = $value;
		
		$pos = $epos+1;
	}
	return $config;
}

function yabbctofudcode($str)
{
	if( preg_match('!\[(img|quote).*?\]!is', $str) )
		$str = preg_replace('!\[(img|quote).*?\]!is', '[\1]', $str);
		
	if( preg_match('!\[(move|shadow|glow|flash|hr|tt|sup|sub).*?\]!is', $str) ) 	
		$str = preg_replace('!\[(move|shadow|glow|flash|hr|tt|sup|sub).*?\]!is', '', $str);
	
	if( preg_match('!\[/(move|shadow|glow|flash|tt|sup|sub)\]!is', $str) )
		$str = preg_replace('!\[/(move|shadow|glow|flash|tt|sup|sub)\]!is', '', $str);
	
	if( preg_match('!\[(left|center|right)\]!is', $str) ) 
		$str = preg_replace('!\[(left|center|right)\]!is', '[align=\1]', $str);
		
	if( preg_match('!\[/(left|center|right)\]!is', $str) )	
		$str = preg_replace('!\[/(left|center|right)\]!is', '[/align]', $str);
	
	if( preg_match('!\[(/)?url\]!is', $str) ) 
		$str = preg_replace('!\[(/)?url\]!is', '[\1url]', $str);
	
	reset($GLOBALS['SML_CONV']);
	while( list($k,$v) = each($GLOBALS['SML_CONV']) ) {
		if( strpos($str, $k) ) $str = str_replace($k, $v, $str);
	}
	
	reverse_FMT($str);
	reverse_nl2br($str);
	
	return smiley_to_post(tags_to_html($str));
}

function mdytostamp($str)
{
	list($m,$d,$y) = explode('/', trim($str));
	return mktime(0,0,0,$m,$d,$y);
}

function INT_yn($s)
{
	return (empty($s)?'N':'Y');
}

function yabbstamp($str)
{
	$dt = explode(' ', $str);
	
	list($m,$d,$y) = explode('/', trim($dt[0]));
	list($h,$mi,$s) = explode(':', trim($dt[sizeof($dt)-1]));
	
	return mktime($h,$mi,$s,$m,$d,$y);
}

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
	fud_use('glob.inc', TRUE);

	$YABB_CONFIG_FILE = realpath($YABB_CONFIG_FILE);
	
	if( empty($YABB_CONFIG_FILE) ) 
		exit("YABB_CONFIG_FILE is blank, cannot proceed!\n Change the value of the value of the \$YABB_CONFIG_FILE variable to the full path of the YaBB's Settings.pl file.\n");

	if( !file_exists($YABB_CONFIG_FILE) )
	    	exit("YABB_CONFIG_FILE ($YABB_CONFIG_FILE) does not contain valid path to YaBB's Settings.pl file.\n");
	
/* Parse YaBB configuration file */
	$YABB_CFG = parse_yabb_config($YABB_CONFIG_FILE);	
	$YABB_DIR = dirname($YABB_CONFIG_FILE);
	
	$cur_dir = getcwd();
	chdir($YABB_DIR);

/* Import YaBB Avatars */
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."avatar");
	
	print_status('Import Avatars');
	$avatar_n = 0;
	
	chdir($YABB_CFG['facesdir']);
	$dir = opendir('.');
	$old_umask=umask(0);
	
	readdir($dir); readdir($dir);
	while( $file = readdir($dir) ) {
		if( @is_dir($file) || @is_link($file) )	continue;
		switch( strtolower(substr(strrchr($file, '.'),1)) )
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
		        	$avatar_n++;
		        	break;
		} 
	}
	chdir($YABB_DIR);
	umask($old_umask);
	
	print_status('Finished Importing ('.$avatar_n.') Avatars');

/* Import YaBB Smilies */

	print_status('Importing Smilies');
	/* appear to be hardcoded 
	 *
	 * Changing tags:
	 * >:( -> :-x
	 * ??? -> :???:
	 * ::) -> :roll:
	 * :-[ -> :blush:
	 * :-/ -> :-|
	 * :'( -> :((
	 *
	 *
	 * Adding Smilies
	 *
	 * ;D -> Cheesy
	 * :-* -> Kiss
	 *
	*/
	
	$GLOBALS['SML_CONV'] = array(
		'>:(' => ' :-x ',
		'???' => ' :???: ',
		'::)' => ' :roll: ',
		':-[' => ' :blush: ',
		':-/' => ' :-| ',
		':\'(' => ' :( '
	);
	
	$old_umask = umask(0);
	
	chdir($YABB_CFG['facesdir'].'/../');
	
	if( @file_exists("kiss.gif") ) {
		if( !copy('kiss.gif', $IMG_ROOT_DISK.'smiley_icons/kiss.gif') ) {
			print_status("Coulnd't copy smiley image (".getcwd()."/kiss.gif) to (".$IMG_ROOT_DISK."smiley_icons/kiss.gif)");
			exit;
		}
		@chmod($IMG_ROOT_DISK.'smiley_icons/kiss.gif', 0666);
		q("INSERT INTO ".$DBHOST_TBL_PREFIX."smiley (img,code,descr) VALUES('kiss.gif',':-*','Kiss')");
	}	
	
	if( @file_exists("cheesy.gif") ) {
		if( !copy('cheesy.gif', $IMG_ROOT_DISK.'smiley_icons/cheesy.gif') ) {
			print_status("Coulnd't copy smiley image (".getcwd()."/cheesy.gif) to (".$IMG_ROOT_DISK."smiley_icons/cheesy.gif)");
			exit;
		}
		@chmod($IMG_ROOT_DISK.'smiley_icons/cheesy.gif', 0666);
		q("INSERT INTO ".$DBHOST_TBL_PREFIX."smiley (img,code,descr) VALUES('cheesy.gif',';D','Cheesy')");
	}	
	
	chdir($YABB_DIR);
	
	umask($old_umask);

	print_status('Finished Importing Smilies');

/* Import YaBB Users */
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."users");
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."custom_tags");
	
	print_status('Importing Users');
	$n_users = 0;
	
	chdir($YABB_CFG['memberdir']);
	$members_arr = file('memberlist.txt');
	while( list(,$v) = each($members_arr) ) {
		$uid = trim($v);
		$user_data = file($uid.'.dat');
		$u_name = trim($user_data[1]);
		$u_email = trim($user_data[2]);		
		
		if( bq("SELECT id FROM ".$DBHOST_TBL_PREFIX."users WHERE login='".addslashes($u_name)."' OR email='".$u_email."'") ) {
			print_status("\tuser: ".$obj->username);
			print_status("\t\tWARNING: Cannot import user ".$u_name.", user with this email and/or login already exists");
			continue;
		}
		
		$from = trim($user_data[15]);
		if( @file_exists($u_name.'.flg') ) 
			$from .= ' '.trim(filetomem($u_name.'.flg'));
		
		if( ($sig = trim($user_data[5])) ) {
			$append_sig = 'Y';
			$sig = yabbctofudcode($sig);
		}
		else
			$append_sig = 'N';
		
		switch( strtolower(trim($user_data[11])) )
		{
			case 'male':
				$gender = 'MALE';
				break;
			case 'female':
				$gender = 'FEMALE';	
				break;
			default:
				$gender = 'UNSPECIFIED';	
		}
		
		if( ($bday = trim($user_data[16])) ) {
			$tmp = explode('/', $bday);
			if( $tmp[2] < 5 )
				$tmp[2] = '20'.$tmp[2];
			else if( $tmp[2] < 100 )
				$tmp[2] = '19'.$tmp[2];	
				
			$bday = $tmp[2].$tmp[0].$tmp[1];
		}
		else
			$bday=0;
		
		
		$avatar_loc = trim($user_data[13]);
		if( $avatar_loc && $avatar_loc != 'blank.gif' ) {
			$avatar_approved='Y';
		}
		else {
			$avatar_loc = '';
			$avatar_approved = 'NO';
		}
		
		q("INSERT INTO ".$DBHOST_TBL_PREFIX."users 
			(
				login,
				alias,
				passwd,
				join_date,
				append_sig,
				email,
				icq,
				location,
				sig,
				aim,
				yahoo,
				home_page,
				bday,
				gender,
				avatar_approved,
				avatar_loc,
				display_email,
				email_conf,
				coppa
			)
			VALUES (
				'".addslashes($u_name)."',
				'".addslashes($u_name)."',
				'".md5(trim($user_data[0]))."',
				".intzero(mdytostamp($user_data[14])).",
				'".$append_sig."',
				'".$u_email."',
				".intnull(trim($user_data[8])).",
				'".addslashes($from)."',
				'".addslashes($sig)."',
				'".addslashes(trim($user_data[9]))."',
				'".addslashes(trim($user_data[10]))."',
				'".addslashes(trim($user_data[4]))."',
				".intzero($bday).",
				'".$gender."',
				'".$avatar_approved."',
				'".$avatar_loc."',
				'".INT_yn(!trim($user_data[19]))."',
				'Y',
				'N'
				)"
			);
			
		$users_db[$uid] = db_lastid();
		
		if( ($c_tag = trim($user_data[7])) ) {
			q("INSERT INTO ".$DBHOST_TBL_PREFIX."custom_tags (name,user_id) VALUES('".addslashes($c_tag)."',".$users_db[$uid].")");
			
			if( $c_tag == 'Administrator' ) q("UPDATE ".$DBHOST_TBL_PREFIX."users SET is_mod='A' WHERE id=".$users_db[$uid]);
		}	
		
		$n_users++;
			
	}
	chdir($YABB_DIR);
	
	unset($members_arr); 	unset($sig);	unset($form);	unset($u_email);
	unset($user_data);	unset($bday);	unset($gender); unset($u_name);
	unset($uid);		unset($avatar_approved);	unset($avatar_loc);		
	
	print_status('Finished Importing ('.$n_users.') Users');
	
/* Import YaBB Categories */
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."cat");
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."group_resources");
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."groups WHERE id>2");
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."forum");
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."group_members");

	print_status('Importing Categories '.db_count($r));
	
	$categories = file($YABB_CFG['vardir'].'/cat.txt');
	$i=1;
	while( list(,$v) = each($categories) ) {
		$catid = trim($v);
		$cat_data = filetomem($YABB_CFG['boardsdir'].'/'.$catid.'.cat');
		$cat_name = trim(substr($cat_data, 0, strpos($cat_data, "\n")));
		
		$cat_data = str_replace("\r", "", $cat_data);
		$pos = strpos($cat_data, "\n")+1;
		$pos = strpos($cat_data, "\n",$pos)+1;
		
		$forums[$i] = explode("\n", trim(substr($cat_data, $pos)));
		$forums[$i][catname_id] = $catid;
		
		q("INSERT INTO ".$DBHOST_TBL_PREFIX."cat (name,view_order) VALUES('".addslashes($cat_name)."',".$i++.")");
	}
	unset($categories); unset($catid); unset($cat_data); unset($cat_name);
	
	print_status('Finished Importing ('.$i.') Categories');
	
/* Import YaBB Forums */
	print_status('Importing Forums');
	$forum_count = 0;
	$forum_list = array();
	$old_cat=0;
	
	chdir($YABB_CFG['boardsdir']);
	
	while( list($k, $v) = each($forums) ) {
		if( $old_cat != $k ) {
			$i=1;
			$old_cat = $k;
		}
		
		while( list($k2, $v2) = each($v) ) {
			if( !is_numeric($k2) || !strlen($v2) ) continue;
	
			$forum_data = file($v2.'.dat');
			
			$frm = new fud_forum_adm;
			$frm->cat_id = $k;
			$frm->name = addslashes(trim($forum_data[0]));
			$frm->descr = addslashes(trim($forum_data[1]));
			$frm->view_order = $i;
			$id = $frm->add($i);
			
			$forum_mods[$id] = explode('|', trim($forum_data[2]));
			
			$forum_list[$id] = $v2;
			
			$forum_count++;
		}
	}
	unset($forum_data); unset($id); unset($forums);
	chdir($YABB_DIR);
	
	print_status('Finished Importing ('.$forum_count.') Forums');	
	
/* Import YaBB Moderators */
	print_status('Importing Moderators');
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."mod");
	
	$mod_count=0;
	while( list($k,$v) = each($forum_mods) ) {
		while( list(,$v2) = each($v) ) {
			if( empty($users_db[$v2]) ) {
				print_status("\tWARNING: Cannot add moderator ".$v2.", user does not exist");
				continue;
			}
		
			q("INSERT INTO ".$DBHOST_TBL_PREFIX."mod (user_id,forum_id) VALUES(".$users_db[$v2].",".$k.")");
			$mod_count++;
		}
	}
	unset($forum_mods);
	print_status('Finished Importing ('.$mod_count.') Moderators');
	
/* Import YaBB Threads */
	
	print_status('Importing Threads');
	$thread_count=0;
	
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."thread");
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."thread_notify");
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."thread_rate_track");
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."thread_view");
	
	chdir($YABB_CFG['boardsdir']);
		
	while( list($k,$v) = each($forum_list) ) {
		$threads_list = array();
		$threads_list = file($v.'.txt');
		$threads_list = array_reverse($threads_list);
		reset($threads_list);
		while( list(,$v2) = each($threads_list) ) {
			if( ($v2=trim($v2)) ) {
				$th_id = substr($v2, 0, strpos($v2, '|'));
				
				q("INSERT INTO ".$DBHOST_TBL_PREFIX."thread (forum_id) VALUES(".$k.")");
				
				$thread_ref[$th_id] = db_lastid();
				$thread_count++;
			}
		}
	}
	chdir($YABB_DIR);
	unset($threads_list); unset($th_id); unset($forum_list);
	
	print_status('Finished Importing ('.$thread_count.') Threads');
	
/* Import YaBB Messages */

	print_status('Importing Messages');
		
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."msg");	
	$msg_count=0;
	chdir($YABB_CFG['datadir']);
	
	while( list($k,$v) = each($thread_ref) ) {
		$thread_msg_list = array();
		$thread_msg_list = file($k.'.txt');
		for( $i=0; $i<count($thread_msg_list); $i++ ) {
			if( !trim($thread_msg_list[$i]) ) continue;
		
			$msg_data = explode('|', $thread_msg_list[$i]);
			
			$fileid = write_body(yabbctofudcode($msg_data[8]), $len, $off);
			q("INSERT INTO ".$DBHOST_TBL_PREFIX."msg
			(
				thread_id,
				poster_id,
				post_stamp,
				subject,
				approved,
				show_sig,
				smiley_disabled,
				ip_addr,
				foff,
				length,
				file_id
			)
			VALUES
			(
				".$v.",
				".intzero($users_db[$msg_data[4]]).",
				".intzero(yabbstamp($msg_data[3])).",
				'".addslashes($msg_data[0])."',
				'Y',
				'Y',
				'Y',
				'".$msg_data[7]."',
				".intzero($off).",
				".intzero($len).",
				$fileid
			)");
			
			if( !$i ) q("UPDATE ".$DBHOST_TBL_PREFIX."thread SET root_msg_id=".db_lastid()." WHERE id=".$v);
			$msg_count++;
		}
	}

	chdir($YABB_DIR);
	print_status('Finished Importing ('.$msg_count.') Messages');

/* Import YaBB User Ranks */
	
	print_status('Importing User Ranks');
	
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."level");
	
	$user_groups = file($YABB_CFG['vardir'].'/membergroups.txt');
	
	q("INSERT INTO ".$DBHOST_TBL_PREFIX."level (name,post_count) VALUES('".addslashes($user_groups[2])."',0)");
	q("INSERT INTO ".$DBHOST_TBL_PREFIX."level (name,post_count) VALUES('".addslashes($user_groups[3])."',".$YABB_CFG['JrPostNum'].")");
	q("INSERT INTO ".$DBHOST_TBL_PREFIX."level (name,post_count) VALUES('".addslashes($user_groups[4])."',".$YABB_CFG['FullPostNum'].")");
	q("INSERT INTO ".$DBHOST_TBL_PREFIX."level (name,post_count) VALUES('".addslashes($user_groups[5])."',".$YABB_CFG['SrPostNum'].")");
	q("INSERT INTO ".$DBHOST_TBL_PREFIX."level (name,post_count) VALUES('".addslashes($user_groups[6])."',".$YABB_CFG['GodPostNum'].")");
	
	print_status('Finished Importing User Ranks');

/* Import YaBB forum Options */

	print_status('Forum Settings');	
	
	$global_config = read_global_config();
	
	$YABB_CFG['webmaster_email'] = preg_replace('!^(.*?)^!', '\1', $YABB_CFG['webmaster_email']);
	
	change_global_val('ADMIN_EMAIL', $YABB_CFG['webmaster_email'], $global_config);
	change_global_val('NOTIFY_FROM', $YABB_CFG['webmaster_email'], $global_config);
	change_global_val('DISABLED_REASON', addslashes($YABB_CFG['maintenancetext']), $global_config);
	change_global_val('MEMBERS_PER_PAGE', addslashes($YABB_CFG['MembersPerPage']), $global_config);
	change_global_val('POSTS_PER_PAGE', addslashes($YABB_CFG['maxmessagedisplay']), $global_config);
	change_global_val('FLOOD_CHECK_TIME', addslashes($YABB_CFG['timeout']), $global_config);
	change_global_val('CUSTOM_AVATAR_MAX_DIM', $YABB_CFG['userpic_width'].'x'.$YABB_CFG['userpic_height'], $global_config);
	change_global_val('FORUM_TITLE', $YABB_CFG['mbname'], $global_config);
	
	if( intval($YABB_CFG['allowpics']) )
		change_global_val('CUSTOM_AVATARS', 'ALL', $global_config);
	else
		change_global_val('CUSTOM_AVATARS', 'OFF', $global_config);
	
	if( !intval($YABB_CFG['guestaccess']) )
		q("UPDATE ".$DBHOST_TBL_PREFIX."group_members SET up_VISIBLE='N', up_READ='N' WHERE user_id=0");
	else if( intval($YABB_CFG['enable_guestposting']) ) 
		q("UPDATE ".$DBHOST_TBL_PREFIX."group_members SET up_POST='Y', up_REPLY='Y', up_FILE='Y', up_SML='Y', up_IMG='Y' WHERE user_id=0");
	
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
	
	print_status("\n\nConversion of YaBB to FUDforum2 has been completed\n Time Taken: ".$time_taken."\n");
	print_status("To complete the process run the consistency checker at:");
	print_status($GLOBALS['WWW_ROOT']."adm/consist.php");
	if( isset($HTTP_SERVER_VARS['REMOTE_ADDR']) ) echo '</pre>';
?>