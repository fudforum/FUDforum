<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: attach.inc.t,v 1.5 2002/08/24 12:16:36 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

class fud_attach
{
	var $id=NULL;
	var $proto=NULL;
	var $location=NULL;
	var $original_name=NULL;
	var $owner=NULL;
	var $private=NULL;
	var $message_id=NULL;
	var $dlcount=NULL;
	var $mime_type=NULL;
	
	var $a_list;
	
	function add($owner, $message_id, $original_name, $cur_location, $private='')
	{
		$proto = proto($cur_location);
		
		$ext = substr($original_name, strrpos($original_name, '.')+1);
		if( !($mime_id = get_mime_by_ext($ext)) ) $mime_id = 0;
		
		$r=q("INSERT INTO {SQL_TABLE_PREFIX}attach (proto, location, original_name, message_id, owner, private, mime_type) VALUES('".$proto."', 'none://unset', '".$original_name."', ".intzero($message_id).", ".intzero($owner).", '".yn($private)."', ".$mime_id.")");
		$id = db_lastid("{SQL_TABLE_PREFIX}attach", $r);
		$this->message_id=$message_id;
		if ( $proto == 'LOCAL' ) {
			$loc = safe_attachment_copy($cur_location, $id);
		}
		else $loc = $cur_location;
		
		if ( $private == 'Y' ) 
			$tbl = '{SQL_TABLE_PREFIX}pmsg';
		else
			$tbl = '{SQL_TABLE_PREFIX}msg';
		
		q("UPDATE {SQL_TABLE_PREFIX}attach SET location='".addslashes($loc)."' WHERE id=".$id);
		if ( $message_id ) {
			qobj("SELECT count(*) AS a_count FROM {SQL_TABLE_PREFIX}attach WHERE message_id=".$message_id, $cObj);
			q("UPDATE ".$tbl." SET attach_cnt=".$cObj->a_count." WHERE id=".$this->message_id);
		}
		
		return $id;
	}
		
	function get($id,$private='')
	{
		$obj = qobj("SELECT * FROM {SQL_TABLE_PREFIX}attach WHERE id=".$id." AND private='".yn($private)."'", $this);
	}
	
	function replace($original_name, $cur_location)
	{
		$proto = proto($cur_location);
		$ext = substr($original_name, strrpos($original_name, '.')+1);
		if( !($mime_id = get_mime_by_ext($ext)) ) $mime_id = 0;
		
		q("UPDATE {SQL_TABLE_PREFIX}attach SET mime_type=".$mime_id.", proto='".$proto."', original_name='".$original_name."', location='".$cur_location."' WHERE id=".$this->id);
		if ( $proto == 'LOCAL' ) {
			$loc = $GLOBALS['FILE_STORE'].$this->id.'.atch';
			if ( file_exists($loc) ) unlink($loc);
			safe_attachment_copy($cur_location, $id);
		}
	}
	
	function delete()
	{
		if( !db_locked() ) {
			$ll = 1;
			db_lock('{SQL_TABLE_PREFIX}attach+');
		}	
		$r = q("SELECT location FROM {SQL_TABLE_PREFIX}attach WHERE id=".$this->id);
		if( db_count($r) ) {
			list($loc) = db_rowarr($r);
			if( file_exists($loc) ) 
				unlink($loc);
		}
		qf($r);
		q("DELETE FROM {SQL_TABLE_PREFIX}attach WHERE id=".$this->id);
		if( $ll ) db_unlock();
	}
	
	function inc_dl_count()
	{
		q("UPDATE {SQL_TABLE_PREFIX}attach SET dlcount=dlcount+1 WHERE id=".$this->id);
	}
	
}

function proto($file)
{
	if ( start_match($file, 'http://') ) 	return "REMOTE";
	if ( start_match($file, 'ftp://') ) 	return "REMOTE";
	
	return "LOCAL";
}

function start_match($haystack, $needle)
{
	if( !strncasecmp($haystack, $needle, strlen($needle)) ) return strlen($needle);
	return 0;
}

function safe_attachment_copy($source, $id)
{
	$loc = $GLOBALS['FILE_STORE'].$id.'.atch';	
	if ( file_exists($loc) ) {
		unlink($loc);
		std_out('"'.$loc.'" already exists, but shouldn\'t (old file unlinked), check security', 'ERR');
	}
	
	if( !@copy($source, $loc) ) {
		std_out('unable to create source file ('.$loc.') check permissions on ('.$GLOBALS['FILE_STORE'].'), we suggest 1777', 'ERR');
	}
	
	@chmod($loc, ($GLOBALS['FILE_LOCK']=='Y'?0600:0666));
	
	return $loc;
}
?>