<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: qbud.php.t,v 1.7 2002/07/31 21:56:50 hackie Exp $
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
	
	if ( !isset($usr) ) {
		std_error('login');
		exit();
	}
	
	if( empty($all) ) $all=0;
	
	if( !$all && is_array($GLOBALS["HTTP_POST_VARS"]["names"]) ) {
		$names = '';
		foreach($GLOBALS["HTTP_POST_VARS"]["names"] as $v) $names .= $v.';';
		echo '<html><body><script language="Javascript"><!--
		
		if( window.opener.document.post_form.msg_to_list.value.length>0 ) 
			window.opener.document.post_form.msg_to_list.value = window.opener.document.post_form.msg_to_list.value+\';\'+"'.addslashes($names).'";
		else
			window.opener.document.post_form.msg_to_list.value = window.opener.document.post_form.msg_to_list.value+"'.addslashes($names).'";
		
		window.close();
		
		//--></script></body></html>';
		exit;
	}
	
	{POST_HTML_PHP}
	
	$res = q("SELECT {SQL_TABLE_PREFIX}users.id, {SQL_TABLE_PREFIX}users.alias FROM {SQL_TABLE_PREFIX}buddy LEFT JOIN {SQL_TABLE_PREFIX}users ON {SQL_TABLE_PREFIX}buddy.bud_id={SQL_TABLE_PREFIX}users.id WHERE {SQL_TABLE_PREFIX}buddy.user_id=".$usr->id);
	
	if( db_count($res) ) {
		$buddies='';
		
		if( $all ) {
			$all_v = '';
			$all_d = '{TEMPLATE: pmsg_none}';
		}
		else {
			$all_v = '1';
			$all_d = '{TEMPLATE: pmsg_all}';
		}
		
		while( $obj = db_rowobj($res) ) {
			$checked = $all ? ' checked' : '';
			$buddies .= '{TEMPLATE: buddy_entry}';
		}
		$qbud_data = '{TEMPLATE: buddy_list}';
	}
	else
		$qbud_data = '{TEMPLATE: no_buddies}';
	
	qf($res);
	
	{POST_PAGE_PHP_CODE}
?>
{TEMPLATE: QBUD_PAGE}