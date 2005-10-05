<?php
/**
* copyright            : (C) 2001-2004 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: isearch.inc.t,v 1.62 2005/10/05 02:51:14 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; either version 2 of the License, or
* (at your option) any later version.
**/

function mb_word_split($str, $lang)
{
	$m = array();

	switch ($lang) {
		case 'chinese_big5':
			preg_match_all('!((?:[A-Za-z]+) | (?:[\xa1-\xfe] [\x40-\x7e] | [\xa1-\xfe] )!xs', $str, $m);
			break;
		case 'chinese': /* bg2312 */
			preg_match_all('!((?:[A-Za-z]+) | (?:[\xa1-\xf7] [\xa1-\xfe] )!xs', $str, $m);
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

	/* if no good locale, default to splitting by spaces */
	if (!$GLOBALS['good_locale']) {
		$GLOBALS['usr']->lang = 'latvian';
	}

	$text = strip_tags(reverse_fmt($text));
	while (1) {
		switch ($GLOBALS['usr']->lang) {
			case 'chinese_big5':
			case 'chinese':
			case 'japanese':
			case 'korean':
				return mb_word_split($text, $GLOBALS['usr']->lang);
				break;

			case 'latvian':
			case 'russian-1251':
				$t1 = array_unique(preg_split('![\x00-\x40]+!', $text, -1, PREG_SPLIT_NO_EMPTY));
				break;

			default:
				$t1 = array_unique(str_word_count(strtolower($text), 1));
				if ($text && !$t1) { /* fall through to split by special chars */
					$GLOBALS['usr']->lang = 'latvian';
					continue;		
				} 
				break;
		}

		/* this is mostly a hack for php version < 4.3 because isset(string[bad offset]) returns a warning */
		error_reporting(0);
	
		foreach ($t1 as $v) {
			if (isset($v[51]) || !isset($v[2])) continue;
			$a[] = _esc($v);
		}

		error_reporting(2047); /* restore error reporting */

		break;
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
			if (!q_singleval("SELECT id FROM {SQL_TABLE_PREFIX}search WHERE word='".$w."'")) {
				q("INSERT INTO {SQL_TABLE_PREFIX}search (word) VALUES('".$w."')");
			}
		}	
	}	
	if ($subj && $w1) {
		db_li('INSERT INTO {SQL_TABLE_PREFIX}title_index (word_id, msg_id) SELECT id, '.$msg_id.' FROM {SQL_TABLE_PREFIX}search WHERE word IN('.implode(',', $w1).')', $ef);
	}
	db_li('INSERT INTO {SQL_TABLE_PREFIX}index (word_id, msg_id) SELECT id, '.$msg_id.' FROM {SQL_TABLE_PREFIX}search WHERE word IN('.implode(',', $w2).')', $ef);
}
?>