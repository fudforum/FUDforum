<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: cookies.inc.t,v 1.27 2003/05/01 18:34:35 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

function ses_make_sysid()
{
	if ($GLOBALS['SESSION_USE_URL'] != 'Y') {
		return;
	}

	return md5($_SERVER['HTTP_USER_AGENT'].$_SERVER['REMOTE_ADDR'].(isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? $_SERVER['HTTP_X_FORWARDED_FOR'] : ''));
}

function ses_get($id=0)
{
	if (!$id) {
		if (isset($_COOKIE[$GLOBALS['COOKIE_NAME']])) {
			$q_opt = "s.ses_id='".addslashes($_COOKIE[$GLOBALS['COOKIE_NAME']])."'";
			/* renew cookie */
			setcookie($GLOBALS['COOKIE_NAME'], $_COOKIE[$GLOBALS['COOKIE_NAME']], __request_timestamp__+$GLOBALS['COOKIE_TIMEOUT'], $GLOBALS['COOKIE_PATH'], $GLOBALS['COOKIE_DOMAIN']);
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
		u.blocked, u.email, u.login, u.notify, u.last_read, u.pm_messages
	FROM {SQL_TABLE_PREFIX}ses s 
		INNER JOIN {SQL_TABLE_PREFIX}users u ON u.id=(CASE WHEN s.user_id>2000000000 THEN 1 ELSE s.user_id END)
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
	setcookie($GLOBALS['COOKIE_NAME'], $ses_id, __request_timestamp__+$GLOBALS['COOKIE_TIMEOUT'], $GLOBALS['COOKIE_PATH'], $GLOBALS['COOKIE_DOMAIN']);

	return ses_get($id);
}
function ses_update_status($ses_id, $str=NULL, $forum_id=0, $ret='')
{
	q('UPDATE {SQL_TABLE_PREFIX}ses SET forum_id='.$forum_id.', time_sec='.__request_timestamp__.', action='.($str ? "'".addslashes($str)."'" : 'NULL').', returnto='.(!is_int($ret) ? strnull(addslashes($_SERVER['QUERY_STRING'])) : 'returnto').' WHERE id='.$ses_id);
}
function ses_putvar($ses_id, $data)
{
	$cond = is_int($ses_id) ? 'id='.$ses_id : "ses_id='".$ses_id."'";

	if (empty($data)) {
		q('UPDATE {SQL_TABLE_PREFIX}ses SET data=NULL WHERE '.$cond);
	} else {
		q('UPDATE {SQL_TABLE_PREFIX}ses SET data=\''.addslashes(serialize($data)).'\' WHERE '.$cond);
	}	
}

function ses_delete($ses_id)
{
	if ($GLOBALS['MULTI_HOST_LOGIN'] != 'Y') {
		q('DELETE FROM {SQL_TABLE_PREFIX}ses WHERE id='.$ses_id);
	}
	setcookie($GLOBALS['COOKIE_NAME'], '', __request_timestamp__-100000, $GLOBALS['COOKIE_PATH'], $GLOBALS['COOKIE_DOMAIN']);

	return 1;
}
?>