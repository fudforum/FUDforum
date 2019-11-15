<?php
/**
* copyright            : (C) 2001-2013 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id$
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; version 2 of the License.
**/

	@set_time_limit(0);
	define('no_session', 1);
	
	/* Disable caching, indexing, etc. */
	if (php_sapi_name() != 'cli') {
		header('Cache-Control: no-cache, no-store, max-age=0, must-revalidate');
		header('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Way back in the past.
		header('Pragma: no-cache');
		header('X-Robots-Tag: noindex, nofollow');
	}

	$disabled = explode(', ', ini_get('disable_functions'));
	if (in_array('exec', $disabled)) {
		exit('The PHP function exec() is disabled on your system. You need to enable it to use this script.');
	}

	/* Run once or continuously. */
	fud_use('job.inc', true);
	if (isset($GLOBALS['CRON_SLEEP_TIME']) && $GLOBALS['CRON_SLEEP_TIME'] > 0) {
		$sleep = $GLOBALS['CRON_SLEEP_TIME'];
	} else {
		$sleep = 0;
	}

	while (true) {

		if ($sleep) sleep($sleep);

		/* Mark CRON execution. */
		$jobfile = $GLOBALS['TMP'] .'LAST_CRON_RUN';
		touch($jobfile);

		/* Specific job or next in line. */
		if (!empty($_SERVER['argv'][1]) && is_numeric($_SERVER['argv'][1])) {
			$job = db_sab('SELECT * FROM '. $GLOBALS['DBHOST_TBL_PREFIX'] .'jobs WHERE id='. $_SERVER['argv'][1]);
		} else {	// Next due.
			$job = db_sab(q_limit('SELECT * FROM {SQL_TABLE_PREFIX}jobs WHERE nextrun <= '. __request_timestamp__ .' AND '. q_bitand('job_opt', 1) .' != 1 ORDER BY nextrun ASC', 1));
		}

		/* Check if we need have something to run. */
		if (!$job) {    // Nothing to do.
			echo date('d M Y H:i:s') .': Nothing to do.';
			if ($sleep) continue; else return;
		}

		/* Skip if the job is locked (still running) and locked less than 10 min ago. */
		if ($job->locked != 0 && $job->locked > __request_timestamp__ - 600) {
			echo date('d M Y H:i:s') .': '. $job->cmd .' is locked (busy running).';
			if ($sleep) continue; else return;
		}

		$path   = $GLOBALS['DATA_DIR'] .'scripts/';
		chdir($path) or die('ERROR: Unable to change to scripts directory '. $path);

		$php  = escapeshellcmd($GLOBALS['PHP_CLI']);
		if (empty($php) && isset($_SERVER['_'])) {
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

		/* Run the job and wait for output. */
		if (defined('fud_logging')) {
			echo date('d M Y H:i:s') .': '. $php .' '. $path . $cmd . $outfile;
		} else {
			echo date('d M Y H:i:s') .': '. $cmd . $outfile;
		}
		$output = array();
		$rc     = 0;
		exec($php .' '. $path . $cmd . $outfile, $output, $rc);

		$exec->unlock($job->id);

		/* Print output to screen if we are running from the web. */
		if (defined('fud_logging') && php_sapi_name() != 'cli') {
			echo '<hr><pre>', file_get_contents($script .'_'. $job->id .'.log');
		}

		/* Call cron plugins. */
		if (defined('plugins')) {
			plugin_call_hook('CRON');
		}

		if (!$sleep) return;
	}

