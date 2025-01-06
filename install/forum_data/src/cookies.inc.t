<?php
/**
* copyright            : (C) 2001-2025 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id$
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; version 2 of the License.
**/

function ses_make_sysid()
{
	if ($GLOBALS['FUD_OPT_2'] & 256) {	// MULTI_HOST_LOGIN
		return;
	}

	$keys = array('REMOTE_USER', 'HTTP_USER_AGENT', 'SERVER_PROTOCOL', 'HTTP_ACCEPT_CHARSET', 'HTTP_ACCEPT_LANGUAGE');
	if ($GLOBALS['FUD_OPT_3'] & 16) {	// SESSION_IP_CHECK
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

function ses_get($id=0)
{
	if (!$id) {
		/* Cookie or URL session? If not, check for known bots. */
		if (!empty($_COOKIE[$GLOBALS['COOKIE_NAME']])) {
			/* Have cookie */
			$q_opt = 's.ses_id='. _esc($_COOKIE[$GLOBALS['COOKIE_NAME']]);
		} else if ((isset($_GET['S']) || isset($_POST['S'])) && $GLOBALS['FUD_OPT_1'] & 128) {
			/* Have session string */
			$url_session = 1;
			$q_opt = 's.ses_id='. _esc((isset($_GET['S']) ? (string) $_GET['S'] : (string) $_POST['S']));
			/* Do not validate against expired URL sessions. */
			$q_opt .= ' AND s.time_sec > '. (__request_timestamp__ - $GLOBALS['SESSION_TIMEOUT']);
		} else {
			/* Unknown user, maybe bot? */
			// Auto login authorized bots.
			// To test: wget --user-agent="Googlebot 1.2" http://127.0.0.1:8080/forum
			$spider_session = 0;
			$my_ip = get_ip();

			include $GLOBALS['FORUM_SETTINGS_PATH'] .'spider_cache';
			foreach ($spider_cache as $spider_id => $spider) {
				if (preg_match('/'. $spider['useragent'] .'/i', $_SERVER['HTTP_USER_AGENT'])) {
					if (empty($spider['bot_ip'])) {
						$spider_session = 1;	// Agent matched, no IPs to check.
						break; 
					} else {
						foreach (explode(',', $spider['bot_ip']) as $bot_ip) {
							if (!($bot_ip = trim($bot_ip))) {
								continue;
							}
							if (strpos($bot_ip, $my_ip) === 0)	{
								$spider_session = 1;	// Agent and an IP matched.
								break;
							}
						}
					}
				}
			}
			if ($spider_session) {
				if ($spider['bot_opts'] & 2) {	// Access blocked.
					die('Go away!');
				}
				if ($id = db_li('INSERT INTO {SQL_TABLE_PREFIX}ses (ses_id, time_sec, sys_id, ip_addr, useragent, user_id) VALUES (\''. $spider['botname'] .'\', '. __request_timestamp__ .', '. _esc(ses_make_sysid()) .', '. _esc($my_ip) .', '. _esc(substr($_SERVER['HTTP_USER_AGENT'], 0, 64)) .', '. $spider['user_id'] .')', $ef, 1)) {
					$q_opt = 's.id='. $id;
				} else {
					$q_opt = 's.ses_id='. _esc($spider['botname']);
				}
				$GLOBALS['FUD_OPT_1'] ^= 128;	// Disable URL sessions for user.
			} else {
				/* NeXuS: What is this? Return if user unknown? Function should
				   return only after the query is run. */
				//return;
				
				// Check sys_id, ip_addr and useragent for a possible match
				$q_opt = 's.sys_id= '._esc(ses_make_sysid()).
				         ' AND s.ip_addr='._esc(get_ip()).
						 ' AND s.useragent='._esc(substr($_SERVER['HTTP_USER_AGENT'], 0, 64));
			}
		}

		/* ENABLE_REFERRER_CHECK */
		if ($GLOBALS['FUD_OPT_3'] & 4 && isset($_SERVER['HTTP_REFERER']) && strncmp($_SERVER['HTTP_REFERER'], $GLOBALS['WWW_ROOT'], strlen($GLOBALS['WWW_ROOT']))) {
			/* More checks, we need those because some proxies mangle referer field. */
			$host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : $_SERVER['SERVER_NAME'];
			/* $p > 8 https:// or http:// */
			if (($p = strpos($_SERVER['HTTP_REFERER'], $host)) === false || $p > 8) {
				$q_opt .= ' AND s.user_id > 2000000000 ';	// Different referrer, force anonymous.
			}
		}
	} else {
		$q_opt = 's.id='. $id;
	}

	$u = db_sab('SELECT
		s.id AS sid, s.ses_id, s.data, s.returnto, s.sys_id,
		t.id AS theme_id, t.lang, t.name AS theme_name, t.locale, t.theme, t.pspell_lang, t.theme_opt,
		u.alias, u.posts_ppg, u.time_zone, u.sig, u.last_visit, u.last_read, u.cat_collapse_status, u.users_opt, u.posted_msg_count, u.topics_per_page,
		u.ignore_list, u.ignore_list, u.buddy_list, u.id, u.group_leader_list, u.email, u.login, u.sq, u.ban_expiry, u.ban_reason, u.flag_cc
	FROM {SQL_TABLE_PREFIX}ses s
		INNER JOIN {SQL_TABLE_PREFIX}users u ON u.id=(CASE WHEN s.user_id>2000000000 THEN 1 ELSE s.user_id END)
		LEFT OUTER JOIN {SQL_TABLE_PREFIX}themes t ON t.id=u.theme
	WHERE '. $q_opt);

	/* Anon user, no session or login. */
	if (!$u || $u->id == 1 || $id) {
		return $u;
	}

	if ($u->sys_id == ses_make_sysid()) {
		return $u;
	} else if ($GLOBALS['FUD_OPT_3'] & 16 || isset($url_session)) {
		/* URL sessions must validate sys_id check and SESSION_IP_CHECK must be disabled */
		return;
	}

	/* Try doing a strict SQ match in last-ditch effort to make things 'work'. */
	if (isset($_POST['SQ']) && $_POST['SQ'] == $u->sq) {
		return $u;
	}

	return;
}

/** Create an anonymous session. */
function ses_anon_make()
{
	// Prevent forum scraping and brute force attacks.
	if ($GLOBALS['MAX_CALLS_FROM_IP'] > 0) {
		$ip_count = q_singleval('SELECT count(ip_addr) FROM {SQL_TABLE_PREFIX}ses WHERE ip_addr = '. _esc(get_ip()));
		if ($ip_count > $GLOBALS['MAX_CALLS_FROM_IP']) {
			header('HTTP/1.1 429 Too Many Requests', true, 429);
			echo 'Too Many Requests';
			die();
		}
	}

	do {
		$uid = 2000000000 + mt_rand(1, 147483647);
		$ses_id = md5($uid . __request_timestamp__ . getmypid());
	} while (!($id = db_li('INSERT INTO {SQL_TABLE_PREFIX}ses (ses_id, time_sec, sys_id, ip_addr, useragent, user_id) VALUES (\''. $ses_id .'\', '. __request_timestamp__ .', '. _esc(ses_make_sysid()) .', '. _esc(get_ip()) .', '. _esc(substr($_SERVER['HTTP_USER_AGENT'], 0, 64)) .', '. $uid .')', $ef, 1)));

	/* When we have an anon user, we set a special cookie allowing us to see who referred this user. */
	if (isset($_GET['rid']) && !isset($_COOKIE['frm_referer_id']) && $GLOBALS['FUD_OPT_2'] & 8192) {
		setcookie($GLOBALS['COOKIE_NAME'] .'_referer_id', $_GET['rid'], __request_timestamp__+31536000, $GLOBALS['COOKIE_PATH'], $GLOBALS['COOKIE_DOMAIN']);
	}

	if ($GLOBALS['FUD_OPT_3'] & 1) {        // SESSION_COOKIES
		setcookie($GLOBALS['COOKIE_NAME'], $ses_id, 0,                                                $GLOBALS['COOKIE_PATH'], $GLOBALS['COOKIE_DOMAIN']);
	} else {
		setcookie($GLOBALS['COOKIE_NAME'], $ses_id, __request_timestamp__+$GLOBALS['COOKIE_TIMEOUT'], $GLOBALS['COOKIE_PATH'], $GLOBALS['COOKIE_DOMAIN']);
	}

	return ses_get($id);
}

/** Update session status to indicate last known action. */
function ses_update_status($ses_id, $action=null, $forum_id=0, $ret='')
{
	if (empty($ses_id)) {
		die('FATAL ERROR: No session, check your forum\'s URL and COOKIE settings.');
	}
	if (strlen($_SERVER['QUERY_STRING']) > 255) {
		// Query string exceeds 'returnto' column length.
                die('FATAL ERROR: QUERY_STRING too long!');
        }
	$sys_id = ses_make_sysid();
	q('UPDATE {SQL_TABLE_PREFIX}ses SET sys_id=\''. $sys_id .'\', forum_id='. $forum_id .', time_sec='. __request_timestamp__ .', action='. ($action ? _esc($action) : 'NULL') .', returnto='. (!is_int($ret) ? (isset($_SERVER['QUERY_STRING']) ? _esc($_SERVER['QUERY_STRING']) : 'NULL') : 'returnto') .' WHERE id='. $ses_id);
}

/** Save or clear a session variable. */
function ses_putvar($ses_id, $data)
{
	$cond = is_int($ses_id) ? 'id='. (int)$ses_id : 'ses_id=\''. $ses_id .'\'';

	if (empty($data)) {
		q('UPDATE {SQL_TABLE_PREFIX}ses SET data=NULL WHERE '. $cond);
	} else {
		q('UPDATE {SQL_TABLE_PREFIX}ses SET data='. _esc(serialize($data)) .' WHERE '. $cond);
	}
}

/** Destroy a session. */
function ses_delete($ses_id)
{
	// Delete all forum sessions.
	// Regardless of MULTI_HOST_LOGIN, all sessions will be terminated.
	q('DELETE FROM {SQL_TABLE_PREFIX}ses WHERE id='. $ses_id);
	setcookie($GLOBALS['COOKIE_NAME'], '', __request_timestamp__-100000, $GLOBALS['COOKIE_PATH'], $GLOBALS['COOKIE_DOMAIN']);

	return 1;
}

function ses_anonuser_auth($id, $error)
{
	if (!empty($_POST)) {
		$_SERVER['QUERY_STRING'] = '';
	}
	q('UPDATE {SQL_TABLE_PREFIX}ses SET data='. _esc(serialize($error)) .', returnto='. ssn($_SERVER['QUERY_STRING']) .' WHERE id='. $id);
	if ($GLOBALS['FUD_OPT_2'] & 32768) {	// USE_PATH_INFO
		header('Location: {ROOT}/l/'. _rsidl);
	} else {
		header('Location: {ROOT}?t=login&'. _rsidl);
	}
	exit;
}
?>
