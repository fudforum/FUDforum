<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: level_adm.inc,v 1.2 2002/06/26 19:47:18 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

class fud_level
{
	var $id;
	var $name=NULL;
	var $post_count=NULL;
	var $pri=NULL;
	var $img=NULL;
	
	var $level_list;
	var $cur_level;
	
	function add()
	{
		q("INSERT INTO ".$GLOBALS['DBHOST_TBL_PREFIX']."level
			(
				name, 
				post_count,
				pri,
				img
			) VALUES(
				'".$this->name."', 
				".$this->post_count.",
				'".$this->pri."',
				'".$this->img."'
			)");
	}
	
	function sync()
	{
		q("UPDATE ".$GLOBALS['DBHOST_TBL_PREFIX']."level SET 
			name='".$this->name."', 
			post_count=$this->post_count,
			pri='".$this->pri."',
			img='".$this->img."'
		WHERE id=".$this->id); 
	}
	
	function get_by_id($id)
	{
		qobj("SELECT * FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."level WHERE id=".$id, $this);
	}
	
	function delete()
	{
		q("DELETE from ".$GLOBALS['DBHOST_TBL_PREFIX']."level WHERE id=".$this->id);
	}
	
	function fetch_vars($array, $prefix) 
	{
		$this->name = $array[$prefix.'name'];
		$this->post_count = $array[$prefix.'post_count'];
	}
	
	function export_vars($prefix)
	{
		$GLOBALS[$prefix.'name'] 	= $this->name;
		$GLOBALS[$prefix.'post_count']	= $this->post_count;
	}
	
	function get_all_levels()
	{
		$result = q("SELECT * FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."level ORDER BY post_count");
		
		unset($this->level_list);
		$this->cur_level = 0;
		while ( $obj = DB_ROWOBJ($result) ) $this->level_list[$this->cur_level++] = $obj;
		
		return $this->cur_level;
	}
	
	function countlev()
	{
		if ( !isset($this->level_list) ) return;
		return count($this->level_list);
	}
	
	function resetlev()
	{
		if ( !isset($this->level_list) ) return;
		$this->cur_level = 0;
	}
	
	function nextlev()
	{
		if ( !isset($this->level_list[$this->cur_level]) ) return;
	
		return $this->level_list[$this->cur_level++];
	}
}
?>