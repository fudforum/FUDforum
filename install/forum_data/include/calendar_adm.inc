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

class fud_calendar
{
	var	$id, $event_day, $event_month, $event_year, $descr, $link;

	function __construct()
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
			event_year,
			event_month,
			event_day,
			descr,
			link
		) VALUES (
			'. ssn($this->event_year) .',
			'. ssn($this->event_month) .',
			'. ssn($this->event_day) .',
			'. ssn($this->descr) .',
			'. ssn($this->link) .'
		)');

		return $this->id;
	}

	function sync($id)
	{
		$tbl =& $GLOBALS['DBHOST_TBL_PREFIX'];

		q('UPDATE '. $tbl.'calendar SET
			event_year='. ssn($this->event_year) .',
			event_month='. ssn($this->event_month) .',
			event_day='. ssn($this->event_day) .',
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
		$where = empty($year) ? '' : 'WHERE event_year = '._esc($year);
		$where .= empty($month) ? '' : (empty($where) ? 'WHERE' : ' AND') .' event_month = '. _esc($month);

		$buffer = "BEGIN:VCALENDAR\n".	// Add vCalendar header.
			"VERSION:2.0\n".
			"PRODID:". $GLOBALS['FORUM_TITLE'] ."\n";

		// Loop through applicable calendar entries.
		$c = q('SELECT id, event_year, event_month, event_day, descr FROM '. $tbl .'calendar '. $where);
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
