<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: avatarsel.php.t,v 1.1.1.1 2002/06/17 23:00:09 hackie Exp $
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
		
	{POST_HTML_PHP}
	$TITLE_EXTRA = ': {TEMPLATE: avatar_sel_form}';
	
	/* here we draw the avatar control */
	$icons_per_row = 5;
	$r = Q("SELECT * FROM {SQL_TABLE_PREFIX}avatar ORDER BY id");
	$avatars_data = '';
	if( !IS_RESULT($r) ) 
		$avatars_data = '{TEMPLATE: no_avatars}';
	else {
		$i=$col=0;
		while ( $obj = DB_ROWOBJ($r) ) {
			if ( !($col++%$icons_per_row) ) $avatars_data .= '{TEMPLATE: row_separator}';
			$avatars_data .= '{TEMPLATE: avatar_entry}';
		}
		QF($r);
	}	
	{POST_PAGE_PHP_CODE}
?>
{TEMPLATE: AVATARSEL_PAGE}