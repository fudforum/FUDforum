<?php
/**
* copyright            : (C) 2001-2004 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: err.inc.t,v 1.45 2004/12/08 18:58:21 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; either version 2 of the License, or
* (at your option) any later version.
**/

function error_dialog($title, $msg, $level='WARN', $ses=null)
{
	if (!$ses) {
		$ses = (int) $GLOBALS['usr']->sid;
	}

	$error_msg = '[Error] '.$title.'<br />';
	$error_msg .= '[Message Sent to User] '.trim($msg).'<br />';
	$error_msg .= '[User IP] '.get_ip().'<br />';
	$error_msg .= '[Requested URL] http://';
	$error_msg .= isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
	$error_msg .= isset($_SERVER['REQUEST_URI']) ? htmlspecialchars($_SERVER['REQUEST_URI']) : '';
	$error_msg .= !empty($_POST) ? '<br />[Post-Data] '.base64_encode(htmlspecialchars(serialize($_POST))) : '';
	$error_msg .= '<br />';

	if (isset($_SERVER['HTTP_REFERER'])) {
		$error_msg .= '[Referring Page] '.htmlspecialchars($_SERVER['HTTP_REFERER']).'<br />';
	}
	error_log('['.gmdate('D M j G:i:s T Y', __request_timestamp__).'] '.base64_encode($error_msg)."\n", 3, $GLOBALS['ERROR_PATH'].'fud_errors');

	/* no need to redirect, we just want to log the error */
	if ($level == 'ATCH') {
		return;
	}

	ses_putvar($ses, array('er_msg' => $msg, 'err_t' => $title));

	if (is_int($ses)) {
		if ($GLOBALS['FUD_OPT_2'] & 32768) {
			header('Location: {FULL_ROOT}{ROOT}/e/'._rsidl);
		} else {
			header('Location: {FULL_ROOT}{ROOT}?t=error&'._rsidl);
		}
	} else {
		if ($GLOBALS['FUD_OPT_2'] & 32768) {
			header('Location: {FULL_ROOT}{ROOT}/e/0/'.$ses);
		} else {
			header('Location: {FULL_ROOT}{ROOT}?t=error&S='.$ses);
		}
	}
	exit;
}

function std_error($type)
{
	if (!isset($_SERVER['HTTP_REFERER'])) {
		$_SERVER['HTTP_REFERER'] = 'unknown';
	}

	$ses_id = s;
	$usr_d->email = $GLOBALS['usr']->email;

	if ($type == 'login') {
		if (__fud_real_user__) {
			$type = 'perms';
		} else {
			ses_anonuser_auth($GLOBALS['usr']->sid, '{TEMPLATE: ERR_login_msg}');
		}
	}

	$err_array = array(
'ERR_disabled'=>array('{TEMPLATE: ERR_disabled_ttl}', '{TEMPLATE: ERR_disabled_msg}'),
'ERR_access'=>array('{TEMPLATE: ERR_access_ttl}', '{TEMPLATE: ERR_access_msg}'),
'ERR_registration_disabled'=>array('{TEMPLATE: ERR_registration_disabled_ttl}', '{TEMPLATE: ERR_registration_disabled_msg}'),
'ERR_user'=>array('{TEMPLATE: ERR_user_ttl}', '{TEMPLATE: ERR_user_msg}'),
'ERR_perms'=>array('{TEMPLATE: permission_denied_title}', '{TEMPLATE: permission_denied_msg}'),
'ERR_systemerr'=>array('{TEMPLATE: ERR_systemerr_ttl}', '{TEMPLATE: ERR_systemerr_msg}'),
'ERR_emailconf'=>array('{TEMPLATE: ERR_emailconf_ttl}', '{TEMPLATE: ERR_emailconf_msg}')
);

	if (isset($err_array['ERR_'.$type])) {
		$err = $err_array['ERR_'.$type];
		error_dialog($err[0], $err[1]);
	} else {
		error_dialog('{TEMPLATE: err_inc_criticaltitle}', '{TEMPLATE: err_inc_criticalmsg}');
	}
}

function invl_inp_err()
{
	error_dialog('{TEMPLATE: core_err_invinp_title}', '{TEMPLATE: core_err_invinp_err}');
}
?>