<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: isearch.inc.t,v 1.12 2003/04/14 18:49:51 hackie Exp $
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

function fetch_search_cache($qry, $start, $count, $logic, $srch_type, $order, $forum_limiter, &$total)
{
	$w = explode(' ', strtolower($qry));
	$qr = ''; $i = 0;
	foreach ($w as $v) {
		$v = trim($v);
		if (!$v || isset($qu[$v]) || strlen($v) <= 2) {
			continue;
		} else if ($i++ == 10) { /* limit query length to 10 words */
			break;
		}
		$qu[$v] = $v;
		$qr .= " '".$v."',";
	}
	if (!$qr) {
		return;
	} else {
		$qr = substr($qr, 0, -1);
	}

	if ($srch_type == 'all') {
		$tbl = 'index';
		$qt = '0';
	} else {
		$tbl = 'title_index';
		$qt = '1';
	}
	$qry_lck = "'".addslashes(implode(' ', $qu))."'";

	db_lock('{SQL_TABLE_PREFIX}search WRITE, {SQL_TABLE_PREFIX}search_cache WRITE, {SQL_TABLE_PREFIX}'.$tbl.' WRITE');
	q('DELETE FROM {SQL_TABLE_PREFIX}search_cache WHERE expiry<'.(__request_timestamp__ - $GLOBALS['SEARCH_CACHE_EXPIRY']));
	if (!($total = q_singleval('SELECT count(*) FROM {SQL_TABLE_PREFIX}search_cache WHERE query_type='.$qt.' AND srch_query='.$qry_lck))) {
		/* nothing in the cache, let's cache */
		$c = uq('SELECT id FROM {SQL_TABLE_PREFIX}search WHERE word IN('.$qr.')');
		while ($r = db_rowarr($c)) {
			$wl[] = $r[0];
		}
		qf($c);
		if ($logic == 'AND' && count($wl) != count($qu)) {
			return;
			db_unlock();
		}
		q('INSERT INTO {SQL_TABLE_PREFIX}search_cache (srch_query, query_type, expiry, msg_id, n_match) 
			SELECT '.$qry_lck.', \''.$qt.'\', '.__request_timestamp__.', msg_id, count(*) as word_count FROM {SQL_TABLE_PREFIX}'.$tbl.' WHERE word_id IN('.implode(',', $wl).') GROUP BY msg_id ORDER BY word_count DESC LIMIT 500');

		$total = db_affected();
		db_unlock();

		if (!$total) {
			return;	
		}
	} else {
		db_unlock();
	}
	if ($forum_limiter) {
		if ($forum_limiter[0] != 'c') {
			$qry_lmt = ' AND f.id=' . (int)$forum_limiter . ' ';
		} else {
			$qry_lmt = ' AND c.id=' . (int)substr($forum_limiter, 1) . ' ';
		}
	} else {
		$qry_lmt = '';
	}

	$total = q_singleval('SELECT count(*)
		FROM {SQL_TABLE_PREFIX}search_cache sc
		INNER JOIN {SQL_TABLE_PREFIX}msg m ON m.id=sc.msg_id
		INNER JOIN {SQL_TABLE_PREFIX}thread t ON m.thread_id=t.id
		INNER JOIN {SQL_TABLE_PREFIX}forum f ON t.forum_id=f.id
		INNER JOIN {SQL_TABLE_PREFIX}cat c ON f.cat_id=c.id
		INNER JOIN {SQL_TABLE_PREFIX}group_cache g1 ON g1.user_id='.(_uid ? '2147483647' : '0').' AND g1.resource_id=f.id
		LEFT JOIN {SQL_TABLE_PREFIX}mod mm ON mm.forum_id=f.id AND mm.user_id='._uid.'
		LEFT JOIN {SQL_TABLE_PREFIX}group_cache g2 ON g2.user_id='._uid.' AND g2.resource_id=f.id
		WHERE 
			sc.query_type='.$qt.' AND sc.srch_query='.$qry_lck.$qry_lmt.'
			'.($logic == 'AND' ? ' AND sc.n_match='.count($qu) : '').'
			'.($GLOBALS['usr']->is_mod != 'A' ? ' AND (mm.id IS NOT NULL OR (CASE WHEN g2.id IS NOT NULL THEN g2.p_READ ELSE g1.p_READ END)=\'Y\')' : ''));
	if (!$total) {
		return;	
	}

	return uq('SELECT 
			u.alias, 
			f.name AS forum_name, f.id AS forum_id,
			m.poster_id, m.id, m.thread_id, m.subject, m.poster_id, m.foff, m.length, m.post_stamp, m.file_id, m.icon
		FROM {SQL_TABLE_PREFIX}search_cache sc
		INNER JOIN {SQL_TABLE_PREFIX}msg m ON m.id=sc.msg_id
		INNER JOIN {SQL_TABLE_PREFIX}thread t ON m.thread_id=t.id
		INNER JOIN {SQL_TABLE_PREFIX}forum f ON t.forum_id=f.id
		INNER JOIN {SQL_TABLE_PREFIX}cat c ON f.cat_id=c.id
		INNER JOIN {SQL_TABLE_PREFIX}group_cache g1 ON g1.user_id='.(_uid ? '2147483647' : '0').' AND g1.resource_id=f.id
		LEFT JOIN {SQL_TABLE_PREFIX}users u ON m.poster_id=u.id
		LEFT JOIN {SQL_TABLE_PREFIX}mod mm ON mm.forum_id=f.id AND mm.user_id='._uid.'
		LEFT JOIN {SQL_TABLE_PREFIX}group_cache g2 ON g2.user_id='._uid.' AND g2.resource_id=f.id
		WHERE 
			sc.query_type='.$qt.' AND sc.srch_query='.$qry_lck.$qry_lmt.'
			'.($logic == 'AND' ? ' AND sc.n_match='.count($qu) : '').'
			'.($GLOBALS['usr']->is_mod != 'A' ? ' AND (mm.id IS NOT NULL OR (CASE WHEN g2.id IS NOT NULL THEN g2.p_READ ELSE g1.p_READ END)=\'Y\')' : '').'
		ORDER BY sc.n_match DESC, m.post_stamp '.$order.' LIMIT '.qry_limit($count, $start));
}
?>