<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: announce_adm.inc,v 1.1.1.1 2002/06/17 23:00:09 hackie Exp $
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
		$res = Q("SELECT ".$GLOBALS['MYSQL_TBL_PREFIX']."announce.subject, ".$GLOBALS['MYSQL_TBL_PREFIX']."announce.text FROM ".$GLOBALS['MYSQL_TBL_PREFIX']."announce INNER JOIN ".$GLOBALS['MYSQL_TBL_PREFIX']."ann_forums ON ".$GLOBALS['MYSQL_TBL_PREFIX']."announce.id=".$GLOBALS['MYSQL_TBL_PREFIX']."ann_forums.ann_id AND ".$GLOBALS['MYSQL_TBL_PREFIX']."ann_forums.forum_id=".$forum_id." WHERE ".$GLOBALS['MYSQL_TBL_PREFIX']."announce.date_started>=".$today." AND ".$GLOBALS['MYSQL_TBL_PREFIX']."announce.date_ended>".$today);
		
		unset($this->a_list);
		while ( $obj = DB_ROWOBJ($res) ) {
			$this->a_list[] = $obj;
		}
		
		QF($res);
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
		Q("INSERT INTO ".$GLOBALS['MYSQL_TBL_PREFIX']."announce (date_started, date_ended, subject, text) VALUES(".$this->date_stared.",".$this->date_ended.",'".$this->subject."','".$this->text."')");
		$this->id = DB_LASTID();
	}
	
	function sync()
	{
		Q("UPDATE ".$GLOBALS['MYSQL_TBL_PREFIX']."announce SET date_started=".$this->date_stared.", date_ended=".$this->date_ended.", subject='".$this->subject."', text='".$this->text."' WHERE id=".$this->id);
	}
	
	function add_forum($frm_id)
	{
		Q("INSERT INTO ".$GLOBALS['MYSQL_TBL_PREFIX']."ann_forums(ann_id, forum_id) VALUES(".$this->id.", ".$frm_id.")");
	}
	
	function rm_forum($frm_id)
	{
		Q("DELETE FROM ".$GLOBALS['MYSQL_TBL_PREFIX']."ann_forums WHERE ann_id=".$this->id." AND forum_id=".$frm_id);
	}
	
	function rm_all_forums()
	{
		Q("DELETE FROM ".$GLOBALS['MYSQL_TBL_PREFIX']."ann_forums WHERE ann_id=".$this->id);
	}
	
	function get_frm_list() 
	{
		if ( isset($this->frm_list) ) unset($this->frm_list);
		$res = Q("SELECT * FROM ".$GLOBALS['MYSQL_TBL_PREFIX']."ann_forums WHERE ann_id=".$this->id);
		
		while ( $obj = DB_ROWOBJ($res) ) {
			$this->frm_list[$obj->forum_id] = $obj;
		}
		QF($res);
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
		Q("DELETE FROM ".$GLOBALS['MYSQL_TBL_PREFIX']."ann_forums WHERE ann_id=".$this->id);
		Q("DELETE FROM ".$GLOBALS['MYSQL_TBL_PREFIX']."announce WHERE id=".$this->id);
	}
	
	function getall()
	{
		$res = Q("SELECT * FROM ".$GLOBALS['MYSQL_TBL_PREFIX']."announce");
		
		unset($this->a_list);
		while ( $obj = DB_ROWOBJ($res) ) {
			$this->a_list[] = $obj;
		}
		QF($res);
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
		$res = Q("SELECT * FROM ".$GLOBALS['MYSQL_TBL_PREFIX']."announce WHERE id=".$id);
		
		if ( !IS_RESULT($res) ) exit("no such annoucement\n");
		
		$obj = DB_SINGLEOBJ($res);
		
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