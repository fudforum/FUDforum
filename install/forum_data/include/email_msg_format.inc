<?php
/**
* copyright            : (C) 2001-2004 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: email_msg_format.inc,v 1.18 2005/06/20 03:35:13 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; either version 2 of the License, or
* (at your option) any later version.
**/

function plain_text(&$data, $qs, $qm, $qe)
{
	$data = post_to_smiley($data);

	/* handle quote tags */
	while (($s = strpos($data, $qs)) !== false) {
		/* for recursive quote tags we need to find the start of the very last tag */
		while (($s2 = strpos($data, $qs, $s+1)) !== false) {
			$s = $s2;
		}

		if (($e1 = strpos($data, $qm, $s)) === false) {
			break;
		}
		if (($e2 = strpos($data, $qe, $e1)) == false) {
			break;
		}

		$str = trim(strip_tags(substr($data, $s, ($e1 - $s)))) . "\n";
		$str .= "> " . str_replace("\n", "\n> ", trim(strip_tags(substr($data, $e1, ($e2 - $e1))))) . "\n";

		$data = substr_replace($data, $str, $s, ($e2 + strlen($qe) - $s));
	}

	/* handle links, the wordwrapping may have corrupted the caption */
	while (($s = strpos($data, '<a href="'))) {
		//$s += 9;
		if (!($e = strpos($data, '</a>', $s))) {
			break;
		}
		if (!($e2 = strpos($data, '"', $s + 9))) { // 9 == <a href="
			break;
		}
		$data = substr_replace($data, substr($data, $s + 9, ($e2 - $s - 9)), $s, ($e + 4 - $s));
	}

	/* handle encoded e-mails */
	if (strpos($data, '&#64;') !== false) {
		$data = str_replace('&#64;', '@', $data);
	}
	if (strpos($data, '&#58;&#47;&#47;') !== false) {
		$data = str_replace('&#58;&#47;&#47;', '://', $data);
	}

	return html_entity_decode(reverse_fmt(strip_tags($data)));
}
?>