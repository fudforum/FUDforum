<?php
/***************************************************************************
* copyright            : (C) 2001-2003 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: smiley.inc.t,v 1.13 2003/11/14 10:50:19 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; either version 2 of the License, or
* (at your option) any later version.
***************************************************************************/

$GLOBALS['__SML_CHR_CHK__'] = array("\n"=>1, "\r"=>1, "\t"=>1, " "=>1, "]"=>1, "["=>1, "<"=>1, ">"=>1, "'"=>1, '"'=>1, "("=>1, ")"=>1, "."=>1, ","=>1, "!"=>1, "?"=>1);

function smiley_to_post($text)
{
	$text_l = strtolower($text);

        $c = uq('SELECT code, '.__FUD_SQL_CONCAT__.'(\'images/smiley_icons/\', img), descr FROM {SQL_TABLE_PREFIX}smiley');
        while ($r = db_rowarr($c)) {
        	$codes = (strpos($r[0], '~') !== false) ? explode('~', strtolower($r[0])) : array(strtolower($r[0]));

		foreach ($codes as $v) {
			$a = 0;
			$len = strlen($v);
			while (($a = strpos($text_l, $v, $a)) !== false) {
				if ((!$a || isset($GLOBALS['__SML_CHR_CHK__'][$text_l[$a - 1]])) && ((@$ch = $text_l[$a + $len]) == "" || isset($GLOBALS['__SML_CHR_CHK__'][$ch]))) {
					$rep = '<img src="'.$r[1].'" border=0 alt="'.$r[2].'">';
					$text = substr_replace($text, $rep, $a, $len);
					$text_l = substr_replace($text_l, $rep, $a, $len);
					$a += strlen($rep);
				} else {
					$a += $len;
				}
			}
		}
	}

	return $text;
}

function post_to_smiley($text)
{
	$c = uq('SELECT code, '.__FUD_SQL_CONCAT__.'(\'images/smiley_icons/\', img), descr FROM {SQL_TABLE_PREFIX}smiley');
	while ($r = db_rowarr($c)) {
		$im = '<img src="'.$r[1].'" border=0 alt="'.$r[2].'">';
		$re[$im] = (($p = strpos($r[0], '~')) !== false) ? substr($r[0], 0, $p) : $r[0];
	}

	return (isset($re) ? strtr($text, $re) : $text);
}
?>