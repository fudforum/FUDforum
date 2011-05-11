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

class fud_job
{
	var $id, $name, $start_minute, $start_hour, $start_dom, $start_month, $start_dow, $cmd, $job_opt=0;

	function __construct()
	{
		foreach ($this as $k => $v) {
			if (isset($_POST['job_'. $k])) {
				$this->{$k} = $_POST['job_'. $k];
			}
		}
	}

	function add()
	{
		$tbl =& $GLOBALS['DBHOST_TBL_PREFIX'];

		$this->id = db_qid('INSERT INTO '. $tbl .'jobs (
			name,
			start_minute,
			start_hour,
			start_dom,
			start_month,
			start_dow,
			cmd,
			job_opt
		) VALUES (
			'. ssn($this->name) .',
			'. ssn($this->start_minute) .',
			'. ssn($this->start_hour) .',
			'. ssn($this->start_dom) .',
			'. ssn($this->start_month) .',
			'. ssn($this->start_dow) .',
			'. ssn($this->cmd) .',
			'. ssn($this->job_opt) .'
		)');

		return $this->id;
	}

	function sync($id)
	{
		$tbl =& $GLOBALS['DBHOST_TBL_PREFIX'];

		q('UPDATE '. $tbl .'jobs SET
			name='. ssn($this->name) .',
			start_minute='. ssn($this->start_minute) .',
			start_hour='. ssn($this->start_hour) .',
			start_dom='. ssn($this->start_dom) .',
			start_month='. ssn($this->start_month) .',
			start_dow='. ssn($this->start_dow) .',
			cmd='. ssn($this->cmd) .',
			job_opt='. ssn($this->job_opt) .'
		WHERE id='. (int)$id);
	}

	function delete($id)
	{
		$tbl =& $GLOBALS['DBHOST_TBL_PREFIX'];

		q('DELETE FROM '. $tbl .'jobs WHERE id = '. (int)$id);
	}

	/** Call cron.php to run job in background (transition from sync to async). Called from admjobs.php. */
	function submit($id)
	{
		$php  = escapeshellcmd($GLOBALS['PHP_CLI']);
		if (empty($php) || !is_executable($php)) {
			throw new Exception('PHP CLI Executable not set or is not executable.');
		}

		$path   = $GLOBALS['DATA_DIR'] .'scripts/';
		chdir($path) or die('ERROR: Unable to change to scripts directory '. $path);

		$script = 'cron.php';
		if (!file_exists($path . $script)) {
			throw new Exception('Cannot submit cron '. $path . $script .'. No such file!');
		}

		if (strncasecmp('win', PHP_OS, 3)) {	// Not Windows.
			// echo "DEBUG: ". $php .' '. $path . $script .' '. $id .' 2>&1 &' ."<hr>";
			pclose(popen($php .' '. $path . $script .' '. (int)$id .' 2>&1 &', 'r'));
		} else {
			pclose(popen('start "FUDjob" /LOW /B "'. $php .'" '. $script .' '. (int)$id, 'r'));
		}
	}

	/** Lock job before execution. */
	function lock($job)
	{
		$nextrun = $this->next_run_time($job);
		q('UPDATE '. $GLOBALS['DBHOST_TBL_PREFIX'] .'jobs SET locked = '. __request_timestamp__ .', nextrun = '. $nextrun .' WHERE id = '. $job->id);
	}

	/** Unlock job after execution. */
	function unlock($id)
	{
		q('UPDATE '. $GLOBALS['DBHOST_TBL_PREFIX'] .'jobs SET lastrun = '. __request_timestamp__ .', locked = 0 WHERE id = '. $id);
	}

	/** Calculate the next run time for a particular job. */
	private function next_run_time($job)
	{
		$reset_hour = $reset_day = $reset_month = $reset_year = null;

		$next_minute  = $current_minute  = date('i', __request_timestamp__);
		$next_hour    = $current_hour    = date('H', __request_timestamp__);
		$next_day     = $current_day     = date('d', __request_timestamp__);
		$next_weekday = $current_weekday = date('w', __request_timestamp__);
		$next_month   = $current_month   = date('m', __request_timestamp__);
		$next_year    = $current_year    = date('Y', __request_timestamp__);

		if ($job->start_minute == '*') {
			++$next_minute;
			if ($next_minute > 59) {
				$reset_hour  = 1;
				$next_minute = 0;
			}
		} else {
			if ($this->next_run_bit($job->start_minute, $current_minute) != false) {
				$next_minute = $this->next_run_bit($job->start_minute, $current_minute);
			} else {
				$next_minute = $this->first_run_time($job->start_minute);
			}
			if ($next_minute <= $current_minute) {
				$reset_hour = 1;
			}
		}

		if ($reset_hour || !$this->run_time_exists($job->start_hour, $current_hour)) {
			if ($job->start_hour == '*') {
				++$next_hour;
				if ($next_hour > 23) {
					$reset_day = 1;
					$next_hour = 0;
				}
			} else {
				if ($this->next_run_bit($job->start_hour, $current_hour) != false) {
					$next_hour = $this->next_run_bit($job->start_hour, $current_hour);
				} else {
					$next_hour = $this->first_run_time($job->start_hour);
					$reset_day = 1;
				}
				if ($next_hour < $current_hour) {
					$reset_day = 1;
				}
			}
			$next_minute = $this->first_run_time($job->start_minute);
		}

		if ($reset_day || ($job->start_dow == '*' && !$this->run_time_exists($job->start_dom, $current_day) || $job->start_dom == '*' && !$this->run_time_exists($job->start_dow, $current_weekday))) {
			if ($job->start_dow == '*')	{
				if ($job->start_dom == '*')	{
					++$next_day;
					if ($next_day > date('t', __request_timestamp__)) {
						$reset_month = 1;
						$next_day    = 1;
					}
				} else {
					if ($this->next_run_bit($job->start_dom, $current_day) != false) {
						$next_day = $this->next_run_bit($job->start_dom, $current_day);
					} else {
						$next_day    = $this->first_run_time($job->start_dom);
						$reset_month = 1;
					}
					if ($next_day < $current_day) {
						$reset_month = 1;
					}
				}
			} else {
				if ($this->next_run_bit($job->start_dow, $current_weekday) != false) {
					$next_weekday = $this->next_run_bit($job->start_dow, $current_weekday);
				} else {
					$next_weekday = $this->first_run_time($job->start_dow);
				}
				$next_day = $current_day + ($next_weekday-$current_weekday);
				if ($next_day <= $current_day) {
					$next_day += 7;
				}

				if ($next_day > date('t', __request_timestamp__)) {
					$reset_month = 1;
				}
			}
			$next_minute = $this->first_run_time($job->start_minute);
			$next_hour   = $this->first_run_time($job->start_hour);
			if ($next_day == $current_day && $next_hour < $current_hour) {
				$reset_month = 1;
			}
		}

		if ($reset_month || !$this->run_time_exists($job->start_month, $current_month)) {
			if ($job->start_month == '*') {
				$next_month++;
				if ($next_month > 12) {
					$reset_year = 1;
					$next_month = 1;
				}
			} else {
				if (next_run_bit($job->start_month, $current_month) != false)	{
					$next_month = $this->next_run_bit($job->start_month, $current_month);
				} else {
					$next_month = $this->first_run_time($job->start_month);
					$reset_year = 1;
				}
				if ($next_month < $current_month) {
					$reset_year = 1;
				}
			}
			$next_minute = $this->first_run_time($job->start_minute);
			$next_hour = $this->first_run_time($job->start_hour);
			if ($job->start_dow == '*') {
				$next_day = $this->first_run_time($job->start_dom);
				if ($next_day == 0) $next_day = 1;
			} else {
				$next_weekday = $this->first_run_time($job->start_dow);
				$new_weekday = date('w', mktime($next_hour, $next_minute, 0, $next_month, 1, $next_year));
				$next_day = 1 + ($next_weekday - $new_weekday);
				if ($next_weekday < $new_weekday) {
					$next_day += 7;
				}
			}
			if ($next_month == $current_month && $next_day == $current_day && $new_hour < $current_hour) {
				$reset_year = 1;
			}
		}

		if ($reset_year) {
			$next_year++;
			$next_minute = $this->first_run_time($job->start_minute);
			$next_hour   = $this->first_run_time($job->start_hour);
			$next_month  = $this->first_run_time($job->start_month);
			if ($next_month == 0) $next_month = 1;
			if ($job->start_dow == '*') {
				$next_day = $this->first_run_time($job->start_dom);
				if ($next_day == 0) $next_day = 1;
			} else {
				$next_weekday = $this->first_run_time($job->start_dow);
				$new_weekday  = date('w', mktime($next_hour, $next_minute, 0, $next_month, 1, $next_year));
				$next_day     = 1 + ($next_weekday-$new_weekday);
				if ($next_weekday < $new_weekday) {
					$next_day += 7;
				}
			}
		}

		return mktime($next_hour, $next_minute, 0, $next_month, $next_day, $next_year);
	}

	/** Return the next run time bit for a cron field. Used by next_run_time().
	 *  For example: build_next_run_bit('0,15,30,45', 20) will return 30.
	 */
	private function next_run_bit($data, $bit)
	{
		if ($data == '*') return $bit;
		$data = explode(',', $data);
		foreach($data as $thing) {
			if ($thing > $bit) {
				return $thing;
			}
		}
		return false;
	}

	/** Checks if a run time exists for a cron field. Used by next_run_time(). */
	private function run_time_exists($data, $bit)
	{
		if ($data == '*') return true;
		$data = explode(',', $data);
		if (in_array($bit, $data)) {
			return true;
		}
		return false;
	}

	/** Fetches the first run bit for a cron field. Used by next_run_time(). */
	private function first_run_time($data)
	{
		if ($data == '*') return '0';
		$data = explode(',', $data);
		return $data[0];
	}

}

?>
