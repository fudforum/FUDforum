<?php
/***************************************************************************
* copyright            : (C) 2001-2004 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: cookies.inc.t,v 1.61 2004/03/08 15:28:59 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; either version 2 of the License, or
* (at your option) any later version.
***************************************************************************/

function ses_make_sysid()
{
	if ($GLOBALS['FUD_OPT_2'] & 256) {
		return;
	}

	$keys = array('HTTP_USER_AGENT', 'SERVER_PROTOCOL', 'HTTP_ACCEPT_CHARSET', 'HTTP_ACCEPT_ENCODING', 'HTTP_ACCEPT_LANGUAGE');
	if ($GLOBALS['FUD_OPT_3'] & 16 && strpos($_SERVER['HTTP_USER_AGENT'], 'AOL') === false) {
		$keys[] = 'HTTP_X_FORWARDED_FOR';
		$keys[] = 'REMOTE_ADDR';
	}
	$pfx = '';
	foreach ($keys as $v) {
		if (isset($_SERVER[$v])) {
			$pfx .= $_SERVER[$v];
		}
	}
	return md5($pfx);
}

function &ses_get($id=0)
{
	if (!$id) {
		if (isset($_COOKIE[$GLOBALS['COOKIE_NAME']])) {
			$q_opt = "s.ses_id='".addslashes($_COOKIE[$GLOBALS['COOKIE_NAME']])."'";
		} else if ((isset($_GET['S']) || isset($_POST['S'])) && $GLOBALS['FUD_OPT_1'] & 128) {
			$q_opt = "s.ses_id='".addslashes((isset($_GET['S']) ? $_GET['S'] : $_POST['S']))."'";
		} else {
			return;
		}
		if ($GLOBALS['FUD_OPT_3'] & 4 && isset($_SERVER['HTTP_REFERER']) && strncmp($_SERVER['HTTP_REFERER'], $GLOBALS['WWW_ROOT'], strlen($GLOBALS['WWW_ROOT']))) {
			/* more checks, we need those because some proxies mangle referer field */
			$host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : $_SERVER['SERVER_NAME'];
			/* $p > 8 https:// or http:// */
			if (($p = strpos($_SERVER['HTTP_REFERER'], $host)) === false || $p > 8) {
				$q_opt .= " AND s.user_id > 2000000000 ";
			}
		}
	} else {
		$q_opt = "s.id='".$id."'";
	}

	$u = db_sab('SELECT
		s.id AS sid, s.ses_id, s.data, s.returnto, s.sys_id,
		t.id AS theme_id, t.lang, t.name AS theme_name, t.locale, t.theme, t.pspell_lang, t.theme_opt,
		u.alias, u.posts_ppg, u.time_zone, u.sig, u.last_visit, u.last_read, u.cat_collapse_status, u.users_opt,
		u.ignore_list, u.ignore_list, u.buddy_list, u.id, u.group_leader_list, u.email, u.login, u.sq
	FROM {SQL_TABLE_PREFIX}ses s
		INNER JOIN {SQL_TABLE_PREFIX}users u ON u.id=(CASE WHEN s.user_id>2000000000 THEN 1 ELSE s.user_id END)
		INNER JOIN {SQL_TABLE_PREFIX}themes t ON t.id=u.theme
	WHERE '.$q_opt);

	if (!$u || $u->id == 1 || $id || $u->sys_id == ses_make_sysid()) {
		return $u;
	}

	/* if strict checks are enabled disallow sys_id mismatch */
	if ($GLOBALS['FUD_OPT_3'] & 16) {
		return;
	}

	/* try doing a strict SQ match in last-ditch effort to make things 'work' */
	if (count($_POST)) {
		if (isset($_POST['SQ']) && $_POST['SQ'] == $u->sq) {
			return $u;
		}
	} else if (isset($_GET['SQ']) && $_GET['SQ'] == $u->sq) {
		return $u;
	}
	return;
}

function &ses_anon_make()
{
	do {
		$uid = 2000000000 + mt_rand(1, 147483647);
		$ses_id = md5($uid . __request_timestamp__ . getmypid());
	} while (!($id = db_li("INSERT INTO {SQL_TABLE_PREFIX}ses (ses_id, time_sec, sys_id, user_id) VALUES ('".$ses_id."', ".__request_timestamp__.", '".ses_make_sysid()."', ".$uid.")", $ef, 1)));

	/* when we have an anon user, we set a special cookie allowing us to see who referred this user */
	if (isset($_GET['rid']) && !isset($_COOKIE['frm_referer_id']) && $GLOBALS['FUD_OPT_2'] & 8192) {
		setcookie($GLOBALS['COOKIE_NAME'].'_referer_id', $_GET['rid'], __request_timestamp__+31536000, $GLOBALS['COOKIE_PATH'], $GLOBALS['COOKIE_DOMAIN']);
	}
	setcookie($GLOBALS['COOKIE_NAME'], $ses_id, __request_timestamp__+$GLOBALS['COOKIE_TIMEOUT'], $GLOBALS['COOKIE_PATH'], $GLOBALS['COOKIE_DOMAIN']);

	return ses_get($id);
}

function ses_update_status($ses_id, $str=null, $forum_id=0, $ret='')
{
	q('UPDATE {SQL_TABLE_PREFIX}ses SET sys_id=\''.ses_make_sysid().'\', forum_id='.$forum_id.', time_sec='.__request_timestamp__.', action='.($str ? "'".addslashes($str)."'" : 'NULL').', returnto='.(!is_int($ret) ? strnull(addslashes($_SERVER['QUERY_STRING'])) : 'returnto').' WHERE id='.$ses_id);
}

function ses_putvar($ses_id, $data)
{
	$cond = is_int($ses_id) ? 'id='.(int)$ses_id : "ses_id='".$ses_id."'";

	if (empty($data)) {
		q('UPDATE {SQL_TABLE_PREFIX}ses SET data=NULL WHERE '.$cond);
	} else {
		q("UPDATE {SQL_TABLE_PREFIX}ses SET data='".addslashes(serialize($data))."' WHERE ".$cond);
	}
}

function ses_delete($ses_id)
{
	if (!($GLOBALS['FUD_OPT_2'] & 256)) {
		q('DELETE FROM {SQL_TABLE_PREFIX}ses WHERE id='.$ses_id);
	}
	setcookie($GLOBALS['COOKIE_NAME'], '', __request_timestamp__-100000, $GLOBALS['COOKIE_PATH'], $GLOBALS['COOKIE_DOMAIN']);

	return 1;
}

function ses_anonuser_auth($id, $error)
{
	q("UPDATE {SQL_TABLE_PREFIX}ses SET data='".addslashes(serialize($error))."', returnto=".strnull(addslashes($_SERVER['QUERY_STRING']))." WHERE id=".$id);
	if ($GLOBALS['FUD_OPT_2'] & 32768) {
		header("Location: {FULL_ROOT}{ROOT}/l/"._rsidl);
	} else {
		header("Location: {FULL_ROOT}{ROOT}?t=login&"._rsidl);
	}
	exit;
}
?>