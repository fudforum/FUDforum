<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: replace_adm.inc,v 1.1.1.1 2002/06/17 23:00:09 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

class fud_replace
{
	var $id=NULL;
	var $type=NULL; 
	var $replace_str=NULL;
	var $with_str=NULL;
	var $from_post=NULL;
	var $to_msg=NULL;
	
	var $r_list;
	
	function add()
	{
		Q("INSERT INTO ".$GLOBALS['MYSQL_TBL_PREFIX']."replace (type, replace_str, with_str, from_post, to_msg)
			VALUES(
				'".$this->type."',
				'".$this->replace_str."',
				'".$this->with_str."',
				".STRNULL($this->from_post).",
				".STRNULL($this->to_msg)."
			)");
		
	}
	
	function sync()
	{
		Q("UPDATE ".$GLOBALS['MYSQL_TBL_PREFIX']."replace SET type='".$this->type."', replace_str='".$this->replace_str."', with_str='".$this->with_str."', from_post=".STRNULL($this->from_post).", to_msg=".STRNULL($this->to_msg)." WHERE id=".$this->id);
	}
	
	function get($id)
	{
		QOBJ("SELECT * FROM ".$GLOBALS['MYSQL_TBL_PREFIX']."replace WHERE id=".$id, $this);
		if ( !$this->id ) exit("no such replace entry\n");
	}
	
	function delete()
	{
		Q("DELETE FROM ".$GLOBALS['MYSQL_TBL_PREFIX']."replace WHERE id=".$this->id);
	}
	
	function getall()
	{
		$res = Q("SELECT * FROM ".$GLOBALS['MYSQL_TBL_PREFIX']."replace ORDER BY id");
		
		if ( !IS_RESULT($res) ) return;
		
		unset($this->r_list); 
		while ( $obj = DB_ROWOBJ($res) ) {
			$this->r_list[] = $obj;
		}
		QF($res);
	}
	
	function countrpl()
	{
		if ( isset($this->r_list) ) return count($this->r_list);
		return;
	}
	
	function resetrpl()
	{
		if ( isset($this->r_list) ) reset($this->r_list);
		return;
	}
	
	function eachrpl()
	{
		if ( !isset($this->r_list) ) return;
		$obj=current($this->r_list);
		if ( !isset($obj) ) return;
		next($this->r_list);
		
		return $obj;
	}
}
?>