<?php
/**
* copyright            : (C) 2001-2009 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: attach.inc.t,v 1.55 2009/02/12 19:51:26 frank Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; version 2 of the License.
**/

function safe_attachment_copy($source, $id, $ext)
{
	$loc = $GLOBALS['FILE_STORE'] . $id . '.atch';
	if (!$ext && !move_uploaded_file($source, $loc)) {
		error_dialog('unable to move uploaded file', 'From: '.$source.' To: '.$loc, 'ATCH');
	} else if ($ext && !copy($source, $loc)) {
		error_dialog('unable to handle file attachment', 'From: '.$source.' To: '.$loc, 'ATCH');
	}
	@unlink($source);

	@chmod($loc, ($GLOBALS['FUD_OPT_2'] & 8388608 ? 0600 : 0666));

	return $loc;
}

function attach_add($at, $owner, $attach_opt=0, $ext=0)
{
	$id = db_qid("INSERT INTO {SQL_TABLE_PREFIX}attach (location,message_id,original_name,owner,attach_opt,mime_type,fsize) SELECT '', 0, "._esc($at['name']).", ".$owner.", ".$attach_opt.", id, ".$at['size']." FROM {SQL_TABLE_PREFIX}mime WHERE fl_ext IN('', "._esc(substr(strrchr($at['name'], '.'), 1)).") ORDER BY fl_ext DESC LIMIT 1");

	safe_attachment_copy($at['tmp_name'], $id, $ext);

	return $id;
}

function attach_finalize($attach_list, $mid, $attach_opt=0)
{
	$id_list = '';
	$attach_count = 0;

	$tbl = !$attach_opt ? 'msg' : 'pmsg';

	foreach ($attach_list as $key => $val) {
		if (!$val) {
			@unlink($GLOBALS['FILE_STORE'].(int)$key.'.atch');
		} else {
			$attach_count++;
			$id_list .= (int)$key.',';
		}
	}

	if ($id_list) {
		$id_list = substr($id_list, 0, -1);
		if (__dbtype__ == 'pgsql') {	// postgreSQL textcat hack
			$cc = __FUD_SQL_CONCAT__.'('.__FUD_SQL_CONCAT__."("._esc($GLOBALS['FILE_STORE']).", id::text), '.atch')";
                } else {
			$cc = __FUD_SQL_CONCAT__.'('.__FUD_SQL_CONCAT__."("._esc($GLOBALS['FILE_STORE']).", id), '.atch')";
		}
		q('UPDATE {SQL_TABLE_PREFIX}attach SET location='.$cc.', message_id='.$mid.' WHERE id IN('.$id_list.') AND attach_opt='.$attach_opt);
		$id_list = ' AND id NOT IN('.$id_list.')';
	} else {
		$id_list = '';
	}

	/* delete any unneeded (removed, temporary) attachments */
	q('DELETE FROM {SQL_TABLE_PREFIX}attach WHERE message_id='.$mid.' '.$id_list);

	if (!$attach_opt && ($atl = attach_rebuild_cache($mid))) {
		q('UPDATE {SQL_TABLE_PREFIX}msg SET attach_cnt='.$attach_count.', attach_cache='._esc(serialize($atl)).' WHERE id='.$mid);
	}

	if (!empty($GLOBALS['usr']->sid)) {
		ses_putvar((int)$GLOBALS['usr']->sid, null);
	}
}

function attach_rebuild_cache($id)
{
	$ret = array();
	$c = uq('SELECT a.id, a.original_name, a.fsize, a.dlcount, COALESCE(m.icon, \'unknown.gif\') FROM {SQL_TABLE_PREFIX}attach a LEFT JOIN {SQL_TABLE_PREFIX}mime m ON a.mime_type=m.id WHERE message_id='.$id.' AND attach_opt=0');
	while ($r = db_rowarr($c)) {
		$ret[] = $r;
	}
	unset($c);
	return $ret;
}

function attach_inc_dl_count($id, $mid)
{
	q('UPDATE {SQL_TABLE_PREFIX}attach SET dlcount=dlcount+1 WHERE id='.$id);
	if (($a = attach_rebuild_cache($mid))) {
		q('UPDATE {SQL_TABLE_PREFIX}msg SET attach_cache='._esc(serialize($a)).' WHERE id='.$mid);
	}
}
?>
