<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: phorum.php,v 1.4 2002/07/01 16:04:28 hackie Exp $
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
 *	2) Change the value of the value of the $PH_SETTINGS_PATH variable
 *	   to the full path of the phorum's settings directory
 *	3) Run this script via the shell or the web.
 *	4) Once the script successfuly runs, run the consitency checker.
 *	5) Voila, you're done.
*/

	$PH_SETTINGS_PATH="/home/forum/F/TR/";

/* DO NOT MODIFY BEYOND THIS POINT */
	
	include_once "GLOBALS.php";
	fud_use('post_proc.inc');
	fud_use('db.inc');
	fud_use('imsg.inc');
	fud_use('imsg_edt.inc');
	fud_use('post_proc.inc');
	fud_use('rhost.inc');
	fud_use('forum.inc');
	fud_use('forum_adm.inc');
	fud_use('groups.inc');
	fud_use('mime.inc');
	fud_use('static/glob.inc');
	
	$PH_SETTINGS_PATH = realpath($PH_SETTINGS_PATH);
	
	if ( empty($PH_SETTINGS_PATH) ) 
		exit("PH_SETTINGS_PATH is blank, cannot proceed!\n Change the value of the value of the \$PH_SETTINGS_PATH variable to the full path of the directory where phorum's setting file resides.\n");
	
	if( !file_exists($PH_SETTINGS_PATH.'/forums.php') )
		exit("PH_SETTINGS_PATH ($PH_SETTINGS_PATH) does not contain valid path to phorum\n");

	include($PH_SETTINGS_PATH.'/forums.php');
	
	$phdb =  mysql_connect($GLOBALS['PHORUM']['DatabaseServer'], $GLOBALS['PHORUM']['DatabaseUser'], $GLOBALS['PHORUM']['DatabasePassword']);
	
	if( !isset($DBHOST_TBL_PREFIX) ) $DBHOST_TBL_PREFIX = $MYSQL_TBL_PREFIX;
	
	if( isset($HTTP_SERVER_VARS['REMOTE_ADDR']) ) echo '<pre>';
	$start_time = time();

function Q2($str)
{
	$r= mysql_db_query($GLOBALS['PHORUM']['DatabaseName'], $str, $GLOBALS['phdb']);
	if( !$r ) exit(mysql_error($GLOBALS['phdb'])."\n");
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
	echo $str."\n";
	flush();
}

function INT_yn($s)
{
	return (empty($s)?'N':'Y');
}

/* Import phorum categories */

	q("DELETE FROM ".$DBHOST_TBL_PREFIX."cat");
	$r = Q2("SELECT * FROM ".$PHORUM['main_table']." WHERE folder=1 ORDER BY id");
	print_status('Importing Categories');
	$i=1;
	$cat_count=0;
	while( $obj = db_rowobj($r) ) {
		print_status($obj->name);
		q("INSERT INTO ".$DBHOST_TBL_PREFIX."cat (id,name,view_order) VALUES(".$obj->id.",'".addslashes($obj->name)."',".$i++.")");
		$cat_count++;
	}
	qf($r);
	
	/* check if top level is needed */
	$r=Q2("SELECT id FROM ".$PHORUM['main_table']." WHERE folder=0 AND parent=0");
	if ( db_count($r) ) {
		q("INSERT INTO ".$DBHOST_TBL_PREFIX."cat (name,view_order) VALUES('".addslashes('Top Level Category (please rename)')."',".$i++.")");
		$TOP_LEVEL_CATID = db_lastid();
		$cat_count++;
	}
	qf($r);
	print_status('Finished Importing ('.$cat_count.') Categories');

