<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: finduser.php.t,v 1.12 2002/11/24 22:41:02 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

	{PRE_HTML_PHP}	
	
	if ($MEMBER_SEARCH_ENABLED != 'Y' || $usr->is_mod != 'A') {
		std_error('disabled');
		exit();
	}
	
	if ( !empty($js_redr) ) define('plain_form', 1);
	
	$TITLE_EXTRA = ': {TEMPLATE: finduser_title}';
	if ( isset($ses) ) $ses->update('{TEMPLATE: finduser_update}');
	{POST_HTML_PHP}	

	$usr_login = ( !empty($usr_login) ) ? trim(stripslashes($usr_login)) : '';
	$usr_email = ( !empty($usr_email) ) ? trim(stripslashes($usr_email)) : '';
	
	if (empty($start) || !is_numeric($start)) $start = 0;
	if (empty($count) || !is_numeric($count)) $count = $GLOBALS['MEMBERS_PER_PAGE'];
	
	if( !empty($pc) ) 
		$ord = "posted_msg_count DESC";
	else if( !empty($us) ) 
		$ord = "alias";
	else
		$ord = "id DESC";	
	
	$np = 1;
	if ( !empty($btn_submit) ) {
		$usr_login = str_replace('\\', '\\\\', $usr_login);
		$usr_email = str_replace('\\', '\\\\', $usr_email);
	
		if ( $usr_login )
			$qry = "WHERE LOWER(alias) LIKE '".strtolower(addslashes($usr_login))."%'";
		else if ( $usr_email ) 
			$qry = "WHERE LOWER(email) LIKE '".strtolower(addslashes($usr_email))."%'";
		else 
			$qry = '';	
			
		$usr_login = str_replace('\\\\', '\\', $usr_login);
		$usr_email = str_replace('\\\\', '\\', $usr_email);
			
		if ( $start || $count ) $lim = "LIMIT ".qry_limit($count, $start);
		
		$returnto = urlencode('{ROOT}?t=finduser&amp;btn_submit=Find&start='.$start.'&amp;'._rsid.'&amp;count='.$count);
		$res = q("SELECT * FROM {SQL_TABLE_PREFIX}users ".$qry." ORDER BY ".$ord." ".$lim);
		$find_user_data = '';
		if ( !db_count($res) ){
			$find_user_data = '{TEMPLATE: find_user_no_results}';
		}
		else {
			$np = 0;
			while ( $obj = db_rowobj($res) ) {
				$pm_link = ( $GLOBALS['PM_ENABLED'] == 'Y' && isset($usr) ) ? '{TEMPLATE: pm_link}' : '';
				$homepage_link = strlen($obj->home_page) ? '{TEMPLATE: homepage_link}' : '';
				$email_link = ($GLOBALS["ALLOW_EMAIL"]=='Y' && $obj->email_messages=='Y') ? '{TEMPLATE: email_link}' : '';
				$find_user_data .= '{TEMPLATE: find_user_entry}';
			}
		}
		
		qf($res);
	}
	else 
		$user_login = $user_email = '';

	if ( empty($np) ) {
		$total = q_singleval("SELECT count(*) FROM {SQL_TABLE_PREFIX}users ".(isset($qry)?$qry:''));
		if ( $total && !empty($btn_submit) ) $pager = tmpl_create_pager($start, $count, $total, '{ROOT}?t=finduser&amp;usr_login='.urlencode($usr_login).'&amp;'._rsid.'&amp;usr_email='.$usr_email.'&amp;pc='.(empty($pc)?'':$pc).'&amp;us='.(empty($us)?'':$us).'&amp;btn_submit=Find&amp;js_redr='.(empty($js_redr)?'':$js_redr).'&amp;append='.(empty($append)?'':$append));
	}
	
	{POST_PAGE_PHP_CODE}
?>
{TEMPLATE: FINDUSER_PAGE}