<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: pmuserloc.php.t,v 1.7 2003/03/13 11:04:47 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

	define('plain_form', 1);
	{PRE_HTML_PHP}	
	
	if ( $MEMBER_SEARCH_ENABLED != 'Y' ) {
		std_error('disabled');
		exit();
	}
	
	{POST_HTML_PHP}	
	$usr_login = ( !empty($usr_login) ) ? trim(stripslashes($usr_login)) : '';
	$usr_email = ( !empty($usr_email) ) ? trim(stripslashes($usr_email)) : '';
	
	if( !empty($pc) ) 
		$ord = "posted_msg_count DESC";
	else if( !empty($us) ) 
		$ord = "alias";
	else
		$ord = "id DESC";	
	
	if ( !empty($btn_submit) ) {
		$usr_login = str_replace('\\', '\\\\', $usr_login);
		$usr_email = str_replace('\\', '\\\\', $usr_email);
		
		if ( $usr_login )
			$qry = "WHERE alias LIKE '".addslashes($usr_login)."%'";
		else if ( $usr_email ) 
			$qry = "WHERE email LIKE '".addslashes($usr_email)."%'";
		else 
			$qry = '';	
		
		$usr_login = str_replace('\\\\', '\\', $usr_login);
		$usr_email = str_replace('\\\\', '\\', $usr_email);
			
		$returnto = urlencode('{ROOT}?t=finduser&btn_submit=Find&start='.$start.'&'._rsid.'&count='.$count);
		$res = q("SELECT * FROM {SQL_TABLE_PREFIX}users ".$qry." ORDER BY ".$ord);
		$find_user_data = '';
		
		if ( db_count($res) ){
			$i=0;
			while ( $obj = db_rowobj($res) ) {
				if ( $overwrite )
					$retlink = 'javascript: window.opener.document.'.$js_redr.'.value=\''.addslashes($obj->alias).'\'; window.close();';
				else 
					$retlink = 'javascript: window.opener.document.'.$js_redr.'.value=window.opener.document.'.$js_redr.'.value+\''.addslashes($obj->alias).'; \'; window.close();';
				
				$find_user_data .= '{TEMPLATE: user_result_entry}';
				$i++;
			}
		}
		else 
			$find_user_data = '{TEMPLATE: no_result_entry}';
		
		qf($res);
	}

	{POST_PAGE_PHP_CODE}
?>
{TEMPLATE: PMUSERLOC_PAGE}