/* Import phorum forums */
	
	print_status('Importing Forums '.db_count($r));
	
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."groups WHERE id>2");
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."group_members");
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."group_resources");
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."group_cache");
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."forum");
	$r = Q2("SELECT * FROM ".$PHORUM['main_table']." WHERE folder=0 ORDER BY parent, id");
	
	$i=1;
	$cat_id=0;
	while( $obj = db_rowobj($r) ) {
		print_status($obj->name);
		if ( $obj->parent == 0 ) $obj->parent = $TOP_LEVEL_CATID;
		if( $cat_id != $obj->parent ) {
			$i=1;
			$cat_id=$obj->parent;
		}
		$frm = new fud_forum_adm;
		$frm->cat_id = $obj->parent;
		$frm->name = addslashes($obj->name);
		$frm->descr = addslashes($obj->forum_desc);
		
		if ( $obj->allow_uploads ) {
			$frm->max_attach_size = round($obj->upload_size/1024);
			$frm->max_file_attachments = $obj->max_uploads;
		}
		
		$id = $frm->add($i);
		q("UPDATE ".$DBHOST_TBL_PREFIX."forum SET id=$obj->id, view_order=".($i++)." WHERE id=$id");
		$gid = q_singleval("SELECT id FROM ".$DBHOST_TBL_PREFIX."groups WHERE res='forum' AND res_id=$id");
		q("UPDATE ".$DBHOST_TBL_PREFIX."groups SET res_id=$obj->id WHERE id=$gid");
		q("UPDATE ".$DBHOST_TBL_PREFIX."group_resources SET resource_id=$obj->id WHERE group_id=$gid");
		
		$anon_post = 0;
		if ( $obj->security < 2 ) {
			$anon_post = 1;
			$str_a = "up_VISIBLE='Y', up_READ='Y', up_POST='Y', up_IMG='Y', up_POLL='Y', up_SML='Y', up_REPLY='Y', up_VOTE='Y'";
			$str_r = $str_a;
		}
		else {
			$str_a = "up_READ='Y', up_VISIBLE='Y'";
			$str_r = "up_VISIBLE='Y', up_READ='Y', up_POST='Y', up_IMG='Y', up_POLL='Y', up_SML='Y', up_REPLY='Y', up_VOTE='Y'";
		}

		if ( $obj->allow_uploads ) {
			$str_r .= ", up_FILE='Y'";
			if ( $anon_post ) $str_a .= ", up_FILE='Y'";
		}

		q("UPDATE ".$DBHOST_TBL_PREFIX."group_members SET $str_a WHERE group_id=$gid AND user_id=0");
		q("UPDATE ".$DBHOST_TBL_PREFIX."group_members SET $str_r WHERE group_id=$gid AND user_id=4294967295");
	}
	qf($r);
	print_status('Finished Importing Forums');

