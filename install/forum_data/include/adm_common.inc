<?php
/**
* copyright            : (C) 2001-2011 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id$
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; version 2 of the License.
**/

/*
 * DEFINE COMMON FUNCTIONS USED THROUGHOUT THE ACP AND OTHER SCRIPT.
 */

/** Print message to web browser or command line. */
function pf($msg='', $webonly=false)
{
	if (php_sapi_name() == 'cli') {
		if ($webonly) return;
		// echo strip_tags(html_entity_decode($msg, NULL, 'UTF-8')) ."\n";
		echo strip_tags(str_replace(array('&nbsp;', '&gt;', '&lt;'), array(' ', '>', '<'), $msg)) ."\n";
	} else {
		if (!preg_match('#<br|<h#i', $msg)) $msg .= '<br />';
		echo $msg;
		@ob_flush(); flush();
	}
}

/** Format date. */
function fdate($date, $format='d F Y H:i')
{
	if (empty($date)) {
		return 'Never';
	}

	if (stristr($GLOBALS['charset'], 'utf-8') !== false && substr(PHP_OS, 0, 3) == 'WIN') {
		// Windows doesn't have UTF-8 locales.
		$disp = utf8_encode(date($format, $date));
	} else {
		$disp = date($format, $date);
	}
	return '<time datetime="'. date('Y-m-d\TH:i:sP', $date) .'">'. $disp .'</time>';
}

/** Format string as a success message. */
function successify($msg)
{
	return '<span style="color:green;"><img src="style/success.gif" style="float:left;" />&nbsp;'. $msg .'</span><br />';
}

/** Format string as an error message. */
function errorify($err)
{
	return '<span style="color:red;"><img src="style/error.gif" style="float:left;" />&nbsp;'. $err .'</span><br />';
}

/** Calculate estimated time of completion for long running tasks. */
function eta_calc($start_time, $step, $step_count)
{
	$time_now = time();
	$progress = $step / $step_count;
	$eta = ($time_now - $start_time) / $progress * (1 - $progress);
	if ($eta > 60) {
		pf( number_format($progress * 100, 2) .'% done; ETA: '. number_format($eta/60, 2) .' minutes');
	} else {
		pf( number_format($progress * 100, 2) .'% done; ETA: '. $eta .' seconds');
	}
}

/** Calculate secs/min/hour/days/months ago. */
function timeago($ptime) {
	$etime = time() - $ptime;

	if ($etime < 1) {
		return '0 seconds';
	}

	$a = array(	12 * 30 * 24 * 60 * 60  =>  'year',
			30 * 24 * 60 * 60       =>  'month',
			24 * 60 * 60            =>  'day',
			60 * 60                 =>  'hour',
			60                      =>  'minute',
			1                       =>  'second'
		);

	foreach ($a as $secs => $str) {
		$d = $etime / $secs;
		if ($d >= 1) {
			$r = round($d);
			return $r .' '. $str . ($r > 1 ? 's ago' : ' ago');
		}
	}
}

?>