<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: mlist_post.inc,v 1.2 2003/01/15 13:09:54 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

function mail_list_post($list_email, $from, $subject, $body, $msg_id, $reply_to='', $attch='', $additional_headers=array())
{
	$multi_part = 0;
	$final_body = '';

	if( is_array($attch) ) {
		$multi_part = 1;
		$boundry = md5(get_random_value());
	
		$final_body = "--".$boundry."\nContent-Type: text/plain;\n  charset=\"".$GLOBALS['CHARSET']."\"\nContent-Transfer-Encoding: 8bit\n\n".$body."\n";
		
		foreach( $attch as $file_name => $file_data ) {
			$final_body .= "--".$boundry."\nContent-Type: ".$file_data[1].";\n  name=\"$file_name\"\nContent-Transfer-Encoding: base64\nContent-Disposition: attachment; filename=\"$file_name\"\n\n".wordwrap(base64_encode($file_data[0]), 76, "\n", 1)."\n\n";
		}
		$final_body .= "\n--".$boundry."--\n";
	}
	else
		$final_body = $body;
		
	if( !$multi_part )
		$header = "Content-Type: text/plain;\n  charset=\"".$GLOBALS['CHARSET']."\"\nContent-Transfer-Encoding: 8bit\n";
	else
		$header = "Content-Type: Multipart/Mixed;\n  boundary=\"$boundry\"\n";
		
	if( $GLOBALS['FORUM_TITLE'] ) $header .= "Organization: ".$GLOBALS['FORUM_TITLE']."\n";	
	if( $reply_to ) $header .= "In-Reply-To: <".$reply_to.">\n";
	$header .= "X-FUDforum: ".md5($GLOBALS['WWW_ROOT'])." <$msg_id>\n";

	if (count($additional_headers)) {
		foreach ($additional_headers as $addh) {
			if (($addh = trim($addh))) {
				$header .= $addh . "\n";
			}
		}
	}
	
	send_email($from, $list_email, $subject, $final_body, $header);
}
?>