<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: ilogin.inc.t,v 1.1.1.1 2002/06/17 23:00:09 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/


class fud_login_block
{
	var $id;
	var $login;
	
	var $l_list;
	
	function add($login)
	{
		Q("INSERT INTO {SQL_TABLE_PREFIX}blocked_logins (login) VALUES('".$login."')");
	}
	
	function sync($login)
	{
		Q("UPDATE {SQL_TABLE_PREFIX}blocked_logins SET login='".$login."' WHERE id=".$this->id);
	}
	
	function get($id)
	{
		$res = Q("SELECT * FROM {SQL_TABLE_PREFIX}blocked_logins WHERE id=".$id);
		if ( !IS_RESULT($res) ) exit("no such login block\n");
		
		$obj = DB_SINGLEOBJ($res);
		$this->id 	= $obj->id;
		$this->login	= addslashes($obj->login);
	}
	
	function delete()
	{
		Q("DELETE FROM {SQL_TABLE_PREFIX}blocked_logins WHERE id=".$this->id);	
	}
	
	function getall()
	{
		$r = Q("SELECT * FROM {SQL_TABLE_PREFIX}blocked_logins ORDER BY id");
		
		unset($this->l_list);
		while ( $obj = DB_ROWOBJ($r) ) {
			$this->l_list[] = $obj;
		}
		if ( isset($this->l_list) ) reset($this->l_list); 
		QF($r);
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
	if ( BQ("SELECT id FROM {SQL_TABLE_PREFIX}blocked_logins WHERE login='".$login."'") ) return 1;
	
	$r = Q("SELECT login FROM {SQL_TABLE_PREFIX}blocked_logins ORDER BY id");
	while ( list($reg) = DB_ROWARR($r) ) {
		if ( !preg_match('/!.*!.*/', $reg, $regs) && !preg_match('!/.*/.*!', $reg, $regs) ) $reg = '!'.$reg.'!s';
		if ( preg_match($reg, $login, $regs) ) { QF($r); return 1; }
	}
	QF($r);
	
	return;
}
	
?>
