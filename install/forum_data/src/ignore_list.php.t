<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: ignore_list.php.t,v 1.3 2002/06/18 18:26:09 hackie Exp $
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
	
	$ign = new fud_ignore;
	
	if( isset($add_login) && strlen($add_login) ) {
		$ignore_id = get_id_by_login($add_login);
		if ( $ignore_id && !($ignore=check_ignore($usr->id, $ignore_id)) ) {
			$ign->add($usr->id, $ignore_id);
			$done=1;
			header("Location: {ROOT}?t=ignore_list&"._rsid."&rand=".get_random_value());
		}
		else if ( $ignore == 1 ) {
			error_dialog('{TEMPLATE: ignore_list_err_info_title}', '{TEMPLATE: ignore_list_err_noign_msg}', '{ROOT}?t=ignore_list&'._rsid);
		}
		else if ( $ignore ) {
			error_dialog('{TEMPLATE: ignore_list_err_info_title}', '{TEMPLATE: ignore_list_err_dup_msg}', '{ROOT}?t=ignore_list&'._rsid);
		}
		else {
			error_dialog('{TEMPLATE: ignore_list_err_nu_title}', '{TEMPLATE: ignore_list_err_nu_msg}', '{ROOT}?t=ignore_list&'._rsid);
		}
		
		exit();
	}
	
	if ( isset($add) && is_numeric($add) && empty($done) ) {
		if ( $add > 0 && check_user($add) ) {
			$test_u = new fud_user;
			$test_u->get_user_by_id($add);
			if( $test_u->is_mod == 'A' ) {
				error_dialog('{TEMPLATE: ignore_list_err_info_title}', '{TEMPLATE: ignore_list_cantign_msg}', (isset($returnto)?$returnto:''));
			}
		}
		
		if( !check_ignore($usr->id, $add) ) {
			$ign->add($usr->id, $add);
			check_return();
		}
		exit;
	}

	if ( isset($del) && is_numeric($del) ) {
		$ign->get_ignore($usr->id, $del);
		$ign->delete();	
		if( empty($returnto) ) $returnto = '{ROOT}?t=ignore_list&'._rsid.'&rand='.get_random_value();
		check_return();	
	}

	if ( isset($ses) ) $ses->update('{TEMPLATE: ignore_list_update}');
	
	{POST_HTML_PHP}
	
	$res = q("SELECT 
			{SQL_TABLE_PREFIX}user_ignore.ignore_id,
			{SQL_TABLE_PREFIX}user_ignore.id as ignoreent_id,
			{SQL_TABLE_PREFIX}users.id,
			{SQL_TABLE_PREFIX}users.login,
			{SQL_TABLE_PREFIX}users.join_date,
			{SQL_TABLE_PREFIX}users.posted_msg_count 
		FROM 
			{SQL_TABLE_PREFIX}user_ignore
		LEFT JOIN 
			{SQL_TABLE_PREFIX}users 
				ON {SQL_TABLE_PREFIX}user_ignore.ignore_id={SQL_TABLE_PREFIX}users.id 
		WHERE 
			{SQL_TABLE_PREFIX}user_ignore.user_id=".$usr->id);
	
	if( db_count($res) ) {
		$returnto = urlencode($GLOBALS["REQUEST_URI"]);
		$ignore_user = '';
		while( $obj = db_rowobj($res) ) {
			if( $obj->ignore_id ) {
				$homepage_link = !empty($obj->home_page) ? '{TEMPLATE: homepage_link}' : '';	
				
				$email_link = ($GLOBALS["ALLOW_EMAIL"]=='Y') ? '{TEMPLATE: email_link}' : '';
				$ignore_user .=	'{TEMPLATE: ignore_user}';
			}
			else {
				$obj->login = $GLOBALS['ANON_NICK'];
				$ignore_user .=	'{TEMPLATE: ignore_anon_user}';
			}
		}
		
		$ignore_list = '{TEMPLATE: ignore_list}';
	}
	qf($res);
	
	{POST_PAGE_PHP_CODE}
?>
{TEMPLATE: IGNORELIST_PAGE}