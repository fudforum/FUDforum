<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: smladd.php.t,v 1.3 2002/07/30 14:34:37 hackie Exp $
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
	
	{POST_HTML_PHP}
	$col_count = 4;
	$col_pos=-1;
	$r = q("SELECT * FROM {SQL_TABLE_PREFIX}smiley ORDER BY vieworder");
	$smiley_www = 'images/smiley_icons/';
	
	while ( $obj = db_rowobj($r) ) {
		if ( ++$col_pos > $col_count ) { 
			$sml_smiley_row .= '{TEMPLATE: sml_smiley_row}';  
			$sml_smiley_entry=''; 
			$col_pos=0; 
		}
		$obj->code = ($a=strpos($obj->code, '~')) ? substr($obj->code,0,$a) : $obj->code;
		$sml_smiley_entry .= '{TEMPLATE: sml_smiley_entry}';
	}
	if ( $col_pos ) $sml_smiley_row .= '{TEMPLATE: sml_smiley_row}';
	qf($r);
	
	{POST_PAGE_PHP_CODE}
?>
{TEMPLATE: SMLLIST_PAGE}
