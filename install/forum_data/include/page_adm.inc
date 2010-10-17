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

class fud_page
{
	var	$id, $slug, $title, $body, $created_by, $page_opt;

	function fud_page()
	{
		foreach ($this as $k => $v) {
			if (isset($_POST['page_'. $k])) {
				$this->{$k} = $_POST['page_'. $k];
			}
		}
	}

	function add()
	{
		$tbl =& $GLOBALS['DBHOST_TBL_PREFIX'];

		$this->id = db_qid('INSERT INTO '. $tbl .'pages (
			slug,
			title,
			body,
			created_by,
			page_opt
		) VALUES (
			'. ssn($this->slug) .',
			'. ssn($this->title) .',
			'. ssn($this->body) .',
			'. (int)$this->created_by .',
			'. (int)$this->page_opt .'
		)');

		return $this->id;
	}

	function sync($id)
	{
		$tbl =& $GLOBALS['DBHOST_TBL_PREFIX'];

		q('UPDATE '. $tbl .'pages SET
			slug='. ssn($this->slug) .',
			title='. ssn($this->title) .',
			body='. ssn($this->body) .',
			created_by='. (int)$this->created_by .',
			page_opt='. (int)$this->page_opt .'
		WHERE id='. (int)$id);
	}

	function delete($id)
	{
		$tbl =& $GLOBALS['DBHOST_TBL_PREFIX'];

		q('DELETE FROM '. $tbl .'pages WHERE id = '. (int)$id);
	}

}

?>
