<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: nntp_adm.inc,v 1.1 2002/07/29 11:58:50 hackie Exp $
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
	var $server, $port, $login, $pass, $timeout, $newsgroup, $auth;
	
	// General Settings
	var $forum_id, $id;
	var $nntp_post_apr, $allow_frm_post, $frm_post_apr, $allow_nttp_attch, $complex_reply_match;
	
	function get($id)
	{
		qobj("SELECT * FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."nntp WHERE id=".$id, $this);
		if( empty($this->id) ) exit("Fatal Error, no newsgroup with id of $id\n");
	}
	
	function add()
	{
		if( $this->auth != 'ORIGINAL' && $this->auth != 'SIMPLE' ) $this->auth = 'NONE';
	
		$r = q("INSERT INTO ".$GLOBALS['DBHOST_TBL_PREFIX']."nntp(
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
			auth
			)
			VALUES(
			".$this->forum_id.",
			'".yn($this->nntp_post_apr)."',
			'".yn($this->allow_frm_post)."',
			'".yn($this->frm_post_apr)."',
			'".yn($this->allow_nttp_attch)."',
			'".yn($this->complex_reply_match)."',
			'".$this->server."',
			".intval($this->port).",
			'".addslashes($this->login)."',
			'".addslashes($this->pass)."',
			".intval($this->timeout).",
			'".addslashes($this->newsgroup)."',
			'".$this->auth."'
			)");

		$this->id = db_lastid($GLOBALS['DBHOST_TBL_PREFIX']."mlist", $r);
		$this->Update_forum_settings();
		
		return $this->id;
	}
	
	function sync()
	{
		q("UPDATE ".$GLOBALS['DBHOST_TBL_PREFIX']."nntp SET
			forum_id=".$this->forum_id.",
			nntp_post_apr='".yn($this->nntp_post_apr)."',
			allow_frm_post='".yn($this->allow_frm_post)."',
			frm_post_apr='".yn($this->frm_post_apr)."',
			allow_nntp_attch='".yn($this->allow_nntp_attch)."',
			complex_reply_match='".yn($this->complex_reply_match)."',
			server='".$this->server."',
			port=".intval($this->port).",
			login='".addslashes($this->login)."',
			pass='".addslashes($this->pass)."',
			timeout=".intval($this->timeout).",
			newsgroup='".addslashes($this->newsgroup)."',
			auth='".$this->auth."'
		WHERE id=".$this->id);
			
		$this->Update_forum_settings();	
	}
	
	function del($id='')
	{
		if( $id ) $this->get($id);
	
		q("DELETE FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."nntp WHERE id=".$this->id);
		if( yn($this->frm_post_apr) ) {
			$this->frm_post_apr = 'N';
			$this->update_forum_settings();
		}
	}
	
	function update_forum_settings()
	{
		if( !db_locked() ) {
			$ll = 1;
			db_lock($GLOBALS['DBHOST_TBL_PREFIX']."forum+");
		}
		
		q("UPDATE ".$GLOBALS['DBHOST_TBL_PREFIX']."forum SET moderated='".yn($this->frm_post_apr)."' WHERE id=".$this->forum_id);
		
		if( $ll ) db_unlock();
	}
}	
?>