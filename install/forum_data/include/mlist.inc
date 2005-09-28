<?php
/**
* copyright            : (C) 2001-2004 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: mlist.inc,v 1.18 2005/09/28 14:15:54 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; either version 2 of the License, or
* (at your option) any later version.
**/

class fud_mlist
{
	var $id, $forum_id, $name;
	var $mlist_opt=73;
	var $subject_regex_haystack, $subject_regex_needle, $body_regex_haystack, $body_regex_needle, $additional_headers;

	function fud_mlist()
	{
		if (isset($_POST) && count($_POST)) {
			$el = array_keys(get_object_vars($this));
			foreach ($el as $v) {
		        	if (isset($_POST['ml_' . $v])) {
					$this->{$v} = $_POST['ml_' . $v];
				}
			}

			$this->mlist_opt =0;
			foreach (array('skip_non_forum_users', 'mlist_post_apr', 'allow_frm_post', 'frm_post_apr', 'allow_mlist_attch', 'allow_mlist_html', 'complex_reply_match', 'create_users') as $v) {
				if (isset($_POST['ml_'.$v])) {
					$this->mlist_opt |= (int) $_POST['ml_'.$v];
				}
			}

			if ($this->subject_regex_haystack) {
				$this->subject_regex_haystack = '/' . $this->subject_regex_haystack . '/' . $_POST['ml_subject_regex_haystack_opt'];
			}
			if ($this->body_regex_haystack) {
				$this->body_regex_haystack = '/' . $this->body_regex_haystack . '/' . $_POST['ml_body_regex_haystack_opt'];
			}
		}
	}

	function add()
	{
		if (!db_locked()) {
			$ll = 1;
			db_lock($GLOBALS['DBHOST_TBL_PREFIX'].'forum WRITE, '.$GLOBALS['DBHOST_TBL_PREFIX'].'mlist WRITE');
		}
		$this->id = db_qid("INSERT INTO ".$GLOBALS['DBHOST_TBL_PREFIX']."mlist(
			forum_id,
			name,
			mlist_opt,
			subject_regex_haystack,
			subject_regex_needle,
			body_regex_haystack,
			body_regex_needle,
			additional_headers
			)
			VALUES(
			".$this->forum_id.",
			"._esc($this->name).",
			".$this->mlist_opt.",
			"._esc($this->subject_regex_haystack).",
			"._esc($this->subject_regex_needle).",
			"._esc($this->body_regex_haystack).",
			"._esc($this->body_regex_needle).",
			"._esc($this->additional_headers)."
			)");
		if ($this->mlist_opt & 4) {
			q('UPDATE '.$GLOBALS['DBHOST_TBL_PREFIX'].'forum SET forum_opt=forum_opt|2 WHERE id='.$this->forum_id);
		}
		if (isset($ll)) {
			db_unlock();
		}
		return $this->id;
	}

	function sync($id)
	{
		if (!db_locked()) {
			$ll = 1;
			db_lock($GLOBALS['DBHOST_TBL_PREFIX'].'forum WRITE, '.$GLOBALS['DBHOST_TBL_PREFIX'].'mlist WRITE');
		}
		list($old_frm_id, $mlist_opt) = db_saq('SELECT forum_id, mlist_opt FROM '.$GLOBALS['DBHOST_TBL_PREFIX'].'mlist WHERE id='.$id);

		q("UPDATE ".$GLOBALS['DBHOST_TBL_PREFIX']."mlist SET
			forum_id=".$this->forum_id.",
			name="._esc($this->name).",
			mlist_opt=".$this->mlist_opt.",
			subject_regex_haystack="._esc($this->subject_regex_haystack).",
			subject_regex_needle="._esc($this->subject_regex_needle).",
			body_regex_haystack="._esc($this->body_regex_haystack).",
			body_regex_needle="._esc($this->body_regex_needle).",
			additional_headers="._esc($this->additional_headers)."
		WHERE id=".$id);

		if ($old_frm_id != $this->forum_id && $mlist_opt & 4) {
			q('UPDATE '.$GLOBALS['DBHOST_TBL_PREFIX'].'forum SET forum_opt=(forum_opt & ~ 2) WHERE id='.$old_frm_id);
		}
		q('UPDATE '.$GLOBALS['DBHOST_TBL_PREFIX'].'forum SET forum_opt=forum_opt | '.($this->mlist_opt & 1 ? 2 : 0).' WHERE id='.$this->forum_id);

		if (isset($ll)) {
			db_unlock();
		}
	}

	function del($id)
	{
		q("DELETE FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."mlist WHERE id=".$id);
	}
}
?>