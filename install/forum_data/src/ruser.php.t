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

	define('plain_form', 1);

/*{PRE_HTML_PHP}*/

	if (!__fud_real_user__) {
		std_error('login');
	}

	/* Change current userid to nlogin. */
	if (isset($_POST['btn_submit'], $_POST['nlogin'], $_POST['cpasswd']) && is_string($_POST['nlogin'])) {
		if (!($r = db_sab('SELECT id, login, passwd, salt FROM {SQL_TABLE_PREFIX}users WHERE login='. _esc($usr->login)))) {
			exit('Go away!');
		}

		if (__fud_real_user__ != $r->id || !((empty($r->salt) && $r->passwd == md5((string)$_POST['cpasswd'])) || $r->passwd == sha1($r->salt . sha1((string)$_POST['cpasswd'])))) {
			$ruser_error_msg = '{TEMPLATE: ruser_invalid_passwd}';
		} else if (strlen($_POST['nlogin']) < 4) {
			$ruser_error_msg = '{TEMPLATE: ruser_err_short_login}';
		} else if (is_login_blocked($_POST['nlogin'])) {
			$ruser_error_msg = '{TEMPLATE: ruser_err_login_notallowed}';
		} else if (get_id_by_login($_POST['nlogin'])) {
			$ruser_error_msg = '{TEMPLATE: ruser_err_loginunique}';
		} else {
			q('UPDATE {SQL_TABLE_PREFIX}users SET login='. _esc($_POST['nlogin']) .' WHERE id='. $r->id);
			if (!($GLOBALS['FUD_OPT_2'] & 128)) {	// USE_ALIASES diabled, set alias = nlogin.
				q('UPDATE {SQL_TABLE_PREFIX}users SET alias='. _esc($_POST['nlogin']) .' WHERE id='. $r->id);
			}
			logaction(__fud_real_user__, 'CHANGE_USER', 0, $r->login);
			exit('<html><script>window.close();</script></html>');
		}

		$ruser_error = '{TEMPLATE: ruser_error}';
	} else {
		$ruser_error = '';
	}

	$TITLE_EXTRA = ': {TEMPLATE: ruser_title}';

/*{POST_HTML_PHP}*/
/*{POST_PAGE_PHP_CODE}*/
?>
{TEMPLATE: RUSER_PAGE}
