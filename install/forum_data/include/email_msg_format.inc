<?php
/**
* copyright            : (C) 2001-2004 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: email_msg_format.inc,v 1.12 2004/11/29 17:17:08 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; either version 2 of the License, or
* (at your option) any later version.
**/

function plain_text(&$data)
{
	$data = post_to_smiley($data);

	/* handle quote tags */
	while (($s = strpos($data, '<table border="0" align="center" width="90%" cellpadding="3" cellspacing="1"><tr><td class="SmallText"><b>')) !== false) {
		/* for recursive quote tags we need to find the start of the very last tag */
		while (($s2 = strpos($data, '<table border="0" align="center" width="90%" cellpadding="3" cellspacing="1"><tr><td class="SmallText"><b>', $s+1)) !== false) {
			$s = $s2;
		}

		if (($e1 = strpos($data, '</b></td></tr><tr><td class="quote"><br />', $s)) === false) {
			break;
		}
		if (($e2 = strpos($data, '<br /></td></tr></table>', $e1)) == false) {
			break;
		}

		$str = trim(strip_tags(substr($data, $s, ($e1 - $s)))) . "\n";
		$str .= "> " . str_replace("\n", "\n> ", trim(strip_tags(substr($data, $e1, ($e2 - $e1))))) . "\n";

		$data = substr_replace($data, $str, $s, ($e2 + strlen('<br /></td></tr></table>') - $s));
	}

	$data = strip_tags($data);
	reverse_fmt($data);

	return html_entity_decode($data);
}
?>