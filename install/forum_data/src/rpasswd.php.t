<?php
/**
* copyright            : (C) 2001-2004 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: rpasswd.php.t,v 1.17 2005/09/08 14:17:00 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; either version 2 of the License, or
* (at your option) any later version.
**/

	define('plain_form', 1);

/*{PRE_HTML_PHP}*/

	if (!__fud_real_user__) {
		std_error('login');
	}

	if (isset($_POST['btn_submit'], $_POST['passwd1'], $_POST['cpasswd']) && is_string($_POST['passwd1'])) {
		if (__fud_real_user__ != q_singleval("SELECT id FROM {SQL_TABLE_PREFIX}users WHERE login="._esc($usr->login)." AND passwd='".md5((string)$_POST['cpasswd'])."'")) {
			$rpasswd_error_msg = '{TEMPLATE: rpasswd_invalid_passwd}';
		} else if ($_POST['passwd1'] !== $_POST['passwd2']) {
			$rpasswd_error_msg = '{TEMPLATE: rpasswd_passwd_nomatch}';
		} else if (strlen($_POST['passwd1']) < 6 ) {
			$rpasswd_error_msg = '{TEMPLATE: rpasswd_passwd_length}';
		} else {
			q("UPDATE {SQL_TABLE_PREFIX}users SET passwd='".md5($_POST['passwd1'])."' WHERE id=".__fud_real_user__);
			logaction(__fud_real_user__, 'CHANGE_PASSWD', 0, get_ip());
			exit('<html><script>window.close();</script></html>');
		}

		$rpasswd_error = '{TEMPLATE: rpasswd_error}';
	} else {
		$rpasswd_error = '';
	}

	$TITLE_EXTRA = ': {TEMPLATE: rpasswd_title}';

/*{POST_HTML_PHP}*/
/*{POST_PAGE_PHP_CODE}*/
?>
{TEMPLATE: RPASSWD_PAGE}