<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: que.inc.t,v 1.1.1.1 2002/06/17 23:00:09 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/


class fud_modque
{
	var $list;
	
	function get($forum_id)
	{
		$r = Q("SELECT * FROM {SQL_TABLE_PREFIX}msg WHERE forum_id=$forum_id AND approved='N'");
		unset($this->list);
		while ( $obj = DB_ROWOBJ($r) ) {
			$this->list[] = $obj;
		}
		QF($r);
	}
	
	function resetm()
	{
		if ( !isset($this->list) ) return;
		reset($this->list);
	}
	
	function countm()
	{
		if ( !isset($this->list) ) return;
		return count($this->list);
	}
	
	function eachm()
	{
		if ( !isset($this->list) ) return;
		$obj = each($this->list);
		if ( !isset($obj) ) return;
		next($this->list);
		
		return $obj;
	}
}
?>