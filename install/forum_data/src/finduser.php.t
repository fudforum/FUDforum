<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: finduser.php.t,v 1.2 2002/06/18 16:12:36 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/
/*#? User Locator Page */

	include_once "GLOBALS.php";
	{PRE_HTML_PHP}	
	
	if ( $MEMBER_SEARCH_ENABLED != 'Y' ) {
		std_error('disabled');
		exit();
	}
	
	if ( !empty($js_redr) ) define('plain_form', 1);
	
	$TITLE_EXTRA = ': {TEMPLATE: finduser_title}';
	if ( isset($ses) ) $ses->update('{TEMPLATE: finduser_update}');
	{POST_HTML_PHP}	

	$usr_login = ( !empty($usr_login) ) ? trim(stripslashes($usr_login)) : '';
	$usr_email = ( !empty($usr_email) ) ? trim(stripslashes($usr_email)) : '';
	
	$user_login = htmlspecialchars($usr_login);
	$user_email = htmlspecialchars($usr_email);

	if ( empty($start) ) $start = 0;
	if ( empty($count) ) $count = $GLOBALS['MEMBERS_PER_PAGE'];
	
	if( !empty($pc) ) 
		$ord = "posted_msg_count DESC";
	else if( !empty($us) ) 
		$ord = "login";
	else
		$ord = "id DESC";	
	
	$np = 1;
	if ( !empty($btn_submit) ) {
		if ( $usr_login )
			$qry = "WHERE login LIKE '".addslashes($usr_login)."%'";
		else if ( $usr_email ) 
			$qry = "WHERE email LIKE '".addslashes($usr_email)."%'";
		else 
			$qry = '';	
			
		if ( $start || $count ) 
			$lim = "LIMIT ".$start.", ".$count;
		
		$returnto = urlencode('{ROOT}?t=finduser&btn_submit=Find&start='.$start.'&'._rsid.'&count='.$count);
		$res = Q("SELECT * FROM {SQL_TABLE_PREFIX}users ".$qry." ORDER BY ".$ord." ".$lim);
		$find_user_data = '';
		if ( !DB_COUNT($res) ){
			$find_user_data = '{TEMPLATE: find_user_no_results}';
		}
		else {
			$np = 0;
			$i=0;
			while ( $obj = DB_ROWOBJ($res) ) {
				$pm_link = ( $GLOBALS['PM_ENABLED'] == 'Y' && isset($usr) ) ? '{TEMPLATE: pm_link}' : '';
				$homepage_link = strlen($obj->home_page) ? '{TEMPLATE: homepage_link}' : '';
				$email_link = ($GLOBALS["ALLOW_EMAIL"]=='Y' && $obj->email_messages=='Y') ? '{TEMPLATE: email_link}' : '';
				$class = $i%2?'RowStyleA':'RowStyleB';
				$find_user_data .= '{TEMPLATE: find_user_entry}';
				$i++;
			}
		}
		
		QF($res);
	}

	if ( empty($np) ) {
		$total = Q_SINGLEVAL("SELECT count(*) FROM {SQL_TABLE_PREFIX}users ".(isset($qry)?$qry:''));
		if ( $total && !empty($btn_submit) ) $pager = tmpl_create_pager($start, $count, $total, '{ROOT}?t=finduser&usr_login='.urlencode($usr_login).'&'._rsid.'&usr_email='.$usr_email.'&pc='.(empty($pc)?'':$pc).'&us='.(empty($us)?'':$us).'&btn_submit=Find&js_redr='.(empty($js_redr)?'':$js_redr).'&append='.(empty($append)?'':$append));
	}
	
	{POST_PAGE_PHP_CODE}
?>
{TEMPLATE: FINDUSER_PAGE}