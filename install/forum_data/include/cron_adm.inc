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

class fud_cron
{
	var	$id, $name, $minute, $hour, $dom, $month, $dow, $cmd;

	function fud_cron()
	{
		foreach ($this as $k => $v) {
			if (isset($_POST['cron_'.$k])) {
				$this->{$k} = $_POST['cron_'.$k];
			}
		}
	}

	function add()
	{
		$tbl =& $GLOBALS['DBHOST_TBL_PREFIX'];

		$this->id = db_qid('INSERT INTO '. $tbl .'cron (
			name,
			minute,
			hour,
			dom,
			month,
			dow,
			cmd
		) VALUES (
			'. ssn($this->name) .',
			'. ssn($this->minute) .',
			'. ssn($this->hour) .',
			'. ssn($this->dom) .',
			'. ssn($this->month) .',
			'. ssn($this->dow) .',
			'. ssn($this->cmd) .'
		)');

		return $this->id;
	}

	function sync($id)
	{
		$tbl =& $GLOBALS['DBHOST_TBL_PREFIX'];

		q('UPDATE '. $tbl.'cron SET
			name='. ssn($this->name).',
			minute='. ssn($this->minute).',
			hour='. ssn($this->hour).',
			dom='. ssn($this->dom).',
			month='. ssn($this->month).',
			dow='. ssn($this->dow).',
			cmd='. ssn($this->cmd).'
		WHERE id='. (int)$id);
	}

	function delete($id)
	{
		$tbl =& $GLOBALS['DBHOST_TBL_PREFIX'];

		q('DELETE FROM '. $tbl .'cron WHERE id = '. (int)$id);
	}

}
?>
