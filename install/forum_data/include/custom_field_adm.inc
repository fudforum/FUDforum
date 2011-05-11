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

class fud_custom_field
{
	var	$name, $descr, $type_opt, $choice, $vieworder, $field_opt;

	function __construct()
	{
		foreach ($this as $k => $v) {
			if (isset($_POST['custom_field_'. $k])) {
				if (is_array($_POST['custom_field_'. $k])) {	// OR options together.
					$opts = 0;
					foreach($_POST['custom_field_'. $k] as $tmp=>$opt) $opts |= $opt;
					$_POST['custom_field_'. $k] = $opts;
				}
				$this->{$k} = $_POST['custom_field_'. $k];
			}
		}
	}

	function add()
	{
		$this->vieworder = q_singleval('SELECT MAX(vieworder) FROM '. $GLOBALS['DBHOST_TBL_PREFIX'] .'custom_fields') + 1;

		$this->id = db_qid('INSERT INTO '. $GLOBALS['DBHOST_TBL_PREFIX'] .'custom_fields (
			name,
			descr,
			type_opt,
			choice,
			field_opt,
			vieworder
		) VALUES (
			'. ssn($this->name) .',
			'. ssn($this->descr) .',
			'. (int)$this->type_opt .',
			'. ssn($this->choice) .',
			'. (int)$this->field_opt .',
			'. (int)$this->vieworder .'
		)');
		$this->rebuild_cache();

		return $this->id;
	}

	function sync($id)
	{
		q('UPDATE '. $GLOBALS['DBHOST_TBL_PREFIX'] .'custom_fields SET
			name='. ssn($this->name) .',
			descr='. ssn($this->descr) .',
			type_opt='. (int)$this->type_opt .',
			choice='. ssn($this->choice) .',
			field_opt='. (int)$this->field_opt .',
			vieworder='. (int)$this->vieworder .'
		WHERE id='. (int)$id);
		$this->rebuild_cache();
	}

	function delete($id)
	{
		q('DELETE FROM '. $GLOBALS['DBHOST_TBL_PREFIX'] .'custom_fields WHERE id = '. (int)$id);
		$this->rebuild_cache();
	}

	static function rebuild_cache() {
		$custom_fields = NULL;

		$c = uq('SELECT * FROM '. $GLOBALS['DBHOST_TBL_PREFIX'] .'custom_fields ORDER BY vieworder');
		while ($r = db_rowobj($c)) {
			$custom_fields[ $r->id ] = (array)$r;
		}
		unset($c);

		$fp = fopen($GLOBALS['TMP'] .'custom_field_cache', 'w');
		fwrite($fp, '<?php $custom_field_cache = '. var_export((array)$custom_fields, 1) .'; ?>');
		fclose($fp);

		if (!strncasecmp('win', PHP_OS, 3)) {
			@unlink($GLOBALS['FORUM_SETTINGS_PATH'] .'custom_field_cache');
		}
		rename($GLOBALS['TMP'] .'custom_field_cache', $GLOBALS['FORUM_SETTINGS_PATH'] .'custom_field_cache');
	}

}
?>
