<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: smiley.inc.t,v 1.2 2002/06/18 18:26:09 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

	
class fud_smiley
{
	var $id=NULL;
	var $img=NULL;
	var $descr=NULL;
	var $code=NULL;
	var $vieworder=NULL;
	
	var $s_list;
	
	function add()
	{
		if ( !db_locked() ) { $ll=1; db_lock('{SQL_TABLE_PREFIX}smiley+'); }
		$this->vieworder = q_singleval("SELECT MAX(vieworder)+1 FROM {SQL_TABLE_PREFIX}smiley");
		q("INSERT INTO {SQL_TABLE_PREFIX}smiley(
				img, 
				descr, 
				code,
				vieworder
			) 
			VALUES (
				'".$this->img."',
				'".$this->descr."',
				'".$this->code."',
				$this->vieworder
			)");
		if ( $ll ) db_unlock();
	}
	
	function get($id)
	{
		qobj("SELECT * FROM {SQL_TABLE_PREFIX}smiley WHERE id=".$id, $this);
		if( empty($this->id) ) exit("no such smiley\n");
	}
	
	function get_by_vieworder($vieworder)
	{
		qobj("SELECT * FROM {SQL_TABLE_PREFIX}smiley WHERE vieworder=".$vieworder, $this);
		if( empty($this->id) ) exit("no such smiley\n");
	}
	
	function sync()
	{
		q("UPDATE {SQL_TABLE_PREFIX}smiley SET img='".$this->img."', descr='".$this->descr."', code='".$this->code."' WHERE id=".$this->id);
	}
	
	function delete()
	{
		if ( !db_locked() ) { $ll=1; db_lock('{SQL_TABLE_PREFIX}smiley+'); }
		$sml = db_singleobj(q("SELECT * FROM {SQL_TABLE_PREFIX}smiley WHERE id=$this->id"));
		q("DELETE FROM {SQL_TABLE_PREFIX}smiley WHERE id=".$this->id);
		q("UPDATE {SQL_TABLE_PREFIX}smiley SET vieworder=vieworder-1 WHERE vieworder>$sml->vieworder");
		/* fix up the vieworder */
		if( @is_writable('../images/smiley_icons/'.$this->img) ) 
			unlink('../images/smiley_icons/'.$this->img);

		if ( $ll ) db_unlock();
	}
	
	function fetch_vars($array, $prefix)
	{
		reset($array);
		while ( list($key, $val) = each($array) ) {
			if ( substr($key, 0, strlen($prefix)) == $prefix ) {
				$key = substr($key, strlen($prefix), strlen($key)-strlen($prefix));

				if ( $key == 'img' )		$this->img = $val;
				if ( $key == 'descr' )		$this->descr = $val;
				if ( $key == 'code' )		$this->code = $val;
			}
		}
	}	
	
	function export_vars($prefix)
	{	
		$GLOBALS[$prefix.'img'] = $this->img;
		$GLOBALS[$prefix.'descr'] = $this->descr;
		$GLOBALS[$prefix.'code'] = $this->code;
	}
	
	function getall()
	{
		$res = q("SELECT * FROM {SQL_TABLE_PREFIX}smiley ORDER BY vieworder");
		if ( !is_result($res) ) return;
		
		unset($this->s_list);
		while ( $obj = db_rowobj($res) ) {
			$this->s_list[] = $obj;
		}
	}
	
	function resets()
	{
		if ( !isset($this->s_list) ) return;
		reset($this->s_list);
	}
	
	function counts()
	{
		if ( !isset($this->s_list) ) return;
		return count($this->s_list);
	}
	
	function eachs()
	{
		if ( !isset($this->s_list) ) return;
		@$obj = current($this->s_list);
		if ( !isset($obj) ) return;
		
		
		next($this->s_list);
		
		return $obj;
	}
	
	function chpos($dv)
	{
		if ( !db_locked() ) { $ll=1; db_lock('{SQL_TABLE_PREFIX}smiley+'); }
		$maxvieworder = q_singleval("SELECT MAX(vieworder)+1 FROM {SQL_TABLE_PREFIX}smiley");
		$TMPPOS = "4294967295";
		q("UPDATE {SQL_TABLE_PREFIX}smiley SET vieworder=$TMPPOS WHERE id=$this->id");
		q("UPDATE {SQL_TABLE_PREFIX}smiley SET vieworder=vieworder-1 WHERE vieworder>$this->vieworder AND vieworder<$maxvieworder");
		q("UPDATE {SQL_TABLE_PREFIX}smiley SET vieworder=vieworder+1 WHERE vieworder>".($dv-1)." AND vieworder<$maxvieworder");
		q("UPDATE {SQL_TABLE_PREFIX}smiley SET vieworder=$dv WHERE id=$this->id");
		if ( $ll ) db_unlock();
	}
}

function char_check($c)
{
	if( !isset($GLOBALS['__SML_CHR_CHK__']) ) $GLOBALS['__SML_CHR_CHK__'] = array("\n"=>1, "\r"=>1, "\t"=>1, " "=>1, "]"=>1, "["=>1, "<"=>1, ">"=>1, "'"=>1, '"'=>1, "("=>1, ")"=>1, "."=>1, ","=>1, "!"=>1, "?"=>1);

	if( $c==NULL || isset($GLOBALS['__SML_CHR_CHK__'][$c]) ) return 1;

	return;
}	

function smiley_to_post($text)
{
	$text_l=strtolower($text);

        $res = q("SELECT * FROM {SQL_TABLE_PREFIX}smiley");
	$smiley_www = 'images/smiley_icons/';
        while ( $obj = db_rowobj($res) ) {
		if ( empty($obj->code) ) continue;
                                      
		$sml_codes = array();
		
		if( strpos($obj->code, '~') ) 
			$sml_codes = explode('~', $obj->code);
		else
			$sml_codes[] = $obj->code;
                 
                reset($sml_codes);                        
		while( list(,$v) = each($sml_codes) ) {
			$a=0;
			$v = strtolower($v);
			$v_len = strlen($v);
			while( ($a = @strpos($text_l, $v, $a)) !== false ) {
				if( !$a || (char_check($text_l[$a-1]) && char_check($text_l[$a+$v_len])) ) {
					$text = substr_replace($text, '<img src="'.$smiley_www.$obj->img.'" border=0 alt="'.$obj->descr.'">', $a, $v_len);
					$text_l = substr_replace($text_l, '<img src="'.$smiley_www.$obj->img.'" border=0 alt="'.$obj->descr.'">', $a, $v_len);
					$a += strlen('<img src="'.$smiley_www.$obj->img.'" border=0 alt="'.$obj->descr.'">');
				}
				else 
					$a += $v_len;
			}
		}
	}
	qf($res);
	
	return $text;
}

function post_to_smiley($text)
{
	$res = q("SELECT * FROM {SQL_TABLE_PREFIX}smiley");
	
	$smiley_www = 'images/smiley_icons/';
	while ( $obj = db_rowobj($res) ) {
		if ( empty($obj->code) ) continue;
		
		$needle = ($a=strpos($obj->code, '~')) ? substr($obj->code,0,$a) : $obj->code;

		$text = str_replace('<img src="'.$smiley_www.$obj->img.'" border=0 alt="'.$obj->descr.'">', $needle, $text);
	}
	qf($res);
	
	return $text;
}
?>