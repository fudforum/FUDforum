<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: nntp_adm.inc,v 1.8 2003/05/06 12:07:01 hackie Exp $
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
	var $forum_id, $id, $nntp_post_apr='N', $allow_frm_post='N', $frm_post_apr='Y', $allow_nntp_attch='N', $complex_reply_match='N', $create_users='Y';

	function fud_nntp_adm()
	{
		if (isset($_POST) && count($_POST)) {
			$el = array_keys(get_object_vars($this));
			foreach ($el as $v) {
				if (isset($_POST['nntp_' . $v])) {
					$this->{$v} = $_POST['nntp_' . $v];
				}
			}
		}	
	}

	function add()
	{
		if ($this->auth != 'ORIGINAL' && $this->auth != 'SIMPLE') {
			$this->auth = 'NONE';
		}
		if (!db_locked()) {
			$ll = 1;
			db_lock($GLOBALS['DBHOST_TBL_PREFIX'].'forum WRITE, '.$GLOBALS['DBHOST_TBL_PREFIX'].'nntp WRITE');
		}

		$this->id = db_qid("INSERT INTO ".$GLOBALS['DBHOST_TBL_PREFIX']."nntp(
			forum_id,
			nntp_post_apr,
			allow_frm_post,
			frm_post_apr,
			allow_nntp_attch,
			complex_reply_match,
			server,
			port,
			login,
			pass,
			timeout,
			newsgroup,
			auth,
			create_users
			)
			VALUES(
			".(int)$this->forum_id.",
			'".yn($this->nntp_post_apr)."',
			'".yn($this->allow_frm_post)."',
			'".yn($this->frm_post_apr)."',
			'".yn($this->allow_nntp_attch)."',
			'".yn($this->complex_reply_match)."',
			'".addslashes($this->server)."',
			".(int)$this->port.",
			'".addslashes($this->login)."',
			'".addslashes($this->pass)."',
			".(int)$this->timeout.",
			'".addslashes($this->newsgroup)."',
			'".$this->auth."',
			'".yn($this->create_users)."'
			)");
		if ($this->frm_post_apr == 'Y') {
			q('UPDATE '.$GLOBALS['DBHOST_TBL_PREFIX'].'forum SET moderated=\'Y\' WHERE id='.$this->forum_id);
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

		list($old_frm_id, $frm_post_apr) = db_saq('SELECT forum_id, frm_post_apr FROM '.$GLOBALS['DBHOST_TBL_PREFIX'].'nntp WHERE id='.$id);

		q("UPDATE ".$GLOBALS['DBHOST_TBL_PREFIX']."nntp SET
			forum_id=".(int)$this->forum_id.",
			nntp_post_apr='".yn($this->nntp_post_apr)."',
			allow_frm_post='".yn($this->allow_frm_post)."',
			frm_post_apr='".yn($this->frm_post_apr)."',
			allow_nntp_attch='".yn($this->allow_nntp_attch)."',
			complex_reply_match='".yn($this->complex_reply_match)."',
			server='".addslashes($this->server)."',
			port=".(int)$this->port.",
			login='".addslashes($this->login)."',
			pass='".addslashes($this->pass)."',
			timeout=".(int)$this->timeout.",
			newsgroup='".addslashes($this->newsgroup)."',
			auth='".$this->auth."',
			create_users='".yn($this->create_users)."'
		WHERE id=".$id);

		if ($old_frm_id != $this->forum_id && $frm_post_apr == 'Y') {
			q('UPDATE '.$GLOBALS['DBHOST_TBL_PREFIX'].'forum SET moderated=\'N\' WHERE id='.$old_frm_id);
		}
		q('UPDATE '.$GLOBALS['DBHOST_TBL_PREFIX'].'forum SET moderated=\''.yn($this->frm_post_apr).'\' WHERE id='.$this->forum_id);

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

	if (!($d = db_saq('SELECT frm_post_apr, forum_id FROM '.$GLOBALS['DBHOST_TBL_PREFIX'].'nntp WHERE id='.$id))) {
		if (isset($ll)) {
			db_unlock();
		}
		return;
	}
	q('DELETE FROM '.$GLOBALS['DBHOST_TBL_PREFIX'].'nntp WHERE id='.$id);
	if ($d[0] == 'Y') {
		q('UPDATE '.$GLOBALS['DBHOST_TBL_PREFIX'].'forum SET moderated=\'N\' WHERE id='.$d[1]);
	}

	if (isset($ll)) {
		db_unlock();
	}
}
?>