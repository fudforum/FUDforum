<?php
/***************************************************************************
* copyright            : (C) 2001-2004 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: thrx_adm.inc,v 1.6 2004/01/04 16:38:25 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it 
* under the terms of the GNU General Public License as published by the 
* Free Software Foundation; either version 2 of the License, or 
* (at your option) any later version.
***************************************************************************/

function thx_add($reason_msg, $th, $frm, $req_by)
{
	if (!db_locked()) {
		$ll = 1;
		db_lock($GLOBALS['DBHOST_TBL_PREFIX'].'thr_exchange WRITE');
	}

	$id = db_qid("INSERT INTO ".$GLOBALS['DBHOST_TBL_PREFIX']."thr_exchange (reason_msg,th,frm,req_by) VALUES('".addslashes(nl2br(htmlspecialchars($reason_msg)))."',".$th.",".$frm.",".$req_by.")");

	if (isset($ll)) {
		db_unlock();
	}

	return $id;
}

function thx_get($id)
{
	return db_sab('SELECT id, reason_msg, th, frm, req_by FROM '.$GLOBALS['DBHOST_TBL_PREFIX'].'thr_exchange WHERE id='.$id);
}

function thx_delete($id)
{
	q('DELETE FROM '.$GLOBALS['DBHOST_TBL_PREFIX'].'thr_exchange WHERE id='.$id);
}
?>