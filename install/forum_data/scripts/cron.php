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

	define('sql_p', $GLOBALS['DBHOST_TBL_PREFIX']);

	/* Set language & locale. */
//echo "TIME NOW = [". date('r') ."]\n";
	$locale = q_singleval('SELECT locale FROM '. sql_p .'themes WHERE theme_opt=1|2 LIMIT 1');
	$GLOBALS['good_locale'] = setlocale(LC_ALL, $locale);
// $GLOBALS['good_locale'] = setlocale(LC_ALL, null);
// echo "DEFAULT TZ=[". date_default_timezone_get() ."]\n";
// ini_set('date.timezone', 'Africa/Johannesburg');
// echo "LOCALE = [". $GLOBALS['good_locale'] ."]\n";
echo "TIME NOW = [". date('r') ."]\n";

// Maximum number of jobs run during one call of pseudocron.
// Set to a low value if your jobs take longer than a few seconds and if you scheduled them
// very close to each other. Set to 0 to run any number of jobs.
$maxJobs = 1;

$debug = true;

define('PC_MINUTE',	1);
define('PC_HOUR',	2);
define('PC_DOM',	3);
define('PC_MONTH',	4);
define('PC_DOW',	5);
define('PC_CMD',	6);

$resultsSummary = '';

function logMessage($msg) {
	echo "$msg\n";
}

function lTrimZeros($number) {
	GLOBAL $debug;
	while ($number[0]=='0') {
		$number = substr($number,1);
	}
	return $number;
}

function multisort(&$array, $sortby) {
   foreach($array as $val) {
       $sortarray[] = $val[$sortby];
   }
   $c = $array;
   $s = array_multisort($sortarray, SORT_ASC, $c, SORT_ASC);
   $array = $c;
   return $s;
}

function parseElement($element, &$targetArray, $numberOfElements) {
	$subelements = explode(',', $element);
	for ($i=0; $i<$numberOfElements; $i++) {
		$targetArray[$i] = $subelements[0]=='*';
	}

	for ($i=0; $i<count($subelements); $i++) {
		if (preg_match('~^(\\*|([0-9]{1,2})(-([0-9]{1,2}))?)(/([0-9]{1,2}))?$~', $subelements[$i], $matches)) {
			if ($matches[1]=='*') {
				$matches[2] = 0;		// from
				$matches[4] = $numberOfElements;		//to
			} elseif (empty($matches[4]) || $matches[4] == '') {
				$matches[4] = $matches[2];
			}
			if (empty($matches[5][0]) || $matches[5][0] != '/') {
				$matches[6] = 1;		// step
			}
			for ($j=lTrimZeros($matches[2]); $j<=lTrimZeros($matches[4]); $j+=lTrimZeros($matches[6])) {
				$targetArray[$j] = TRUE;
			}
		}
	}

	echo "parse: $element to $numberOfElements elements => [". implode('/', $targetArray) ."]\n";
}

function incDate(&$dateArr, $amount, $unit) {

// echo sprintf("Increasing from %02d/%02d %02d:%02d by %d %6s ", $dateArr['mday'],$dateArr['mon'],$dateArr['hours'],$dateArr['minutes'],$amount,$unit);
	if ($unit == 'mday') {
		$dateArr['hours'] = 0;
		$dateArr['minutes'] = 0;
		$dateArr['seconds'] = 0;
		$dateArr['mday'] += $amount;
		$dateArr['wday'] += $amount % 7;
		if ($dateArr['wday']>6) {
			$dateArr['wday']-=7;
		}

		$months28 = Array(2);
		$months30 = Array(4,6,9,11);
		$months31 = Array(1,3,5,7,8,10,12);

		if (
			(in_array($dateArr['mon'], $months28) && $dateArr['mday']==28) ||
			(in_array($dateArr['mon'], $months30) && $dateArr['mday']==30) ||
			(in_array($dateArr['mon'], $months31) && $dateArr['mday']==31)
		) {
			$dateArr['mon']++;
			$dateArr['mday'] = 1;
		}

	} elseif ($unit=='hour') {
		if ($dateArr['hours']==23) {
			incDate($dateArr, 1, 'mday');
		} else {
			$dateArr['minutes'] = 0;
			$dateArr['seconds'] = 0;
			$dateArr['hours']++;
		}
	} elseif ($unit=='minute') {
		if ($dateArr['minutes']==59) {
			incDate($dateArr, 1, 'hour');
		} else {
			$dateArr['seconds'] = 0;
			$dateArr['minutes']++;
		}
	}
// echo sprintf("to %02d/%02d %02d:%02d\n", $dateArr['mday'],$dateArr['mon'],$dateArr['hours'],$dateArr['minutes']);
}

