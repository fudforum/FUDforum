<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: err.inc.t,v 1.21 2003/04/21 14:14:39 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

function error_dialog($title, $msg, $level='WARN', $ses=NULL)
{
	if (!$ses) {
		$ses = (int) $GLOBALS['usr']->sid;
	}

	if (!strcasecmp($level, 'FATAL')) {
		$error_msg = "[Error Level: $level] $title<br />\n";
		$error_msg .= "[Message Sent to User] ".trim($msg)."<br />\n";
		$error_msg .= "[User's IP] ".$_SERVER['REMOTE_ADDR']."<br />\n";
		$error_msg .= "[Requested URL] http://".$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']."<br />\n";
		if (isset($_SERVER['HTTP_REFERER'])) {
			$error_msg .= "[Referring Page] ".$_SERVER['HTTP_REFERER']."<br />\n";
		}
		
		error_log('['.gmdate("D M j G:i:s T Y", __request_timestamp__).'] '.base64_encode($error_msg)."\n", 3, $GLOBALS['ERROR_PATH'].'fud_errors');
	}

	ses_putvar($ses, array('er_msg' => $msg, 'err_t' => $title));

	if (is_int($ses)) {
		header('Location: {ROOT}?t=error&'._rsidl);
	} else {
		header('Location: {ROOT}?t=error&S='.$ses);
	}
	exit;
}

function std_error($type)
{
	if (!isset($_SERVER['HTTP_REFERER'])) {
		$_SERVER['HTTP_REFERER'] = 'unknown';
	}

	$err_array = array(
'ERR_login'=>array('{TEMPLATE: ERR_login_ttl}', '{TEMPLATE: ERR_login_msg}', '{TEMPLATE: ERR_login_url}'),
'ERR_disabled'=>array('{TEMPLATE: ERR_disabled_ttl}', '{TEMPLATE: ERR_disabled_msg}', '{TEMPLATE: ERR_disabled_url}'),
'ERR_access'=>array('{TEMPLATE: ERR_access_ttl}', '{TEMPLATE: ERR_access_msg}', '{TEMPLATE: ERR_access_url}'),
'ERR_registration_disabled'=>array('{TEMPLATE: ERR_registration_disabled_ttl}', '{TEMPLATE: ERR_registration_disabled_msg}', '{TEMPLATE: ERR_registration_disabled_url}'),
'ERR_user'=>array('{TEMPLATE: ERR_user_ttl}', '{TEMPLATE: ERR_user_msg}', '{TEMPLATE: ERR_user_url}'),
'ERR_systemerr'=>array('{TEMPLATE: ERR_systemerr_ttl}', '{TEMPLATE: ERR_systemerr_msg}', '{TEMPLATE: ERR_systemerr_url}')
);

	$err = $err_array['ERR_'.$type];
	if (is_array($err)) {
		error_dialog($err[0], $err[1], $err[2]);
	} else {
		error_dialog('{TEMPLATE: err_inc_criticaltitle}', '{TEMPLATE: err_inc_criticalmsg}');
	}
}

function std_out($text, $level='INFO')
{
	$fp = fopen($GLOBALS['ERROR_PATH'].'std_out.log', 'ab');
	$log_str = gmdate("Y-m-d-H-i-s", __request_timestamp__);
	$log_str .= " [".$level."] ";
	$log_str .= str_replace("\n", ' ', str_replace("\r", ' ', $text))."\n";
	fwrite($fp, $log_str);
	fclose($fp);
	@chmod($GLOBALS['ERROR_PATH'].'std_out.log',($GLOBALS['FILE_LOCK']=='Y'?0600:0666));
}

function invl_inp_err()
{
	error_dialog('{TEMPLATE: core_err_invinp_title}', '{TEMPLATE: core_err_invinp_err}');
}

function fud_sql_error_handler($query, $error_string, $error_number, $server_version)
{
	if (db_locked()) {
		db_unlock();
	}

	if (!isset($_SERVER['PATH_TRANSLATED'])) {
		$_SERVER['PATH_TRANSLATED'] = __FILE__;
	}

	$error_msg = "(".basename($_SERVER['PATH_TRANSLATED']).") ".$error_number.": ".$error_string."<br />\n";
	$error_msg .= "Query: ".htmlspecialchars($query)."<br />\n";
	$error_msg .= "Server Version: ".$server_version."<br />\n";
	if (isset($_SERVER['HTTP_REFERER'])) {
		$error_msg .= "[Referring Page] ".$_SERVER['HTTP_REFERER']."<br />\n";
	}

	if( !error_log('['.gmdate("D M j G:i:s T Y", __request_timestamp__).'] '.base64_encode($error_msg)."\n", 3, $GLOBALS['ERROR_PATH'].'sql_errors') ) {
		echo "<b>UNABLE TO WRITE TO SQL LOG FILE</b><br>\n";
		echo $error_msg;
	} else {
		/* XXX: debug 
		if (_uid && $GLOBALS['usr']->is_mod == 'A') {
			echo $error_msg;
		} else {
			trigger_error('{TEMPLATE: err_inc_query_err}', E_USER_ERROR);
		}
		*/
		echo $error_msg;
	}
	exit;
}
?>