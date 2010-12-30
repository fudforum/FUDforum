#!/usr/bin/php -q
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

	if (!ini_get('register_argc_argv')) {
		exit("Please enable the 'register_argc_argv' php.ini directive.\n");
	}

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
	$locale = q_singleval(q_limit('SELECT locale FROM '. sql_p .'themes WHERE theme_opt='. (1|2), 1));
	$GLOBALS['good_locale'] = setlocale(LC_ALL, $locale);
	date_default_timezone_set($GLOBALS['SERVER_TZ']);

	/* Mark CRON execution. */
	$jobfile = $GLOBALS['ERROR_PATH'] . 'LAST_CRON_RUN';
	touch($jobfile);

	/* Specific job or next in line. */
	if (!empty($_SERVER['argv'][1]) && is_numeric($_SERVER['argv'][1])) {
		$job = db_sab('SELECT * FROM '. $GLOBALS['DBHOST_TBL_PREFIX'] .'jobs WHERE id='. $_SERVER['argv'][1]);
	} else {	// Next due.
		$job = db_sab(q_limit('SELECT * FROM '. $GLOBALS['DBHOST_TBL_PREFIX'] .'jobs WHERE nextrun <= '. __request_timestamp__ .' AND '. q_bitand('job_opt', 1) .' != 1 ORDER BY nextrun ASC', 1));
	}

	// Check if we need have something to run.
	if (!$job) {    // Nothing to do.
		echo date('d M Y H:i:s') .": Nothing to run.\n";
		return;
	}

	/* Skip if the job is locked (still running) and locked less than 10 min ago. */
	if ($job->locked != 0 && $job->locked > __request_timestamp__ - 600) {
		echo date('d M Y H:i:s') .': '. $job->cmd ." is locked (busy running).\n";
		return;
	}

	$path   = $GLOBALS['DATA_DIR'] .'scripts/';
	chdir($path) or die('ERROR: Unable to change to scripts directory '. $path);

	$php  = escapeshellcmd($GLOBALS['PHP_CLI']);
	if (empty($php)) {
		$php = $_SERVER['_'];   // Get from Linux env.
	}
	if (empty($php)) {
		throw new Exception('PHP CLI Executable not set.');
	}

	if (preg_match('/(.*)\s+(.*)/', $job->cmd, $m) && isset($m[2]) ) {
		$script = escapeshellcmd($m[1]);
		$cmd = $script .' '. escapeshellarg($m[2]);
	} else {
		$script = escapeshellcmd($job->cmd);
		$cmd = $script;
	}
	$outfile = ' >'. $script .'_'. $job->id .'.log 2>&1';

	if (!file_exists($path . $script)) {
		throw new Exception('Cannot run task '. $path . $script .'. No such file!');
	}

	$exec = new fud_job;
	$exec->lock($job);

	/* Run the job */
	echo date('d M Y H:i:s') .': '. $php .' '. $path . $cmd . $outfile ."\n";
	$output = array();
	$rc     = 0;
	exec($php .' '. $path . $cmd . $outfile, $output, $rc);
//	if (strncasecmp('win', PHP_OS, 3)) {	// Not Windows.
//		exec($php .' '. $path . $cmd .' '. $outfile, $output);
//	} else {
//		exec('start "FUDjob" /LOW /B "'. $php .'" '. $cmd .' '. $job->id . $outfile, $output);
//	}

	$exec->unlock($job->id);

	// Call cron plugins.
	if (defined('plugins')) {
		plugin_call_hook('CRON');
	}

?>
