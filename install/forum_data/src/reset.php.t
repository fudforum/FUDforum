<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: reset.php.t,v 1.7 2003/04/21 17:42:40 hackie Exp $
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

function usr_reset_key($id)
{
	db_lock('{SQL_TABLE_PREFIX}users WRITE');
	do {
		$reset_key = md5(get_random_value(128));
	} while (q_singleval("SELECT id FROM {SQL_TABLE_PREFIX}users WHERE reset_key='".$reset_key."'"));
	q("UPDATE {SQL_TABLE_PREFIX}users SET reset_key='".$reset_key."' WHERE id=".$id);
	db_unlock();

	return $reset_key;
}

function usr_reset_passwd($id)
{
	$randval = dechex(get_random_value(32));
	q("UPDATE {SQL_TABLE_PREFIX}users SET passwd='".md5($randval)."', reset_key='0' WHERE id=".$id);
	return $randval;
}
	if (_uid) {
		header('Location: {ROOT}?t=index');
		exit;	
	}

	if (isset($_GET['reset_key'])) {
		db_lock('{SQL_TABLE_PREFIX}users WRITE');
		if (($ui = db_saq("SELECT email, login, id FROM {SQL_TABLE_PREFIX}users WHERE reset_key='".addslashes($_GET['reset_key'])."'"))) {
			$passwd = usr_reset_passwd($ui[2]);
			db_unlock();
			send_email($GLOBALS['NOTIFY_FROM'], $ui[0], '{TEMPLATE: reset_newpass_title}', '{TEMPLATE: reset_newpass_msg}');
			ses_putvar($usr->sid, '{TEMPLATE: reset_login_notify}');
			header('Location: {ROOT}?t=login&'._rsidl);
			exit;
		}
		db_unlock();
		error_dialog('{TEMPLATE: reset_err_invalidkey_title}', '{TEMPLATE: reset_err_invalidkey_msg}');
	}

	if (isset($_GET['email'])) {
		$email = $_GET['email'];
	} else if (isset($_POST['email'])) {
		$email = $_POST['email'];
	} else {
		$email = '';
	}

	if ($email) {
		if ($uobj = db_sab('SELECT id, email_conf FROM {SQL_TABLE_PREFIX}users WHERE email=\''.addslashes($email).'\'')) {
			if ($EMAIL_CONFIRMATION == 'Y' && $uobj->email_conf == 'N') {
				$conf_key = usr_email_unconfirm($uobj->id);
				$url = '{ROOT}?t=emailconf&conf_key='.$conf_key;
				send_email($NOTIFY_FROM, $email, '{TEMPLATE: register_conf_subject}', '{TEMPLATE: register_conf_msg}');
			} else {
				$key = usr_reset_key($uobj->id);
				$url = '{ROOT}?t=reset&reset_key='.$key;
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