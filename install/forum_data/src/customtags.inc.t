<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: customtags.inc.t,v 1.1.1.1 2002/06/17 23:00:09 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/


class fud_custom_tag
{
	var $id;
	var $name;
	var $user_id;
	
	function delete()
	{
		Q("DELETE FROM {SQL_TABLE_PREFIX}custom_tags WHERE id=".$this->id);
		$this->sync();
	}
	
	function delete_user($user_id)
	{
		Q("DELETE FROM {SQL_TABLE_PREFIX}custom_tags WHERE user_id=".$user_id);
	}
	
	function add()
	{
		Q("INSERT INTO {SQL_TABLE_PREFIX}custom_tags(name, user_id) VALUES('".$this->name."', ".$this->user_id.")");
		$this->sync();
		return DB_LASTID();
	}
	
	function export_vars($prefix)
	{
		$GLOBALS[$prefix.'name'] 	= $this->name;
		$GLOBALS[$prefix.'user_id']	= $this->user_id;
	}
	
	function get($id)
	{
		$obj = DB_SINGLEOBJ(Q("SELECT * FROM {SQL_TABLE_PREFIX}custom_tags WHERE id=".$id));
		if ( !$obj ) return;
		$this->id 	= $obj->id;
		$this->user_id 	= $obj->user_id;
		$this->name	= $obj->name;
	}
	
	function sync()
	{
		$ct='';
		$r = Q("SELECT name FROM {SQL_TABLE_PREFIX}custom_tags WHERE user_id=".$this->user_id);
		
		if( DB_COUNT($r) ) {
			while ( list($name) = DB_ROWARR($r) ) {
				$ct .= addslashes($name).'<br>';
			}
			$ct = '<font class="LevelText">'.$ct.'</font>';
		}
		else $ct='';
		QF($r);
		Q("UPDATE {SQL_TABLE_PREFIX}users SET custom_status='".$ct."' WHERE id=".$this->user_id);
	}
}
?>