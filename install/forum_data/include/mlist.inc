<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: mlist.inc,v 1.7 2003/01/27 12:18:48 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

class fud_mlist
{
	var $id=NULL, $forum_id=NULL, $name=NULL;
	var $create_users=NULL, $mlist_post_apr=NULL, $allow_frm_post=NULL, $frm_post_apr=NULL, $allow_mlist_attch=NULL, $allow_mlist_html=NULL, $complex_reply_match=NULL;
	var $subject_regex_haystack=NULL, $subject_regex_needle=NULL, $body_regex_haystack=NULL, $body_regex_needle=NULL, $additional_headers=NULL;

	function get($id)
	{
		qobj("SELECT * FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."mlist WHERE id=".$id, $this);
		if( empty($this->id) ) exit("Fatal Error, no mailing list with id of $id\n");
	}
	
	function add()
	{
		$r = q("INSERT INTO ".$GLOBALS['DBHOST_TBL_PREFIX']."mlist(
			forum_id,
			name,
			mlist_post_apr,
			allow_frm_post,
			frm_post_apr,
			allow_mlist_attch,
			allow_mlist_html,
			complex_reply_match,
			subject_regex_haystack,
			subject_regex_needle,
			body_regex_haystack,
			body_regex_needle,
			create_users,
			additional_headers
			)
			VALUES(
			".$this->forum_id.",
			'".$this->name."',
			'".yn($this->mlist_post_apr)."',
			'".yn($this->allow_frm_post)."',
			'".yn($this->frm_post_apr)."',
			'".yn($this->allow_mlist_attch)."',
			'".yn($this->allow_mlist_html)."',
			'".yn($this->complex_reply_match)."',
			'".$this->subject_regex_haystack."',
			'".$this->subject_regex_needle."',
			'".$this->body_regex_haystack."',
			'".$this->body_regex_needle."',
			'".yn($this->create_users)."',
			'".$this->additional_headers."'
			)");

		$this->id = db_lastid($GLOBALS['DBHOST_TBL_PREFIX']."mlist", $r);
		$this->Update_forum_settings();
		
		return $this->id;
	}
	
	function sync()
	{
		q("UPDATE ".$GLOBALS['DBHOST_TBL_PREFIX']."mlist SET
			forum_id=".$this->forum_id.",
			name='".$this->name."',
			mlist_post_apr='".yn($this->mlist_post_apr)."',
			allow_frm_post='".yn($this->allow_frm_post)."',
			frm_post_apr='".yn($this->frm_post_apr)."',
			allow_mlist_attch='".yn($this->allow_mlist_attch)."',
			allow_mlist_html='".yn($this->allow_mlist_html)."',
			complex_reply_match='".yn($this->complex_reply_match)."',
			subject_regex_haystack='".$this->subject_regex_haystack."',
			subject_regex_needle='".$this->subject_regex_needle."',
			body_regex_haystack='".$this->body_regex_haystack."',
			body_regex_needle='".$this->body_regex_needle."',
			additional_headers='".$this->additional_headers."',
			create_users='".yn($this->create_users)."'
		WHERE id=".$this->id);
		
		$this->Update_forum_settings();
	}
	
	function del($id='')
	{
		if( $id ) $this->get($id);
	
		q("DELETE FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."mlist WHERE id=".$this->id);
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