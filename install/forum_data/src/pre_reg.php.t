<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: pre_reg.php.t,v 1.6 2003/04/10 19:08:38 hackie Exp $
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

	if (isset($_POST['disagree'])) {
		header('Location: {ROOT}?'._rsidl);
		exit;
	} else if (isset($_POST['agree'])) {
		header('Location: {ROOT}?t=register&'._rsidl.'&reg_coppa='.($GLOBALS['COPPA'] != 'Y' ? 'N' : strtoupper($_POST['coppa'])));
		exit;
	}

	$TITLE_EXTRA = ': {TEMPLATE: forum_terms}';

/*{POST_HTML_PHP}*/

	if (!isset($_GET['coppa'])) {
		$_GET['coppa'] = 'N';
	}
		
	$msg_file = $_GET['coppa'] == 'Y' ? '{TEMPLATE: forum_rules_13}' : '{TEMPLATE: forum_rules}';

/*{POST_PAGE_PHP_CODE}*/
?>
{TEMPLATE: PREREG_PAGE}