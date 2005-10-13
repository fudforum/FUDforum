<?php
/***************************************************************************
* copyright            : (C) 2001-2004 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: wbb.php,v 1.11 2005/10/13 19:32:08 hackie Exp $
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
 *	2) Change the value of the value of the $WBB_CFG_PATH variable
 *	   to the full path of the 'data.inc.php' file.
 *	3) Run this script via the shell or the web.
 *	4) Once the script successfuly runs, run the consitency checker.
 *	5) Voila, you're done.
*/
	
	$WBB_CFG_PATH = "";
	
/* DO NOT MODIFY BEYOND THIS POINT */	
	$start_time = time();
	
	if( !file_exists($WBB_CFG_PATH) || !is_readable($WBB_CFG_PATH) ) 
		exit("Cannot read Wbb's config file at: '".$WBB_CFG_PATH."'<br>\n");
		
function parse_ikon_sql_cfg()
{
	include_once $GLOBALS['WBB_CFG_PATH'];
	$GLOBALS['__WBB_SQL__']['prefix'] = $mysqldb.'.bb'.$n.'_'; 
}

function intyn($s)
{
	return (empty($s)?'N':'Y');
}

function wq($qry)
{
	$qry = str_replace('{WBB}', $GLOBALS['__WBB_SQL__']['prefix'], $qry);
	
	if (!($r = mysql_query($qry))) {
		exit("Query Failure: $qry <br>\nSQL Reason: ".mysql_error()."<br>\n");
	}

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
	return preg_replace('!&#(\d+);!e', "chr(\\1)", str_replace('&nbsp;', ' ',  reverse_fmt($s)));
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
	fud_use('smiley.inc');
	fud_use('rhost.inc');
	fud_use('groups.inc');
	fud_use('private.inc');
	fud_use('mime.inc');
	fud_use('rev_fmt.inc');
	fud_use('attach.inc');
	fud_use('glob.inc', true);
	
	parse_ikon_sql_cfg();
	
	// Import Categories
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."cat");
	$r = wq("SELECT boardid,boardname,descriptiontext FROM {WBB}boards WHERE isboard=0 ORDER BY sort");
	print_status('Importing Categories');
	$i=1;
	$cat_count=0;
	while( $obj = db_rowobj($r) ) {
		q("INSERT INTO ".$DBHOST_TBL_PREFIX."cat (id,name,view_order,description) VALUES(".$obj->boardid.",'".addslashes($obj->boardname)."',".$i++.", '".addslashes($obj->descriptiontext)."')");
		$cat_count++;
	}
	qf($r);
	print_status('Finished Importing ('.$cat_count.') Categories');
	
	// Import Group Permissions
	print_status('Importing Group Permissions');
	$perms['canstarttopic'] = 'p_POST';
	$perms['canviewboard'] = 'p_VISIBLE';
	$perms['canreplytopic'] = 'p_REPLY';
	$perms['canpostpoll'] = 'p_POLL';
	$perms['canvotepoll'] = 'p_VOTE';
	
	// all regged users
	$obj = db_singleobj(wq("SELECT * FROM {WBB}groups WHERE title='User'"));
	$fud_perms = '';
	foreach($perms as $k => $v ) $fud_perms .= $v."='".intyn($obj->{$k})."', ";
	
	q("UPDATE ".$DBHOST_TBL_PREFIX."groups SET ".substr($fud_perms, 0, -2)." WHERE id=2");
	
	// all anon users
	$obj = db_singleobj(wq("SELECT * FROM {WBB}groups WHERE title='Guests'"));
	$fud_perms = '';
	foreach($perms as $k => $v ) $fud_perms .= $v."='".intyn($obj->{$k})."', ";
	
	q("UPDATE ".$DBHOST_TBL_PREFIX."groups SET ".substr($fud_perms, 0, -2)." WHERE id=1");
	
	print_status('Finished Importing Group Permissions');
	
	// Import Forums
	$r = wq("SELECT * FROM {WBB}boards WHERE isboard=1 ORDER BY boardparentid, sort");
	print_status('Importing Forums');
	
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."groups WHERE id>2");
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."group_members");
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."group_resources");
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."group_cache");
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."forum");
	
	$cat_id=0;
	while( $obj = db_rowobj($r) ) {
		unset($frm);
		$frm = new fud_forum_adm;
		$frm->cat_id = $obj->boardparentid;
		$frm->name = addslashes(htmlspecialchars($obj->boardname));
		$frm->descr = addslashes(htmlspecialchars($obj->descriptiontext));
		$frm->post_passwd = addslashes($obj->boardpassword);
		
		$id = $frm->add($i);
		q("UPDATE ".$DBHOST_TBL_PREFIX."forum SET id=".$obj->boardid.", view_order=".($i++)." WHERE id=$id");
		$gid = q_singleval("SELECT id FROM ".$DBHOST_TBL_PREFIX."groups WHERE res='forum' AND res_id=".$id);
		q("UPDATE ".$DBHOST_TBL_PREFIX."groups SET res_id=".$obj->boardid.", p_VISIBLE='".intyn(!$obj->invisible)."' WHERE id=".$gid);
		q("UPDATE ".$DBHOST_TBL_PREFIX."group_resources SET resource_id=".$obj->boardid." WHERE group_id=".$gid);
	}
	print_status('Finished Importing ('.db_count($r).') Forums');
	qf($r);
	
	// Import Users
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."users WHERE id!=100000");
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."ses");
	q("DELETE FROm ".$DBHOST_TBL_PREFIX."custom_tags");
	$THEME = q_singleval("SELECT id FROM ".$DBHOST_TBL_PREFIX."themes WHERE t_default='Y' AND enabled='Y'");
	
	$r = wq("SELECT * FROM {WBB}user_table");	
	print_status('Importing Members');
	while( $obj = db_rowobj($r) ) {
		if( 	bq("SELECT id FROM ".$DBHOST_TBL_PREFIX."users WHERE login='".addslashes($obj->username)."'") || 
			bq("SELECT id FROM ".$DBHOST_TBL_PREFIX."users WHERE email='".addslashes($obj->regemail)."'") 
		) 
			continue;
	
	
		if ($obj->avatarid) {
			$avatar_approved = 'Y';
			/* skip this step for now */
		} else {
			$obj->avatar = '';
			$avatar_approved = 'NO';
		}
	
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
				append_sig, 
				posts_ppg, 
				time_zone, 
				invisible_mode, 
				last_visit, 
				conf_key, 
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
				".$obj->userid.",
				'".addslashes($obj->username)."',
				'".addslashes(htmlspecialchars($obj->username))."',
				'".$obj->userpassword."',
				'".addslashes($obj->username)."',
				'".addslashes($obj->regemail)."',
				'".intyn(!$obj->show_email_global)."',
				'Y',
				'EMAIL',
				'".intyn($obj->mods_may_email)."',
				'Y',
				'Y',
				".intnull($obj->usericq).",
				".ssn($obj->aim).",
				".ssn($obj->yim).",
				'Y',
				'".$GLOBALS['POSTS_PER_PAGE']."',
				'".$GLOBALS['SERVER_TZ']."',
				'".intyn($obj->invisible)."',
				".(int)$obj->lastvisit.",
				'0',
				".(int)$obj->regdate.",
				'".addslashes($obj->location)."',
				".$THEME.",
				'N',
				".ssn($obj->interests).",
				'".intyn($obj->view_sigs)."',
				'".intyn($obj->hide_userpic)."',
				".(int)$obj->lastactivity.",
				".ssn(preg_replace('!&#(\d+);!e', "chr(\\1)", $obj->signatur)).",
				'msg',
				".ssn($obj->userhp).",
				'Y',
				'".$avatar_approved."'
			)
		");
		
		// import user's custom tags
		if( trim($obj->statusextra) ) 
			q("INSERT INTO ".$DBHOST_TBL_PREFIX."custom_tags (name,user_id) VALUES('".addslashes(htmlspecialchars($obj->statusextra))."',".$obj->userid.")");
	}
	print_status('Finished Importing ('.db_count($r).') Members');

	// import threads
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."thread");
	$r = wq("SELECT * FROM {WBB}threads");
	print_status('Importing Topics');
	while( $obj = db_rowobj($r) ) {
		if( $obj->TOPIC_STATE == 'link' || $obj->TOPIC_STATE == 'moved' ) continue;
	
		$locked = $obj->TOPIC_STATE == 'open' ? 'N' : 'Y';
	
		q("INSERT INTO ".$DBHOST_TBL_PREFIX."thread (
			id,
			forum_id,
			views
			)
			VALUES (
			".$obj->threadid.",
			".$obj->boardparentid.",
			".$obj->views."
			)
		");
	}
	print_status('Finished Importing ('.db_count($r).') Topics');
	
	// import messages
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."msg");
	$r = wq("SELECT * FROM {WBB}posts");
		
	$ffid = q_singleval("SELECT id FROM ".$DBHOST_TBL_PREFIX."forum LIMIT 1");	
			
	print_status('Importing Messages');
	while( $obj = db_rowobj($r) ) {
		$obj->message = smiley_to_post(tags_to_html($obj->message));
		$fileid = write_body($obj->message, $len, $off, $ffid);
	
		q("INSERT INTO ".$DBHOST_TBL_PREFIX."msg (
			id,
			thread_id,
			poster_id,
			post_stamp,
			update_stamp,
			updated_by,
			subject,
			show_sig,
			smiley_disabled,
			foff,
			length,
			file_id,
			ip_addr,
			approved
			)
			VALUES(
			".$obj->postid.",
			".$obj->threadparentid.",
			".(int)$obj->userid.",
			".$obj->posttime.",
			".$obj->edittime.",
			".$obj->editorid.",
			'".addslashes(htmlspecialchars($obj->posttopic))."',
			'".intyn($obj->signature)."',
			'".intyn(!$obj->disable_smilies)."',
			".(int)$off.",
			".(int)$len.",
			".$fileid.",
			".ssn($obj->ip).",
			'Y'
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
	$r = wq("SELECT {WBB}object2board.objectid,{WBB}object2board.boardid FROM {WBB}groups INNER JOIN {WBB}user_table ON {WBB}groups.id={WBB}user_table.groupid INNER JOIN {WBB}object2board ON {WBB}object2board.objectid={WBB}user_table.userid AND {WBB}object2board.mod=1");
	while( $obj = db_rowobj($r) ) {
		q("INSERT INTO ".$DBHOST_TBL_PREFIX."mod (user_id,forum_id) VALUES(".$obj->objectid.",".$obj->boardid.")");	
	}
	print_status('Finished Importing ('.db_count($r).') Moderators');
	qf($r);
	
	// import member titles
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."level");
	print_status('Importing Member Titles');
	$r = wq("select posts,rank,grafik from {WBB}ranks INNER JOIN {WBB}groups ON {WBB}ranks.groupid={WBB}groups.id WHERE title='User' ORDER BY posts;");
	while( $obj = db_rowobj($r) ) {
		q("INSERT INTO ".$DBHOST_TBL_PREFIX."level (name,post_count,img) VALUES('".addslashes($obj->rank)."',".(int)$obj->posts.", '".addslashes($obj->grafik)."')");
	}	
	print_status('Finished Importing ('.db_count($r).') Member Titles');
	qf($r);

	// import subscriptions
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."thread_notify");
	print_status('Importing Subscriptions');
	$r = wq("SELECT * FROM {WBB}notify");
	while( $obj = db_rowobj($r) ) {
		q("INSERT INTO ".$DBHOST_TBL_PREFIX."thread_notify (user_id,thread_id) VALUES(".$obj->userid.",".$obj->threadid.")");
	}	
	print_status('Finished Importing ('.db_count($r).') Subscriptions');
	qf($r);
	
	// import polls
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."poll");
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."poll_opt");
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."poll_opt_track");
	print_status('Importing Polls');
	$r = wq("SELECT * FROM {WBB}poll");
	
	$old_id = 0;
	$poll_id = NULL;
	
	while( $obj = db_rowobj($r) ) {
		if ($old_id !== $poll_id) {
			list($id,$subj) = db_singlearr(q("SELECT ".$DBHOST_TBL_PREFIX."msg.id,".$DBHOST_TBL_PREFIX."msg.subject FROM ".$DBHOST_TBL_PREFIX."thread INNER JOIN ".$DBHOST_TBL_PREFIX."msg ON ".$DBHOST_TBL_PREFIX."thread.root_msg_id=".$DBHOST_TBL_PREFIX."msg.id WHERE ".$DBHOST_TBL_PREFIX."thread.id=".$obj->threadid));
		
			q("INSERT INTO ".$DBHOST_TBL_PREFIX."poll (
				name, 
				owner
				) 
				VALUES(
				'".addslashes($subj)."',
				".$id."
			)");
		
			$poll_id = mysql_insert_id();
			q("UPDATE ".$DBHOST_TBL_PREFIX."msg SET poll_id=".$poll_id." WHERE id=".$id);
		}	
		
		// Import poll options
		q("INSERT INTO ".$DBHOST_TBL_PREFIX."poll_opt (name,poll_id,count) VALUES('".addslashes($obj->field)."', ".$poll_id.", ".(int)$obj->votes.")");
	}
	print_status('Finished Importing ('.db_count($r).') Polls');
	qf($r);
	
	// Import Admins
	print_status('Importing Admins');
	$r = wq("SELECT {WBB}user_table.userid FROM {WBB}groups INNER JOIN {WBB}user_table ON {WBB}user_table.groupid={WBB}groups.id WHERE {WBB}groups.title='Administrator'");
	while( list($uid) = db_rowarr($r) ) {
		q("UPDATE ".$DBHOST_TBL_PREFIX."users SET is_mod='A' WHERE id=".$uid);
		q("DELETE FROM ".$DBHOST_TBL_PREFIX."mod WHERE user_id=".$uid);
	}	
	print_status('Finished Importing ('.db_count($r).') Admins');
	qf($r);

	// import private messages 
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."pmsg");
	
	print_status('Importing Private Messages');
	$r = wq("SELECT * FROM {WBB}pms");
	while( $obj = db_rowobj($r) ) {
		$pmsg = new fud_pmsg;
		$pmsg->ouser_id = $obj->senderid;
		$pmsg->post_stamp = $obj->sendtime;
		$pmsg->show_sig = intyn($obj->signature);
		$pmsg->smiley_disabled = intyn($obj->disable_smilies);
		$pmsg->subject = addslashes($obj->subject);
		
		$obj->message = smiley_to_post(tags_to_html($obj->message));
		list($pmsg->foff, $pmsg->length) = write_pmsg_body($obj->message);
		$GLOBALS['recv_user_id'][] = $obj->recipientid;
		
		$pmsg->send_pmsg();
		unset($GLOBALS['recv_user_id'], $pmsg, $GLOBALS["send_to_array"]);
	}
	print_status('Finished Importing ('.db_count($r).') Private Messages');
	qf($r);
	
	// import global forum options
	$r = wq("SELECT * FROM bb5_config");
	$wbb_config = db_singleobj($r);

	print_status('Importing Global Options');
	$global_config = read_global_config();
	
	change_global_val('FORUM_TITLE', $obj->master_board_name, $global_config);
	change_global_val('ADMIN_EMAIL', $obj->master_email, $global_config);
	change_global_val('NOTIFY_FROM', $obj->master_email, $global_config);
	change_global_val('DISABLED_REASON', $obj->boardoff_text, $global_config);
	
	if ($obj->sigbbcode) 
		$code = 'ML';
	else if ($obj->sightml)
		$code = 'HTML';	
	else
		$code = 'N';	

	change_global_val('FORUM_CODE_SIG', $code, $global_config);
	change_global_val('FORUM_SML_SIG', intyn($obj->sigsmilies), $global_config);
	change_global_val('FORUM_IMG_SIG', intyn($obj->sigimage), $global_config);
	change_global_val('FORUM_IMG_CNT_SIG', $obj->sigmaximage, $global_config);
	change_global_val('CUSTOM_AVATAR_MAX_DIM', $obj->avatar_width.'x'.$obj->avatar_height, $global_config);
	change_global_val('CUSTOM_AVATAR_MAX_SIZE', $obj->avatar_size, $global_config);
	change_global_val('MAX_IMAGE_COUNT', $obj->maximage, $global_config);
	
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
	
	print_status("\n\nConversion of Wbb to FUDforum2 has been completed\n Time Taken: ".$time_taken."\n");
	print_status("To complete the process run the consistency checker at:");
	print_status($GLOBALS['WWW_ROOT']."adm/consist.php");
?>