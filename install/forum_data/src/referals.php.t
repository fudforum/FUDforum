<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: referals.php.t,v 1.4 2002/07/08 23:15:19 hackie Exp $
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

	if ( isset($ses) ) $ses->update('{TEMPLATE: referals_update}');
	
	{POST_HTML_PHP}	
	if( empty($id) || !is_numeric($id) ) $id = NULL;
	$returnto = urlencode($GLOBALS["REQUEST_URI"]);

	if( $id ) {
		$r = q("SELECT id,alias, home_page FROM {SQL_TABLE_PREFIX}users WHERE id=".$id);
		if( ($ttl=db_count($r)) ) {
			list($r_id,$r_login) = db_singlearr($r);
			$res = q("SELECT alias AS login,id,join_date,posted_msg_count FROM {SQL_TABLE_PREFIX}users WHERE referer_id=".$id);
			$i=0;
			$refered_entry_data = '';
			while ( $obj = db_rowobj($res) ) {
				$user_login = htmlspecialchars($obj->login);
				if( $GLOBALS['PM_ENABLED'] == 'Y' && isset($usr) ) 
					$pm_link = '{TEMPLATE: pm_link}';
				else  
					$pm_link = '';
				
				if ( strlen($obj->home_page) ) {
					$homepage = $obj->home_page;
					$homepage_link = '{TEMPLATE: homepage_link}';
				}
				else $homepage_link = '';
				
				if ( $GLOBALS["ALLOW_EMAIL"]=='Y' ) {
					$email_link = '{TEMPLATE: email_link}';
				}
				else $email_link = '';
				
				$style = 'RowStyle'.(($i%2)?'A':'B');

				$refered_entry_data .= '{TEMPLATE: refered_entry}';
				$i++;		
			}
			qf($res);
		}
	}	

	if( empty($ttl) ) {
		$refered_entry_data = '{TEMPLATE: no_refered}';
	}
	
	$r_login = htmlspecialchars($r_login);
	
	{POST_PAGE_PHP_CODE}
	
?>
{TEMPLATE: REFERALS_PAGE}
