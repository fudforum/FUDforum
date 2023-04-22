<?php
/**
* copyright            : (C) 2001-2023 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id$
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; version 2 of the License.
**/

class fud_announce
{
	var	$id, $date_started, $date_ended, $subject, $text, $opt;
	var     $year1, $month1, $day1;
	var     $year2, $month2, $day2;

	function __construct()
	{
		foreach ($this as $k => $v) {
			if (isset($_POST['announce_'. $k])) {
				if (is_array($_POST['announce_'. $k])) {	// OR options together.
					$opts = 0;
					foreach($_POST['announce_'. $k] as $tmp=>$opt) $opts |= $opt;
					$_POST['announce_'. $k] = $opts;
				}
				$this->{$k} = $_POST['announce_'. $k];
			}
		}
		
		$this->date_started = mk_date($this->year1, $this->month1, $this->day1);
		$this->date_ended   = mk_date($this->year2, $this->month2, $this->day2);
	}

	/** Add an announcement. */
	function add()
	{
		$this->id = db_qid('INSERT INTO '. $GLOBALS['DBHOST_TBL_PREFIX'] .'announce (
			date_started,
			date_ended,
			subject,
			text,
			ann_opt
		) VALUES (
			'. ssn($this->date_started) .',
			'. ssn($this->date_ended) .',
			'. ssn($this->subject) .',
			'. ssn($this->text) .',
			'. (int)$this->opt .'
		)');
		$this->rebuild_cache();

		return $this->id;
	}

	/** Update an announcement. */
	function sync($id)
	{
		q('UPDATE '. $GLOBALS['DBHOST_TBL_PREFIX'] .'announce SET
			date_started='. ssn($this->date_started) .',
			date_ended='. ssn($this->date_ended) .',
			subject='. ssn($this->subject) .',
			text='. ssn($this->text) .',
			ann_opt='. (int)$this->opt .'
		WHERE id='. (int)$id);
		$this->rebuild_cache();
	}

	/** Delete an announcement. */
	static function delete($id)
	{
		q('DELETE FROM '. $GLOBALS['DBHOST_TBL_PREFIX'] .'announce WHERE id = '. (int)$id);
		q('DELETE FROM '. $GLOBALS['DBHOST_TBL_PREFIX'] .'ann_forums WHERE ann_id='. (int)$id);
		fud_announce::rebuild_cache();
	}

	/** Rebuild cache of non-forum related announcements. For example, for front page. */
	static function rebuild_cache() {
		$announcements = NULL;

		$c = uq('SELECT * FROM '. $GLOBALS['DBHOST_TBL_PREFIX'] .'announce WHERE '. q_bitand('ann_opt', 1) .' = 1');
		while ($r = db_rowobj($c)) {
			$r->start = mktime(0,   0,  0, substr($r->date_started, 4, 2), substr($r->date_started, -2), substr($r->date_started, 0, 4));
			$r->end   = mktime(23, 59, 59, substr($r->date_ended,   4, 2), substr($r->date_ended,   -2), substr($r->date_ended,   0, 4));
			if ($r->end < __request_timestamp__) {
				// Skip expired announcements.
				continue;
			}

			// Unset what we don't need.
			unset($r->date_started);
			unset($r->date_ended);

			$announcements[ $r->id ] = (array)$r;
		}
		unset($c);

		$fp = fopen($GLOBALS['TMP'] .'announce_cache', 'w');
		fwrite($fp, '<?php $announce_cache = '. var_export((array)$announcements, 1) .'; ?>');
		fclose($fp);

		if (!strncasecmp('win', PHP_OS, 3)) {
			@unlink($GLOBALS['FORUM_SETTINGS_PATH'] .'announce_cache');
		}
		rename($GLOBALS['TMP'] .'announce_cache', $GLOBALS['FORUM_SETTINGS_PATH'] .'announce_cache');
	}

}
?>

