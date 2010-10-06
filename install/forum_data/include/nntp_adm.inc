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

class fud_nntp_adm
{
	// NNTP Settings.
	var $server, $port=119, $login, $pass, $timeout=25, $newsgroup, $auth, $imp_limit=10;

	// General Settings.
	var $forum_id, $id, $nntp_opt=108, $custom_sig;

	function fud_nntp_adm()
	{
		if (isset($_POST) && count($_POST)) {
			foreach (array_keys(get_object_vars($this)) as $v) {
				if (isset($_POST['nntp_'. $v])) {
					$this->{$v} = $_POST['nntp_'. $v];
				}
			}
			$this->custom_sig = trim($this->custom_sig);

			$this->nntp_opt = 0;
			foreach (array('skip_non_forum_users','nntp_post_apr', 'allow_frm_post', 'frm_post_apr', 'allow_nntp_attch', 'complex_reply_match', 'create_users', 'auth') as $v) {
				if (isset($_POST['nntp_'.$v])) {
					$this->nntp_opt |= (int) $_POST['nntp_'. $v];
				}
			}
		}
	}

	function add()
	{
		$this->id = db_qid('INSERT INTO '. $GLOBALS['DBHOST_TBL_PREFIX'] .'nntp(
			forum_id,
			server,
			port,
			login,
			pass,
			timeout,
			newsgroup,
			nntp_opt,
			imp_limit,
			custom_sig
			)
			VALUES(
			'. (int)$this->forum_id .',
			'. _esc($this->server) .',
			'. (int)$this->port .',
			'. _esc($this->login) .',
			'. _esc($this->pass) .',
			'. (int)$this->timeout .',
			'. _esc($this->newsgroup) .',
			'. $this->nntp_opt .',
			'. (int)$this->imp_limit .',
			'. _esc($this->custom_sig) .'
			)');

		if ($this->nntp_opt & 1) {
			q('UPDATE '. $GLOBALS['DBHOST_TBL_PREFIX'] .'forum SET forum_opt='. q_bitor('forum_opt', 2) .' WHERE id='. $this->forum_id);
		}

		return $this->id;
	}

	function sync($id)
	{
		list($old_frm_id, $nntp_opt) = db_saq('SELECT forum_id, nntp_opt FROM '. $GLOBALS['DBHOST_TBL_PREFIX'] .'nntp WHERE id='. $id);

		q('UPDATE '. $GLOBALS['DBHOST_TBL_PREFIX'] .'nntp SET
			forum_id='. (int)$this->forum_id .',
			server='. _esc($this->server) .',
			port='. (int)$this->port .',
			login='. _esc($this->login) .',
			pass='. _esc($this->pass) .',
			timeout='. (int)$this->timeout .',
			imp_limit='. (int)$this->imp_limit .',
			newsgroup='. _esc($this->newsgroup) .',
			nntp_opt='. $this->nntp_opt .',
			custom_sig='. _esc($this->custom_sig) .'
		WHERE id='. $id);

		if ($old_frm_id != $this->forum_id && $nntp_opt & 1) {
			q('UPDATE '. $GLOBALS['DBHOST_TBL_PREFIX'] .'forum SET forum_opt='. q_bitand('forum_opt', ~2) .' WHERE id='. $old_frm_id);
		}
		q('UPDATE '. $GLOBALS['DBHOST_TBL_PREFIX'] .'forum SET forum_opt='. q_bitor('forum_opt', ($this->nntp_opt & 1 ? 2 : 0)) .' WHERE id='. $this->forum_id);
	}
}

function nntp_del($id)
{
	if (!($d = db_saq('SELECT nntp_opt, forum_id FROM '. $GLOBALS['DBHOST_TBL_PREFIX'] .'nntp WHERE id='. $id))) {
		return;
	}
	q('DELETE FROM '. $GLOBALS['DBHOST_TBL_PREFIX'] .'nntp WHERE id='. $id);
	if ($d[0] & 1) {
		q('UPDATE '. $GLOBALS['DBHOST_TBL_PREFIX'] .'forum SET forum_opt='. q_bitand('forum_opt', ~2) .' WHERE id='. $d[1]);
	}
}

function nntp_reset($id)
{
	q('UPDATE '. $GLOBALS['DBHOST_TBL_PREFIX'] .'nntp SET tracker = 0 WHERE id='. $id);
}

?>
