<?php
/**
* copyright            : (C) 2001-2020 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id$
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; version 2 of the License.
**/

function str_word_count_utf8($text) {
	if (@preg_match('/\p{L}/u', 'a') == 1) {	// PCRE unicode support is turned on
		// Match utf-8 words to index:
		// - If you also want to index numbers, use regex "/[\p{N}\p{L}][\p{L}\p{N}\p{Mn}\p{Pd}'\x{2019}]*/u".
		// - Remove the \p{N} if you don't want to index words with numbers in them.
		preg_match_all("/\p{L}[\p{L}\p{N}\p{Mn}\p{Pd}'\x{2019}]*/u", $text, $m);
		return $m[0];
	} else {
		return str_word_count($text, 1);
	}
}

function text_to_worda($text, $minlen=2, $maxlen=51, $uniq=0)
{
	$words = array();
	$text = strtolower(strip_tags(reverse_fmt($text)));

	// Throw away words that are too short or too long.
        if (!isset($minlen)) $minlen = 2;
        if (!isset($maxlen)) $maxlen = 51;

	// Languages like Chinese, Japanese and Korean can have very short and very long words.
	$lang = isset($GLOBALS['usr']->lang) ? $GLOBALS['usr']->lang : '';
	if ($lang == 'zh-hans' || $lang == 'zh-hant' || $lang == 'ja' || $lang == 'ko') {
		$minlen = 0;
		$maxlen = 100;
	}

	$t1 = str_word_count_utf8($text, 1);
	foreach ($t1 as $word) {
		if (isset($word[$maxlen]) || !isset($word[$minlen])) continue;	// Check wWord length.
		$word = _esc($word);

		// Count the frequency of each unique word.
	        if (isset($words[$word])) { 
           		$words[$word]++;
		} else {
			$words[$word] = 1;
		}
	}

	// Return unique words, with or without word counts.
	return $uniq ? $words : array_keys($words);
}

function index_text($subj, $body, $msg_id)
{
	// Remove stuff in [quote] tags.
	while (preg_match('!{TEMPLATE: post_html_quote_start_p1}(.*?){TEMPLATE: post_html_quote_start_p2}(.*?){TEMPLATE: post_html_quote_end}!is', $body)) {
		$body = preg_replace('!{TEMPLATE: post_html_quote_start_p1}(.*?){TEMPLATE: post_html_quote_start_p2}(.*?){TEMPLATE: post_html_quote_end}!is', '', $body);
	}

        // Remove quotes imported Usenet/ Mailing lists.
        while (preg_match('/<font color="[^"]*">&gt;[^<]*<\/font><br \/>/s', $body)) {
                $body = preg_replace('/<font color="[^"]*">&gt;[^<]*<\/font><br \/>/s', '', $body);
        }

	// Spilt text into word arrays, note how $subj is repeated for increaded relevancy.
	$w1 = text_to_worda($subj, null, null, 1);
	$w2 = text_to_worda($subj .' '. $subj .' '. $body, null, null, 1);
	if (!$w2) {
		return;
	}

	// Register word so that we can get an id.
	ins_m('{SQL_TABLE_PREFIX}search', 'word', 'text', array_keys($w2));

	// Populate title index
	if ($subj && $w1) {
		foreach ($w1 as $word => $count) {
			try {
				q('INSERT INTO {SQL_TABLE_PREFIX}title_index (word_id, msg_id, frequency) SELECT id, '. $msg_id .','. $count .' FROM {SQL_TABLE_PREFIX}search WHERE word = '. $word);
			} catch(Exception $e) {}

		}
	}

	// Populate index.
	foreach ($w2 as $word => $count) {
		try {
			q('INSERT INTO {SQL_TABLE_PREFIX}index (word_id, msg_id, frequency) SELECT id, '. $msg_id .','. $count .' FROM {SQL_TABLE_PREFIX}search WHERE word = '. $word);
		} catch(Exception $e) {}
	}

	// Clear search cache.
	q('DELETE FROM {SQL_TABLE_PREFIX}search_cache');
	// "WHERE msg_id='. $msg_id" for better performance, but newly indexed text will not be immediately searchable.
}


?>
