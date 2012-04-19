<?php
/**
* copyright            : (C) 2001-2012 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: karma_track.php.t 4898 2010-01-25 21:30:30Z naudefj $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; version 2 of the License.
**/

	define('plain_form', 1);

/*{PRE_HTML_PHP}*/

	/* Only admins have access to this control panel. */
	if (!_uid) {
		std_error('login');
	} if (!($usr->users_opt & 1048576)) {
		std_error('access');
	}

	$msgid   = isset($_GET['msgid'])   ? (int)$_GET['msgid']   : 0;
	$karmaid = isset($_GET['karmaid']) ? (int)$_GET['karmaid'] : 0;
	if (!$msgid) {
		invl_inp_err();
	}

	$usrid = db_sab('SELECT poster_id FROM {SQL_TABLE_PREFIX}msg WHERE id = '. $msgid);
	if (!$usrid) {
		invl_inp_err();
	}

	/* delete rating */
	if ($karmaid && sq_check(0, $usr->sq) && $msgid) {
		q('DELETE FROM {SQL_TABLE_PREFIX}karma_rate_track WHERE msg_id='. $msgid .' AND id = '. $karmaid);
		$rt = db_saq('SELECT SUM(rating) FROM {SQL_TABLE_PREFIX}karma_rate_track WHERE poster_id='. $usrid->poster_id);
		q('UPDATE {SQL_TABLE_PREFIX}users SET karma='. (int)$rt[0] .' WHERE id='. $usrid->poster_id);

		logaction(_uid, 'DELKARMA', 0, 'removed karma of user '. $usrid->poster_id .' for message '. $msgid);
	}

/*{POST_HTML_PHP}*/

	$c = uq('SELECT u.alias, k.rating, k.id, k.msg_id FROM {SQL_TABLE_PREFIX}karma_rate_track k INNER JOIN {SQL_TABLE_PREFIX}users u ON k.user_id = u.id WHERE k.poster_id = '. $usrid->poster_id);
	$table_data = '';
	while ($r = db_rowarr($c)) {
		$table_data .= '{TEMPLATE: karma_track_entry}';
	}
	unset($c);
/*{POST_PAGE_PHP_CODE}*/
?>
{TEMPLATE: KARMA_TRACK_PAGE}
