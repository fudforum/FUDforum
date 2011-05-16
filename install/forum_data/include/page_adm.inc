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

class fud_page
{
	var	$id, $slug, $title, $foff, $length, $body, $created_by, $page_opt;

	function __construct()
	{
		foreach ($this as $k => $v) {
			if (isset($_POST['page_'. $k])) {
				if (is_array($_POST['page_'. $k])) {	// OR options together.
					$opts = 0;
					foreach($_POST['page_'. $k] as $tmp=>$opt) $opts |= $opt;
					$_POST['page_'. $k] = $opts;
				}
				$this->{$k} = $_POST['page_'. $k];
			}
		}
	}

	function add()
	{
		$tbl =& $GLOBALS['DBHOST_TBL_PREFIX'];

		if ($GLOBALS['FUD_OPT_3'] & 32768) {
			$this->foff   = -1;
			$this->length = db_qid('INSERT INTO '. $tbl .'msg_store (data) VALUES('. _esc($this->body) .')');
		} else {
			list($this->foff, $this->length) = $this->write_page_body($this->body);
		}

		$this->id = db_qid('INSERT INTO '. $tbl .'pages (
			slug,
			title,
			foff,
			length,
			created_by,
			page_opt
		) VALUES (
			'. ssn($this->slug) .',
			'. ssn($this->title) .',
			'. (int)$this->foff .',
			'. (int)$this->length .',
			'. (int)$this->created_by .',
			'. (int)$this->page_opt .'
		)');

		return $this->id;
	}

	function sync($id)
	{
		$tbl =& $GLOBALS['DBHOST_TBL_PREFIX'];

		if ($GLOBALS['FUD_OPT_3'] & 32768) {
			$this->foff = -1;
			if ($mid = q_singleval('SELECT length FROM '. $tbl .'pages WHERE id='. (int)$id)) {
				q('DELETE FROM '. $tbl .'msg_store WHERE id='. $mid);
			}
			$this->length = db_qid('INSERT INTO '. $tbl .'msg_store (data) VALUES('. _esc($this->body) .')');
		} else {
			list($this->foff, $this->length) = $this->write_page_body($this->body);
		}

		q('UPDATE '. $tbl .'pages SET
			slug='.       ssn($this->slug) .',
			title='.      ssn($this->title) .',
			foff='.       (int)$this->foff .',
			length='.     (int)$this->length .',
			created_by='. (int)$this->created_by .',
			page_opt='.   (int)$this->page_opt .'
		WHERE id='. (int)$id);
	}

	static function delete($id)
	{
		$tbl =& $GLOBALS['DBHOST_TBL_PREFIX'];

		if ($GLOBALS['FUD_OPT_3'] & 32768 && ($mid = q_singleval('SELECT length FROM '. $tbl .'pages WHERE id='. (int)$id))) {
			q('DELETE FROM '. $tbl .'msg_store WHERE id='. $mid);
		}

		q('DELETE FROM '. $tbl .'pages WHERE id = '. (int)$id);
	}

	static function read_page_body($offset, $length)
	{
		if ($length < 1) {
			return;
		}

		if ($GLOBALS['FUD_OPT_3'] & 32768 && $offset == -1) {
			return q_singleval('SELECT data FROM '. $GLOBALS['DBHOST_TBL_PREFIX'] .'msg_store WHERE id='. $length);
		}

		$fp = fopen($GLOBALS['MSG_STORE_DIR'] .'pages', 'rb');
		fseek($fp, $offset, SEEK_SET);
		$str = fread($fp, $length);
		fclose($fp);

		return $str;
	}

	static function write_page_body($text)
	{
		if (($ll = !db_locked())) {
			db_lock($GLOBALS['DBHOST_TBL_PREFIX'] .'fl_pg WRITE');
		}

		$fp = fopen($GLOBALS['MSG_STORE_DIR'] .'pages', 'ab');
		if (!$fp) {
			exit("FATAL ERROR: cannot open page message store<br />\n");
		}

		fseek($fp, 0, SEEK_END);
		if (!($s = ftell($fp))) {
			$s = __ffilesize($fp);
		}

		if (($len = fwrite($fp, $text)) !== strlen($text)) {
			exit("FATAL ERROR: system has ran out of disk space<br />\n");
		}
		fclose($fp);

		if ($ll) {
			db_unlock();
		}

		if (!$s) {
			chmod($GLOBALS['MSG_STORE_DIR'] .'pages', ($GLOBALS['FUD_OPT_2'] & 8388608 ? 0600 : 0666));
		}

		return array($s, $len);
	}

}

?>
