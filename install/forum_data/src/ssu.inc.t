<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: ssu.inc.t,v 1.3 2003/04/12 13:54:30 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/
function send_status_update($uid, $ulogin, $uemail, $title, $msg)
{
	if ($GLOBALS['PM_ENABLED'] == 'Y') {
		if (defined('no_inline')) {
			fud_use('private.inc');
		}
		$GLOBALS['recv_user_id'][] = $uid;
		$pmsg = new fud_pmsg;
		$pmsg->to_list = addslashes($ulogin);
		$pmsg->ouser_id = _uid;
		$pmsg->post_stamp = __request_timestamp__;
		$pmsg->subject = addslashes($title);
		list($pmsg->foff, $pmsg->length) = write_pmsg_body(nl2br($msg));
		$pmsg->send_pmsg();	
	} else {
		if (defined('no_inline')) {
			fud_use('iemail.inc');
		}
		
		send_email($GLOBALS['NOTIFY_FROM'], $uemail, $title, $msg);
	}
}
?>