<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: customtags.inc.t,v 1.3 2002/06/26 19:35:54 hackie Exp $
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
		q("DELETE FROM {SQL_TABLE_PREFIX}custom_tags WHERE id=".$this->id);
		$this->sync();
	}
	
	function delete_user($user_id)
	{
		q("DELETE FROM {SQL_TABLE_PREFIX}custom_tags WHERE user_id=".$user_id);
	}
	
	function add()
	{
		if ( !db_locked() ) {
			db_lock("{SQL_TABLE_PREFIX}custom_tags+");
			$ll=1;
		}
		$r=q("INSERT INTO {SQL_TABLE_PREFIX}custom_tags(name, user_id) VALUES('".$this->name."', ".$this->user_id.")");
		$this->sync();
		$this->id = db_lastid("{SQL_TABLE_PREFIX}custom_tags", $r);
		if ( $ll ) db_unlock();
		return $this->id;
	}
	
	function export_vars($prefix)
	{
		$GLOBALS[$prefix.'name'] 	= $this->name;
		$GLOBALS[$prefix.'user_id']	= $this->user_id;
	}
	
	function get($id)
	{
		$obj = db_singleobj(q("SELECT * FROM {SQL_TABLE_PREFIX}custom_tags WHERE id=".$id));
		if ( !$obj ) return;
		$this->id 	= $obj->id;
		$this->user_id 	= $obj->user_id;
		$this->name	= $obj->name;
	}
	
	function sync()
	{
		$ct='';
		$r = q("SELECT name FROM {SQL_TABLE_PREFIX}custom_tags WHERE user_id=".$this->user_id);
		
		if( db_count($r) ) {
			while ( list($name) = db_rowarr($r) ) {
				$ct .= addslashes($name).'<br>';
			}
			$ct = '<font class="LevelText">'.$ct.'</font>';
		}
		else $ct='';
		qf($r);
		q("UPDATE {SQL_TABLE_PREFIX}users SET custom_status='".$ct."' WHERE id=".$this->user_id);
	}
}
?>