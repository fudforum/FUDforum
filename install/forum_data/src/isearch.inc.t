<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: isearch.inc.t,v 1.21 2003/10/01 23:54:10 hackie Exp $
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

	reverse_fmt($subj);
	$subj = trim(preg_replace('!\s+!', ' ', strip_tags(strtolower($subj))));
	reverse_fmt($body);
	$body = trim(preg_replace('!\s+!', ' ', strip_tags(strtolower($body))));

	/* build full text index */
	$t1 = array_unique(explode(' ', $subj));
	$t2 = array_unique(explode(' ', $body));

	/* this is mostly a hack for php verison < 4.3 because isset(string[bad offset]) returns a warning */
	error_reporting(E_ERROR);

	foreach ($t1 as $v) {
		if (isset($v[51]) || !isset($v[3])) continue;
		$w1[] = "'".addslashes($v)."'";
	}

	if (isset($w1)) {
		$w2 = $w1;
	}

	foreach ($t2 as $v) {
		if (isset($v[51]) || !isset($v[3])) continue;
		$w2[] = "'".addslashes($v)."'";
	}

	if (!$w2) {
		return;
	}

	$w2 = array_unique($w2);
	ins_m('{SQL_TABLE_PREFIX}search', 'word', $w2);

	if (isset($w1)) {
		db_li('INSERT INTO {SQL_TABLE_PREFIX}index (word_id, msg_id) SELECT id, '.$msg_id.' FROM {SQL_TABLE_PREFIX}search WHERE word IN('.implode(',', $w1).')', $ef);
	}
	db_li('INSERT INTO {SQL_TABLE_PREFIX}title_index (word_id, msg_id) SELECT id, '.$msg_id.' FROM {SQL_TABLE_PREFIX}search WHERE word IN('.implode(',', $w2).')', $ef);
}
?>