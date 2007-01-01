<?php
/**
* copyright            : (C) 2001-2007 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: mlist_post.inc,v 1.18 2007/01/01 18:23:42 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; version 2 of the License.
**/

function mail_list_post($list_email, $from, $subject, $body, $msg_id, $reply_to=null, $attch=null, $attach_mime=null, $additional_headers=null)
{
	$multi_part = 0;
	$final_body = '';

	if (is_array($attch)) {
		$multi_part = 1;
		$boundry = md5(get_random_value());

		$final_body = "\n--".$boundry."\nContent-Type: text/plain;\n  charset=\"".$GLOBALS['CHARSET']."\"\nContent-Transfer-Encoding: 8bit\n\n".$body."\n";

		foreach ($attch as $file_name => $file_data) {
			$final_body .= "\n--".$boundry."\nContent-Type: ".$attach_mime[$file_name]."; name=\"$file_name\"\nContent-Transfer-Encoding: base64\nContent-Disposition: attachment; filename=\"".$file_name."\"\n\n".chunk_split(base64_encode($file_data))."\n\n";
		}
		$final_body .= "\n--".$boundry."--\n";
	} else {
		$final_body = $body;
	}

	if (!$multi_part) {
		$header = "Content-Type: text/plain;\n  charset=\"".$GLOBALS['CHARSET']."\"\nContent-Transfer-Encoding: 8bit\n";
	} else {
		$header = "MIME-Version: 1.0\nContent-Type: Multipart/Mixed;\n  boundary=\"$boundry\"\n";
	}

	if ($GLOBALS['FORUM_TITLE']) {
		$header .= "Organization: ".$GLOBALS['FORUM_TITLE']."\n";
	}
	if ($reply_to) {
		$header .= "In-Reply-To: <".$reply_to.">\n";
	}
	$header .= "X-FUDforum: ".md5($GLOBALS['WWW_ROOT'])." <$msg_id>\n";

	/* Set our own message id */
	$tmp = parse_url($GLOBALS['WWW_ROOT']);
	$mid = dechex($msg_id).'.'.dechex(__request_timestamp__).'@'. $tmp['host'];
	q("UPDATE ".$GLOBALS['DBHOST_TBL_PREFIX']."msg SET mlist_msg_id="._esc($mid)." WHERE id=".$msg_id);
	$header .= "Message-ID: <".$mid.">\n";

	if (is_array($additional_headers)) {
		$header .= implode("\n", $additional_headers);
	} else {
		$header .= trim($additional_headers) . "\n";
	}

	send_email($from, $list_email, $subject, $final_body, $header);
}
?>