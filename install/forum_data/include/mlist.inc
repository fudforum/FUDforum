<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: mlist.inc,v 1.8 2003/05/06 21:46:32 hackie Exp $
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
	var $id, $forum_id, $name;
	var $create_users='Y', $mlist_post_apr='N', $allow_frm_post='N', $frm_post_apr='Y', $allow_mlist_attch='Y', $allow_mlist_html='N', $complex_reply_match='N';
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
			'".addslashes($this->name)."',
			'".yn($this->mlist_post_apr)."',
			'".yn($this->allow_frm_post)."',
			'".yn($this->frm_post_apr)."',
			'".yn($this->allow_mlist_attch)."',
			'".yn($this->allow_mlist_html)."',
			'".yn($this->complex_reply_match)."',
			'".addslashes($this->subject_regex_haystack)."',
			'".addslashes($this->subject_regex_needle)."',
			'".addslashes($this->body_regex_haystack)."',
			'".addslashes($this->body_regex_needle)."',
			'".yn($this->create_users)."',
			'".addslashes($this->additional_headers)."'
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
			db_lock($GLOBALS['DBHOST_TBL_PREFIX'].'forum WRITE, '.$GLOBALS['DBHOST_TBL_PREFIX'].'mlist WRITE');
		}
		list($old_frm_id, $frm_post_apr) = db_saq('SELECT forum_id, frm_post_apr FROM '.$GLOBALS['DBHOST_TBL_PREFIX'].'mlist WHERE id='.$id);

		q("UPDATE ".$GLOBALS['DBHOST_TBL_PREFIX']."mlist SET
			forum_id=".$this->forum_id.",
			name='".addslashes($this->name)."',
			mlist_post_apr='".yn($this->mlist_post_apr)."',
			allow_frm_post='".yn($this->allow_frm_post)."',
			frm_post_apr='".yn($this->frm_post_apr)."',
			allow_mlist_attch='".yn($this->allow_mlist_attch)."',
			allow_mlist_html='".yn($this->allow_mlist_html)."',
			complex_reply_match='".yn($this->complex_reply_match)."',
			subject_regex_haystack='".addslashes($this->subject_regex_haystack)."',
			subject_regex_needle='".addslashes($this->subject_regex_needle)."',
			body_regex_haystack='".addslashes($this->body_regex_haystack)."',
			body_regex_needle='".addslashes($this->body_regex_needle)."',
			additional_headers='".addslashes($this->additional_headers)."',
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
	
	function del($id='')
	{
		if( $id ) $this->get($id);
	
		q("DELETE FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."mlist WHERE id=".$this->id);
		if( yn($this->frm_post_apr) ) {
			$this->frm_post_apr = 'N';
			$this->update_forum_settings();
		}
	}
}
?>