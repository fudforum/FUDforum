<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: smiley.inc.t,v 1.6 2003/04/17 11:53:46 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

$GLOBALS['__SML_CHR_CHK__'] = array("\n"=>1, "\r"=>1, "\t"=>1, " "=>1, "]"=>1, "["=>1, "<"=>1, ">"=>1, "'"=>1, '"'=>1, "("=>1, ")"=>1, "."=>1, ","=>1, "!"=>1, "?"=>1);

function char_check($c)
{
	return (($c === NULL || isset($GLOBALS['__SML_CHR_CHK__'][$c])) ? 1 : 0);
}	

function smiley_to_post($text)
{
	$text_l = strtolower($text);

        $c = uq('SELECT code, '.__FUD_SQL_CONCAT__.'(\'images/smiley_icons/\', img), descr FROM {SQL_TABLE_PREFIX}smiley');
        while ($r = db_rowarr($c)) {
        	$codes = (strpos($r[0], '~') !== FALSE) ? explode('~', strtolower($r[0])) : array(strtolower($r[0]));

		foreach ($sml_codes as $v) {
			$a = 0;
			$len = strlen($v);
			while (($a = strpos($text_l, $v, $a)) !== FALSE) {
				if (!$a || (char_check($text_l[$a - 1]) && char_check($text_l[$a + 1]))) {
					$rep = '<img src="'.$r[1].'" border=0 alt="'.$r[2].'">';
					$text = substr_replace($text_l, $rep, $a, $len);
					$text_l = substr_replace($text_l, $rep, $a, $len);
					$a += strlen($rep);
				} else {
					$a += $len;
				}
			}
		}
	}
        qf($c);

	return $text;
}

function post_to_smiley($text)
{
	$c = uq('SELECT code, '.__FUD_SQL_CONCAT__.'(\'images/smiley_icons/\', img), descr FROM {SQL_TABLE_PREFIX}smiley');
	while ($r = db_rowarr($c)) {
		$im = '<img src="'.$r[1].'" border=0 alt="'.$r[2].'">';
		$re[$im] = (($p = strpos($r[0], '~')) !== FALSE) ? substr($r[0], 0, $p) : $r[0];
	}
	qf($c);
	
	return (isset($re) ? strtr($text, $re) : $text);
}
?>