<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: buddy.inc.t,v 1.2 2002/06/18 18:26:09 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/


class fud_buddy
{
	var $id;
	var $bud_id;
	var $user_id;
	
	function add($user_id, $bud_id)
	{
		if ( !$user_id ) $user_id = $this->user_id;
		q("INSERT INTO {SQL_TABLE_PREFIX}buddy (bud_id, user_id) VALUES (".$bud_id.", ".$user_id.")");
	}
	
	function delete($id='')
	{
		if ( !strlen($id) ) $id = $this->id;
		q("DELETE FROM {SQL_TABLE_PREFIX}buddy WHERE id=".$id);
	}	

	function get($id)
	{
		$r = q("SELECT * FROM {SQL_TABLE_PREFIX}buddy WHERE id=".$id);
		$obj = db_singleobj($r);
		if ( !$obj ) { exit("no such buddy"); };
		
		$this->id 	= $obj->id;
		$this->bud_id 	= $obj->bud_id;
		$this->user_id	= $obj->user_id;
		
		return $id;
	}
	
	function get_buddy($user_id, $id)
	{
		$r = q("SELECT * FROM {SQL_TABLE_PREFIX}buddy WHERE id=".$id." AND user_id=".$user_id);
		$obj = db_singleobj($r);
		if ( !$obj ) { exit("no such buddy"); };
		
		$this->id 	= $obj->id;
		$this->bud_id 	= $obj->bud_id;
		$this->user_id	= $obj->user_id;
		
		return $id;
	}
}

function check_buddy($user_id, $bud_id)
{
	$r = q("SELECT id FROM {SQL_TABLE_PREFIX}buddy WHERE user_id=".$user_id." AND bud_id=".$bud_id);
	$obj = db_singleobj($r);
	
	return $obj;
}
?>