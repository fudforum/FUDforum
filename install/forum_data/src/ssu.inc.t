<?php
/**
* copyright            : (C) 2001-2006 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: ssu.inc.t,v 1.22 2006/09/19 14:37:55 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; version 2 of the License.
**/

function send_status_update($uid, $ulogin, $uemail, $title, $msg)
{
	if ($GLOBALS['FUD_OPT_1'] & 1024) {
		if (defined('no_inline')) {
			fud_use('private.inc');
			fud_use('iemail.inc');
			fud_use('rev_fmt.inc');
		}
		$GLOBALS['recv_user_id'] = (array)$uid;
		$pmsg = new fud_pmsg;
		$pmsg->to_list = $ulogin;
		$pmsg->ouser_id = _uid;
		$pmsg->post_stamp = __request_timestamp__;
		$pmsg->subject = $title;
		$pmsg->host_name = 'NULL';
		$pmsg->ip_addr = '0.0.0.0';
		list($pmsg->foff, $pmsg->length) = write_pmsg_body(nl2br($msg));
		$pmsg->send_pmsg();
		return;
	}

	if (defined('no_inline')) {
		fud_use('iemail.inc');
	}
	send_email($GLOBALS['NOTIFY_FROM'], $uemail, $title, $msg);
}
?>