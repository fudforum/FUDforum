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

class fud_custom_fields
{
	var	$name, $descr, $type_opt, $choice, $vieworder, $field_opt;

	function fud_custom_fields()
	{
		foreach ($this as $k => $v) {
			if (isset($_POST['custom_field_'. $k])) {
				$this->{$k} = $_POST['custom_field_'. $k];
			}
		}
	}

	function add()
	{
		$tbl =& $GLOBALS['DBHOST_TBL_PREFIX'];
		$this->vieworder = q_singleval('SELECT MAX(vieworder) FROM '. $tbl .'custom_fields') + 1;

		$this->id = db_qid('INSERT INTO '. $tbl .'custom_fields (
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

		return $this->id;
	}

	function sync($id)
	{
		$tbl =& $GLOBALS['DBHOST_TBL_PREFIX'];

		q('UPDATE '. $tbl.'custom_fields SET
			name='. ssn($this->name) .',
			descr='. ssn($this->descr) .',
			type_opt='. (int)$this->type_opt .',
			choice='. ssn($this->choice) .',
			field_opt='. (int)$this->field_opt .',
			vieworder='. (int)$this->vieworder .'
		WHERE id='. (int)$id);
	}

	function delete($id)
	{
		$tbl =& $GLOBALS['DBHOST_TBL_PREFIX'];

		q('DELETE FROM '. $tbl .'custom_fields WHERE id = '. (int)$id);
	}

}
?>
