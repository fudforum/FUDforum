<?php
/**
* copyright            : (C) 2001-2010 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id$
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; version 2 of the License.
**/

/*{PRE_HTML_PHP}*/

	/* Permissions check, this form is only allowed for moderators & admins unless public.
	 * Check if IP display is allowed.
	 */
	if (!($usr->users_opt & (524288|1048576)) && !($FUD_OPT_1 & 134217728)) {
		invl_inp_err();
	}

function __fud_whois($ip, $whois_server='')
{
	if (!$whois_server) {
		$whois_server = $GLOBALS['FUD_WHOIS_SERVER'];
	}

	$er = error_reporting(0);

	if (!$sock = fsockopen($whois_server, 43, $errno, $errstr, 20)) {
		error_reporting($er);
		$errstr = preg_match('/WIN/', PHP_OS) ? utf8_encode($errstr) : $errstr;	// Windows silliness.
		return '{TEMPLATE: ip_connect_err}';
	}
	fputs($sock, $ip ."\n");
	$buffer = '';
	do {
		$buffer .= fread($sock, 10240);
	} while (!feof($sock));
	fclose($sock);

	return $buffer;
}

function fud_whois($ip)
{
	$result = __fud_whois($ip);

	/* Check if ARIN can handle the request or if we need to
	 * request information from another server.
	 */
	if (($p = strpos($result, 'ReferralServer: whois://')) !== false) {
		$p += strlen('ReferralServer: whois://');
		$e = strpos($result, "\n", $p);
		$whois = substr($result, $p, ($e - $p));
		if ($whois) {
			$result = __fud_whois($ip, $whois);
		}
	}

	return ($result ? $result : '{TEMPLATE: ip_no_whois}');
}

/*{POST_HTML_PHP}*/

	if (isset($_POST['ip'])) {
		$_GET['ip'] = $_POST['ip'];
	}
	$ip = isset($_GET['ip']) ? long2ip(ip2long($_GET['ip'])) : '';
	if (isset($_POST['user'])) {
		$_GET['user'] = $_POST['user'];
	}
	if (isset($_GET['user'])) {
		if (($user_id = (int) $_GET['user'])) {
			$user = q_singleval('SELECT alias FROM {SQL_TABLE_PREFIX}users WHERE id='. $user_id);
		} else {
			list($user_id, $user) = db_saq('SELECT id, alias FROM {SQL_TABLE_PREFIX}users WHERE alias=' ._esc(char_fix(htmlspecialchars($_GET['user']))));
		}
	} else {
		$user = '';
	}

	$TITLE_EXTRA = ': {TEMPLATE: ip_title}';

	if ($ip) {
		if (substr_count($ip, '.') == 3) {
			$cond = 'm.ip_addr=\''. $ip .'\'';
		} else {
			$cond = 'm.ip_addr LIKE \''. $ip .'%\'';
		}

		$o = uq('SELECT DISTINCT(m.poster_id), u.alias FROM {SQL_TABLE_PREFIX}msg m INNER JOIN {SQL_TABLE_PREFIX}users u ON m.poster_id=u.id WHERE '. $cond);
		$user_list = '';
		$i = 0;
		while ($r = db_rowarr($o)) {
			$user_list .= '{TEMPLATE: ip_user_entry}';
		}
		unset($o);
		$o = uq('SELECT id, alias FROM {SQL_TABLE_PREFIX}users WHERE reg_ip='. ip2long($ip));
		while ($r = db_rowarr($o)) {
			$user_list .= '{TEMPLATE: ip_user_entry}';
		}
		unset($o);
		$page_data = '{TEMPLATE: ip_users}';
	} else if ($user) {
		$o = uq('SELECT DISTINCT(ip_addr) FROM {SQL_TABLE_PREFIX}msg WHERE poster_id='. $user_id);
		$ip_list = '';
		$i = 0;
		while ($r = db_rowarr($o)) {
			$ip_list .= '{TEMPLATE: ip_ip_entry}';
		}
		unset($o);
		
		$o = uq('SELECT reg_ip FROM {SQL_TABLE_PREFIX}users WHERE id='. $user_id);
		while ($r = db_rowarr($o)) {
			$r[0] = long2ip($r[0]);
			$ip_list .= '{TEMPLATE: ip_ip_entry}';
		}
		unset($o);

		$page_data = '{TEMPLATE: ip_info}';
	} else {
		$page_data = '';
	}

/*{POST_PAGE_PHP_CODE}*/
?>
{TEMPLATE: IP_PAGE}
