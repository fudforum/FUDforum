<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: err.inc.t,v 1.4 2002/08/05 00:47:55 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

/*
 * if severity is >0 the error handler will merely output the error the file 
 * however if the error severity is 0, the the function will terminate the script.
*/ 

function error_handler($function_name, $error_message, $severity)
{	
	if( !($fp = fopen($GLOBALS['ERROR_PATH'].'errors.inc', 'ab')) ) 
		exit('<br><font color="#ff0000">Unable to open error file</font><br>');

	unset($error_msg);

	$err = gmdate("Y-m-d-H-i-s", __request_timestamp__);
	$err .= ' [s:'.$GLOBALS["HTTP_SERVER_VARS"]["SCRIPT_FILENAME"].'@'.$function_name.'] '.str_replace("\r", ' ', str_replace("\n", ' ', $GLOBALS["HTTP_SERVER_VARS"]["REMOTE_ADDR"].' "'.$GLOBALS["HTTP_SERVER_VARS"]["PATH_TRANSLATED"].'" "'.$GLOBALS["HTTP_SERVER_VARS"]["HTTP_USER_AGENT"].'" '.$error_message))."\n";
	
	$error_msg = "\n--------------------------------------\n";
	$error_msg .= "Error in function/script: <b>".$function_name."</b>\n";
	$error_msg .= "has caused the following error: <b>".$error_message."</b>\n";
	$error_msg .= "while processing script name: <b>".$GLOBALS["HTTP_SERVER_VARS"]["SCRIPT_FILENAME"]."</b>\n";
	$error_msg .= "the error occured at: <b>".gmdate("d/m/Y H:i:s T")."</b>\n";
	$error_msg .= "Browser: <b>".$GLOBALS["HTTP_SERVER_VARS"]["HTTP_USER_AGENT"]."</b>\nUser Ip: <b>".$GLOBALS["HTTP_SERVER_VARS"]["REMOTE_ADDR"]."</b>\nScript Accessed: <b>".$GLOBALS["HTTP_SERVER_VARS"]["PATH_TRANSLATED"]."</b>\n\n";
	echo nl2br($error_msg);
	
	fwrite($fp, $err);
	fclose($fp);	
	@chmod($GLOBALS['ERROR_PATH'].'errors.inc', ($GLOBALS['FILE_LOCK']=='Y'?0600:0666));

	if( !$severity ) exit;
}

function error_dialog($title, $msg, $returnto, $level='', $ses=NULL)
{
	if ( empty($ses) ) $ses = $GLOBALS['ses'];
	
	$level = ( empty($level) ) ? 'WARN' : strtoupper($level);
	$ref = !empty($GLOBALS["HTTP_SERVER_VARS"]["HTTP_REFERER"]) ? $GLOBALS["HTTP_SERVER_VARS"]["HTTP_REFERER"] : '';
		
	if ( $level == 'FATAL' ) {
		$err_str = gmdate("Y-m-d-H-i-s", __request_timestamp__);
		$err_str .= " [d:$level]";
		$err_str .= ' '.strip_tags(str_replace("\n", ' ', str_replace("\r", ' ', $title.':'.$msg.':'.$returnto.':'.$ref))).':'.$GLOBALS['HTTP_SERVER_VARS']['REMOTE_ADDR'].':'.$GLOBALS['HTTP_SERVER_VARS']['REQUEST_URI']."\n";
		$fp = fopen($GLOBALS['ERROR_PATH'].'error_dialog.log', 'ab');
			fwrite($fp, $err_str);
			fflush($fp);
		fclose($fp);
		@chmod($GLOBALS['ERROR_PATH'].'error_dialog.log',($GLOBALS['FILE_LOCK']=='Y'?0600:0666));
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

?>