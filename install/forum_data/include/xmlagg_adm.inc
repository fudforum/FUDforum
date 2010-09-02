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

class fud_xmlagg_adm
{
	// XML Aggregation Settings.
	var $name, $url;

	// General Settings.
	var $forum_id, $id, $xmlagg_opt=2, $custom_sig;

	function fud_xmlagg_adm()
	{
		if (isset($_POST) && count($_POST)) {
			foreach (array_keys(get_object_vars($this)) as $v) {
				if (isset($_POST['xmlagg_'. $v])) {
					$this->{$v} = $_POST['xmlagg_'. $v];
				}
			}
			$this->custom_sig = trim($this->custom_sig);

			$this->xmlagg_opt = 0;
			foreach (array('skip_non_forum_users', 'xmlagg_post_apr', 'create_users', 'complex_reply_match') as $v) {
				if (isset($_POST['xmlagg_'.$v])) {
					$this->xmlagg_opt |= (int) $_POST['xmlagg_'. $v];
				}
			}
		}
	}

	function add()
	{
		$this->id = db_qid('INSERT INTO '. $GLOBALS['DBHOST_TBL_PREFIX'] .'xmlagg(
			forum_id,
			name,
			url,
			xmlagg_opt,
			custom_sig
			)
			VALUES(
			'. (int)$this->forum_id .',
			'. _esc($this->name) .',
			'. _esc($this->url) .',
			'. $this->xmlagg_opt .',
			'. _esc($this->custom_sig) .'
			)');

		if ($this->xmlagg_opt & 1) {
			q('UPDATE '. $GLOBALS['DBHOST_TBL_PREFIX'] .'forum SET forum_opt='. q_bitor('forum_opt', 2) .' WHERE id='. $this->forum_id);
		}

		return $this->id;
	}

	function sync($id)
	{
		list($old_frm_id, $xmlagg_opt) = db_saq('SELECT forum_id, xmlagg_opt FROM '. $GLOBALS['DBHOST_TBL_PREFIX'] .'xmlagg WHERE id='. $id);

		q('UPDATE '. $GLOBALS['DBHOST_TBL_PREFIX'] .'xmlagg SET
			forum_id='. (int)$this->forum_id .',
			name='. _esc($this->name) .',
			url='. _esc($this->url) .',
			xmlagg_opt='. $this->xmlagg_opt .',
			custom_sig='. _esc($this->custom_sig) .'
		WHERE id='. $id);

		if ($old_frm_id != $this->forum_id && $xmlagg_opt & 1) {
			q('UPDATE '. $GLOBALS['DBHOST_TBL_PREFIX'] .'forum SET forum_opt='. q_bitand('forum_opt', ~2) .' WHERE id='. $old_frm_id);
		}
		q('UPDATE '. $GLOBALS['DBHOST_TBL_PREFIX'] .'forum SET forum_opt='. q_bitor('forum_opt', ($this->xmlagg_opt & 1 ? 2 : 0)) .' WHERE id='. $this->forum_id);
	}
}

function xmlagg_del($id)
{
	if (!($d = db_saq('SELECT xmlagg_opt, forum_id FROM '. $GLOBALS['DBHOST_TBL_PREFIX'] .'xmlagg WHERE id='. $id))) {
		return;
	}
	q('DELETE FROM '. $GLOBALS['DBHOST_TBL_PREFIX'] .'xmlagg WHERE id='. $id);
	if ($d[0] & 1) {
		q('UPDATE '. $GLOBALS['DBHOST_TBL_PREFIX'] .'forum SET forum_opt='. q_bitand('forum_opt', ~2) .' WHERE id='. $d[1]);
	}
}

function xmlagg_reset($id)
{
	q('UPDATE '. $GLOBALS['DBHOST_TBL_PREFIX'] .'xmlagg SET last_load_date = 0 WHERE id='. $id);
}
?>
