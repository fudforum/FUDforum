<?php
/***************************************************************************
* copyright            : (C) 2001-2004 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: ip.php.t,v 1.2 2004/04/07 16:30:08 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; either version 2 of the License, or
* (at your option) any later version.
***************************************************************************/

/*{PRE_HTML_PHP}*/

	/* permissions check, this form is only allowed for moderators & admins unless public 
	 * IP display is allowed
	 */
	if (!($usr->users_opt & (524288|1048576)) && !($FUD_OPT_1 & 134217728)) {
		invl_inp_err();
	}

function __fud_whois($ip, $whois_server='whois.arin.net')
{
	$er = error_reporting(0);

	if (!$sock = fsockopen($whois_server, 43, $n, $e, 20)) {
		error_reporting($er);
		return;
	}
	fputs($sock, $ip."\n");
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

	/* check if Arin can handle the request or if we need to 
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
	$ip = isset($_GET['ip']) ? addslashes($_GET['ip']) : '';
	if (isset($_POST['user'])) {
		$_GET['user'] = $_POST['user'];
	}
	if (isset($_GET['user'])) {
		if (is_numeric($_GET['user'])) {
			$user_id = (int) $_GET['user'];
			$user = q_singleval("SELECT alias FROM {SQL_TABLE_PREFIX}users WHERE id=".$user_id);
		} else {
			list($user_id, $user) = db_saq("SELECT id, alias FROM {SQL_TABLE_PREFIX}users WHERE alias='".addslashes($_GET['user'])."'");
		}
	} else {
		$user = '';
	}

	$TITLE_EXTRA = ': {TEMPLATE: ip_title}';

	$page_data = '';
	if ($ip) {
		if (substr_count($ip, '.') == 3) {
			$cond = "m.ip_addr='".$ip."'";
		} else {
			$cond = "m.ip_addr LIKE '".$ip."%'";
		}

		$o = uq("SELECT DISTINCT(m.poster_id), u.alias from {SQL_TABLE_PREFIX}msg m INNER JOIN {SQL_TABLE_PREFIX}users u ON m.poster_id=u.id WHERE ".$cond);
		$user_list = ' ';
		$i = 1;
		while ($r = db_rowarr($o)) {
			$user_list .= '{TEMPLATE: ip_user_entry}';
			$i++;
		}
		if ($user_list) {
			$page_data = '{TEMPLATE: ip_users}';
		}
	} else if ($user) {
		$o = uq("SELECT DISTINCT(ip_addr) FROM {SQL_TABLE_PREFIX}msg WHERE poster_id=".$user_id);
		$ip_list = ' ';
		$i = 1;
		while ($r = db_rowarr($o)) {
			$ip_list .= '{TEMPLATE: ip_ip_entry}';
			$i++;
		}
		if ($ip_list) {
			$page_data = '{TEMPLATE: ip_info}';
		}
	}

/*{POST_PAGE_PHP_CODE}*/	
?>
{TEMPLATE: IP_PAGE}
