<?php
/**
* copyright            : (C) 2001-2009 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: isearch.inc.t,v 1.70 2009/04/15 16:45:48 frank Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; version 2 of the License.
**/

function mb_word_split($str, $lang)
{
	$m = array();

	switch ($lang) {
		case 'chinese_big5':
			preg_match_all('!((?:[A-Za-z]+) | (?:[\xa1-\xfe] [\x40-\x7e] | [\xa1-\xfe] ) )!xs', $str, $m);
			break;
		case 'chinese': /* bg2312 */
			preg_match_all('!((?:[A-Za-z]+) | (?:[\xa1-\xf7] [\xa1-\xfe] ) )!xs', $str, $m);
			break;
		case 'japanese': /* utf-8 */
		case 'korean':
			preg_match_all('!((?:[\x0-\x7f]+) | (?:[\xc0-\xfd]{1}[\x80-\xbf]+) )!xs', $str, $m);
			break;
	}

	if (!$m) {
		return array();
	}

	$m2 = array();
	foreach (array_unique($m[0]) as $v) {
		if (isset($v[1])) {
			$m2[] = _esc($v);
		}
	}

	return $m2;
}

function text_to_worda($text)
{
	$a = array();
	$text = strip_tags(reverse_fmt($text));

	// Match utf-8 words (remove the \p{N} if you don't want to index words with numbers)
	preg_match_all("/\p{L}[\p{L}\p{N}\p{Mn}\p{Pd}'\x{2019}]*/u", $text, $t1);
	foreach ($t1[0] as $v) {
		if (isset($v[51]) || !isset($v[2])) continue;   // word too long or too short
		$a[] = _esc($v);
	}

	return $a;
}

function index_text($subj, $body, $msg_id)
{
	/* Remove Stuff In Quotes */
	while (preg_match('!{TEMPLATE: post_html_quote_start_p1}(.*?){TEMPLATE: post_html_quote_start_p2}(.*?){TEMPLATE: post_html_quote_end}!is', $body)) {
		$body = preg_replace('!{TEMPLATE: post_html_quote_start_p1}(.*?){TEMPLATE: post_html_quote_start_p2}(.*?){TEMPLATE: post_html_quote_end}!is', '', $body);
	}

	if ($subj && ($w1 = text_to_worda($subj))) {
		$w2 = array_merge($w1, text_to_worda($body));
	} else {
		$w2 = text_to_worda($body);
	}

	if (!$w2) {
		return;
	}

	$w2 = array_unique($w2);

	if (__dbtype__ != 'pgsql') {
		ins_m('{SQL_TABLE_PREFIX}search', 'word', $w2, 'text', 0);
	} else {
		foreach ($w2 as $w) {
			if (!q_singleval("SELECT id FROM {SQL_TABLE_PREFIX}search WHERE word=".$w)) {
				q("INSERT INTO {SQL_TABLE_PREFIX}search (word) VALUES(".$w.")");
			}
		}	
	}	
	if ($subj && $w1) {
		db_li('INSERT INTO {SQL_TABLE_PREFIX}title_index (word_id, msg_id) SELECT id, '.$msg_id.' FROM {SQL_TABLE_PREFIX}search WHERE word IN('.implode(',', $w1).')', $ef);
	}
	db_li('INSERT INTO {SQL_TABLE_PREFIX}index (word_id, msg_id) SELECT id, '.$msg_id.' FROM {SQL_TABLE_PREFIX}search WHERE word IN('.implode(',', $w2).')', $ef);
}
?>
