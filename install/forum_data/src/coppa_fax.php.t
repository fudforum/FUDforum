<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: coppa_fax.php.t,v 1.2 2002/07/30 14:34:37 hackie Exp $
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
	
	/* this form is for printing, therefor it lacks any advanced layout */
	if ( !isset($usr) ) { header('Location: {ROOT}?t=index&'._rsid); exit(); }
	
	if( $fp = @fopen($GLOBALS['FORUM_SETTINGS_PATH'].'coppa_maddress.msg', 'r') ) {
		$coppa_address = fread($fp, filesize($GLOBALS['FORUM_SETTINGS_PATH'].'coppa_maddress.msg'));
		fclose($fp);
	}
	else 
		$coppa_address = '';

	{POST_PAGE_PHP_CODE}
?>
{TEMPLATE: COPPAFAX_PAGE}