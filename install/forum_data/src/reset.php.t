<?php
/**
* copyright            : (C) 2001-2017 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id$
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; version 2 of the License.
**/

/*{PRE_HTML_PHP}*/

	ses_update_status($usr->sid, '{TEMPLATE: reset_update}');

	/* User is logged in, redirect to forum index. */
	if (_uid) {
		if ($FUD_OPT_2 & 32768) {
			header('Location: {ROOT}/i/'. _rsidl);
		} else {
			header('Location: {ROOT}?t=index&'. _rsidl);
		}
		exit;
	}

	/* Password resets are disabled. */
	if (!($FUD_OPT_4 & 2)) {
                std_error('disabled');
        }

	/* Process the reset key. */
	if (isset($_GET['reset_key'])) {
		if (($ui = db_saq('SELECT email, login, id FROM {SQL_TABLE_PREFIX}users WHERE reset_key='. _esc((string)$_GET['reset_key'])))) {
			// Generate new password and salt for user.
			$salt   = substr(md5(uniqid(mt_rand(), true)), 0, 9);
			$passwd = dechex(get_random_value(32));	// New password that will be mailed to the user.
			q('UPDATE {SQL_TABLE_PREFIX}users SET passwd=\''. sha1($salt . sha1($passwd)) .'\', salt=\''. $salt .'\', reset_key=NULL WHERE id='. $ui[2]);

			// Send new password to user via e-mail.
			send_email($NOTIFY_FROM, $ui[0], '{TEMPLATE: reset_newpass_title}', '{TEMPLATE: reset_newpass_msg}');

			// Message to display on login screen.
			ses_putvar((int)$usr->sid, 'resetmsg={TEMPLATE: reset_login_notify}');
			
			// Redirect user to login screen.
			if ($FUD_OPT_2 & 32768) {
				header('Location: {ROOT}/l/'. _rsidl);
			} else {
				header('Location: {ROOT}?t=login&'. _rsidl);
			}
			exit;
		}
		error_dialog('{TEMPLATE: reset_err_invalidkey_title}', '{TEMPLATE: reset_err_invalidkey_msg}');
	}

	/* Check if we received an e-mail address. */
	if (isset($_GET['email'])) {
		$email = (string) $_GET['email'];
	} else if (isset($_POST['email'])) {
		$email = (string) $_POST['email'];
	} else {
		$email = '';
	}

	/* Send user a reset key via e-mail. */
	if ($email) {
		if ($uobj = db_sab('SELECT id, users_opt FROM {SQL_TABLE_PREFIX}users WHERE email='. _esc($email))) {
			if ($FUD_OPT_2 & 1 && !($uobj->users_opt & 131072)) {
				// User's e-mail must be confirmed.
				$uent = new stdClass();
				$uent->conf_key = usr_email_unconfirm($uobj->id);
				send_email($NOTIFY_FROM, $email, '{TEMPLATE: register_conf_subject}', '{TEMPLATE: register_conf_msg}');
			} else {
				// Reset it and notify user.
				q('UPDATE {SQL_TABLE_PREFIX}users SET reset_key=\''. ($key = md5(__request_timestamp__ . $uobj->id . get_random_value())) .'\' WHERE id='. $uobj->id);
				$url = '{FULL_ROOT}?t=reset&reset_key='. $key;
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
