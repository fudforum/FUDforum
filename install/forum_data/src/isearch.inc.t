<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: isearch.inc.t,v 1.16 2003/05/06 03:55:06 hackie Exp $
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
	q('DELETE FROM {SQL_TABLE_PREFIX}index WHERE msg_id='.$msg_id);
	q('DELETE FROM {SQL_TABLE_PREFIX}title_index WHERE msg_id='.$msg_id);
}	
	
function index_text($subj, $body, $msg_id)
{
	/* Remove Stuff In Quotes */
	while (preg_match('!<table border="0" align="center" width="90%" cellpadding="3" cellspacing="1"><tr><td class="SmallText"><b>(.*?)</b></td></tr><tr><td class="quote"><br>(.*?)<br></td></tr></table>!is', $body)) {
		$body = preg_replace('!<table border="0" align="center" width="90%" cellpadding="3" cellspacing="1"><tr><td class="SmallText"><b>(.*?)</b></td></tr><tr><td class="quote"><br>(.*?)<br></td></tr></table>!is', '', $body);
	}
		
	reverse_FMT($subj);
	$subj = trim(preg_replace('!\s+!', ' ', strtolower($subj)));
	reverse_FMT($body);
	$body = trim(preg_replace('!\s+!', ' ', strip_tags(strtolower($body))));

	/* build full text index */
	$t1 = explode(' ', $subj);
	$t2 = explode(' ', $body);
	
	foreach ($t1 as $v) {
		if (strlen($v) > 50 || strlen($v) < 3 || isset($w1[$v])) {
			continue;
		}
		$w1[$v] = $v;
		$w2[$v] = $v;
	}
	foreach ($t2 as $v) {
		if (strlen($v) > 50 || strlen($v) < 3 || isset($w2[$v])) {
			continue;
		}
		$w2[$v] = $v;
	}

	if (!isset($w2)) {
		return;
	}	

	if (!db_locked()) {
		$ll = 1;
		db_lock('{SQL_TABLE_PREFIX}search WRITE, {SQL_TABLE_PREFIX}index WRITE, {SQL_TABLE_PREFIX}title_index WRITE');
	}
	
	/* subject + body index */
	foreach ($w2 as $v) {
		if (!($wid = q_singleval('SELECT id FROM {SQL_TABLE_PREFIX}search WHERE word=\''.($v = addslashes($v)).'\''))) {
			$wid = db_qid('INSERT INTO {SQL_TABLE_PREFIX}search (word) VALUES(\''.$v.'\')');
		}
		$w2d[] = $wid;
		if (isset($w1[$v])) {
			$w1d[] = $wid;
		}
	}
	if (isset($w2d) && count($w2d)) {
		q('INSERT INTO {SQL_TABLE_PREFIX}index (word_id, msg_id) SELECT id, '.$msg_id.' FROM {SQL_TABLE_PREFIX}search WHERE id IN('.implode(',', $w2d).')');
	}
	if (isset($w1d) && count($w1d)) {
		q('INSERT INTO {SQL_TABLE_PREFIX}title_index (word_id, msg_id) SELECT id, '.$msg_id.' FROM {SQL_TABLE_PREFIX}search WHERE id IN('.implode(',', $w1d).')');
	}

	if (isset($ll)) {
		db_unlock();
	}
}
?>