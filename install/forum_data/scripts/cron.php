#!/usr/bin/php -q
<?php
/**
* copyright            : (C) 2001-2017 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id$
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; version 2 of the License.
**/

	@set_time_limit(0);
	define('no_session', 1);
	define('fud_logging', 1);

	if (file_exists('./GLOBALS.php')) {
		require('GLOBALS.php');
	} else if (strncmp($_SERVER['argv'][0], '.', 1)) {
		require (dirname($_SERVER['argv'][0]) .'/GLOBALS.php');
	} else {
		require (getcwd() .'/GLOBALS.php');
	}

	fud_use('err.inc');
	fud_use('db.inc');

	/* Set language, locale and time zone. */
	$sql_p = $GLOBALS['DBHOST_TBL_PREFIX'];
	list($theme_name, $locale) = db_saq(q_limit('SELECT name, locale FROM '. $sql_p .'themes WHERE '. q_bitand('theme_opt', (1|2)) .' = 3', 1));
	$GLOBALS['good_locale'] = setlocale(LC_ALL, $locale);
	date_default_timezone_set($GLOBALS['SERVER_TZ']);

	include($GLOBALS['WWW_ROOT_DISK'] .'theme/'. $theme_name .'/cron.php');
	echo "\n";
?>
