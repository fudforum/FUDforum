<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: avatar.inc.t,v 1.2 2002/06/18 18:26:09 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/


class fud_avatar
{
	var $id=NULL;
	var $img=NULL;
	var $descr=NULL;
	
	var $s_list;
	
	function add()
	{
		q("INSERT INTO {SQL_TABLE_PREFIX}avatar(img, descr) VALUES('".$this->img."','".$this->descr."')");
	}
	
	function get($id)
	{
		qobj("SELECT * FROM {SQL_TABLE_PREFIX}avatar WHERE id=".$id, $this);
	}
	
	function sync()
	{
		q("UPDATE {SQL_TABLE_PREFIX}avatar SET img='".$this->img."', descr='".$this->descr."' WHERE id=".$this->id);
	}
	
	function delete()
	{
		q("DELETE FROM {SQL_TABLE_PREFIX}avatar WHERE id=".$this->id);
	}
	
	function fetch_vars($array, $prefix)
	{
		$this->img = $array[$prefix.'img'];
		$this->descr = $array[$prefix.'descr'];
	}	
	
	function export_vars($prefix)
	{	
		$GLOBALS[$prefix.'img'] = $this->img;
		$GLOBALS[$prefix.'descr'] = $this->descr;
	}
	
	function getall()
	{
		$res = q("SELECT * FROM {SQL_TABLE_PREFIX}avatar");
		if ( !is_result($res) ) return;
		
		unset($this->s_list);
		while ( $obj = db_rowobj($res) ) {
			$this->s_list[] = $obj;
		}
		
		qf($res);
	}
	
	function resets()
	{
		if ( !isset($this->s_list) ) return;
		reset($this->s_list);
	}
	
	function counts()
	{
		if ( !isset($this->s_list) ) return;
		return count($this->s_list);
	}
	
	function eachs()
	{
		if ( !isset($this->s_list) ) return;
		@$obj = current($this->s_list);
		if ( !isset($obj) ) return;
		
		
		next($this->s_list);
		
		return $obj;
	}
	
	function avt_count()
	{
		return q_singleval("SELECT count(*) FROM {SQL_TABLE_PREFIX}avatar");
	}
}
?>