<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: usercp.inc.t,v 1.6 2003/03/28 12:58:39 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/
		
	$adm_str = $rid = $pm = '';
	$ret_to = '&amp;returnto='.urlencode(!empty($GLOBALS['returnto'])?urldecode($GLOBALS['returnto']):$GLOBALS['HTTP_SERVER_VARS']['REQUEST_URI']);

	if ( empty($GLOBALS['usr']) ) {
		$login_n_logout = '{TEMPLATE: login}';
		$register_n_profile = '{TEMPLATE: register}';
	} else {
		if( $GLOBALS['usr']->is_mod == 'A' ) $admin_control_panel = '{TEMPLATE: admin_control_panel}';
		$login_n_logout = '{TEMPLATE: logout}';
		$register_n_profile = '{TEMPLATE: profile}';
		
		if ( $GLOBALS['PM_ENABLED']=='Y' ) {
			$c = q_singleval("SELECT count(*) FROM {SQL_TABLE_PREFIX}pmsg WHERE duser_id=".$GLOBALS["usr"]->id." AND folder_id='INBOX' AND read_stamp=0");
			$private_msg = ( $c ) ? '{TEMPLATE: private_msg_unread}' : '{TEMPLATE: private_msg_empty}';
		}
	}
	if ($GLOBALS['MEMBER_SEARCH_ENABLED'] == 'Y' || $GLOBALS['usr']->is_mod == 'A') {
		$member_search = '{TEMPLATE: member_search}';
	} else {
		$member_search = '';
	}
?>