<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: cookies.inc.t,v 1.19 2003/04/09 10:55:57 hackie Exp $
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
	var $id, $ses_id, $tm, $user_id, $data, $action, $sys_id, $returnto;

	function update($str=NULL, $forum_id=0)
	{
		if ($str) {
			$this->action = $str;
			q('UPDATE {SQL_TABLE_PREFIX}ses SET forum_id='.$forum_id.', time_sec='.__request_timestamp__.', action='.strnull(addslashes($this->action)).', returnto='.strnull(addslashes($_SERVER['QUERY_STRING'])).' WHERE id='.$this->id);
		} else {
			q('UPDATE {SQL_TABLE_PREFIX}ses SET forum_id='.$forum_id.', time_sec='.__request_timestamp__.', action=NULL, returnto='.strnull(addslashes($_SERVER['QUERY_STRING'])).' WHERE id='.$this->id);
		}
	}
	function action_update($str)
	{
		q('UPDATE {SQL_TABLE_PREFIX}ses SET time_sec='.__request_timestamp__.', action=\''.addslashes($str).'\' WHERE id='.$this->id);
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

	function sync_vars($clear=FALSE)
	{
		
	}

	function save_session($user_id=0,$not_use_cookie='')
	{
		if ($user_id) {
			$this->user_id = $user_id;
		}

		if ($this->data) {
			$db_str = "'". addslashes(serialize($this->data)) . "'";
		} else {
			$db_str = 'NULL';
		}
		
		if ($GLOBALS['MULTI_HOST_LOGIN'] == 'Y' && $this->user_id && !$not_use_cookie) {
			if (($res = db_saq("SELECT ses_id, id FROM {SQL_TABLE_PREFIX}ses WHERE user_id='".$this->user_id."'"))) {
				if ($res[1] != $this->id) {
					q('DELETE FROM {SQL_TABLE_PREFIX}ses WHERE id='.$this->id);
				}
				
				$this->id = $res[1];
				$this->ses_id = $res[2];
			}
		}
		
		if (!$this->id) {
			if ($this->user_id && $this->user_id < 2000000000) {
				q("DELETE FROM {SQL_TABLE_PREFIX}ses WHERE user_id=".$this->user_id);
			} else {
				$this->user_id = 0;
			}

			if (!db_locked()) {
				db_lock('{SQL_TABLE_PREFIX}ses WRITE');
				$ll = 1;
			}

			while (bq("SELECT id FROM {SQL_TABLE_PREFIX}ses WHERE ses_id='".($ses_id = md5(get_random_value(128)))."'"));
			if(!$this->user_id) {
				$this->user_id = q_singleval("SELECT CASE WHEN MAX(user_id)>2000000000 THEN MAX(user_id)+1 ELSE 2000000001 END FROM {SQL_TABLE_PREFIX}ses");
			}
			if (!isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
				$_SERVER['HTTP_X_FORWARDED_FOR'] = '';
			}
			
			$this->id = db_qid("INSERT INTO {SQL_TABLE_PREFIX}ses (ses_id,time_sec,data,sys_id,user_id) VALUES('".$ses_id."',".__request_timestamp__.", ".$db_str.", '".md5($_SERVER['HTTP_USER_AGENT'].$_SERVER['REMOTE_ADDR'].$_SERVER['HTTP_X_FORWARDED_FOR'])."',".$this->user_id.")");

			if (isset($ll)) {
				db_unlock();
			}
			
			$this->ses_id = $ses_id;
		} else {
			if ($this->user_id && $this->user_id<2000000000) {
				q("DELETE FROM {SQL_TABLE_PREFIX}ses WHERE user_id=".$this->user_id." AND ses_id!='".$this->ses_id."'");
				$usr_id_fld = ' user_id='.$this->user_id.',';
			}	
			
			if (isset($_COOKIE[$GLOBALS['COOKIE_NAME']]) || $not_use_cookie) {
				if (!isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
					$_SERVER['HTTP_X_FORWARDED_FOR'] = '';
				}
				q("UPDATE {SQL_TABLE_PREFIX}ses SET sys_id='".md5($_SERVER['HTTP_USER_AGENT'].$_SERVER['REMOTE_ADDR'].$_SERVER['HTTP_X_FORWARDED_FOR'])."', ".$usr_id_fld." time_sec=".__request_timestamp__.", data=".$db_str." WHERE id=".$this->id);
			} else {
				q("UPDATE {SQL_TABLE_PREFIX}ses SET sys_id=NULL, ".(isset($usr_id_fld)?$usr_id_fld:'')." time_sec=".__request_timestamp__.", data=".$db_str." WHERE id=".$this->id);
			}
		}
		
		if (empty($not_use_cookie)) {
			setcookie($GLOBALS['COOKIE_NAME'], $this->ses_id, __request_timestamp__+$GLOBALS['COOKIE_TIMEOUT'], $GLOBALS['COOKIE_PATH'], $GLOBALS['COOKIE_DOMAIN']);
		}
		
		return $this->ses_id;
	}
	
	function restore_session($ses_id)
	{
		if (!$this->sys_id) {
			qobj("SELECT * FROM {SQL_TABLE_PREFIX}ses WHERE ses_id='".addslashes($ses_id)."'", $this);
		} else {
			qobj("SELECT * FROM {SQL_TABLE_PREFIX}ses WHERE ses_id='".addslashes($ses_id)."' AND sys_id='".$this->sys_id."'", $this);	
		}
			
		if (!$this->id) {
			return;
		}
			
		if ($this->data) {
			$this->data = unserialize($this->data);
		}
		
		return $this->ses_id;
	}
	
	
}

