<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: ignore.inc.t,v 1.2 2002/06/18 18:26:09 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/


class fud_ignore
{
	var $id;
	var $ignore_id;
	var $user_id;
	
	function add($user_id, $ignore_id)
	{
		if ( !$user_id ) $user_id = $this->user_id;
		q("INSERT INTO {SQL_TABLE_PREFIX}user_ignore (ignore_id, user_id) VALUES (".$ignore_id.", ".$user_id.")");
	}
	
	function delete($id='')
	{
		if ( !strlen($id) ) $id = $this->id;
		q("DELETE FROM {SQL_TABLE_PREFIX}user_ignore WHERE id=".$id);
	}	

	function get($id)
	{
		$r = q("SELECT * FROM {SQL_TABLE_PREFIX}user_ignore WHERE id=".$id);
		$obj = db_singleobj($r);
		if ( !$obj ) { exit("no such ignore"); };
		
		$this->id 	= $obj->id;
		$this->ignore_id 	= $obj->ignore_id;
		$this->user_id	= $obj->user_id;
		
		return $id;
	}
	
	function get_ignore($user_id, $id)
	{
		$r = q("SELECT * FROM {SQL_TABLE_PREFIX}user_ignore WHERE id=".$id." AND user_id=".$user_id);
		$obj = db_singleobj($r);
		if ( !$obj ) { exit("no such ignore"); };
		
		$this->id 		= $obj->id;
		$this->ignore_id 	= $obj->ignore_id;
		$this->user_id		= $obj->user_id;
		
		return $id;
	}
}

function check_ignore($user_id, $ignore_id)
{
	$r = q("SELECT {SQL_TABLE_PREFIX}user_ignore.id,{SQL_TABLE_PREFIX}users.is_mod FROM {SQL_TABLE_PREFIX}user_ignore INNER JOIN {SQL_TABLE_PREFIX}users ON {SQL_TABLE_PREFIX}user_ignore.user_id={SQL_TABLE_PREFIX}users.id WHERE user_id=".$user_id." AND ignore_id=".$ignore_id);
	if( !is_result($r) ) {
		$is_mod = q_singleval("SELECT is_mod FROM {SQL_TABLE_PREFIX}users WHERE id=".$ignore_id);
		if( $is_mod == 'A' ) return 1;
			
		return;
	}
	
	list($id,$is_mod) = db_singlearr($r);
	
	if( $is_mod == 'A' ) return 1;
	else return $id;
}
?>