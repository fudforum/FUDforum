<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: email_msg_format.inc,v 1.4 2003/10/05 22:19:56 hackie Exp $
****************************************************************************

****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

function handle_quote_tag($matches)
{
	$ret = $matches[1];

	if (trim($matches[2]) != 'Quote:') {
		$ret .= trim(strip_tags($matches[2]))."\n";
	}

	$ret .= "> ".str_replace("\n", "\n> ", trim(strip_tags($matches[3])))."\n";

	return $ret;
}

function plain_text(&$data)
{
	$data = post_to_smiley($data);

	while (preg_match('!<table border="0" align="center" width="90%" cellpadding="3" cellspacing="1"><tr><td class="SmallText"><b>(.*?)</b></td></tr><tr><td class="quote"><br>(.*?)<br></td></tr></table>!mis', $data)) {
		$data = preg_replace_callback('!(.*)<table border="0" align="center" width="90%" cellpadding="3" cellspacing="1"><tr><td class="SmallText"><b>(.*?)</b></td></tr><tr><td class="quote"><br>(.*?)<br></td></tr></table>!mis', 'handle_quote_tag', $data, 1);
	}

	$data = strip_tags($data);
	reverse_fmt($data);

	return $data;
}
?>