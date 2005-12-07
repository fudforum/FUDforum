<?php
/**
* copyright            : (C) 2001-2006 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: email.php.t,v 1.24 2005/12/07 18:07:45 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; either version 2 of the License, or
* (at your option) any later version.
**/

/*{PRE_HTML_PHP}*/

	if (!_uid) {
		std_error('login');
	}

	if (!($FUD_OPT_2 & 1073741824)) {
		error_dialog('{TEMPLATE: email_err_unabletoemail_title}', '{TEMPLATE: email_err_unabletoemail_msg}');
	}

	is_allowed_user($usr);

function set_err($type, $msg)
{
	$GLOBALS['_ERROR_'][$type] = $msg;
	$GLOBALS['error'] = 1;
}

function get_err($type)
{
	if (!isset($GLOBALS['_ERROR_'][$type])) {
		return;
	}
	return '{TEMPLATE: email_error_text}';
}

function mail_check()
{
	if (!strlen(trim($_POST['tx_body']))) {
		set_err('tx_body', '{TEMPLATE: email_error_body}');
	}

	if (!strlen(trim($_POST['tx_subject']))) {
		set_err('tx_subject', '{TEMPLATE: email_error_subject}');
	}

	if (!strlen(trim($_POST['tx_name']))) {
		set_err('tx_name', '{TEMPLATE: email_error_namerequired}');
	} else if (!q_singleval("SELECT id FROM {SQL_TABLE_PREFIX}users WHERE alias="._esc(htmlspecialchars($_POST['tx_name'])))) {
		set_err('tx_name', '{TEMPLATE: email_error_invaliduser}');
	}

	return $GLOBALS['error'];
}

	$_ERROR_ = array();
	$error = $tx_name = $tx_subject = $tx_body = '';

	if (isset($_GET['toi']) && (int)$_GET['toi']) {
		$tx_name = q_singleval('SELECT alias FROM {SQL_TABLE_PREFIX}users WHERE id='.(int)$_GET['toi']);
	} 
	if (isset($_POST['btn_submit'])) {
		if (!mail_check()) {
			if (!($email = q_singleval('SELECT email FROM {SQL_TABLE_PREFIX}users WHERE alias='._esc(htmlspecialchars($_POST['tx_name'])).' AND (users_opt & 16) > 0'))) {
				error_dialog('{TEMPLATE: email_err_unabletoemail_title}', '{TEMPLATE: email_error_unabletolocaddr}');
			}
			send_email($usr->email, $email, $_POST['tx_subject'], $_POST['tx_body'], 'Reply-To: '.$usr->email);
			check_return($usr->returnto);
		}

		foreach (array('tx_name', 'tx_subject', 'tx_body') as $v) {
			$tx_name = isset($_POST[$v]) ? $_POST[$v] : '';
		}
	}

	/* start page */
	$TITLE_EXTRA = ': {TEMPLATE: email_title}';

/*{POST_HTML_PHP}*/
/*{POST_PAGE_PHP_CODE}*/
?>
{TEMPLATE: EMAIL_PAGE}