<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: buddy_list.php.t,v 1.5 2002/07/07 21:08:30 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

	include_once "GLOBALS.php";
	{PRE_HTML_PHP}
		
	if ( !isset($usr) ) {
		std_error('login');
		exit();
	}
	
	if( !isset($returnto) ) $returnto='';
	
	$bud = new fud_buddy;
	
	if( isset($add_login) && strlen($add_login) ) {
		$buddy_id = get_id_by_login($add_login);
		
		if ( $buddy_id == $usr->id ) {
			error_dialog('{TEMPLATE: err_info}', '{TEMPLATE: buddy_list_err_cantadd}', '{ROOT}?t=buddy_list&'._rsid);
			exit();
		}
		
		if ( $buddy_id && !($buddy=check_buddy($usr->id, $buddy_id)) ) {
			$bud->add($usr->id, $buddy_id);
			$done=1;
			header("Location: {ROOT}?t=buddy_list&"._rsid."&rand=".get_random_value());
		}
		else if ( $buddy ) {
			error_dialog('{TEMPLATE: err_info}', '{TEMPLATE: buddy_list_err_dup}', '{ROOT}?t=buddy_list&'._rsid);
		}
		else {
			error_dialog('{TEMPLATE: buddy_list_err_nouser_title}', '{TEMPLATE: buddy_list_err_nouser}', '{ROOT}?t=buddy_list&'._rsid);
		}
		
		exit();
	}
	
	if ( is_numeric($add) && empty($done) && ($buddy_login=check_user($add)) ) {
		if( !check_buddy($usr->id, $add) ) $bud->add($usr->id, $add);
		check_return();
		exit();
	}

	if ( is_numeric($del) ) {
		$bud->get_buddy($usr->id, $del);
		$bud->delete();	
		if( !$returnto ) $returnto = '{ROOT}?t=buddy_list&'._rsid;
		check_return();	
	}

	if ( isset($ses) ) $ses->update('{TEMPLATE: buddy_list_update}');

	{POST_HTML_PHP}
	
	$res = q("SELECT 
			{SQL_TABLE_PREFIX}buddy.id as bud_id,
			{SQL_TABLE_PREFIX}users.id,
			{SQL_TABLE_PREFIX}users.login,
			{SQL_TABLE_PREFIX}users.join_date,
			{SQL_TABLE_PREFIX}users.bday,
			{SQL_TABLE_PREFIX}users.invisible_mode,
			{SQL_TABLE_PREFIX}users.posted_msg_count,
			{SQL_TABLE_PREFIX}users.home_page,
			{SQL_TABLE_PREFIX}ses.time_sec
		FROM {SQL_TABLE_PREFIX}buddy 
		LEFT JOIN {SQL_TABLE_PREFIX}users 
			ON {SQL_TABLE_PREFIX}buddy.bud_id={SQL_TABLE_PREFIX}users.id 
		LEFT JOIN {SQL_TABLE_PREFIX}ses
			ON {SQL_TABLE_PREFIX}buddy.bud_id={SQL_TABLE_PREFIX}ses.user_id		
		WHERE 
			{SQL_TABLE_PREFIX}buddy.user_id=".$usr->id);
	
	if( db_count($res) ) {
		$buddies='';
		while( $obj = db_rowobj($res) ) {
			$homepage_link = !empty($obj->home_page) ? '{TEMPLATE: homepage_link}' : '';
			$online_status = ( $obj->invisible_mode=='N' && $obj->time_sec+$GLOBALS['LOGEDIN_TIMEOUT']*60 > __request_timestamp__ ) ? '{TEMPLATE: online_indicator}' : '{TEMPLATE: offline_indicator}';
			
			if( substr($obj->bday,4) == date("md") ) {
				$age = date("Y")-substr($obj->bday,0,4);
				$bday_indicator = '{TEMPLATE: bday_indicator}';
			}
			else
				$bday_indicator = '';
			
			$contact_link = $GLOBALS['PM_ENABLED']=='Y' ? '{TEMPLATE: pm_link}' : '{TEMPLATE: email_link}';
			
			$buddies .= '{TEMPLATE: buddy}';
		}
		$buddies = '{TEMPLATE: buddy_list}';
	}
	qf($res);
	
	{POST_PAGE_PHP_CODE}
?>
{TEMPLATE: BUDDYLIST_PAGE}