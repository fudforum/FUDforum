<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: mklist.php.t,v 1.2 2002/07/30 14:34:37 hackie Exp $
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
	
	if ( !isset($l) ) $l = array();
	if ( !isset($a) ) $a = array();
	if ( !isset($opt) ) $opt = '';	

	if( !empty($opt_list) )
		$opt_list = trim(base64_decode(stripslashes($opt_list)));
	else
		$opt_list = '';	
	
	if ( !empty($del) ) {
		$opt_list = preg_replace("/(\\n|^)$del(\\n|\$)/", "\n", $opt_list, 1);
		$opt_list = trim($opt_list);
	}
	
	if ( !empty($opt_list) ) {
		$a = explode("\n", $opt_list);
	}
	
	if ( strlen($opt) ) {
		$a[] = $opt;
	}
	
	if ( !empty($go) ) {
		if ( !sizeof($a) ) exit('<html><script>window.close();</script></html>');
		
		$tp = explode(':', $tp);
		
		$tag = "[LIST TYPE=$tp[1]]\n";
		foreach ( $a as $o ) {
			$tag .= "[*]$o\\n";
		}
		$tag .= "[/LIST]";
		echo '<html><script>';
		
		readfile('lib.js');
		echo "\n\n".'insertParentTag(\''.str_replace("\n", '\n', $tag).'\', \' \'); window.close();</script></html>';
		exit();
	}
	
	{POST_HTML_PHP}

	$tp_select_data = tmpl_draw_select_opt("OL:1\nOL:a\nUL:square\nUL:disc\nUL:circle", "{TEMPLATE: mklist_numerical}\n{TEMPLATE: mklist_aplha}\n{TEMPLATE: mklist_square}\n{TEMPLATE: mklist_disc}\n{TEMPLATE: mklist_circle}", $tp, '{TEMPLATE: sel_opt}', '{TEMPLATE: sel_opt_selected}');
	$tp = explode(':', $tp); 
	if ( count($a) ) {
		$list_tag = trim($tp[0]);
		$list_type = trim($tp[1]);
		$list_entry_data = '';
	
		foreach ( $a as $op ) {
			$list_entry_data .= '{TEMPLATE: list_entry}';
		}
		$list_sample = '{TEMPLATE: list_sample}';
	}
	
	$form_option_list = base64_encode($opt_list."\n".$opt);
	{POST_PAGE_PHP_CODE}
?>
{TEMPLATE: MKLIST_PAGE}