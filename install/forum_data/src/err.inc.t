<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: err.inc.t,v 1.8 2002/08/28 20:32:44 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

function error_dialog($title, $msg, $returnto, $level='', $ses=NULL)
{
	if ( empty($ses) ) $ses = $GLOBALS['ses'];
	
	$level = ( empty($level) ) ? 'WARN' : strtoupper($level);
	$ref = !empty($GLOBALS["HTTP_SERVER_VARS"]["HTTP_REFERER"]) ? $GLOBALS["HTTP_SERVER_VARS"]["HTTP_REFERER"] : '';
		
	if ( $level == 'FATAL' ) {
		$error_msg = "[Error Level: $level] $title<br />\n";
		$error_msg .= "[Message Sent to User] ".trim($msg)."<br />\n";
		$error_msg .= "[User's IP] ".$GLOBALS['HTTP_SERVER_VARS']['REMOTE_ADDR']."<br />\n";
		$error_msg .= "[Requested URL] http://".$GLOBALS['HTTP_SERVER_VARS']['HTTP_HOST'].$GLOBALS['HTTP_SERVER_VARS']['REQUEST_URI']."<br />\n";
		if( !empty($GLOBALS["HTTP_SERVER_VARS"]["HTTP_REFERER"]) ) $error_msg .= "[Referring Page] ".$GLOBALS["HTTP_SERVER_VARS"]["HTTP_REFERER"]."<br />\n";
		
		error_log('['.gmdate("D M j G:i:s T Y", __request_timestamp__).'] '.base64_encode($error_msg)."\n", 3, $GLOBALS['ERROR_PATH'].'fud_errors');
	}

	$err_id = md5(get_random_value(128).__request_timestamp__);
	$ses->putvar('err_id', $err_id);
	$ses->putvar('er_msg', $msg);
	$ses->putvar('err_t', $title);
	$ses->putvar('ret_to', base64_encode($returnto));
	$ses->save_session();
	header('Location: {ROOT}?t=error&'.str_replace("&amp;", "&", _rsid).'&err_id='.$err_id);
	exit;
}

function std_error($type)
{
	$err_array = array(
'ERR_login'=>array('{TEMPLATE: ERR_login_ttl}', '{TEMPLATE: ERR_login_msg}', '{TEMPLATE: ERR_login_url}'),
'ERR_disabled'=>array('{TEMPLATE: ERR_disabled_ttl}', '{TEMPLATE: ERR_disabled_msg}', '{TEMPLATE: ERR_disabled_url}'),
'ERR_access'=>array('{TEMPLATE: ERR_access_ttl}', '{TEMPLATE: ERR_access_msg}', '{TEMPLATE: ERR_access_url}'),
'ERR_registration_disabled'=>array('{TEMPLATE: ERR_registration_disabled_ttl}', '{TEMPLATE: ERR_registration_disabled_msg}', '{TEMPLATE: ERR_registration_disabled_url}'),
'ERR_emailconf'=>array('{TEMPLATE: ERR_emailconf_ttl}', '{TEMPLATE: ERR_emailconf_msg}', '{TEMPLATE: ERR_emailconf_url}'),
'ERR_user'=>array('{TEMPLATE: ERR_user_ttl}', '{TEMPLATE: ERR_user_msg}', '{TEMPLATE: ERR_user_url}'),
'ERR_systemerr'=>array('{TEMPLATE: ERR_systemerr_ttl}', '{TEMPLATE: ERR_systemerr_msg}', '{TEMPLATE: ERR_systemerr_url}')
);

	$err = $err_array["ERR_".$type];
	if ( is_array($err) ) {
		error_dialog($err[0], $err[1], $err[2], $err[3]);
	}
	else {
		error_dialog('{TEMPLATE: err_inc_criticaltitle}', '{TEMPLATE: err_inc_criticalmsg}', '{ROOT}?t=index&'._rsid, 'FATAL');
		exit();
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
	error_dialog('{TEMPLATE: core_err_invinp_title}', '{TEMPLATE: core_err_invinp_err}', NULL, 'FATAL');
	exit;
}

function fud_sql_error_handler($query, $error_string, $error_number, $server_version)
{
	if( db_locked() ) db_unlock();

	if( empty($HTTP_SERVER_VARS['PATH_TRANSLATED']) ) $HTTP_SERVER_VARS['PATH_TRANSLATED'] = $GLOBALS['HTTP_SERVER_VARS']['argv'][0];
	
	$error_msg = "(".basename($HTTP_SERVER_VARS['PATH_TRANSLATED']).") ".$error_number.": ".$error_string."<br />\n";
	$error_msg .= "Query: ".htmlspecialchars($query)."<br />\n";
	$error_msg .= "Server Version: ".$server_version."<br />\n";
	
	if( !error_log('['.gmdate("D M j G:i:s T Y", __request_timestamp__).'] '.base64_encode($error_msg)."\n", 3, $GLOBALS['ERROR_PATH'].'sql_errors') ) {
		echo "<b>UNABLE TO WRITE TO SQL LOG FILE</b><br>\n";
		echo $error_msg;
	} else {
		if( isset($GLOBALS['usr']) && $GLOBALS['usr']->is_mod == 'A' ) 
			echo $error_msg;
		else	
			trigger_error('{TEMPLATE: err_inc_query_err}', E_USER_ERROR);
	}
	exit;
}
?>