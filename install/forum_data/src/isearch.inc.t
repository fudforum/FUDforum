<?php
/***************************************************************************
* copyright            : (C) 2001-2004 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: isearch.inc.t,v 1.38 2004/08/09 13:06:56 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; either version 2 of the License, or
* (at your option) any later version.
***************************************************************************/


function delete_msg_index($msg_id)
{
	q('DELETE FROM {SQL_TABLE_PREFIX}index WHERE msg_id='.$msg_id);
	q('DELETE FROM {SQL_TABLE_PREFIX}title_index WHERE msg_id='.$msg_id);
}

function mb_word_split($str)
{
	$m = array();
	$lang = $GLOBALS['usr']->lang == 'chinese' ? 'EUC-CN' : 'BIG-5';

	if (extension_loaded('iconv')) {
		preg_match_all('!(\w)!u', @iconv($lang, 'UTF-8', $str), $m);
	} else if (extension_loaded('mbstring')) {
		preg_match_all('!(\w)!u', @mb_convert_encoding($str, 'UTF-8', $lang), $m);
	} else { /* poor man's alternative to proper multi-byte support */
		preg_match_all("!([\\1-\\255]{1,2})!", $str, $m);
	}

	if (!$m) {
		return array();
	}

	$m = array_unique($m[0]);
	foreach ($m as $v) {
		if (isset($v[1])) {
			$m2[] = "'".addslashes($v)."'";
		}
	}

	return isset($m2) ? $m2 : array();
}

if (!function_exists("str_word_count")) {
	function str_word_count($str, $a)
	{
		return explode(' ', trim(preg_replace(array('!\W!', '!\s+!'), array(' ', ' '), $str)));
	}
}

function text_to_worda($text)
{
	$a = array();

	while (1) {
		reverse_fmt($text);
	
		switch ($GLOBALS['usr']->lang) {
			case 'chinese_big5':
			case 'chinese':
				return array_unique(mb_word_split($text));
		
			case 'japanese':
				preg_match_all('!(\w)!u', $text, $tmp);
				return array_unique($tmp[0]);

			case 'latvian':
			case 'russian-1251':
				$t1 = array_unique(preg_split('![\x00-\x64]+!', $text, -1, PREG_SPLIT_NO_EMPTY));
				break;

			default:
				$t1 = array_unique(str_word_count(strip_tags(strtolower($text)), 1));
				break;
		}

		/* this is mostly a hack for php verison < 4.3 because isset(string[bad offset]) returns a warning */
		error_reporting(0);
	
		foreach ($t1 as $v) {
			if (isset($v[51]) || !isset($v[2])) continue;
			$a[] = "'".addslashes($v)."'";
		}

		error_reporting(E_ALL); /* restore error reporting */

		break;
	}

	return $a;
}

function index_text($subj, $body, $msg_id)
{
	/* Remove Stuff In Quotes */
	while (preg_match('!<table border="0" align="center" width="90%" cellpadding="3" cellspacing="1"><tr><td class="SmallText"><b>(.*?)</b></td></tr><tr><td class="quote"><br>(.*?)<br></td></tr></table>!is', $body)) {
		$body = preg_replace('!<table border="0" align="center" width="90%" cellpadding="3" cellspacing="1"><tr><td class="SmallText"><b>(.*?)</b></td></tr><tr><td class="quote"><br>(.*?)<br></td></tr></table>!is', '', $body);
	}

	$w1 = text_to_worda($subj);
	$w2 = $w1 ? array_merge($w1, text_to_worda($body)) : text_to_worda($body);

	if (!$w2) {
		return;
	}

	$w2 = array_unique($w2);
	if (__dbtype__ == 'mysql') {
		ins_m('{SQL_TABLE_PREFIX}search', 'word', $w2);
	} else {
		if (!defined('search_prep')) {
			define('search_prep', 'PREPARE {SQL_TABLE_PREFIX}srch_ins (text) AS INSERT INTO {SQL_TABLE_PREFIX}search (word) VALUES($1)');
			define('search_prep2', 'PREPARE {SQL_TABLE_PREFIX}srch_sel (text) AS SELECT id FROM {SQL_TABLE_PREFIX}search WHERE word=$1');
			pg_query(fud_sql_lnk, search_prep);
			pg_query(fud_sql_lnk, search_prep2);
		}
		foreach ($w2 as $w) {			
			if (pg_num_rows(pg_query(fud_sql_lnk, "EXECUTE {SQL_TABLE_PREFIX}srch_sel (".$w.")")) < 1) {
				pg_query(fud_sql_lnk, "EXECUTE {SQL_TABLE_PREFIX}srch_ins (".$w.")");
			}
		}
	}

	if ($w1) {
		db_li('INSERT INTO {SQL_TABLE_PREFIX}title_index (word_id, msg_id) SELECT id, '.$msg_id.' FROM {SQL_TABLE_PREFIX}search WHERE word IN('.implode(',', $w1).')', $ef);
	}
	db_li('INSERT INTO {SQL_TABLE_PREFIX}index (word_id, msg_id) SELECT id, '.$msg_id.' FROM {SQL_TABLE_PREFIX}search WHERE word IN('.implode(',', $w2).')', $ef);
}
?>