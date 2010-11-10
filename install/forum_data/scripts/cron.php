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

	@ini_set('memory_limit', '128M');
	define('forum_debug', 1);
	unset($_SERVER['REMOTE_ADDR']);

	if (strncmp($_SERVER['argv'][0], '.', 1)) {
		require (dirname($_SERVER['argv'][0]) .'/GLOBALS.php');
	} else {
		require (getcwd() .'/GLOBALS.php');
	}

	fud_use('err.inc');
	fud_use('db.inc');
	fud_use('job.inc', true);

	define('sql_p', $GLOBALS['DBHOST_TBL_PREFIX']);

	/* Set language, locale and time zone. */
	$locale = q_singleval('SELECT locale FROM '. sql_p .'themes WHERE theme_opt='. (1|2) .' LIMIT 1');
	$GLOBALS['good_locale'] = setlocale(LC_ALL, $locale);
	date_default_timezone_set($GLOBALS['SERVER_TZ']);

	/* Mark CRON execution. */
	$jobfile = $GLOBALS['ERROR_PATH'] . 'LAST_CRON_RUN';
	touch($jobfile);

	/* Run the next in line job. */
	$job = new fud_job();
	try {
		$job->run();
	} catch (Exception $e) {
		echo 'Unable to run job: '. $e->getMessage();
	}

	// Call cron plugins.
	if (defined('plugins')) {
		plugin_call_hook('CRON');
	}

?>
