<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: attach.inc.t,v 1.13 2003/04/08 14:14:27 hackie Exp $
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
	var $fsize=NULL;
	
	var $a_list;
	
	
	
	function finalize($attach_list, $mid, $private='N')
	{
		$id_list = '';
		$attach_count = 0;
	
		$tbl = ($private == 'N' ? 'msg' : 'pmsg'); 
	
		foreach( $attach_list as $key => $val ) {
			if( empty($val) ) {
				q("DELETE FROM {SQL_TABLE_PREFIX}attach WHERE id=".intval($key));
				if( @file_exists($GLOBALS['FILE_STORE'].$key.'.atch') ) @unlink($GLOBALS['FILE_STORE'].$key.'.atch');
			} else {
				$attach_count++;
				$id_list .= $key.',';
			}
		}
	
		if( !empty($id_list) ) {
			q("UPDATE {SQL_TABLE_PREFIX}attach SET location=".sql_concat("'".$GLOBALS['FILE_STORE']."'",'id',"'.atch'").", message_id=".$mid." WHERE id IN(".substr($id_list,0,-1).") AND private='".$private."'");
			$id_list = " AND id NOT IN(".substr($id_list, 0, -1).")";
		} else {
			$id_list = '';
		}	
			
		q("DELETE FROM {SQL_TABLE_PREFIX}attach WHERE message_id=".$mid." AND private='".$private."'".$id_list);	
	}
	
	function full_add($owner, $message_id, $original_name, $cur_location, $fsize, $private='N')
	{
		if( !($this->mime_type = get_mime_by_ext(substr(strrchr($original_name, '.'), 1))) ) $this->mime_type = 0;
		
		$r = q("INSERT INTO {SQL_TABLE_PREFIX}attach (proto,original_name,owner,private,mime_type,fsize,message_id) VALUES('LOCAL','".$original_name."', ".$owner.", '".$private."',".$this->mime_type.",".intzero($fsize).",".$message_id.")");
		$this->id = db_lastid("{SQL_TABLE_PREFIX}attach", $r);
		
		safe_attachment_copy($cur_location, $this->id);
		
		return $this->id;
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
	$loc = $GLOBALS['FILE_STORE'] . $id . '.atch';	
	if (!move_uploaded_file($source, $loc)) {
		std_out('unable to uploaded file', 'ERR');
	}
	
	@chmod($loc, ($GLOBALS['FILE_LOCK'] == 'Y' ? 0600 : 0666));
	
	return $loc;
}

function attach_add($at, $owner, $private='N')
{
	$this->mime_type = (int) get_mime_by_ext(substr(strrchr($at['name'], '.'), 1));

	$id = db_qid("INSERT INTO {SQL_TABLE_PREFIX}attach (location,message_id,proto,original_name,owner,private,mime_type,fsize) VALUES('',0,'LOCAL','".addslashes($at['name'])."', ".$owner.", '".$private."',".$this->mime_type.",".$at['size'].")");

	safe_attachment_copy($at['tmp_name'], $id);
		
	return $id;
}

function attach_finalize($attach_list, $mid, $private='N')
{
	$id_list = '';
	$attach_count = 0;
	
	$tbl = ($private == 'N' ? 'msg' : 'pmsg'); 
	
	foreach ($attach_list as $key => $val) {
		if (empty($val)) {
			q('DELETE FROM {SQL_TABLE_PREFIX}attach WHERE id='.(int)$key);
			@unlink($GLOBALS['FILE_STORE'].$key.'.atch');
		} else {
			$attach_count++;
			$id_list .= $key.',';
		}
	}
	
	if (!empty($id_list)) {
		$id_list = substr($id_list, 0, -1);
		q("UPDATE {SQL_TABLE_PREFIX}attach SET location=".__FUD_SQL_CONCAT__."('".$GLOBALS['FILE_STORE']."','id','.atch'), message_id=".$mid." WHERE id IN(".$id_list.") AND private='".$private."'");
		$id_list = " AND id NOT IN(".$id_list.")";
	} else {
		$id_list = '';
	}	
			
	q("DELETE FROM {SQL_TABLE_PREFIX}attach WHERE message_id=".$mid." AND private='".$private."'".$id_list);	

	if ($private == 'N' && ($atl = attach_rebuild_cache($mid))) {
		q('UPDATE {SQL_TABLE_PREFIX}msg SET attach_cache=\''.addslashes(@serialize($atl)).'\' WHERE id='.$mid);
	}
}

function attach_rebuild_cache($id)
{
	$c = uq('SELECT a.id, a.original_name, a.fsize, a.dlcount, CASE WHEN m.icon IS NULL THEN \'unknown.gif\' ELSE m.icon END FROM {SQL_TABLE_PREFIX}attach a LEFT JOIN {SQL_TABLE_PREFIX}mime m ON a.mime_type=m.id WHERE message_id='.$id.' AND private=\'N\'');
	while ($r = db_rowarr($c)) {
		$ret[] = $r;	
	}
	qf($c);

	return (isset($ret) ? $ret : NULL);
}
?>