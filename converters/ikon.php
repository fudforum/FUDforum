<?php
/***************************************************************************
* copyright            : (C) 2001-2006 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: ikon.php,v 1.15 2006/05/26 19:51:01 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it 
* under the terms of the GNU General Public License as published by the 
* Free Software Foundation; either version 2 of the License, or 
* (at your option) any later version.
***************************************************************************/

	set_time_limit(6000);
/*
 *	Usage Instructions
 *
 *	1) Copy this script into the main web directory of FUDforum 2.
 *	2) Change the value of the value of the $IKONBOARD_CFG_PATH variable
 *	   to the full path of the Ikonboard settings directory
 *	3) Run this script via the shell or the web.
 *	4) Once the script successfuly runs, run the consitency checker.
 *	5) Voila, you're done.
*/
	
	$IKONBOARD_CFG_PATH = "";
	
/* DO NOT MODIFY BEYOND THIS POINT */	
	$start_time = time();
	
	if( !file_exists($IKONBOARD_CFG_PATH) || !is_readable($IKONBOARD_CFG_PATH) ) 
		exit("Cannot read Ikonboard config file at: '".$IKONBOARD_CFG_PATH."'<br>\n");
		
function parse_ikon_cfg()
{
	$GLOBALS['__IKON_CFG__'] = array();

	if (!preg_match_all("@'([A-Z_]+)'\s*=>\s*q!(.*?)!,@", file_get_contents($GLOBALS['IKONBOARD_CFG_PATH']), $m)) {
		exit("Failed to parse IkonBoard configuration file.\n");
	}

	foreach ($m[1] as $k => $v) {
		$GLOBALS['__IKON_CFG__'][$v] = $m[2][$k];
	}
}

function intyn($s)
{
	return (empty($s)?'N':'Y');
}

