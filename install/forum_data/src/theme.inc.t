<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: theme.inc.t,v 1.6 2002/09/11 22:14:23 hackie Exp $
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
	var $pspell_lang='';
	var $t_default='';
	
	function add()
	{
		if ( !db_locked() ) {
			$ll = 1;
			db_lock('{SQL_TABLE_PREFIX}themes+');
		}
		if ( $this->t_default=='Y' ) {
			q("UPDATE {SQL_TABLE_PREFIX}themes SET t_default='N' WHERE t_default='Y'");
			$this->enabled = 'Y';
		}
		
		$r = q("INSERT INTO {SQL_TABLE_PREFIX}themes (
			name,
			theme,
			lang, 
			locale, 
			enabled,
			pspell_lang,
			t_default
		)
			VALUES
			(
				'$this->name',
				'$this->theme',
				'$this->lang',
				'$this->locale',
				'".yn($this->enabled)."',
				".strnull($this->pspell_lang).",
				'".yn($this->t_default)."'
			)");
		$this->id = db_lastid("{SQL_TABLE_PREFIX}themes", $r);
		if ( $ll ) db_unlock();
		return $this->id;
	}
	
	function sync()
	{
		if ( !db_locked() ) { $ll=1; db_lock('{SQL_TABLE_PREFIX}themes+'); }
		if ( $this->t_default == 'Y' ) {
			q("UPDATE {SQL_TABLE_PREFIX}themes SET t_default='N' WHERE t_default='Y'");
			if ( $this->enabled != 'Y' ) $this->enabled = 'Y';
			
		}

		q("UPDATE {SQL_TABLE_PREFIX}themes SET 
			name='$this->name', 
			theme='$this->theme', 
			lang='$this->lang', 
			locale='$this->locale', 
			enabled='".yn($this->enabled)."',
			pspell_lang=".strnull($this->pspell_lang).",
			t_default='".yn($this->t_default)."'
		WHERE id=$this->id");
		
		if ( $this->enabled != 'Y' && !is_result(q("SELECT id FROM {SQL_TABLE_PREFIX}themes WHERE enabled='Y'")) )
			q("UPDATE {SQL_TABLE_PREFIX}themes SET enabled='Y' WHERE id=1");
		
		if ( $this->t_default != 'Y' && !is_result(q("SELECT id FROM {SQL_TABLE_PREFIX}themes WHERE t_default='Y'")) )
			q("UPDATE {SQL_TABLE_PREFIX}themes SET t_default='Y' WHERE id=1");
		
		if ( $ll ) db_unlock();
	}
	
	function get($id)
	{
		qobj("SELECT * FROM {SQL_TABLE_PREFIX}themes WHERE id=".$id, $this);
	}
	
	function delete()
	{
		if ( !db_locked() ) { $ll=1; db_lock('{SQL_TABLE_PREFIX}themes+, {SQL_TABLE_PREFIX}users+'); }
		
		q("DELETE FROM {SQL_TABLE_PREFIX}themes WHERE id=".$this->id);
		
		/* Check is this was a default theme and if so, make some other theme default */
		if( $this->t_default == 'Y' ) {
			if( !($new_default=q_singleval("SELECT id FROM {SQL_TABLE_PREFIX}themes WHERE enabled='Y'")) ) {
				/* could not find any enabled themes to make default, make 'default' theme the default */
				$new_default = 1;
			}
			q("UPDATE {SQL_TABLE_PREFIX}themes SET enabled='Y', t_default='Y' WHERE id=".$new_default);
		}
		
		q("UPDATE {SQL_TABLE_PREFIX}users SET theme=".$new_default." WHERE theme=".$this->id);
		
		if ( $ll ) db_unlock();
	}
}

function default_theme()
{
	$obj = db_singleobj(q("SELECT id, name FROM {SQL_TABLE_PREFIX}themes WHERE t_default='Y'"));
	
	return $obj;
}
?>