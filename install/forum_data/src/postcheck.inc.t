<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: postcheck.inc.t,v 1.3 2002/07/08 23:15:19 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

$GLOBALS['__error__'] = 0;

function set_err($err, $msg)
{
	$GLOBALS['__err_msg__'][$err] = $msg;
	$GLOBALS['__error__'] = 1;
}

function is_post_error()
{
	return $GLOBALS['__error__'];
}

function get_err($err, $br=0)
{
	if( isset($err) && isset($GLOBALS['__err_msg__'][$err]) )
		return ($br?'{TEMPLATE: post_error_breakback}':'{TEMPLATE: post_error_breakfront}');
}
		
function post_check_images()
{
	global $msg_body;
	
	if ( $GLOBALS['MAX_IMAGE_COUNT'] && $GLOBALS['MAX_IMAGE_COUNT'] < count_images($msg_body) ) {
		return -1;
	}
		
	return 0;
}

function check_post_form()
{
	global $msg_subject;
	
	if ( !strlen($msg_subject) ) {
		set_err('msg_subject', '{TEMPLATE: postcheck_subj_needed}');
	}
	
	if ( post_check_images() ) {
		set_err('msg_body', '{TEMPLATE: postcheck_max_images_err}');
	}
	
	if ( isset($GLOBALS['ses']->at_name) && filter_ext($GLOBALS['ses']->at_name) ) {
		set_err('msg_file', '{TEMPLATE: postcheck_not_allowed_file}');
	}
	
	return $GLOBALS['__error__'];
} 

function check_ppost_form()
{
	global $msg_subject;
	
	if ( !strlen($msg_subject) ) {
		set_err('msg_subject', '{TEMPLATE: postcheck_subj_needed}');
	}
	
	if ( post_check_images() ) {
		set_err('msg_body', '{TEMPLATE: postcheck_max_images_err}');
	}
	
	if ( isset($GLOBALS['ses']->at_name) && filter_ext($GLOBALS['ses']->at_name) ) {
		set_err('msg_file', '{TEMPLATE: postcheck_not_allowed_file}');
	}
	
	$GLOBALS['msg_to_list'] = trim($GLOBALS['msg_to_list']);
	$list = explode(";", $GLOBALS['msg_to_list']);
	while ( list(, $v) = each($list) ) {
		$v = trim($v);
		if( strlen($v) ) {
			$r = q("SELECT {SQL_TABLE_PREFIX}users.id,{SQL_TABLE_PREFIX}user_ignore.ignore_id FROM {SQL_TABLE_PREFIX}users LEFT JOIN {SQL_TABLE_PREFIX}user_ignore ON {SQL_TABLE_PREFIX}user_ignore.user_id={SQL_TABLE_PREFIX}users.id AND {SQL_TABLE_PREFIX}user_ignore.ignore_id=".$GLOBALS["usr"]->id." WHERE {SQL_TABLE_PREFIX}users.alias='".addslashes($v)."'");	
			if( !is_result($r) ) {
				set_err('msg_to_list', '{TEMPLATE: postcheck_no_such_user}');
				break;
			}
			else {
				$obj = db_singleobj($r);
				if( !empty($obj->ignore_id) ) {
					set_err('msg_to_list', '{TEMPLATE: postcheck_ignored}');
					break;
				}
				else 
					$GLOBALS['recv_user_id'][] = $obj->id;	
			}		
		}
	}
	
	if( empty($GLOBALS['msg_to_list']) ) set_err('msg_to_list', '{TEMPLATE: postcheck_no_recepient}');

	return $GLOBALS['__error__'];
} 

function check_femail_form()
{
	if( empty($GLOBALS["HTTP_POST_VARS"]["femail"]) || validate_email($GLOBALS["HTTP_POST_VARS"]["femail"]) ) {
		set_err('femail', '{TEMPLATE: postcheck_invalid_email}');
	}
	
	if( empty($GLOBALS["HTTP_POST_VARS"]["subj"]) ) {
		set_err('subj', '{TEMPLATE: postcheck_email_subject}');
	}
	
	if( empty($GLOBALS["HTTP_POST_VARS"]["body"]) ) {
		set_err('body', '{TEMPLATE: postcheck_email_body}');
	}

	return $GLOBALS['__error__'];
}

function count_images($text)
{
	$text = strtolower($text);
	$a = substr_count($text, '[img]');
	$b = substr_count($text, '[/img]');
	
	return ( ($a>$b)?$b:$a );
}
?>