<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: reg_conf.php.t,v 1.1 2003/06/05 20:16:02 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

/*{PRE_HTML_PHP}*/

/* if a regged user or anon user send back to the front page */
if (!__fud_real_user__ || _uid) {
	if ($GLOBALS['USE_PATH_INFO'] != 'Y') {
		header('Location: {ROOT}?t=index&'._rsidl);
	} else {
		header('Location: {ROOT}/i/'._rsidl);
	}
	exit;
}

$msg = '';

if ($usr->email_conf != 'Y') {
	$msg = '{TEMPLATE: reg_conf_email}';
}
if ($usr->acc_status == 'P') {
	if ($msg) {
		$msg .= ' {TEMPLATE: reg_conf_sep}';
	}
	$msg .= '{TEMPLATE: reg_conf_account}';
}

$TITLE_EXTRA = ': {TEMPLATE: reg_conf_title}';
/*{POST_HTML_PHP}*/

/*{POST_PAGE_PHP_CODE}*/
?>
{TEMPLATE: REG_CONF}