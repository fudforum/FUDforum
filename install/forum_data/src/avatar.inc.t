<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: avatar.inc.t,v 1.1.1.1 2002/06/17 23:00:09 hackie Exp $
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
		Q("INSERT INTO {SQL_TABLE_PREFIX}avatar(img, descr) VALUES('".$this->img."','".$this->descr."')");
	}
	
	function get($id)
	{
		QOBJ("SELECT * FROM {SQL_TABLE_PREFIX}avatar WHERE id=".$id, $this);
	}
	
	function sync()
	{
		Q("UPDATE {SQL_TABLE_PREFIX}avatar SET img='".$this->img."', descr='".$this->descr."' WHERE id=".$this->id);
	}
	
	function delete()
	{
		Q("DELETE FROM {SQL_TABLE_PREFIX}avatar WHERE id=".$this->id);
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
		$res = Q("SELECT * FROM {SQL_TABLE_PREFIX}avatar");
		if ( !IS_RESULT($res) ) return;
		
		unset($this->s_list);
		while ( $obj = DB_ROWOBJ($res) ) {
			$this->s_list[] = $obj;
		}
		
		QF($res);
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
		return Q_SINGLEVAL("SELECT count(*) FROM {SQL_TABLE_PREFIX}avatar");
	}
}
?>