<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: ipoll.inc.t,v 1.1.1.1 2002/06/17 23:00:09 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

	
class fud_poll
{
	var $id;
	var $name=NULL;
	var $owner=NULL;
	var $creation_date=NULL;
	var $expiry_date=NULL;
	var $max_votes=NULL;
	
	function add()
	{
		Q("INSERT INTO {SQL_TABLE_PREFIX}poll (
			name, 
			owner, 
			creation_date, 
			expiry_date,
			max_votes
			) 
			VALUES(
			'".$this->name."',
			".$this->owner.",
			".__request_timestamp__.",
			".INTZERO($this->expiry_date).",
			0
			)");
		$this->id = DB_LASTID();
		
		return $this->id;
	}
	
	function sync()
	{
		Q("UPDATE {SQL_TABLE_PREFIX}poll SET 
			name='".$this->name."',
			expiry_date=".INTZERO($this->expiry_date).",
			max_votes=".INTZERO($this->max_votes)."
		WHERE id=".$this->id);
	}
	
	function get($id) 
	{
		QOBJ("SELECT * FROM {SQL_TABLE_PREFIX}poll WHERE id=".$id, $this);
	}
	
	function delete()
	{
		Q("UPDATE {SQL_TABLE_PREFIX}msg SET poll_id=0 WHERE poll_id=".$this->id);
		Q("DELETE FROM {SQL_TABLE_PREFIX}poll_opt WHERE poll_id=".$this->id);
		Q("DELETE FROM {SQL_TABLE_PREFIX}poll_opt_track WHERE poll_id=".$this->id);
		Q("DELETE FROM {SQL_TABLE_PREFIX}poll WHERE id=".$this->id);
	}
	
	function regvote($user_id)
	{
		Q("INSERT INTO {SQL_TABLE_PREFIX}poll_opt_track(poll_id, user_id) VALUES(".$this->id.", ".$user_id.")");
	}
	
	function voted($user_id)
	{
		return Q_SINGLEVAL("SELECT id FROM {SQL_TABLE_PREFIX}poll_opt_track WHERE poll_id=".$this->id." AND user_id=".$user_id);
	}
}

class fud_poll_opt
{
	var $id=NULL;
	var $poll_id=NULL;
	var $name=NULL;
	var $count=NULL;
	
	var $all=NULL;
	var $all_c=NULL;
	
	function add()
	{
		Q("INSERT INTO {SQL_TABLE_PREFIX}poll_opt (poll_id, name, count) VALUES (".$this->poll_id.",'".$this->name."',".INTZERO($this->count).")");
		return DB_LASTID();
	}
	
	function sync()
	{
		Q("UPDATE {SQL_TABLE_PREFIX}poll_opt SET name='".$this->name."' WHERE id=".$this->id);
	}
	
	function get($id)
	{
		QOBJ("SELECT * FROM {SQL_TABLE_PREFIX}poll_opt WHERE id=".$id, $this);
	}
	
	function delete()
	{
		Q("DELETE FROM {SQL_TABLE_PREFIX}poll_opt WHERE id=".$this->id);
	}
	
	function fetch_vars($array, $prefix)
	{
		$this->name = $array[$prefix.'name'];
	}
	
	function increase()
	{
		Q("UPDATE {SQL_TABLE_PREFIX}poll_opt SET count=count+1 WHERE id=".$this->id);
	}
	
	function get_poll($pl_id) 
	{
		$res = Q("SELECT * FROM {SQL_TABLE_PREFIX}poll_opt WHERE poll_id=".$pl_id." ORDER BY id");
		if ( !IS_RESULT($res) ) return;
		
		unset($this->all);
		$this->all_c = 0;
		while ( $obj=DB_ROWOBJ($res) ) {
			$this->all[] = $obj;
		}
		
		QF($res);
	}
	
	function reset_opt()
	{
		if ( isset($this->all) ) reset($this->all);
	}
	
	function count_opt()
	{
		if ( isset($this->all) ) return count($this->all);
		return;
	}
	
	function next_opt()
	{
		if ( $this->all_c > count($this->all) ) return;
		return ( isset($this->all[$this->all_c])?$this->all[$this->all_c++]:'');
	}
}

?>