<?php
/**
* copyright            : (C) 2001-2009 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: reset.php.t,v 1.32 2009/10/23 19:15:03 frank Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; version 2 of the License.
**/

/*{PRE_HTML_PHP}*/

	if (_uid) {
		if ($FUD_OPT_2 & 32768) {
			header('Location: {FULL_ROOT}{ROOT}/i/' . _rsidl);
		} else {
			header('Location: {FULL_ROOT}{ROOT}?t=index&' . _rsidl);
		}
		exit;
	}

	if (isset($_GET['reset_key'])) {
		if (($ui = db_saq('SELECT email, login, id FROM {SQL_TABLE_PREFIX}users WHERE reset_key='._esc((string)$_GET['reset_key'])))) {
			q("UPDATE {SQL_TABLE_PREFIX}users SET passwd='".md5(($passwd = dechex(get_random_value(32))))."', reset_key='0' WHERE id=".$ui[2]);
			send_email($NOTIFY_FROM, $ui[0], '{TEMPLATE: reset_newpass_title}', '{TEMPLATE: reset_newpass_msg}');
			ses_putvar((int)$usr->sid, '{TEMPLATE: reset_login_notify}');
			if ($FUD_OPT_2 & 32768) {
				header('Location: {FULL_ROOT}{ROOT}/l/'._rsidl);
			} else {
				header('Location: {FULL_ROOT}{ROOT}?t=login&'._rsidl);
			}
			exit;
		}
		error_dialog('{TEMPLATE: reset_err_invalidkey_title}', '{TEMPLATE: reset_err_invalidkey_msg}');
	}

	if (isset($_GET['email'])) {
		$email = (string) $_GET['email'];
	} else if (isset($_POST['email'])) {
		$email = (string) $_POST['email'];
	} else {
		$email = '';
	}

	if ($email) {
		if ($uobj = db_sab('SELECT id, users_opt FROM {SQL_TABLE_PREFIX}users WHERE email='._esc($email))) {
			if ($FUD_OPT_2 & 1 && !($uobj->users_opt & 131072)) {
				$uent = new stdClass();
				$uent->conf_key = usr_email_unconfirm($uobj->id);
				send_email($NOTIFY_FROM, $email, '{TEMPLATE: register_conf_subject}', '{TEMPLATE: register_conf_msg}');
			} else {
				q("UPDATE {SQL_TABLE_PREFIX}users SET reset_key='".($key = md5(__request_timestamp__ . $uobj->id . get_random_value()))."' WHERE id=".$uobj->id);
				$url = '{FULL_ROOT}{ROOT}?t=reset&reset_key='.$key;
				send_email($NOTIFY_FROM, $email, '{TEMPLATE: reset_newpass_title}', '{TEMPLATE: reset_reset}');
			}
			error_dialog('{TEMPLATE: reset_err_rstconf_title}', '{TEMPLATE: reset_err_rstconf_msg}');
		} else {
			$no_such_email = '{TEMPLATE: no_such_email}';
		}
	} else {
		$no_such_email = '';
	}

	$TITLE_EXTRA = ': {TEMPLATE: reset_title}';

/*{POST_HTML_PHP}*/
/*{POST_PAGE_PHP_CODE}*/
?>
{TEMPLATE: RESET_PAGE}