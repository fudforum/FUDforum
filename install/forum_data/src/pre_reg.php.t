<?php
/***************************************************************************
* copyright            : (C) 2001-2003 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: pre_reg.php.t,v 1.12 2003/11/06 01:43:02 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it 
* under the terms of the GNU General Public License as published by the 
* Free Software Foundation; either version 2 of the License, or 
* (at your option) any later version.
***************************************************************************/

/*{PRE_HTML_PHP}*/

	if (isset($_POST['disagree'])) {
		if ($FUD_OPT_2 & 32768) {
			header('Location: {ROOT}/i/'._rsidl);
		} else {
			header('Location: {ROOT}?'._rsidl);
		}
		exit;
	} else if (isset($_POST['agree'])) {
		if ($FUD_OPT_2 & 32768) {
			header('Location: {ROOT}/re/' . ($FUD_OPT_1 & 1048576 ?(int)$_POST['coppa'] : 0) .'/'._rsidl);
		} else {
			header('Location: {ROOT}?t=register&'._rsidl.'&reg_coppa='.($FUD_OPT_1 & 1048576 ?(int)$_POST['coppa'] : 0));
		}
		exit;
	}

	$TITLE_EXTRA = ': {TEMPLATE: forum_terms}';

/*{POST_HTML_PHP}*/

	if (!isset($_GET['coppa']) || $_GET['coppa'] === '0') {
		$_GET['coppa'] = 0;
	}

	$msg_file = $_GET['coppa'] ? '{TEMPLATE: forum_rules_13}' : '{TEMPLATE: forum_rules}';

/*{POST_PAGE_PHP_CODE}*/
?>
{TEMPLATE: PREREG_PAGE}