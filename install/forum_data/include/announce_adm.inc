<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: announce_adm.inc,v 1.2 2002/06/26 19:47:18 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

class fud_announce_adm 
{
	var $id;
	var $date_started;
	var $date_ended;
	
	var $subject;
	var $text;
	
	var $a_list;
	var $frm_list;
	
	function get_filtered($forum_id, $today)
	{
		$res = q("SELECT ".$GLOBALS['DBHOST_TBL_PREFIX']."announce.subject, ".$GLOBALS['DBHOST_TBL_PREFIX']."announce.text FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."announce INNER JOIN ".$GLOBALS['DBHOST_TBL_PREFIX']."ann_forums ON ".$GLOBALS['DBHOST_TBL_PREFIX']."announce.id=".$GLOBALS['DBHOST_TBL_PREFIX']."ann_forums.ann_id AND ".$GLOBALS['DBHOST_TBL_PREFIX']."ann_forums.forum_id=".$forum_id." WHERE ".$GLOBALS['DBHOST_TBL_PREFIX']."announce.date_started>=".$today." AND ".$GLOBALS['DBHOST_TBL_PREFIX']."announce.date_ended>".$today);
		
		unset($this->a_list);
		while ( $obj = db_rowobj($res) ) {
			$this->a_list[] = $obj;
		}
		
		qf($res);
	}
	
	function counta()
	{
		if ( !isset($this->a_list) ) return;
		return count($this->a_list);
	}
	
	function eacha()
	{
		if ( !isset($this->a_list) ) return;
		$obj = current($this->a_list);
		if ( !isset($obj) ) return;
		next($this->a_list);
		
		return $obj;
	}

	function add()
	{
		if ( !db_locked() ) {
			$ll = 1;
			db_lock($GLOBALS['DBHOST_TBL_PREFIX']."announce+");
		}
		$r = q("INSERT INTO ".$GLOBALS['DBHOST_TBL_PREFIX']."announce (date_started, date_ended, subject, text) VALUES(".$this->date_stared.",".$this->date_ended.",'".$this->subject."','".$this->text."')");
		$this->id = db_lastid($GLOBALS['DBHOST_TBL_PREFIX']."announce", $r);
		if( $ll ) db_unlock();
		
		return $this->id;
	}
	
	function sync()
	{
		q("UPDATE ".$GLOBALS['DBHOST_TBL_PREFIX']."announce SET date_started=".$this->date_stared.", date_ended=".$this->date_ended.", subject='".$this->subject."', text='".$this->text."' WHERE id=".$this->id);
	}
	
	function add_forum($frm_id)
	{
		q("INSERT INTO ".$GLOBALS['DBHOST_TBL_PREFIX']."ann_forums(ann_id, forum_id) VALUES(".$this->id.", ".$frm_id.")");
	}
	
	function rm_forum($frm_id)
	{
		q("DELETE FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."ann_forums WHERE ann_id=".$this->id." AND forum_id=".$frm_id);
	}
	
	function rm_all_forums()
	{
		q("DELETE FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."ann_forums WHERE ann_id=".$this->id);
	}
	
	function get_frm_list() 
	{
		if ( isset($this->frm_list) ) unset($this->frm_list);
		$res = q("SELECT * FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."ann_forums WHERE ann_id=".$this->id);
		
		while ( $obj = db_rowobj($res) ) {
			$this->frm_list[$obj->forum_id] = $obj;
		}
		qf($res);
	}

	function reset_forums()
	{
		if ( !isset($this->frm_list) ) return;
		
		reset($this->frm_list);
	}
	
	function each_forum()
	{
		if ( !isset($this->frm_list) ) return;
		$obj = current($this->frm_list);
		if ( !isset($obj) ) return;
		next($this->frm_list);
		
		return $obj;
	}
	
	function delete()
	{
		q("DELETE FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."ann_forums WHERE ann_id=".$this->id);
		q("DELETE FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."announce WHERE id=".$this->id);
	}
	
	function getall()
	{
		$res = q("SELECT * FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."announce");
		
		unset($this->a_list);
		while ( $obj = db_rowobj($res) ) {
			$this->a_list[] = $obj;
		}
		qf($res);
	}
	
	function reseta()
	{
		if ( !isset($this->a_list) ) return;
		reset($this->a_list);
	}
	
	function fetch_vars($array, $prefix)
	{
		$this->date_started = $array[$prefix.'date_started'];
		$this->date_ended = $array[$prefix.'date_ended'];
		$this->subject = $array[$prefix.'subject'];
		$this->text = $array[$prefix.'text'];
	}	
	
	function export_vars($prefix)
	{	
		$GLOBALS[$prefix.'date_started'] = $this->date_started;
		$GLOBALS[$prefix.'date_ended'] = $this->date_ended;
		$GLOBALS[$prefix.'subject'] = $this->subject;
		$GLOBALS[$prefix.'text'] = $this->text;
	}
	
	
	function get($id)
	{
		$res = q("SELECT * FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."announce WHERE id=".$id);
		
		if ( !is_result($res) ) exit("no such annoucement\n");
		
		$obj = db_singleobj($res);
		
		$this->id 		= $obj->id;
		$this->date_started	= $obj->date_started;
		$this->date_ended	= $obj->date_ended;
		$this->subject		= $obj->subject;
		$this->text		= $obj->text;
	}
	
	function is_set_forum($frm_id)
	{
		if ( !isset($this->frm_list) ) $this->get_frm_list();
		
		if ( isset($this->frm_list[$frm_id]) ) return $frm_id;
		
		return;
	}
}
?>