function getLastScheduledRunTime($job) {

	$extjob = Array();
	parseElement($job[PC_MINUTE], $extjob[PC_MINUTE], 60);
	parseElement($job[PC_HOUR],   $extjob[PC_HOUR],   24);
	parseElement($job[PC_DOM],    $extjob[PC_DOM],    31);
	parseElement($job[PC_MONTH],  $extjob[PC_MONTH],  12);
	parseElement($job[PC_DOW],    $extjob[PC_DOW],     7);

	// echo "EXTJOB: "; print_r($extjob);

	$dateArr = getdate(getLastActualRunTime($job[0], $job[PC_CMD]));
	// echo "LAST ACTUAL: "; print_r($dateArr);
	$minutesAhead = 0;
	while (
		$minutesAhead<525600 AND 
		(!$extjob[PC_MINUTE][$dateArr['minutes']] OR 
		!$extjob[PC_HOUR][$dateArr['hours']] OR 
		(!$extjob[PC_DOM][$dateArr['mday']] OR !$extjob[PC_DOW][$dateArr['wday']]) OR
		!$extjob[PC_MONTH][$dateArr['mon']])
	) {
// echo "$minutesAhead, ";
		if (!$extjob[PC_DOM][$dateArr['mday']] OR !$extjob[PC_DOW][$dateArr['wday']]) {
			incDate($dateArr, 1, 'mday');
			$minutesAhead+=1440;
			continue;
		}
		if (!$extjob[PC_HOUR][$dateArr['hours']]) {
			incDate($dateArr, 1, 'hour');
			$minutesAhead+=60;
			continue;
		}
		if (!$extjob[PC_MINUTE][$dateArr['minutes']]) {
			incDate($dateArr, 1, 'minute');
			$minutesAhead++;
			continue;
		}
	}

	echo "LAST SHEDULED: "; print_r($dateArr);

	return mktime($dateArr['hours'], $dateArr['minutes'], 0, $dateArr['mon'], $dateArr['mday'], $dateArr['year']);
}

function getLastActualRunTime($jobno, $jobname) {
	$jobfile = $GLOBALS['ERROR_PATH'] . urlencode($jobname) .'_'. $jobno .'.job';
	if (file_exists($jobfile)) {
		return filemtime($jobfile);
	}
	echo "CHECK TIME ON = $jobfile]\n";
	return 0;
}

function markLastRun($jobno, $jobname, $lastRun) {
	$jobfile = $GLOBALS['ERROR_PATH'] . urlencode($jobname) . '_'. $jobno .'.job';
	echo "MARK FILE=[$jobfile]\n";
	touch($jobfile);
}

function runJob($job) {
	GLOBAL $debug, $resultsSummary;
	$resultsSummary = '';

	$lastActual = $job['lastActual'];
	$lastScheduled = $job['lastScheduled'];

	if ($lastScheduled<time()) {
		logMessage('Running job '. $job[PC_CMD]);
		logMessage('  Now:             '. date('r', time()));
		logMessage('  Last actual run: '. date('r', $lastActual));
		logMessage('  Last scheduled:  '. date('r', $lastScheduled));
		if ($debug) {
			$e = @error_reporting(1);
			// DO IT!		include(dirname(__FILE__)."/".$job[PC_CMD]);		// display errors only when debugging
			@error_reporting($e);
		} else {
			$e = @error_reporting(0);
			// DO IT!		@include($job[PC_CMD]);		// any error messages are supressed
			@error_reporting($e);
		}
		markLastRun($job[0], $job[PC_CMD], $lastScheduled);
		return true;
	} else {
		if ($debug) {
			logMessage('Skipping job '. $job[PC_CMD]);
			logMessage('  Now:             '. date('r', time()));
			logMessage('  Last actual run: '. date('r', $lastActual));
			logMessage('  Last scheduled:  '. date('r', $lastScheduled));
		}
		return false;
	}
}

function parse_cron_entries() {
	$jobs = Array();

	$c = uq('SELECT id,	minute, hour, dom, month, dow, cmd FROM '. sql_p .'cron');
	while ($r = db_rowarr($c)) {
		$jobNumber = $r[0];
		$jobs[$jobNumber] = Array($r[0], $r[1], $r[2], $r[3], $r[4], $r[5], $r[6]);
		$jobs[$jobNumber]['lastActual']    = getLastActualRunTime($jobNumber, $jobs[$jobNumber][PC_CMD]);
		$jobs[$jobNumber]['lastScheduled'] = getLastScheduledRunTime($jobNumber, $jobs[$jobNumber]);
	}

	if (!empty($jobs)) {
		multisort($jobs, 'lastScheduled');
var_dump($jobs);
	} else {
		logMessage('No jobs to run.');
	}

	return $jobs;
}

$jobs = parse_cron_entries();
$jobsRun = 0;
for ($i=0; $i<count($jobs); $i++) {
	if ($maxJobs==0 || $jobsRun<$maxJobs) {
		if (runJob($jobs[$i])) {
			$jobsRun++;
		}
	}
}
?>
