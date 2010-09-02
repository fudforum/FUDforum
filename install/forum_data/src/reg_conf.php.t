<?php
/**
* copyright            : (C) 2001-2010 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id$
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; version 2 of the License.
**/

/*{PRE_HTML_PHP}*/

/* If a registered user or anon user send back to the front page. */
if (!__fud_real_user__ || _uid) {
	if ($FUD_OPT_2 & 32768) {
		header('Location: {FULL_ROOT}{ROOT}/i/'. _rsidl);
	} else {
		header('Location: {FULL_ROOT}{ROOT}?t=index&'. _rsidl);
	}
	exit;
}

$msg = '';

if (!($usr->users_opt & 131072)) {
	$msg = '{TEMPLATE: reg_conf_email}';
}
if ($usr->users_opt & 2097152) {
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