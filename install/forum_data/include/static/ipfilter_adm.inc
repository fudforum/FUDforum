<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: ipfilter_adm.inc,v 1.1.1.1 2002/06/17 23:00:09 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/
	

class fud_ip_filter_adm extends fud_ip_filter
{
	function ipset($val)
	{
		unset($this->ip);
		for ( $i=0; $i<4; $i++ ) $this->ip[$i] = $val;
	}
	

	function ipaddr()
	{
		$str = $this->ip[0].'.'.$this->ip[1].'.'.$this->ip[2].'.'.$this->ip[3];
		return $str;
	}
	
	
	function set_ip($ipaddr)
	{
		$this->ipset(0xFF);
		$seg = explode('.', $ipaddr);
		for ( $i=0; $i<count($seg); $i++ ) {
			if ( $seg[$i] == '*' ) 
				$this->ip[$i] = 0xFF;
			else
				$this->ip[$i] = $seg[$i];
		}
	}
	
	function set_ip_list($a, $b, $c, $d)
	{
		unset($this->ip);
		$this->ip[0] = ($a==255)?'*':$a;
		$this->ip[1] = ($b==255)?'*':$b;
		$this->ip[2] = ($c==255)?'*':$c;
		$this->ip[3] = ($d==255)?'*':$d;
	}
	
	function add($ipaddr)
	{
		$this->set_ip($ipaddr);
		Q("INSERT INTO ".$GLOBALS['MYSQL_TBL_PREFIX']."ip_block (ca, cb, cc, cd) VALUES (".$this->ip[0].",".$this->ip[1].",".$this->ip[2].",".$this->ip[3].")");
	}
	
	function get($id)
	{
		$res = Q("SELECT * FROM ".$GLOBALS['MYSQL_TBL_PREFIX']."ip_block WHERE id=".$id);
		if ( !IS_RESULT($res) ) exit("no such filter\n");

		$obj = DB_SINGLEOBJ($res);
		$this->set_ip_list($obj->ca, $obj->cb, $obj->cc, $obj->cd);
		$this->id = $obj->id;
		
		return $obj->id;
	}
	
	function is_matched($iparr)
	{		
		$m = 0;
		for ( $i=0; $i<count($vic); $i++ ) {
			if ( $this->ip[$i] == $vic[$i] ) {
				++$m;
				continue;
			}
			
			if ( ($this->ip[$i]&$vic[$i]) == $vic[$i] ) {
				++$m;
				continue;
			}
		}
		
		return ( $m==4 ) ? 1 : 0;
	}

	function sync($ipaddr)
	{
		$this->set_ip($ipaddr);
		Q("UPDATE ".$GLOBALS['MYSQL_TBL_PREFIX']."ip_block SET ca=".$this->ip[0].", cb=".$this->ip[1].", cc=".$this->ip[2].", cd=".$this->ip[3]." WHERE id=$this->id");
	}
	
	function getall()
	{
		$res = Q("SELECT * FROM ".$GLOBALS['MYSQL_TBL_PREFIX']."ip_block");
		
		unset($this->iplist);
		while ( $obj = DB_ROWOBJ($res) ) {
			$this->iplist[] = $obj;
		}
		QF($res);		
		if ( isset($this->iplist) ) reset($this->iplist);
	}
	
	function resetip()
	{
		if ( !isset($this->iplist) ) return;
		
		reset($this->iplist);
	}
	
	function countip()
	{
		if ( !isset($this->iplist) ) return;
		return count($this->iplist);
	}
	
	function eachip()
	{
		if ( !isset($this->iplist) ) return;
		$obj = current($this->iplist);
		if ( !isset($obj) ) return;
		if ( !$obj ) return;
		$ip[0] = ( $obj->ca == 255 ) ? '*' :  $obj->ca;
		$ip[1] = ( $obj->cb == 255 ) ? '*' :  $obj->cb;
		$ip[2] = ( $obj->cc == 255 ) ? '*' :  $obj->cc;
		$ip[3] = ( $obj->cd == 255 ) ? '*' :  $obj->cd;
		$ro->id = $obj->id;
		$ro->ip = $ip;
		next($this->iplist);
		
		return $ro;
	}
	
	function delete()
	{
		Q("DELETE FROM ".$GLOBALS['MYSQL_TBL_PREFIX']."ip_block WHERE id=".$this->id);
	}
}
?>