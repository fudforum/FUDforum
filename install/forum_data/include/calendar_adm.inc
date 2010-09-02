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

class fud_calendar
{
	var	$id, $day, $month, $year, $descr, $link;

	function fud_calendar()
	{
		foreach ($this as $k => $v) {
			if (isset($_POST['cal_'. $k])) {
				$this->{$k} = $_POST['cal_'. $k];
			}
		}
	}

	function add()
	{
		$tbl =& $GLOBALS['DBHOST_TBL_PREFIX'];

		$this->id = db_qid('INSERT INTO '. $tbl .'calendar (
			year,
			month,
			day,
			descr,
			link
		) VALUES (
			'. ssn($this->year) .',
			'. ssn($this->month) .',
			'. ssn($this->day) .',
			'. ssn($this->descr) .',
			'. ssn($this->link) .'
		)');

		return $this->id;
	}

	function sync($id)
	{
		$tbl =& $GLOBALS['DBHOST_TBL_PREFIX'];

		q('UPDATE '. $tbl.'calendar SET
			year='. ssn($this->year) .',
			month='. ssn($this->month) .',
			day='. ssn($this->day) .',
			descr='. ssn($this->descr) .',
			link='. ssn($this->link) .'
		WHERE id='. (int)$id);
	}

	function delete($id)
	{
		$tbl =& $GLOBALS['DBHOST_TBL_PREFIX'];

		q('DELETE FROM '. $tbl .'calendar WHERE id = '. (int)$id);
	}

	function export($year=null, $month=null)
	{
		$tbl =& $GLOBALS['DBHOST_TBL_PREFIX'];

		$where = '';
		$where = empty($year) ? '' : 'WHERE year = '._esc($year);
		$where .= empty($month) ? '' : (empty($where) ? 'WHERE' : ' AND') .' month = '. _esc($month);

		$buffer = "BEGIN:VCALENDAR\n".	// Add vCalendar header.
			"VERSION:2.0\n".
			"PRODID:". $GLOBALS['FORUM_TITLE'] ."\n";

		// Loop through applicable calendar entries.
		$c = q('SELECT id, year, month, day, descr FROM '. $tbl .'calendar '. $where);
		while ($r = db_rowarr($c)) {
			$buffer .= "BEGIN:VEVENT\n".
				'UID:'. $r[0] .'@'. $_SERVER['HTTP_HOST'] ."\n".
				'SUMMARY:'. $r[4] ."\n".
				'DTSTART:'. sprintf('%04d%02d%02d', $r[1], $r[2], $r[3]) ."T000000\n".
				'DTEND:'. sprintf('%04d%02d%02d', $r[1], $r[2], $r[3]) ."T000000\n".
				"END:VEVENT\n";
		}

		$buffer .= "END:VCALENDAR\n";	// vCalendar footer.

		return $buffer;
	}
}
?>
