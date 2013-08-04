<?php
/**
* copyright            : (C) 2001-2013 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id$
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; version 2 of the License.
**/

/** Prepare E-mail and send it to the forum's Mailing List. */
function mail_list_post($list_email, $from, $subject, $body, $msg_id, $reply_to=null, $attch=null, $attach_mime=null, $additional_headers=null)
{
	$multi_part = 0;
	$final_body = '';

	/* Handle file attachments. */
	if (is_array($attch)) {
		$multi_part = 1;
		$boundry = md5(get_random_value());

		$final_body = "\n--". $boundry ."\nContent-Type: text/plain;\n  charset=\"". $GLOBALS['CHARSET'] ."\"\nContent-Transfer-Encoding: 8bit\n\n". $body ."\n";

		foreach ($attch as $file_name => $file_data) {
			$final_body .= "\n--". $boundry ."\nContent-Type: ". $attach_mime[$file_name] ."; name=\"$file_name\"\nContent-Transfer-Encoding: base64\nContent-Disposition: attachment; filename=\"". $file_name ."\"\n\n". chunk_split(base64_encode($file_data)) ."\n\n";
		}
		$final_body .= "\n--". $boundry ."--\n";
	} else {
		$final_body = $body;
	}

	/* Add MIME multipart headers. */
	if (!$multi_part) {
		$header = "MIME-Version: 1.0\nContent-Type: text/plain;\n  charset=\"". $GLOBALS['CHARSET'] ."\"\nContent-Transfer-Encoding: 8bit\n";
	} else {
		$header = "MIME-Version: 1.0\nContent-Type: Multipart/Mixed;\n  boundary=\"$boundry\"\n";
	}

	/* Add Organization: header (use the forum title). */
	if ($GLOBALS['FORUM_TITLE']) {
		$header .= 'Organization: '. $GLOBALS['FORUM_TITLE'] ."\n";
	}
	
	/* Add Reply-To: header. */
	if ($reply_to) {
		$header .= 'In-Reply-To: <'. $reply_to .">\n";
	}
	$header .= 'X-FUDforum: '. md5($GLOBALS['WWW_ROOT']) ." <$msg_id>\n";

	/* Generate a Message-ID for our own message. */
	$tmp = parse_url($GLOBALS['WWW_ROOT']);
	$mid = dechex($msg_id) .'.'. dechex(__request_timestamp__) .'@'. $tmp['host'];
	q('UPDATE '. $GLOBALS['DBHOST_TBL_PREFIX'] .'msg SET mlist_msg_id='. _esc($mid) .' WHERE id='. $msg_id);
	$header .= 'Message-ID: <'. $mid .">\n";

	/* Add References: header for mail threading. */
	$msg_ids = db_all('SELECT mlist_msg_id FROM '. $GLOBALS['DBHOST_TBL_PREFIX'] .'msg WHERE thread_id=(SELECT thread_id FROM '. $GLOBALS['DBHOST_TBL_PREFIX'] .'msg WHERE id='. $msg_id .') AND apr=1 AND mlist_msg_id IS NOT NULL ORDER BY post_stamp');
	// Take off the most recent message id, because it's the one we're replying to.
	array_pop($msg_ids);
	// The References header is limited to 10 Message IDs. Keep the first and remove 2..N so that we end up with 10.
	if (count($msg_ids) > 10) {
		array_splice($msg_ids, 1, count($msg_ids)-10);
	}
	if ($msg_ids) {
		// Appends each message-id to the References header.
		$reference_header = '';
		while (list(, $msg_id) = each($msg_ids)) {
			$reference_header .= '<'. $msg_id .'> ';
		}
		$header .= 'References: '. $reference_header ."\n";
	}

	/* Append any other specified headers. */
	if (is_array($additional_headers)) {
		$header .= implode("\n", $additional_headers);
	} else {
		$header .= trim($additional_headers) ."\n";
	}

	/* Send the E-mail to the Mailing List. */
	send_email($from, $list_email, $subject, $final_body, $header);
}

?>
