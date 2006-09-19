<?php
/**
* copyright            : (C) 2001-2006 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: pre_reg.php.t,v 1.21 2006/09/19 14:37:55 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; version 2 of the License.
**/

/*{PRE_HTML_PHP}*/

	if (isset($_POST['disagree'])) {
		if ($FUD_OPT_2 & 32768) {
			header('Location: {FULL_ROOT}{ROOT}/i/'._rsidl);
		} else {
			header('Location: {FULL_ROOT}{ROOT}?'._rsidl);
		}
		exit;
	} else if (isset($_POST['agree'])) {
		if ($FUD_OPT_2 & 32768) {
			header('Location: {FULL_ROOT}{ROOT}/re/' . ($FUD_OPT_1 & 1048576 ?(int)$_POST['coppa'] : 0) .'/'._rsidl);
		} else {
			header('Location: {FULL_ROOT}{ROOT}?t=register&'._rsidl.'&reg_coppa='.($FUD_OPT_1 & 1048576 ?(int)$_POST['coppa'] : 0));
		}
		exit;
	}

	$TITLE_EXTRA = ': {TEMPLATE: forum_terms}';

/*{POST_HTML_PHP}*/

	$_GET['coppa'] = isset($_GET['coppa']) ? (int) $_GET['coppa'] : 0;

/*{POST_PAGE_PHP_CODE}*/
?>
{TEMPLATE: PREREG_PAGE}