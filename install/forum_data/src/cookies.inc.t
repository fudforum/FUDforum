<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: cookies.inc.t,v 1.1.1.1 2002/06/17 23:00:09 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

	
class fud_session
{
	var $id=NULL;
	var $ses_id=NULL;
	var $tm=NULL;
	var $user_id=NULL;
	var $data=NULL;
	var $action=NULL;
	var $sys_id=NULL;

	function update($str='')
	{
		if ( strlen($str) ) $this->action = $str;
		Q("UPDATE {SQL_TABLE_PREFIX}ses SET time_sec=".__request_timestamp__.", action=".STRNULL(addslashes($this->action))." WHERE id=".$this->id);
	}

	function putvar($name, $val)
	{
		$this->data[$name] = $val;
	}
	
	function getvar($name)
	{
		return $this->data[$name];
	}
	
	function rmvar($name)
	{
		unset($this->data[$name]);
	}
	
	function save_session($user_id='',$not_use_cookie='')
	{
		if ( !empty($user_id) ) $this->user_id = $user_id;

		if( is_array($this->data) && count($this->data) ) {
			reset($this->data);
			$db_str = '$this->data = array(';
			while ( list($key, $val) = each($this->data) ) {
				$db_str .= "'".addcslashes($key,"'")."'=>'".addcslashes($val,"'")."',";;
			}
			$db_str = substr($db_str, 0, -1).');';
		}
		
		$this->tm = __request_timestamp__;
		
		if ( empty($this->id) ) {
			
			if ( !empty($this->user_id) && $this->user_id<2000000000 )
				Q("DELETE FROM {SQL_TABLE_PREFIX}ses WHERE user_id=".$this->user_id);
			else 
				$this->user_id=0;

			DB_LOCK('{SQL_TABLE_PREFIX}ses+');
				while ( BQ("SELECT id FROM {SQL_TABLE_PREFIX}ses WHERE ses_id='".($ses_id = md5(get_random_value(128)))."'") );
				if( empty($this->user_id) ) $this->user_id = Q_SINGLEVAL("SELECT IF(MAX(user_id)>2000000000,MAX(user_id)+1,2000000001) FROM {SQL_TABLE_PREFIX}ses");
				Q("INSERT INTO {SQL_TABLE_PREFIX}ses (ses_id,time_sec,data,sys_id,user_id) VALUES('".$ses_id."',".$this->tm.",'".addslashes($db_str)."', '".md5($GLOBALS["HTTP_SERVER_VARS"]["HTTP_USER_AGENT"].$GLOBALS["HTTP_SERVER_VARS"]["REMOTE_ADDR"].$GLOBALS["HTTP_SERVER_VARS"]["HTTP_X_FORWARDED_FOR"])."',".$this->user_id.")");
				$this->id = DB_LASTID();
			DB_UNLOCK();
			
			$this->ses_id = $ses_id;
		} 
		else {
			if ( !empty($this->user_id) && $this->user_id<2000000000 ) {
				Q("DELETE FROM {SQL_TABLE_PREFIX}ses WHERE user_id=".$this->user_id." AND ses_id!='".$this->ses_id."'");
				$usr_id_fld = ' user_id='.$this->user_id.',';
			}	
			
			if( empty($GLOBALS['HTTP_COOKIE_VARS'][$GLOBALS['COOKIE_NAME']]) || $not_use_cookie ) 
				$sys_id = md5($GLOBALS["HTTP_SERVER_VARS"]["HTTP_USER_AGENT"].$GLOBALS["HTTP_SERVER_VARS"]["REMOTE_ADDR"].$GLOBALS["HTTP_SERVER_VARS"]["HTTP_X_FORWARDED_FOR"]);
			else 
				$sys_id = 0;
			
			Q("UPDATE {SQL_TABLE_PREFIX}ses SET sys_id='".$sys_id."', ".$usr_id_fld." time_sec=".$this->tm.", data='".addslashes($db_str)."' WHERE id=".$this->id);
		}
		
		if( empty($not_use_cookie) ) $this->cookie_set_session($this->ses_id);
		
		return $this->ses_id;
	}
	
	function restore_session($ses_id)
	{
		if( empty($this->sys_id) ) 
			QOBJ("SELECT * FROM {SQL_TABLE_PREFIX}ses WHERE ses_id='".$ses_id."'", $this);
		else
			QOBJ("SELECT * FROM {SQL_TABLE_PREFIX}ses WHERE ses_id='".$ses_id."' AND sys_id='".$this->sys_id."'", $this);	
			
		if( empty($this->id) ) return;	
			
		if( !empty($this->data) ) eval($this->data);
		
		return $this->ses_id;
	}
	
	function delete_session()
	{
		if ( empty($this->id) ) return;	
		Q("DELETE FROM {SQL_TABLE_PREFIX}ses WHERE id=".$this->id." OR ses_id='".$this->ses_id."'");
		$this->user_id = $this->ses_id = $this->id = $this->data = $this->action = NULL;
		clear_cookie();
		return 1;
	}
	
	function cookie_set_session($ses_id)
	{
		setcookie($GLOBALS['COOKIE_NAME'], $ses_id, __request_timestamp__+$GLOBALS['COOKIE_TIMEOUT'], $GLOBALS['COOKIE_PATH'], $GLOBALS['COOKIE_DOMAIN']);
	}
	
	function cookie_get_session()
	{
		if( !empty($GLOBALS['HTTP_COOKIE_VARS'][$GLOBALS['COOKIE_NAME']]) )
			return $this->restore_session($GLOBALS['HTTP_COOKIE_VARS'][$GLOBALS['COOKIE_NAME']]);
		else if( !empty($GLOBALS["HTTP_GET_VARS"]["S"]) || !empty($GLOBALS["HTTP_POST_VARS"]["S"]) ) {
			$this->sys_id = md5($GLOBALS["HTTP_SERVER_VARS"]["HTTP_USER_AGENT"].$GLOBALS["HTTP_SERVER_VARS"]["REMOTE_ADDR"].$GLOBALS["HTTP_SERVER_VARS"]["HTTP_X_FORWARDED_FOR"]);
			return $this->restore_session((!empty($GLOBALS["HTTP_GET_VARS"]["S"])?$GLOBALS["HTTP_GET_VARS"]["S"]:$GLOBALS["HTTP_POST_VARS"]["S"]));
		}	
		else
			return;
	}
	
	function countvar()
	{
		return count($this->data);
	}
	
	function resetvar()
	{
		if ( is_array($this->data) ) reset($this->data);
	}
	
	function nextvar()
	{
		list($RET['key'], $RET['val']) = each($this->data);
		return $RET;
	}
}

function clear_cookie()
{
	setcookie($GLOBALS['COOKIE_NAME'], 0, __request_timestamp__-100000, $GLOBALS['COOKIE_PATH'], $GLOBALS['COOKIE_DOMAIN']);
}

function set_referer_cookie($id)
{
	setcookie('frm_referer_id', $id, __request_timestamp__+31536000, $GLOBALS['COOKIE_PATH'], $GLOBALS['COOKIE_DOMAIN']);
}

function clear_old_sessions()
{
	Q("DELETE FROM {SQL_TABLE_PREFIX}ses WHERE time_sec<".($tm_sample-$GLOBALS['COOKIE_TIMEOUT'])." OR (time_sec<".(__request_timestamp__-$GLOBALS['SESSION_TIMEOUT'])." AND sys_id!=0)");
}
?>