<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: isearch.inc.t,v 1.6 2002/07/08 23:15:19 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/


function delete_msg_index($msg_id)
{
	q("DELETE FROM {SQL_TABLE_PREFIX}index WHERE msg_id=".$msg_id);
	q("DELETE FROM {SQL_TABLE_PREFIX}title_index WHERE msg_id=".$msg_id);
}	
	
function index_text($subj, $body, $msg_id)
{
	
	/* Remove Stuff In Quotes */
	while ( preg_match('!<table border="0" align="center" width="90%" cellpadding="3" cellspacing="1"><tr><td class="SmallText"><b>(.*?)</b></td></tr><tr><td class="quote"><br>(.*?)<br></td></tr></table>!is', $body) ) 
		$body = preg_replace('!<table border="0" align="center" width="90%" cellpadding="3" cellspacing="1"><tr><td class="SmallText"><b>(.*?)</b></td></tr><tr><td class="quote"><br>(.*?)<br></td></tr></table>!is', '', $body);
		
	/* Remove HTML */
	reverse_FMT($body);
	$body = addslashes(strip_tags($body));

	reverse_FMT($subj);
	$subj = strtolower($subj);
	$subj = trim(preg_replace('!\s+!', ' ', $subj));
	
	$body = strtolower($body);
	$body = trim(preg_replace('!\s+!', ' ', $body));

	/* build full text index */
	$w = explode(' ', trim($subj.' '.$body));
	$a = count($w);
	
	if ( !db_locked() ) {
		$ll=1;
		db_lock('{SQL_TABLE_PREFIX}search+,
			 {SQL_TABLE_PREFIX}index+,
			 {SQL_TABLE_PREFIX}title_index+
			');
	}
	
	for ( $i=0; $i<$a; $i++ ) {
		if ( strlen($w[$i]) > 50 || strlen($w[$i])<3 ) continue;
		
		$r=q("SELECT id FROM {SQL_TABLE_PREFIX}search WHERE word='".$w[$i]."'");
		if ( !is_result($r) ) {
			$r = q("INSERT INTO {SQL_TABLE_PREFIX}search (word) VALUES('".$w[$i]."')");
			$id = db_lastid("{SQL_TABLE_PREFIX}search", $r);
		}
		else list($id) = db_singlearr($r);
		
		if ( !bq("SELECT id FROM {SQL_TABLE_PREFIX}index WHERE word_id=".$id." AND msg_id=".$msg_id) ) {
			q("INSERT INTO {SQL_TABLE_PREFIX}index(word_id, msg_id) VALUES(".$id.", ".$msg_id.")");
		}
	}
	
	/* build subject only index */
	$w = explode(' ', $subj);
	for ( $i=0; $i<count($w); $i++ ) {
		if ( strlen($w[$i]) > 50 || strlen($w[$i])<3 ) continue;
		$r=q("SELECT id FROM {SQL_TABLE_PREFIX}search WHERE word='".$w[$i]."'");
		if ( !is_result($r) ) {
			$r = q("INSERT INTO {SQL_TABLE_PREFIX}search (word) VALUES('".$w[$i]."')");
			$id = db_lastid("{SQL_TABLE_PREFIX}search", $r);
		}
		else list($id) = db_singlearr($r);
		
		if ( !bq("SELECT id FROM {SQL_TABLE_PREFIX}title_index WHERE word_id=$id AND msg_id=".$msg_id) ) {
			q("INSERT INTO {SQL_TABLE_PREFIX}title_index(word_id, msg_id) VALUES(".$id.", ".$msg_id.")");
		}
	}
	
	if ( $ll ) db_unlock();
}

function re_build_index()
{
	if ( !db_locked() ) { $ll=1; db_lock('{SQL_TABLE_PREFIX}search+, {SQL_TABLE_PREFIX}index+, {SQL_TABLE_PREFIX}title_index+, {SQL_TABLE_PREFIX}msg+'); }
	q("DELETE FROM {SQL_TABLE_PREFIX}search");
	q("DELETE FROM {SQL_TABLE_PREFIX}index");
	q("DELETE FROM {SQL_TABLE_PREFIX}title_index");
	$r = q("SELECT id,subject,thread_id,length,foff,file_id FROM {SQL_TABLE_PREFIX}msg ORDER BY thread_id,id ASC");
	if( !($cnt=db_count($r)) ) {
		qf($r);
		db_unlock();
		return;
	}

	$th_id=$i=0;
	$old_suject=$subj=NULL;
	while( $obj = db_rowobj($r) ) {
		if( $th_id != $obj->thread_id ) {
			
			$th_id = $obj->thread_id;
		}
		$body = read_msg_body($obj->foff, $obj->length, $obj->file_id);
		
		/* Remove Stuff In Quotes */
		while ( preg_match('!<table border="0" align="center" width="90%" cellpadding="3" cellspacing="1"><tr><td class="SmallText"><b>(.*?)</b></td></tr><tr><td class="quote"><br>(.*?)<br></td></tr></table>!is', $body) ) 
			$body = preg_replace('!<table border="0" align="center" width="90%" cellpadding="3" cellspacing="1"><tr><td class="SmallText"><b>(.*?)</b></td></tr><tr><td class="quote"><br>(.*?)<br></td></tr></table>!is', '', $body);
		
		/* Remove HTML */
		$body = addslashes(strip_tags($body));
		
		/* Do not store the same subjects twice */
		if( $old_suject!=$obj->subject ) {
			$subj = addslashes($obj->subject);
			$old_suject = $obj->subject;
		}
		else
			$subj = NULL;
			
		index_text($subj, $body, $obj->id);
	}
	un_register_fps();
	if ( $ll ) db_unlock();
}

function search($str, $fld, $start, $count, $forum_limiter='')
{
	$w = explode(" ", $str);
	$qr = '';
	$qry_uniq = array();
	
	while( list(,$v) = each($w) ) {
		if( !$v ) continue;
		$v = strtolower($v);
		if ( isset($qry_uniq[$v]) ) continue;
		
		$qr .= " '".$v."',";
		$qry_uniq[$v] = 1;
	}
	
	if( $qr )
		$qr = substr($qr, 0, -1);
	else
		return q("SELECT id FROM {SQL_TABLE_PREFIX}search WHERE id=0");	
	
	$field = ( $fld != 'subject' ) ? '{SQL_TABLE_PREFIX}index' : '{SQL_TABLE_PREFIX}title_index';
	
	$r = q("SELECT id FROM {SQL_TABLE_PREFIX}search WHERE word IN(".$qr.")");
	if( !db_count($r) ) return $r;
	$qr='';
	while( list($id) = DB_ROWARR($r) ) $qr .= $id.',';
	QF($r);
	$qr = substr($qr, 0, -1);
	
	if( $GLOBALS['usr']->is_mod != 'A' ) {
		if( is_numeric($forum_limiter) ) {
			if( !is_perms(_uid, $forum_limiter, 'READ') ) return q("SELECT id FROM {SQL_TABLE_PREFIX}index WHERE id=0");

			$forum_limiter_sql = " {SQL_TABLE_PREFIX}forum.id=".$forum_limiter." AND ";
		}
		else {
			if( !($fids = get_all_perms(_uid)) ) return q("SELECT id FROM {SQL_TABLE_PREFIX}index WHERE id=0");
		
			$forum_limiter_sql = '{SQL_TABLE_PREFIX}forum.id IN ('.$fids.') AND ';
		
			if( $forum_limiter[0]=='c' && is_numeric(substr($forum_limiter,1)) ) 
				$forum_limiter_sql .= '{SQL_TABLE_PREFIX}cat.id='.substr($forum_limiter,1).' AND ';
		}
	}	
	else
		$forum_limiter_sql = '';

	$r = q("SELECT 
		".$field.".msg_id AS msg_id,
		SUM(1) as rev_match
	FROM 
		".$field."
		INNER JOIN {SQL_TABLE_PREFIX}msg ON ".$field.".msg_id={SQL_TABLE_PREFIX}msg.id
		INNER JOIN {SQL_TABLE_PREFIX}thread ON {SQL_TABLE_PREFIX}msg.thread_id={SQL_TABLE_PREFIX}thread.id
		INNER JOIN {SQL_TABLE_PREFIX}forum ON {SQL_TABLE_PREFIX}thread.forum_id={SQL_TABLE_PREFIX}forum.id
		INNER JOIN {SQL_TABLE_PREFIX}cat ON {SQL_TABLE_PREFIX}forum.cat_id={SQL_TABLE_PREFIX}cat.id
	WHERE 
		".$field.".word_id IN ( ".$qr." ) AND
		".$forum_limiter_sql."
		{SQL_TABLE_PREFIX}msg.approved='Y'
	GROUP BY 
		".$field.".msg_id 
	ORDER BY rev_match DESC");
	
	if ( !db_count($r) ) return $r;
	
	$idlist = '';
	while ( $obj = db_rowobj($r) ) {
		$idlist .= $obj->msg_id.',';
	}
	
	$idlist = substr($idlist, 0, -1);
	qf($r);
	
	$r = q("SELECT
			{SQL_TABLE_PREFIX}users.alias AS login,
			{SQL_TABLE_PREFIX}forum.name AS forum_name, 
			{SQL_TABLE_PREFIX}forum.id AS forum_id,
			{SQL_TABLE_PREFIX}msg.poster_id,
			{SQL_TABLE_PREFIX}msg.id, 
			{SQL_TABLE_PREFIX}msg.thread_id,
			{SQL_TABLE_PREFIX}msg.subject,
			{SQL_TABLE_PREFIX}msg.poster_id,
			{SQL_TABLE_PREFIX}msg.foff,
			{SQL_TABLE_PREFIX}msg.length,
			{SQL_TABLE_PREFIX}msg.post_stamp,
			{SQL_TABLE_PREFIX}msg.file_id
		FROM 
			{SQL_TABLE_PREFIX}msg 
			INNER JOIN {SQL_TABLE_PREFIX}thread ON {SQL_TABLE_PREFIX}msg.thread_id={SQL_TABLE_PREFIX}thread.id
			INNER JOIN {SQL_TABLE_PREFIX}forum ON {SQL_TABLE_PREFIX}thread.forum_id={SQL_TABLE_PREFIX}forum.id
			INNER JOIN {SQL_TABLE_PREFIX}cat ON {SQL_TABLE_PREFIX}forum.cat_id={SQL_TABLE_PREFIX}cat.id
			LEFT JOIN {SQL_TABLE_PREFIX}users ON {SQL_TABLE_PREFIX}msg.poster_id={SQL_TABLE_PREFIX}users.id
		WHERE 
			{SQL_TABLE_PREFIX}msg.id IN ($idlist)
		");

	return $r;
}
?>