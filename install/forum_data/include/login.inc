<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: login.inc,v 1.2 2002/06/26 19:47:18 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/
	fud_use('db.inc');
	
class fud_login_block
{
	var $id;
	var $login;
	var $l_list;
	
	function add($login)
	{
		q("INSERT INTO ".$GLOBALS['DBHOST_TBL_PREFIX']."blocked_logins (login) VALUES('".$login."')");
	}
	
	function sync($login)
	{
		q("UPDATE ".$GLOBALS['DBHOST_TBL_PREFIX']."blocked_logins SET login='".$login."' WHERE id=".$this->id);
	}
	
	function get($id)
	{
		$res = q("SELECT * FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."blocked_logins WHERE id=".$id);
		if ( !is_result($res) ) exit("no such login block\n");
		
		$obj = db_singleobj($res);
		$this->id 	= $obj->id;
		$this->login	= addslashes($obj->login);
	}
	
	function delete()
	{
		q("DELETE FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."blocked_logins WHERE id=".$this->id);	
	}
	
	function getall()
	{
		$r = q("SELECT * FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."blocked_logins ORDER BY id");
		
		unset($this->l_list);
		while ( $obj = db_rowobj($r) ) {
			$this->l_list[] = $obj;
		}
		if ( isset($this->l_list) ) reset($this->l_list); 
		qf($r);
	}
	
	function resetl()
	{
		if ( !isset($this->l_list) ) return;
		reset($this->l_list);
	}
	
	function countl()
	{
		if ( !isset($this->l_list) ) return;
		return count($this->l_list);
	}
	
	function eachl()
	{
		if ( !isset($this->l_list) ) return;
		$obj = current($this->l_list);
		if ( !isset($obj) ) return;
		next($this->l_list);
		
		return $obj;
	}
	
}

function is_blocked_login($login)
{
	if ( bq("SELECT id FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."blocked_logins WHERE login='".$login."'") ) return 1;
	
	$r = q("SELECT login FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."blocked_logins ORDER BY id");
	while ( list($reg) = db_rowarr($r) ) {
		if ( !preg_match('/!.*!.*/', $reg, $regs) && !preg_match('!/.*/.*!', $reg, $regs) ) $reg = '!'.$reg.'!s';
		if ( preg_match($reg, $login, $regs) ) { qf($r); return 1; }
	}
	qf($r);
	
	return;
}
?>
