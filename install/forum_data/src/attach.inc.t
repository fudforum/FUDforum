<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: attach.inc.t,v 1.23 2003/06/03 17:30:18 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

function safe_attachment_copy($source, $id, $ext)
{
	$loc = $GLOBALS['FILE_STORE'] . $id . '.atch';	
	if (!$ext && !move_uploaded_file($source, $loc)) {
		std_out('unable to move uploaded file', 'ERR');
	} else if ($ext && !copy($source, $loc)) {
		std_out('unable to handle file attachment', 'ERR');
	}
	@unlink($source);

	@chmod($loc, ($GLOBALS['FILE_LOCK'] == 'Y' ? 0600 : 0666));

	return $loc;
}

function attach_add($at, $owner, $private='N', $ext=0)
{
	$this->mime_type = (int) q_singleval("SELECT id FROM {SQL_TABLE_PREFIX}mime WHERE fl_ext='".addslashes(substr(strrchr($at['name'], '.'), 1))."'");

	$id = db_qid("INSERT INTO {SQL_TABLE_PREFIX}attach (location,message_id,proto,original_name,owner,private,mime_type,fsize) VALUES('',0,'LOCAL','".addslashes($at['name'])."', ".$owner.", '".$private."',".$this->mime_type.",".$at['size'].")");

	safe_attachment_copy($at['tmp_name'], $id, $ext);
		
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
		$cc = __FUD_SQL_CONCAT__.'('.__FUD_SQL_CONCAT__."('".$GLOBALS['FILE_STORE']."', id), '.atch')";
		q("UPDATE {SQL_TABLE_PREFIX}attach SET location=".$cc.", message_id=".$mid." WHERE id IN(".$id_list.") AND private='".$private."'");
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
		q('UPDATE {SQL_TABLE_PREFIX}msg SET attach_cnt='.$attach_count.', attach_cache=\''.addslashes(@serialize($atl)).'\' WHERE id='.$mid);
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