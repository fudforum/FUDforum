<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: attach.inc.t,v 1.18 2003/04/17 09:37:33 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

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
	$this->mime_type = (int) q_singleval("SELECT id FROM {SQL_TABLE_PREFIX}mime WHERE fl_ext='".addslashes(substr(strrchr($at['name'], '.'), 1))."'");

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
			$del[] = (int)$key;
			@unlink($GLOBALS['FILE_STORE'].(int)$key.'.atch');
		} else {
			$attach_count++;
			$id_list .= $key.',';
		}
	}
	
	if (!empty($id_list)) {
		$id_list = substr($id_list, 0, -1);
		q("UPDATE {SQL_TABLE_PREFIX}attach SET location=".__FUD_SQL_CONCAT__."('".$GLOBALS['FILE_STORE']."', id, '.atch'), message_id=".$mid." WHERE id IN(".$id_list.") AND private='".$private."'");
		$id_list = ' AND id NOT IN('.$id_list.')';
	} else {
		$id_list = '';
	}	
			
	/* delete any temp attachments created during message creation */
	if (isset($del)) {
		q('DELETE FROM {SQL_TABLE_PREFIX}attach WHERE id IN('.implode(',', $del).')');
	}

	/* delete any prior (removed) attachments if there are any */
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

function attach_inc_dl_count($id, $mid)
{
	q('UPDATE {SQL_TABLE_PREFIX}attach SET dlcount=dlcount+1 WHERE id='.$id);
	if (($a = attach_rebuild_cache($mid))) {
		q('UPDATE {SQL_TABLE_PREFIX}msg SET attach_cache=\''.addslashes(@serialize($a)).'\' WHERE id='.$mid);
	}
}
?>