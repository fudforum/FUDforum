<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: pre_reg.php.t,v 1.1.1.1 2002/06/17 23:00:09 hackie Exp $
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

	if ( !empty($disagree) ) {
		header("Location: ".$WWW_ROOT.'?'._rsid);
		exit;
	}
	else if ( !empty($agree) ) {
		if( $GLOBALS['COPPA'] != 'Y' ) $coppa = 'n';
		header("Location: {ROOT}?t=register&"._rsid."&coppa=".$coppa);
		exit;
	}

	$TITLE_EXTRA = ': {TEMPLATE: forum_terms}';
	{POST_HTML_PHP}
	$msg_file = ( trim(strtolower($coppa))=='y' ) ? '{TEMPLATE: forum_rules_13}' : '{TEMPLATE: forum_rules}';
	{POST_PAGE_PHP_CODE}
?>
{TEMPLATE: PREREG_PAGE}