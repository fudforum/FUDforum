<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: nntp_adm.inc,v 1.10 2003/09/30 04:13:16 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

class fud_nntp_adm
{
	// NNTP Settings
	var $server, $port=119, $login, $pass, $timeout=25, $newsgroup, $auth;

	// General Settings
	var $forum_id, $id, $nntp_opt=33;

	function fud_nntp_adm()
	{
		if (isset($_POST) && count($_POST)) {
			$el = array_keys(get_object_vars($this));
			foreach ($el as $v) {
				if (isset($_POST['nntp_' . $v])) {
					$this->{$v} = $_POST['nntp_' . $v];
				}
			}

			$this->nntp_opt =0;
			foreach (array('nntp_post_apr', 'allow_frm_post', 'frm_post_apr', 'allow_nntp_attch', 'complex_reply_match', 'create_users', 'auth') as $v) {
				if (isset($_POST['nntp_'.$v])) {
					$this->nntp_opt |= (int) $_POST['nntp_'.$v];
				}
			}
		}	
	}

	function add()
	{
		if (!db_locked()) {
			$ll = 1;
			db_lock($GLOBALS['DBHOST_TBL_PREFIX'].'forum WRITE, '.$GLOBALS['DBHOST_TBL_PREFIX'].'nntp WRITE');
		}

		$this->id = db_qid("INSERT INTO ".$GLOBALS['DBHOST_TBL_PREFIX']."nntp(
			forum_id,
			server,
			port,
			login,
			pass,
			timeout,
			newsgroup,
			nntp_opt
			)
			VALUES(
			".(int)$this->forum_id.",
			'".addslashes($this->server)."',
			".(int)$this->port.",
			'".addslashes($this->login)."',
			'".addslashes($this->pass)."',
			".(int)$this->timeout.",
			'".addslashes($this->newsgroup)."',
			".$this->nntp_opt."
			)");
		if ($this->nntp_opt & 1) {
			q('UPDATE '.$GLOBALS['DBHOST_TBL_PREFIX'].'forum SET forum_opt=forum_opt | 2 WHERE id='.$this->forum_id);
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
			db_lock($GLOBALS['DBHOST_TBL_PREFIX'].'forum WRITE, '.$GLOBALS['DBHOST_TBL_PREFIX'].'nntp WRITE');
		}

		list($old_frm_id, $nntp_opt) = db_saq('SELECT forum_id, nntp_opt FROM '.$GLOBALS['DBHOST_TBL_PREFIX'].'nntp WHERE id='.$id);

		q("UPDATE ".$GLOBALS['DBHOST_TBL_PREFIX']."nntp SET
			forum_id=".(int)$this->forum_id.",
			server='".addslashes($this->server)."',
			port=".(int)$this->port.",
			login='".addslashes($this->login)."',
			pass='".addslashes($this->pass)."',
			timeout=".(int)$this->timeout.",
			newsgroup='".addslashes($this->newsgroup)."',
			nntp_opt=".$this->nntp_opt."
		WHERE id=".$id);

		if ($old_frm_id != $this->forum_id && $nntp_opt & 1) {
			q('UPDATE '.$GLOBALS['DBHOST_TBL_PREFIX'].'forum SET forum_opt=(forum_opt & ~ 2) WHERE id='.$old_frm_id);
		}
		q('UPDATE '.$GLOBALS['DBHOST_TBL_PREFIX'].'forum SET forum_opt=forum_opt | '.($nntp_opt & 1 ? 2 : 0).' WHERE id='.$this->forum_id);

		if (isset($ll)) {
			db_unlock();
		}
	}
}	

function nntp_del($id)
{
	if (!db_locked()) {
		$ll = 1;
		db_lock($GLOBALS['DBHOST_TBL_PREFIX'].'forum WRITE, '.$GLOBALS['DBHOST_TBL_PREFIX'].'nntp WRITE');
	}

	if (!($d = db_saq('SELECT nntp_opt, forum_id FROM '.$GLOBALS['DBHOST_TBL_PREFIX'].'nntp WHERE id='.$id))) {
		if (isset($ll)) {
			db_unlock();
		}
		return;
	}
	q('DELETE FROM '.$GLOBALS['DBHOST_TBL_PREFIX'].'nntp WHERE id='.$id);
	if ($d[0] & 1) {
		q('UPDATE '.$GLOBALS['DBHOST_TBL_PREFIX'].'forum SET forum_opt=(forum_opt & ~ 2) WHERE id='.$d[1]);
	}

	if (isset($ll)) {
		db_unlock();
	}
}
?>