function iq($qry)
{
	if( !($r=mysql_db_query($GLOBALS['__IKON_CFG__']['DB_NAME'], $qry, $GLOBALS['__IKON_SQL__'])) ) 
		exit("Query Failure: $qry <br>\nSQL Reason: ".mysql_error($GLOBALS['__IKON_SQL__'])."<br>\n");

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

function print_status($str)
{
	echo $str."<br>\n";
}

function decode_ib3($str)
{
	return preg_replace('!&#(\d+);!e', "chr(\\1)", str_replace('&nbsp;', ' ',  reverse_fmt($str)));
}

function fetch_img($url)
{
	$ub = parse_url($url);
	
	if( empty($ub['port']) ) $ub['port'] = 80;
	if( !empty($ub['query']) ) $ub['path'] .= '?'.$ub['query'];
	
	$fs = fsockopen($ub['host'], $ub['port'], $errno, $errstr, 10);
	if( !$fs ) return;
	
	fputs($fs, "GET ".$ub['path']." HTTP/1.0\r\nHost: ".$ub['host']."\r\n\r\n");
	
	$ret_code = fgets($fs, 255);
	
	if( !strstr($ret_code, '200') ) {
		fclose($fs);
		return;
	}
	
	$img_str = '';
	
	while( !feof($fs) ) $img_str .= fread($fs, $GLOBALS['CUSTOM_AVATAR_MAX_SIZE']);
	fclose($fs);
	
	$img_str = substr($img_str, strpos($img_str, "\r\n\r\n")+4);

	$fp = FALSE;
	do {
		if ( $fp ) fclose($fp);
		$fp = fopen(($path=tempnam($GLOBALS['TMP'],getmypid())), 'ab');
	} while ( ftell($fp) );
	
	fwrite($fp, $img_str);
	fclose($fp);
	
	if( function_exists("GetImageSize") && !($size=@GetImageSize($path)) ) { unlink($path); return; }
	
	list($w,$h) = explode('x', trim($GLOBALS['__IKON_CFG__']['AV_DIMS']));
	
	if( $GLOBALS['MOGRIFY_BIN'] && ($size[0] > $w || $size[1] > $h) )
		exec($GLOBALS['MOGRIFY_BIN'].' -geometry '.trim($GLOBALS['__IKON_CFG__']['AV_DIMS']).' '.$path);

	return $path;
}


	// Include FUDforum's includes that are needed
	define('admin_form', 1);
	
	include_once "GLOBALS.php";
	fud_use('post_proc.inc');
	fud_use('db.inc');
	fud_use('imsg_edt.inc');
	fud_use('post_proc.inc');
	fud_use('rhost.inc');
	fud_use('groups.inc');
	fud_use('mime.inc');
	fud_use('rev_fmt.inc');
	fud_use('attach.inc');
	fud_use('glob.inc', TRUE);
	
	parse_ikon_cfg();
	
	// Connect to Ikon board MySQL
	if( !($GLOBALS['__IKON_SQL__'] = mysql_connect($GLOBALS['__IKON_CFG__']['DB_IP'], $GLOBALS['__IKON_CFG__']['DB_USER'], $GLOBALS['__IKON_CFG__']['DB_PASS'])) )
		exit("Couldn't open MySQL connection to Ikon board database. MySQL Reason: ".mysql_error($GLOBALS['__IKON_SQL__'])."<br>\n");	

	// Import Categories
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."cat");
	$r = iq("SELECT * FROM ib_categories ORDER BY CAT_POS");
	print_status('Importing Categories');
	$i=1;
	$cat_count=0;
	while( $obj = db_rowobj($r) ) {
		q("INSERT INTO ".$DBHOST_TBL_PREFIX."cat (id,name,view_order,description) VALUES(".$obj->CAT_ID.",'".addslashes($obj->CAT_NAME)."',".$i++.", '".addslashes($obj->CAT_DESC)."')");
		$cat_count++;
	}
	qf($r);
	print_status('Finished Importing ('.$cat_count.') Categories');
	
	// Import Group Permissions
	print_status('Importing Group Permissions');
	$perms['POST_NEW_TOPICS'] = 'p_POST';
	$perms['VIEW_BOARD'] = 'p_VISIBLE';
	$perms['REPLY_OTHER_TOPICS'] = 'p_REPLY';
	$perms['OPEN_CLOSE_TOPICS'] = 'p_LOCK';
	$perms['POST_POLLS'] = 'p_POLL';
	$perms['VOTE_POLLS'] = 'p_VOTE';
	
	// all regged users
	$obj = db_singleobj(q("SELECT * FROM ib_mem_groups WHERE id=".$GLOBALS['__IKON_CFG__']['MEMBER_GROUP']));
	$fud_perms = '';
	foreach($perms as $k => $v ) $fud_perms .= $v."='".intyn($obj->{$k})."', ";
	
	q("UPDATE ".$DBHOST_TBL_PREFIX."groups SET ".substr($fud_perms, 0, -2)." WHERE id=2");
	
	// all anon users
	$obj = db_singleobj(q("SELECT * FROM ib_mem_groups WHERE id=".$GLOBALS['__IKON_CFG__']['GUEST_GROUP']));
	$fud_perms = '';
	foreach($perms as $k => $v ) $fud_perms .= $v."='".intyn($obj->{$k})."', ";
	
	q("UPDATE ".$DBHOST_TBL_PREFIX."groups SET ".substr($fud_perms, 0, -2)." WHERE id=1");
	
	print_status('Finished Importing Group Permissions');
	
	// Import Forums
	$r = iq("SELECT * FROM ib_forum_info ORDER BY CATEGORY, FORUM_POSITION");
	print_status('Importing Forums');
	
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."groups WHERE id>2");
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."group_members");
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."group_resources");
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."group_cache");
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."forum");
	
	$cat_id=0;
	while( $obj = db_rowobj($r) ) {
		if( $cat_id != $obj->CATEGORY ) {
			$i=1;
			$cat_id = $obj->CATEGORY;
		}
		
		unset($frm);
		$frm = new fud_forum_adm;
		$frm->cat_id = $obj->CATEGORY;
		$frm->name = addslashes(htmlspecialchars(decode_ib3($obj->FORUM_NAME)));
		$frm->descr = addslashes(htmlspecialchars(decode_ib3($obj->FORUM_DESC)));
		
		if ( $obj->ALLOW_ATTACH ) {
			$frm->max_attach_size = 1024;
			$frm->max_file_attachments = 1;
		}
		
		if( $obj->FORUM_HTML ) 
			$frm->tag_style = 'HTML';
		else if( $obj->FORUM_IBC )
			$frm->tag_style = 'ML';
		else
			$frm->tag_style = 'NONE';
		
		$frm->moderated = intyn($obj->MODERATE);
		
		$id = $frm->add($i);
		q("UPDATE ".$DBHOST_TBL_PREFIX."forum SET id=$obj->FORUM_ID, view_order=".($i++)." WHERE id=$id");
		$gid = q_singleval("SELECT id FROM ".$DBHOST_TBL_PREFIX."groups WHERE res='forum' AND res_id=$id");
		q("UPDATE ".$DBHOST_TBL_PREFIX."groups SET res_id=$obj->FORUM_ID WHERE id=$gid");
		q("UPDATE ".$DBHOST_TBL_PREFIX."group_resources SET resource_id=$obj->FORUM_ID WHERE group_id=$gid");
	}
	print_status('Finished Importing ('.db_count($r).') Forums');
	qf($r);
	
	// Import Users
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."users");
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."ses");
	q("DELETE FROm ".$DBHOST_TBL_PREFIX."custom_tags");
	$THEME = q_singleval("SELECT id FROM ".$DBHOST_TBL_PREFIX."themes WHERE t_default='Y' AND enabled='Y'");
	
	$r = iq("SELECT * FROM ibf_members WHERE id>0");	
	print_status('Importing Members');
	while( $obj = db_rowobj($r) ) {
		if( 	bq("SELECT id FROM ".$DBHOST_TBL_PREFIX."users WHERE login='".addslashes($obj->name)."'") || 
			bq("SELECT id FROM ".$DBHOST_TBL_PREFIX."users WHERE email='".addslashes($obj->email)."'") 
		) 
			continue;
	
		if( strpos($obj->avatar, '://') && ($path = fetch_img($obj->avatar)) ) {
			$avatar_approved = 'Y';
			copy($path, $GLOBALS['WWW_ROOT_DISK'].'images/custom_avatars/'.$obj->id);
			unlink($path);
		}
		else {
			$obj->avatar = '';
			$avatar_approved = 'NO';
		}
	
		$obj->name = decode_ib3($obj->name);
		
		q("INSERT INTO 
			".$DBHOST_TBL_PREFIX."users (
				id,
				login,
				alias,
				passwd, 
				name, 
				email, 
				display_email, 
				notify, 
				notify_method, 
				ignore_admin, 
				email_messages,
				pm_messages, 
				icq, 
				aim,
				yahoo,
				msnm,
				append_sig, 
				posts_ppg, 
				time_zone, 
				bday, 
				invisible_mode, 
				last_visit, 
				conf_key, 
				user_image, 
				join_date, 
				location, 
				theme, 
				coppa, 
				interests,
				show_sigs,
				show_avatars,
				last_read,
				sig,
				default_view,
				home_page,
				email_conf,
				avatar_approved
			)
			VALUES (
				".$obj->id.",
				'".addslashes($obj->name)."',
				'".addslashes(htmlspecialchars($obj->name))."',
				'".$obj->password."',
				'".addslashes($obj->name)."',
				'".addslashes($obj->email)."',
				'".intyn($obj->hide_email)."',
				'Y',
				'EMAIL',
				'".yn($obj->allow_admin_mails)."',
				'Y',
				'Y',
				".intnull($obj->icq_number).",
				".ssn($obj->aim_name).",
				".ssn($obj->yahoo).",
				".ssn($obj->msnname).",
				'Y',
				'".$GLOBALS['POSTS_PER_PAGE']."',
				'".$GLOBALS['SERVER_TZ']."',
				".(int)($obj->bday_year.$obj->bday_month.$obj->bday_day).",
				'N',
				".(int)$obj->last_visit.",
				'0',
				".ssn($obj->photo).",
				".(int)$obj->joined.",
				'".addslashes($obj->location)."',
				".$THEME.",
				'N',
				".ssn($obj->interests).",
				'".intyn($obj->view_sigs)."',
				'".intyn($obj->view_avs)."',
				".(int)$obj->last_activity.",
				".ssn(preg_replace('!&#(\d+);!e', "chr(\\1)", $obj->signature)).",
				'msg',
				".ssn($obj->website).",
				'Y',
				'".$avatar_approved."'
			)
		");
		
		// import user's custom tags
		if( $obj->title ) 
			q("INSERT INTO ".$DBHOST_TBL_PREFIX."custom_tags (name,user_id) VALUES('".addslashes(htmlspecialchars(decode_ib3($obj->title)))."',".$obj->id.")");
	}
	print_status('Finished Importing ('.db_count($r).') Members');
	
	// import threads
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."thread");
	$r = iq("SELECT * FROM ib_forum_topics");
	print_status('Importing Topics');
	while( $obj = db_rowobj($r) ) {
		if( $obj->TOPIC_STATE == 'link' || $obj->TOPIC_STATE == 'moved' ) continue;
	
		$locked = $obj->TOPIC_STATE == 'open' ? 'N' : 'Y';
	
		q("INSERT INTO ".$DBHOST_TBL_PREFIX."thread (
			id,
			forum_id,
			views,
			locked,
			moved_to
			)
			VALUES (
			".$obj->TOPIC_ID.",
			".$obj->FORUM_ID.",
			".$obj->TOPIC_VIEWS.",
			'".$locked."',
			".(int)$obj->MOVED_TO."
			)
		");
	}
	print_status('Finished Importing ('.db_count($r).') Topics');
	
	// import messages
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."msg");
	$r = iq("SELECT 
			ib_forum_posts.*,
			ib_forum_topics.TOPIC_TITLE,
			ib_forum_topics.FORUM_ID,
			ibf_members.id AS aid
		FROM 
			ib_forum_posts 
		INNER JOIN ib_forum_topics ON 
			ib_forum_posts.TOPIC_ID=ib_forum_topics.TOPIC_ID
		INNER JOIN ibf_members ON
			ibf_members.misc=ib_forum_posts.AUTHOR");
			
	print_status('Importing Messages');
	while( $obj = db_rowobj($r) ) {
		$fileid = write_body(preg_replace('!&#(\d+);!e', "chr(\\1)", $obj->POST), $len, $off, $obj->FORUM_ID);
	
		q("INSERT INTO ".$DBHOST_TBL_PREFIX."msg (
			id,
			thread_id,
			poster_id,
			post_stamp,
			subject,
			show_sig,
			smiley_disabled,
			foff,
			length,
			file_id,
			ip_addr
			)
			VALUES(
			".$obj->POST_ID.",
			".$obj->TOPIC_ID.",
			".(int)$obj->aid.",
			".$obj->POST_DATE.",
			'".addslashes(htmlspecialchars(decode_ib3($obj->TOPIC_TITLE)))."',
			'".intyn($obj->ENABLE_SIG)."',
			'".intyn(!$obj->ENABLE_EMO)."',
			".(int)$off.",
			".(int)$len.",
			".$fileid.",
			".ssn($obj->IP_ADDR)."
		)");	
	}
	print_status('Finished Importing ('.db_count($r).') Messages');
	qf($r);
	
	// update root_msg_id for threads
	print_status('Updating Messages to Thread Relations');
	$r = q("select thread_id,MIN(id) FROM ".$DBHOST_TBL_PREFIX."msg GROUP BY thread_id");
	while( list($tid,$id) = db_rowarr($r) ) 
		q("UPDATE ".$DBHOST_TBL_PREFIX."thread SET root_msg_id=".$id." WHERE id=".$tid);
	qf($r);
	print_status('Finished Messages to Thread Relations');
	
	// import moderators
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."mod");
	print_status('Importing Moderators');
	$r = iq("SELECT ib_forum_moderators.*, ibf_members.id FROM ib_forum_moderators INNER JOIN ibf_members ON ib_forum_moderators.MEMBER_ID=ibf_members.misc");
	while( $obj = db_rowobj($r) ) {
		q("INSERT INTO ".$DBHOST_TBL_PREFIX."mod (user_id,forum_id) VALUES(".$obj->id.",".$obj->FORUM_ID.")");	
	}
	print_status('Finished Importing ('.db_count($r).') Moderators');
	qf($r);
	
	// import member titles
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."level");
	print_status('Importing Member Titles');
	$r = iq("SELECT * FROM ibf_titles");
	while( $obj = db_rowobj($r) ) 
		q("INSERT INTO ".$DBHOST_TBL_PREFIX."level (name,post_count) VALUES('".addslashes($obj->title)."',".(int)$obj->posts.")");
	print_status('Finished Importing ('.db_count($r).') Member Titles');
	qf($r);

	// import address book
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."buddy");
	print_status('Importing Address Book as Buddy List');
	$r = iq("SELECT ibf_members.id AS user_id, ibf_members2.id AS bud_id FROM ib_address_books 
		INNER JOIN ibf_members ON ib_address_books.MEMBER_ID=ibf_members.misc
		INNER JOIN ibf_members AS ibf_members2 ON ib_address_books.IN_MEMBER_ID=ibf_members2.misc");
	while( $obj = db_rowobj($r) )
		q("INSERT INTO ".$DBHOST_TBL_PREFIX."buddy (user_id,bud_id) VALUES(".$obj->user_id.", ".$obj->bud_id.")");
	print_status('Finished Importing ('.db_count($r).') Address Book as Buddy List entries');
	qf($r);

	// import subscriptions
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."thread_notify");
	print_status('Importing Subscriptions');
	$r = iq("SELECT ib_forum_subscriptions.TOPIC_ID, ibf_members.id FROM ib_forum_subscriptions INNER JOIN ibf_members ON ib_forum_subscriptions.MEMBER_ID=ibf_members.misc");
	while( $obj = db_rowobj($r) )
		q("INSERT INTO ".$DBHOST_TBL_PREFIX."thread_notify (user_id,thread_id) VALUES(".$obj->id.",".$obj->TOPIC_ID.")");
	print_status('Finished Importing ('.db_count($r).') Subscriptions');
	qf($r);
	
	// import file attachments
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."attach");
	print_status('Importing Attachments');
	$r = iq("SELECT ib_attachments.*, ib_forum_posts.POST_ID, ib_forum_posts.ATTACH_HITS FROM ib_forum_posts INNER JOIN ib_attachments ON ib_forum_posts.ATTACH_ID=ib_attachments.ID WHERE ib_forum_posts.ATTACH_ID!=''");
	while( $obj = db_rowobj($r) ) {
		$id = fud_attach::full_add($obj->owner, $obj->POST_ID, $obj->FILE_NAME, $GLOBALS['__IKON_CFG__']['IMAGES_URL'].'/upload/'.$obj->FILE_NAME, filesize($GLOBALS['__IKON_CFG__']['IMAGES_URL'].'/upload/'.$obj->FILE_NAME));
		if( $obj->ATTACH_HITS ) q("UPDATE ".$DBHOST_TBL_PREFIX."attach SET dlcount=".$obj->ATTACH_HITS." WHERE id=".$id);
	}
	print_status('Finished Importing ('.db_count($r).') Attachments');
	qf($r);
	
	// import polls
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."poll");
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."poll_opt");
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."poll_opt_track");
	print_status('Importing Polls');
	$r = iq("SELECT * FROM ib_forum_polls");
	while( $obj = db_rowobj($r) ) {
		$r2 = iq("SELECT TOPIC_ID FROM ib_forum_topics WHERE TOPIC_START_DATE=".$obj->POLL_STARTED." AND TOPIC_STARTER='".addslashes($obj->POLL_STARTER)."' AND (POLL_STATE='open' OR POLL_STATE='closed') AND FORUM_ID=".$obj->FORUM_ID);
		if( !(list($tid) = db_singlearr($r2)) ) continue;		
		if( !(list($uid,$mid)=db_singlearr(q("SELECT ".$DBHOST_TBL_PREFIX."users.id,".$DBHOST_TBL_PREFIX."msg.id FROM ".$DBHOST_TBL_PREFIX."thread INNER JOIN ".$DBHOST_TBL_PREFIX."msg ON ".$DBHOST_TBL_PREFIX."thread.root_msg_id=".$DBHOST_TBL_PREFIX."msg.id INNER JOIN ".$DBHOST_TBL_PREFIX."users ON ".$DBHOST_TBL_PREFIX."msg.poster_id=".$DBHOST_TBL_PREFIX."users.id WHERE ".$DBHOST_TBL_PREFIX."thread.id=".$tid))) ) continue;
		
		
		preg_match_all('!\d+~::~\<\!\-\-\d+\-\-\>(.*?)~=~(\d+)\|!s', $obj->POLL_ANSWERS, $poll_opts);
		q("INSERT INTO ".$DBHOST_TBL_PREFIX."poll (
			id,
			name, 
			owner, 
			creation_date,
			expiry_date
			) 
			VALUES(
			".$obj->POLL_ID.",
			'".addslashes(preg_replace('!&#(\d+);!e', "chr(\\1)", $obj->POLL_TITLE))."',
			".$uid.",
			".(int)$obj->POLL_STARTED.",
			".(($obj->POLL_STATE=='closed')?($obj->POLL_STARTED+1):0)."
		)");
		
		q("UPDATE ".$DBHOST_TBL_PREFIX."msg SET poll_id=".$obj->POLL_ID." WHERE id=".$mid);
		
		// Import poll options
		foreach( $poll_opts[1] as $opt_name ) {
			list($count) = each($poll_opts[2]);
			q("INSERT INTO ".$DBHOST_TBL_PREFIX."poll_opt (name,poll_id,count) VALUES('".addslashes(preg_replace('!&#(\d+);!e', "chr(\\1)", $opt_name))."', ".$obj->POLL_ID.", ".(int)$count.")");
		}	
	}
	print_status('Finished Importing ('.db_count($r).') Polls');
	qf($r);
	
	// Import poll votes
	print_status('Importing Poll Votes');
	$r = iq("SELECT ibf_members.id,ib_forum_poll_voters.POLL_ID FROM ib_forum_poll_voters INNER JOIN ibf_members ON ib_forum_poll_voters.MEMBER_ID=ibf_members.misc"); 
	while( list($uid, $pid) = db_rowarr($r) ) 
		q("INSERT INTO ".$DBHOST_TBL_PREFIX."poll_opt_track (user_id,poll_id) VALUES(".$uid.", ".$pid.")");
	print_status('Finished Importing ('.db_count($r).') Poll Votes'); 
	qf($r);
	
	// Import Admins
	print_status('Importing Admins');
	$r = iq("SELECT id FROM ibf_members WHERE mgroup=".$GLOBALS['__IKON_CFG__']['SUPAD_GROUP']);
	while( list($uid) = db_rowarr($r) )
		q("UPDATE ".$DBHOST_TBL_PREFIX."users SET is_mod='A' WHERE id=".$uid);
	print_status('Finished Importing ('.db_count($r).') Admins');
	qf($r);

	// import global forum options
	print_status('Importing Global Options');
	$global_config = read_global_config();
	if( $GLOBALS['__IKON_CFG__']['AVATARS'] ) {
		if( $GLOBALS['__IKON_CFG__']['AV_ALLOW_URL'] )
			change_global_val('CUSTOM_AVATARS', $GLOBALS['__IKON_CFG__']['ALL'], $global_config);
		else
			change_global_val('CUSTOM_AVATARS', $GLOBALS['__IKON_CFG__']['BUILT_UPLOAD'], $global_config);
	}
	
	if( $GLOBALS['__IKON_CFG__']['MSG_ALLOW_HTML'] )
		$code = 'HTML';
	else if( $GLOBALS['__IKON_CFG__']['MSG_ALLOW_CODE'] )
		$code = 'ML';
	else 
		$code = 'NONE';	
	
	change_global_val('CUSTOM_AVATAR_MAX_DIM', $GLOBALS['__IKON_CFG__']['AV_DIMS'], $global_config);
	change_global_val('FORUM_TITLE', $GLOBALS['__IKON_CFG__']['BOARDNAME'], $global_config);
	change_global_val('POSTS_PER_PAGE', $GLOBALS['__IKON_CFG__']['DISPLAY_MAX_POSTS'], $global_config);
	change_global_val('THREADS_PER_PAGE', $GLOBALS['__IKON_CFG__']['DISPLAY_MAX_TOPICS'], $global_config);
	change_global_val('SITE_HOME_PAGE', $GLOBALS['__IKON_CFG__']['HOME_URL'], $global_config);
	change_global_val('MAX_LOCATION_SHOW', $GLOBALS['__IKON_CFG__']['MAX_LOCATION_LENGTH'], $global_config);
	change_global_val('FLOOD_CHECK_TIME', (int)$GLOBALS['__IKON_CFG__']['FLOOD_CONTROL'], $global_config);
	change_global_val('EMAIL_CONFIRMATION', intyn($GLOBALS['__IKON_CFG__']['VERIFY_MAIL']), $global_config);
	change_global_val('PUBLIC_STATS', intyn($GLOBALS['__IKON_CFG__']['SHOW_STATS']), $global_config);
	change_global_val('SHOW_ONLINE', intyn($GLOBALS['__IKON_CFG__']['ONLINE_OFFLINE_STATUS']), $global_config);
	change_global_val('SESSION_TIMEOUT', $GLOBALS['__IKON_CFG__']['SESSION_EXPIRATION'], $global_config);
	change_global_val('FORUM_CODE_SIG', $code, $global_config);
	change_global_val('PRIVATE_TAGS', $code, $global_config);
	change_global_val('FORUM_IMG_CNT_SIG', (int)$GLOBALS['__IKON_CFG__']['MAX_IMAGES'], $global_config);
	change_global_val('MAX_IMAGE_COUNT', (int)$GLOBALS['__IKON_CFG__']['MAX_IMAGES'], $global_config);
	change_global_val('ALLOW_EMAIL', intyn($GLOBALS['__IKON_CFG__']['USE_MAIL_FORM']), $global_config);
	
	write_global_config($global_config);
	print_status('Finished Importing Global Options');
	
	$time_taken = time() - $start_time;
	if( $time_taken > 120 ) 
		$time_taken .= ' seconds';
	else {
		$m = floor($time_taken/60);
		$s = $time_taken - $m*60;
		$time_taken = $m." minutes ".$s." seconds";
	}	
	
	print_status("\n\nConversion of Ikonboard to FUDforum2 has been completed\n Time Taken: ".$time_taken."\n");
	print_status("To complete the process run the consistency checker at:");
	print_status($GLOBALS['WWW_ROOT']."adm/consist.php");
?>