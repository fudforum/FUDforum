<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: ext.inc,v 1.1 2002/06/26 19:44:35 hackie Exp $
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

class fud_ext_block
{
	var $id;
	var $ext;
	
	var $c_list;
	
	function add()
	{
		q("INSERT INTO ".$GLOBALS['MYSQL_TBL_PREFIX']."ext_block (ext) VALUES('".$this->ext."')");
		$this->mk_regexp();
	}
	
	function sync()
	{
		q("UPDATE ".$GLOBALS['MYSQL_TBL_PREFIX']."ext_block SET ext='".$this->ext."' WHERE id=".$this->id);
		$this->mk_regexp();
	}
	
	function delete()
	{
		q("DELETE FROM ".$GLOBALS['MYSQL_TBL_PREFIX']."ext_block WHERE id=".$this->id);
		$this->mk_regexp();
	}
	
	function get($id)
	{
		$res = q("SELECT * FROM ".$GLOBALS['MYSQL_TBL_PREFIX']."ext_block WHERE id=".$id);
		
		if ( !is_result($res) ) exit("no such extension\n");
		$obj = db_singleobj($res);
		
		$this->id	= $obj->id;
		$this->ext	= $obj->ext;
	}
	
	function fetch_vars($array, $prefix)
	{
		$this->ext = $array[$prefix.'ext'];
	}
	
	function export_vars($prefix)
	{	
		$GLOBALS[$prefix.'ext'] = $this->ext;
	}
	
	function getall()
	{
		$res = q("SELECT * FROM ".$GLOBALS['MYSQL_TBL_PREFIX']."ext_block");
				
		unset($this->c_list);
		while ( $obj = db_rowobj($res) ) {
			$this->c_list[] = $obj;
		}
		qf($res);
	}
	
	function resetc()
	{
		if ( !isset($this->c_list) ) return;
		reset($this->c_list);
	}
	
	function countc()
	{
		if ( !isset($this->c_list) ) return;
		return count($this->c_list);
	}
	
	function eachc()
	{
		if ( !isset($this->c_list) ) return;
		$obj = current($this->c_list);
		if ( !isset($obj) ) return;
		next($this->c_list);
		return $obj;
	}
	
	function mk_regexp()
	{
		$r = q("SELECT * FROM ".$GLOBALS['MYSQL_TBL_PREFIX']."ext_block");
		
		if ( !is_result($r) ) {
			$regexp = '';
		}
		else { 
			$regexp = '.*\.(';
			while ( $obj = db_rowobj($r) ) {
				if( $obj->ext == '*' ) {
					$regexp = '';
					break;
				}
				$regexp .= $obj->ext.'|';
			}
			qf($r);
		}
		
		if( !empty($regexp) ) $regexp = substr($regexp, 0, -1).')';
		
		$fp = fopen($GLOBALS['FORUM_SETTINGS_PATH'].'file_filter_regexp', 'wb');
			fwrite($fp, $regexp);
		fclose($fp);
		@chmod($GLOBALS['FORUM_SETTINGS_PATH'].'file_filter_regexp',0600);
	}
}
?>