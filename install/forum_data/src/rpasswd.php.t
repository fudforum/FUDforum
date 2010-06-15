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

	/* Change current password (cpasswd) to passwd1 (use passwd2 for verification). */
	if (isset($_POST['btn_submit'], $_POST['passwd1'], $_POST['cpasswd']) && is_string($_POST['passwd1'])) {
		if (!($r = db_sab('SELECT id, passwd, salt FROM {SQL_TABLE_PREFIX}users WHERE login='. _esc($usr->login)))) {
			exit('Go away!');
		}
		
		if (__fud_real_user__ != $r->id || !((empty($r->salt) && $r->passwd == md5((string)$_POST['cpasswd'])) || $r->passwd == sha1($r->salt . sha1((string)$_POST['cpasswd'])))) {
			$rpasswd_error_msg = '{TEMPLATE: rpasswd_invalid_passwd}';
		} else if ($_POST['passwd1'] !== $_POST['passwd2']) {
			$rpasswd_error_msg = '{TEMPLATE: rpasswd_passwd_nomatch}';
		} else if (strlen($_POST['passwd1']) < 6 ) {
			$rpasswd_error_msg = '{TEMPLATE: rpasswd_passwd_length}';
		} else {
			$salt = substr(md5(uniqid(mt_rand(), true)), 0, 9);
			$secure_pass = sha1($salt . sha1($_POST['passwd1']));
			q('UPDATE {SQL_TABLE_PREFIX}users SET passwd='. _esc($secure_pass) .', salt='. _esc($salt) .' WHERE id='. __fud_real_user__);
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
