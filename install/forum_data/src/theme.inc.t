<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: theme.inc.t,v 1.1.1.1 2002/06/17 23:00:09 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/
	
class fud_theme
{
	var $id='';
	var $name='';
	var $theme='';
	var $lang='';
	var $locale='';
	var $enabled='';
	var $t_default='';
	
	function add()
	{
		DB_LOCK('{SQL_TABLE_PREFIX}themes+');
		if ( $this->t_default=='Y' ) {
			Q("UPDATE {SQL_TABLE_PREFIX}themes SET t_default='N' WHERE t_default='Y'");
			$this->enabled = 'Y';
		}
		
		Q("INSERT INTO {SQL_TABLE_PREFIX}themes (
			name,
			theme,
			lang, 
			locale, 
			enabled,
			t_default
		)
			VALUES
			(
				'$this->name',
				'$this->theme',
				'$this->lang',
				'$this->locale',
				'".YN($this->enabled)."',
				'".YN($this->t_default)."'
			)");
		$this->id = DB_LASTID();
		DB_UNLOCK();
		return $this->id;
	}
	
	function sync()
	{
		DB_LOCK('{SQL_TABLE_PREFIX}themes+');
		if ( $this->t_default == 'Y' ) {
			Q("UPDATE {SQL_TABLE_PREFIX}themes SET t_default='N' WHERE t_default='Y'");
			if ( $this->enabled != 'Y' ) $this->enabled = 'Y';
			
		}

		Q("UPDATE {SQL_TABLE_PREFIX}themes SET 
			name='$this->name', 
			theme='$this->theme', 
			lang='$this->lang', 
			locale='$this->locale', 
			enabled='".YN($this->enabled)."',
			t_default='".YN($this->t_default)."'
		WHERE id=$this->id");
		
		if ( $this->enabled != 'Y' && !IS_RESULT(Q("SELECT id FROM {SQL_TABLE_PREFIX}themes WHERE enabled='Y'")) )
			Q("UPDATE {SQL_TABLE_PREFIX}themes SET enabled='Y' WHERE id=1");
		
		if ( $this->t_default != 'Y' && !IS_RESULT(Q("SELECT id FROM {SQL_TABLE_PREFIX}themes WHERE t_default='Y'")) )
			Q("UPDATE {SQL_TABLE_PREFIX}themes SET t_default='Y' WHERE id=1");
		
		DB_UNLOCK();
	}
	
	function get($id)
	{
		QOBJ("SELECT * FROM {SQL_TABLE_PREFIX}themes WHERE id=$id", $this);
	}
	
	function delete()
	{
		Q("DELETE FROM {SQL_TABLE_PREFIX}themes WHERE id=$this->id");
	}
}

function default_theme()
{
	$obj = DB_SINGLEOBJ(Q("SELECT id, name FROM {SQL_TABLE_PREFIX}themes WHERE t_default='Y'"));
	
	return $obj;
}
?>