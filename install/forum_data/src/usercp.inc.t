<?php
/***************************************************************************
* copyright            : (C) 2001-2003 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: usercp.inc.t,v 1.18 2003/11/14 10:50:20 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; either version 2 of the License, or
* (at your option) any later version.
***************************************************************************/

	if (!__fud_real_user__) {
		$login_n_logout = '{TEMPLATE: login}';
		$register_n_profile = '{TEMPLATE: register}';
		$admin_control_panel = $private_msg = '';
	} else {
		$admin_control_panel = $usr->users_opt & 1048576 ? '{TEMPLATE: admin_control_panel}' : '';
		$login_n_logout = '{TEMPLATE: logout}';
		$register_n_profile = '{TEMPLATE: profile}';

		if ($FUD_OPT_1 & 1024) {
			$c = q_singleval('SELECT count(*) FROM {SQL_TABLE_PREFIX}pmsg WHERE duser_id='._uid.' AND fldr=1 AND read_stamp=0');
			$private_msg = $c ? '{TEMPLATE: private_msg_unread}' : '{TEMPLATE: private_msg_empty}';
		} else {
			$private_msg = '';
		}
	}
 	$member_search = ($FUD_OPT_1 & 8388608 || (_uid && $FUD_OPT_1 & 4194304) || $usr->users_opt & 1048576) ? '{TEMPLATE: member_search}' : '';
 	$u_forum_search = $FUD_OPT_1 & 16777216 ? '{TEMPLATE: u_forum_search}' : '';
?>