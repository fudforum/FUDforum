<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: qbud.php.t,v 1.2 2002/06/18 16:12:36 hackie Exp $
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
	include_once "GLOBALS.php";
	{PRE_HTML_PHP}
	
	if ( !isset($usr) ) {
		std_error('login');
		exit();
	}
	
	if( empty($all) ) $all=0;
	
	if( !empty($GLOBALS["HTTP_POST_VARS"]["S"]) && !$all ) {
		$names = '';
		while( list(,$v) = each($GLOBALS["HTTP_POST_VARS"]["names"]) ) $names .= $v.';';
		echo '<html><body><script language="Javascript"><!--
		
		if( window.opener.document.post_form.msg_to_list.value.length>0 ) 
			window.opener.document.post_form.msg_to_list.value = window.opener.document.post_form.msg_to_list.value+\';\'+"'.$names.'";
		else
			window.opener.document.post_form.msg_to_list.value = window.opener.document.post_form.msg_to_list.value+"'.$names.'";
		
		window.close();
		
		//--></script></body></html>';
		exit;
	}
	
	{POST_HTML_PHP}
	
	$res = Q("SELECT {SQL_TABLE_PREFIX}users.id, {SQL_TABLE_PREFIX}users.login FROM {SQL_TABLE_PREFIX}buddy LEFT JOIN {SQL_TABLE_PREFIX}users ON {SQL_TABLE_PREFIX}buddy.bud_id={SQL_TABLE_PREFIX}users.id WHERE {SQL_TABLE_PREFIX}buddy.user_id=".$usr->id);
	
	if( DB_COUNT($res) ) {
		$buddies='';
		
		if( $all ) {
			$all_v = '';
			$all_d = '{TEMPLATE: pmsg_none}';
		}
		else {
			$all_v = '1';
			$all_d = '{TEMPLATE: pmsg_all}';
		}
		
		while( $obj = DB_ROWOBJ($res) ) {
			$checked = $all ? ' checked' : '';
			$buddies .= '{TEMPLATE: buddy_entry}';
		}
		$qbud_data = '{TEMPLATE: buddy_list}';
	}
	else
		$qbud_data = '{TEMPLATE: no_buddies}';
	
	QF($res);
	
	{POST_PAGE_PHP_CODE}
?>
{TEMPLATE: QBUD_PAGE}