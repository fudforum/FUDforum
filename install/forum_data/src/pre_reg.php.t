<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: pre_reg.php.t,v 1.5 2003/04/02 01:46:35 hackie Exp $
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

	if (isset($_POST['disagree'])) {
		header('Location: '.$WWW_ROOT.'?'._rsidl);
		exit;
	} else if (isset($_POST['agree'])) {
		header('Location: {ROOT}?t=register&'._rsidl.'&reg_coppa='.($GLOBALS['COPPA'] != 'Y' ? 'N' : strtoupper($_REQUEST['coppa'])));
		exit;
	}

	$TITLE_EXTRA = ': {TEMPLATE: forum_terms}';
	{POST_HTML_PHP}
	$msg_file = $_REQUEST['coppa'] == 'y' ? '{TEMPLATE: forum_rules_13}' : '{TEMPLATE: forum_rules}';
	{POST_PAGE_PHP_CODE}
?>
{TEMPLATE: PREREG_PAGE}