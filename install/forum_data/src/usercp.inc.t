<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: usercp.inc.t,v 1.7 2003/03/29 11:40:09 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/
		
	$ret_to = '&amp;returnto='.urlencode(!empty($GLOBALS['returnto'])?urldecode($GLOBALS['returnto']):$_SERVER['REQUEST_URI']);

	if (!_uid) {
		$login_n_logout = '{TEMPLATE: login}';
		$register_n_profile = '{TEMPLATE: register}';
		$admin_control_panel = $private_msg = '';
	} else {
		if ($usr->is_mod == 'A') {
			$admin_control_panel = '{TEMPLATE: admin_control_panel}';
		} else {
			$admin_control_panel = '';
		}
		$login_n_logout = '{TEMPLATE: logout}';
		$register_n_profile = '{TEMPLATE: profile}';
		
		if ($GLOBALS['PM_ENABLED']=='Y') {
			$c = q_singleval('SELECT count(*) FROM {SQL_TABLE_PREFIX}pmsg WHERE duser_id='._uid.' AND folder_id=\'INBOX\' AND read_stamp=0');
			$private_msg = $c ? '{TEMPLATE: private_msg_unread}' : '{TEMPLATE: private_msg_empty}';
		} else {
			$private_msg = '';
		}
	}
	if ($GLOBALS['MEMBER_SEARCH_ENABLED'] == 'Y' || $usr->is_mod == 'A') {
		$member_search = '{TEMPLATE: member_search}';
	} else {
		$member_search = '';
	}
?>