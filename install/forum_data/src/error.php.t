<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: error.php.t,v 1.1.1.1 2002/06/17 23:00:09 hackie Exp $
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
	
	if ( !empty($ok) ) check_return();
	$TITLE_EXTRA = ': {TEMPLATE: error_title}';
	{POST_HTML_PHP}
	
	if ( $err_id != $ses->getvar('err_id') ) {
		$error_msg = '{TEMPLATE: error_invalidurl}';
		$error_title = '{TEMPLATE: error_error}';
	}
	else {
		$error_msg = $ses->getvar('er_msg');
		$error_title = $ses->getvar('err_t');
		$returnto = base64_decode($ses->getvar('ret_to'));
	}

	if( empty($returnto) ) $returnto = urlencode($GLOBALS["WWW_ROOT"].'?'._rsid);
	$ret = create_return();
	{POST_PAGE_PHP_CODE}
?>
{TEMPLATE: ERROR_PAGE}