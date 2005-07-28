<?php
/**
* copyright            : (C) 2001-2004 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: logedin.inc.t,v 1.38 2005/07/28 13:29:01 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; either version 2 of the License, or
* (at your option) any later version.
**/

function &rebuild_stats_cache($last_msg_id)
{
	$tm_expire = __request_timestamp__ - ($GLOBALS['LOGEDIN_TIMEOUT'] * 60);

	list($obj->last_user_id, $obj->user_count) = db_saq('SELECT MAX(id), count(*)-1 FROM {SQL_TABLE_PREFIX}users');

	$obj->online_users_anon	= q_singleval('SELECT count(*) FROM {SQL_TABLE_PREFIX}ses s WHERE time_sec>'.$tm_expire.' AND user_id>2000000000');
	$obj->online_users_hidden = q_singleval('SELECT count(*) FROM {SQL_TABLE_PREFIX}ses s INNER JOIN {SQL_TABLE_PREFIX}users u ON u.id=s.user_id WHERE s.time_sec>'.$tm_expire.' AND (u.users_opt & 32768) > 0');
	$obj->online_users_reg = q_singleval('SELECT count(*) FROM {SQL_TABLE_PREFIX}ses s INNER JOIN {SQL_TABLE_PREFIX}users u ON u.id=s.user_id WHERE s.time_sec>'.$tm_expire.' AND (u.users_opt & 32768)=0');
	$c = uq('SELECT u.id, u.alias, u.users_opt, u.custom_color FROM {SQL_TABLE_PREFIX}ses s INNER JOIN {SQL_TABLE_PREFIX}users u ON u.id=s.user_id WHERE s.time_sec>'.$tm_expire.' AND (u.users_opt & 32768)=0 ORDER BY s.time_sec DESC LIMIT '.$GLOBALS['MAX_LOGGEDIN_USERS']);
	$obj->online_users_text = array();
	while ($r = db_rowarr($c)) {
		$obj->online_users_text[$r[0]] = draw_user_link($r[1], $r[2], $r[3]);
	}
	unset($c);

	q('UPDATE {SQL_TABLE_PREFIX}stats_cache SET
		cache_age='.__request_timestamp__.',
		last_user_id='.(int)$obj->last_user_id.',
		user_count='.(int)$obj->user_count.',
		online_users_anon='.(int)$obj->online_users_anon.',
		online_users_hidden='.(int)$obj->online_users_hidden.',
		online_users_reg='.(int)$obj->online_users_reg.',
		online_users_text='.ssn(addslashes(serialize($obj->online_users_text))));

	$obj->last_user_alias = q_singleval('SELECT alias FROM {SQL_TABLE_PREFIX}users WHERE id='.$obj->last_user_id);
	$obj->last_msg_subject = q_singleval('SELECT subject FROM {SQL_TABLE_PREFIX}msg WHERE id='.$last_msg_id);

	return $obj;
}

$logedin = $forum_info = '';

if ($FUD_OPT_1 & 1073741824 || $FUD_OPT_2 & 16) {
	if (!($st_obj = db_sab('SELECT sc.*,m.subject AS last_msg_subject, u.alias AS last_user_alias FROM {SQL_TABLE_PREFIX}stats_cache sc INNER JOIN {SQL_TABLE_PREFIX}users u ON u.id=sc.last_user_id INNER JOIN {SQL_TABLE_PREFIX}msg m ON m.id='.$last_msg_id.' WHERE sc.cache_age>'.(__request_timestamp__ - $STATS_CACHE_AGE)))) {
		$st_obj = rebuild_stats_cache($last_msg_id);
	} else if ($st_obj->online_users_text) {
		$st_obj->online_users_text = unserialize($st_obj->online_users_text);
	}

	if ($FUD_OPT_1 & 1073741824) {
		if (!empty($st_obj->online_users_text)) {
			foreach($st_obj->online_users_text as $k => $v) {
				$logedin .= '{TEMPLATE: online_user_link} ';
			}
		}
		$logedin = '{TEMPLATE: logedin}';
	}
	if ($FUD_OPT_2 & 16) {
		$forum_info = '{TEMPLATE: forum_info}';
	}
}
?>