function ses_make_sysid()
{
	return md5($_SERVER['HTTP_USER_AGENT'].$_SERVER['REMOTE_ADDR'].(isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? $_SERVER['HTTP_X_FORWARDED_FOR'] : ''));
}

function ses_get($id=0)
{
	if (!$id) {
		if (isset($_COOKIE[$GLOBALS['COOKIE_NAME']])) {
			$q_opt = "s.ses_id='".addslashes($_COOKIE[$GLOBALS['COOKIE_NAME']])."'";
		} else if (isset($_REQUEST['S']) && $GLOBALS['SESSION_USE_URL'] == 'Y') {
			$q_opt = "s.ses_id='".addslashes($_REQUEST['S'])."' AND sys_id='".ses_make_sysid()."'";
		} else {
			return;
		}
	} else {
		$q_opt = "s.id='".$id."'";
	}

	return db_sab('SELECT 
		s.id AS sid, s.ses_id, s.data, s.returnto,
		
		t.id AS theme_id, t.lang, t.name AS theme_name, t.locale, t.theme, t.pspell_lang,	
		
		u.alias, u.append_sig, u.show_sigs, u.show_avatars, u.show_im, u.posts_ppg, u.time_zone,
		u.sig, u.last_visit, u.email_conf, u.last_read, u.default_view, u.is_mod, u.cat_collapse_status,
		u.ignore_list, u.acc_status, u.ignore_list, u.buddy_list, u.id, u.group_leader_list, u.coppa,
		u.blocked, u.email, u.login, u.notify
	FROM {SQL_TABLE_PREFIX}ses s 
		INNER JOIN {SQL_TABLE_PREFIX}users u ON u.id=(CASE WHEN s.user_id>2000000000 THEN 2147483647 ELSE s.user_id END)
		INNER JOIN {SQL_TABLE_PREFIX}themes t ON t.id=u.theme 
	WHERE '.$q_opt);
}

function ses_anon_make()
{
	db_lock('{SQL_TABLE_PREFIX}ses WRITE');
	while (bq("SELECT id FROM {SQL_TABLE_PREFIX}ses WHERE ses_id='".($ses_id = md5(get_random_value(128)))."'"));
	$uid = q_singleval('SELECT CASE WHEN MAX(user_id)>2000000000 THEN MAX(user_id)+1 ELSE 2000000001 END FROM {SQL_TABLE_PREFIX}ses');
	$id = db_qid("INSERT INTO {SQL_TABLE_PREFIX}ses (ses_id,time_sec,sys_id,user_id) VALUES('".$ses_id."',".__request_timestamp__.", '".ses_make_sysid()."', ".$uid.")");
	db_unlock();

	/* when we have an anon user, we set a special cookie allowing us to see who referred this user */
	if (isset($_GET['rid']) && !isset($_COOKIE['frm_referer_id']) && $GLOBALS['TRACK_REFERRALS'] == 'Y') {
		setcookie($GLOBALS['COOKIE_NAME'].'_referer_id', $_GET['rid'], __request_timestamp__+31536000, $GLOBALS['COOKIE_PATH'], $GLOBALS['COOKIE_DOMAIN']);
	}

	return ses_get($id);
}
function ses_update_status($ses_id, $str=NULL, $forum_id=0, $ret='')
{
	q('UPDATE {SQL_TABLE_PREFIX}ses SET forum_id='.$forum_id.', time_sec='.__request_timestamp__.', action='.($str ? "'".addslashes($str)."'" : 'NULL').', returnto='.(!is_int($ret) ? strnull(addslashes($_SERVER['QUERY_STRING'])) : 'returnto').' WHERE id='.$ses_id);
}
function ses_putvar($ses_id, $data)
{
	if (empty($data)) {
		q('UPDATE {SQL_TABLE_PREFIX}ses SET data=NULL WHERE id='.$ses_id);
	} else {
		q('UPDATE {SQL_TABLE_PREFIX}ses SET data=\''.addslashes(serialize($data)).'\' WHERE id='.$ses_id);
	}	
}

function ses_delete(&$usr)
{
	if (!$usr->sid) {
		return;	
	}
	q('DELETE FROM {SQL_TABLE_PREFIX}ses WHERE id='.$usr->sid);
	unset($usr->sid, $usr->ses_id, $usr->id, $usr->returnto);
	setcookie($GLOBALS['COOKIE_NAME'], '', __request_timestamp__-100000, $GLOBALS['COOKIE_PATH'], $GLOBALS['COOKIE_DOMAIN']);

	return 1;
}
?>