/* import phorum users */

	q("DELETE FROM ".$DBHOST_TBL_PREFIX."users");
	$r = Q2("SELECT * FROM ".$PHORUM['main_table']."_auth GROUP BY login ORDER BY id DESC");
	print_status('Importing users '.db_count($r));
	while ( $obj = db_rowobj($r) ) {
		print_status($obj->name);
		$append_sig = ( $obj->sig ) ? 'Y' : 'N';
		q("INSERT INTO ".$DBHOST_TBL_PREFIX."users 
			(
				id,
				login,
				passwd,
				email,
				home_page,
				icq,
				aim,
				yahoo,
				msnm,
				append_sig,
				sig,
				email_conf,
				coppa,
				join_date
			)
			VALUES (
				$obj->id,
				'".addslashes($obj->name)."',
				'".$obj->password."',
				'".addslashes($obj->email)."',
				'".addslashes($obj->webpage)."',
				".intnull($obj->icq).",
				'".addslashes($obj->aim)."',
				'".addslashes($obj->yahoo)."',
				'".addslashes($obj->msnm)."',
				'$append_sig',
				".strnull(tags_to_html($obj->sig)).",
				'Y',
				'N',
				".time()."
			)");
	}
	qf($r);
	print_status('Finished Importing users');

/* import phorum moderators */

	q("DELETE FROM ".$DBHOST_TBL_PREFIX."mod");
	$r = Q2("SELECT * FROM ".$PHORUM['main_table']."_moderators");
	print_status('Importing Moderators '.db_count($r));
	while ( $obj = db_rowobj($r) ) {
		q("UPDATE ".$DBHOST_TBL_PREFIX."users SET is_mod='A' WHERE id=$obj->user_id");
		q("INSERT INTO ".$DBHOST_TBL_PREFIX."mod (user_id, forum_id) VALUES($obj->user_id, $obj->forum_id)");
	} 
	qf($r);
	print_status('Finished Importing Moderators');

/* import phorum threads, messages & file attachments */
	
	q("DELETE FROM  ".$DBHOST_TBL_PREFIX."thread");
	q("DELETE FROM  ".$DBHOST_TBL_PREFIX."msg");
	q("DELETE FROM  ".$DBHOST_TBL_PREFIX."attach");
	
	$oldumask = umask(0);
	
	$tr = Q2("SELECT table_name, id FROM ".$PHORUM['main_table']." WHERE folder=0");
	while ( $tobj = db_rowobj($tr) ) {
		$forum_id = $tobj->id;
/* import threads */
	
		$thread_arr = array();
		
		$r = Q2("SELECT * FROM ".$tobj->table_name." WHERE parent=0 ORDER BY thread, parent");
		echo "SELECT * FROM ".$tobj->table_name." WHERE parent=0 ORDER BY thread, parent\n";
		print_status("Importing (".db_count($r).") threads for ".$tobj->table_name);
		while ( $obj = db_rowobj($r) ) {
			q("INSERT INTO ".$DBHOST_TBL_PREFIX."thread (forum_id) VALUES(".$forum_id.")");
			$thread_arr[$obj->id] = db_lastid();
		}
		qf($r);

/* import messages */
		
		$r = Q2("SELECT *, UNIX_TIMESTAMP(datestamp) AS post_stamp 
			FROM 
				".$tobj->table_name." 
				LEFT JOIN 
				".$tobj->table_name."_bodies
					ON ".$tobj->table_name.".id=".$tobj->table_name."_bodies.id
			ORDER BY ".$tobj->table_name.".thread, ".$tobj->table_name.".datestamp");

		echo "SELECT *, UNIX_TIMESTAMP(datestamp) AS post_stamp 
			FROM 
				".$tobj->table_name." 
				LEFT JOIN 
				".$tobj->table_name."_bodies
					ON ".$tobj->table_name.".id=".$tobj->table_name."_bodies.id
			ORDER BY ".$tobj->table_name.".thread, ".$tobj->table_name.".datestamp\n";

		print_status("\tImporting (".db_count($r).") messages for ".$tobj->table_name);

		$msgid_arr = array();
		
		while ( $obj = db_rowobj($r) ) {
			$fileid = write_body(tags_to_html($obj->body), $len, $off);
			
			q("INSERT INTO ".$DBHOST_TBL_PREFIX."msg
			(
				thread_id,
				poster_id,
				post_stamp,
				reply_to,
				subject,
				approved,
				smiley_disabled,
				host_name,
				offset,
				length,
				file_id
			)
			VALUES
			(
				".$thread_arr[$obj->thread].",
				$obj->userid,
				$obj->post_stamp,
				$obj->parent,
				'".addslashes($obj->subject)."',
				'Y',
				'N',
				".strnull($obj->host).",
				".intzero($off).",
				".intzero($len).",
				$fileid
			)");
			
			$msgid_arr[$obj->id] = db_lastid();
		}
		qf($r);
		
		$r = q("SELECT MIN(id) as id,thread_id FROM ".$DBHOST_TBL_PREFIX."msg GROUP BY thread_id");
		while( $obj = db_rowobj($r) ) 
			q("UPDATE ".$DBHOST_TBL_PREFIX."thread SET root_msg_id=".$obj->id." WHERE id=".$obj->thread_id);
		qf($r);
		
		unset($thread_arr);
		
/* import file attachments if there are any */

		if( mysql_db_query($GLOBALS['PHORUM']['DatabaseName'], "SELECT COUNT(*) FROM ".$tobj->table_name."_attachments", $GLOBALS['phdb']) ) {
			$r = Q2("SELECT * FROM ".$tobj->table_name."_attachments");
			print_status("\tImporting (".db_count($r).") file attachments for $tobj->table_name");
			while ( $obj = db_rowobj($r) ) {
				$from = realpath($GLOBALS['PHORUM']['AttachmentDir'].'/'.$tobj->table_name.'/'.$obj->message_id.'_'.$obj->id.strrchr($obj->filename, '.'));
							
				if( !@file_exists($from) ) {
					print_status("\t\tWARNING: file attachment ".$from." doesn't exist");
					continue;
				}
			
				$mime = get_mime_by_ext(substr(strrchr($obj->filename, '.'), 1));
				if( !$mime ) $mime = 40;
			
				$owner = q_singleval("SELECT poster_id FROM ".$DBHOST_TBL_PREFIX."msg WHERE id=".$obj->message_id);
			
				q("INSERT INTO ".$DBHOST_TBL_PREFIX."attach
				(
					original_name,
					message_id,
					mime_type,
					proto,
					owner	
				)
				VALUES(
					'".addslashes($obj->filename)."',
					".$msgid_arr[$obj->message_id].",
					".$mime.",
					'LOCAL',
					".intzero($owner)."
				)");	
				
				$attach_id = db_lastid();
				
				if( !copy($from, $FILE_STORE.$attach_id.'.atch') ) {
					print_status("Couldn't copy file attachment (".$from.") to (".$FILE_STORE.$attach_id.'.atch'.")");
					exit;
				}
			
				chmod($FILE_STORE.$attach_id.'.atch', 0666);
			
				q("UPDATE ".$DBHOST_TBL_PREFIX."attach SET location='".$FILE_STORE.$attach_id.'.atch'."' WHERE id=".$attach_id);
			}		
			qf($r);
		}
	}
	qf($tr);
	umask($oldumask);
	unset($msgid_arr);
	
/* import general phorum settings */
	
	print_status('Importing Forum Settings');	
	
	$global_config = read_global_config();
		
	change_global_val('ADMIN_EMAIL', $GLOBALS['PHORUM']['DefaultEmail'], $global_config);
	change_global_val('NOTIFY_FROM', $GLOBALS['PHORUM']['DefaultEmail'], $global_config);
	change_global_val('POSTS_PER_PAGE', $GLOBALS['PHORUM']['DefaultDisplay'], $global_config);
	change_global_val('PRIVATE_ATTACHMENTS', $GLOBALS['PHORUM']['MaximumNumberAttachments'], $global_config);
	change_global_val('PRIVATE_ATTACH_SIZE', ($GLOBALS['PHORUM']['AttachmentSizeLimit']*1000), $global_config);
	
	write_global_config($global_config);
	
	q("DELETE FROM ".$DBHOST_TBL_PREFIX."ext_block");
	$allowed_ext = explode(';', $GLOBALS['PHORUM']['AttachmentFileTypes']);
	while( list(,$v) = each($allowed_ext) ) {
		if( ($v=trim($v)) ) q("INSERT INTO ".$DBHOST_TBL_PREFIX."ext_block (ext) VALUES('".addslashes($v)."')");
	}
	
	print_status('Finished Importing Forum Settings');
	
	$time_taken = time() - $start_time;
	if( $time_taken > 120 ) 
		$time_taken .= ' seconds';
	else {
		$m = floor($time_taken/60);
		$s = $time_taken - $m*60;
		$time_taken = $m." minutes ".$s." seconds";
	}	
	
	print_status("\n\nConversion of Phorum to FUDforum2 has been completed\n Time Taken: ".$time_taken."\n");
	print_status("To complete the process run the consistency checker at:");
	print_status($GLOBALS['WWW_ROOT']."adm/consist.php");
	if( isset($HTTP_SERVER_VARS['REMOTE_ADDR']) ) echo '</pre>';
?>