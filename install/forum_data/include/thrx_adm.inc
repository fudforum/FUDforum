<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: thrx_adm.inc,v 1.2 2002/06/26 19:47:18 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

class fud_thr_exchange
{
	var $id=NULL;
	var $reason_msg=NULL;
	var $th=NULL;
	var $frm=NULL;
	var $req_by=NULL;
	
	function add()
	{
		if ( !db_locked() ) {
			$ll = 1;
			db_lock($GLOBALS['DBHOST_TBL_PREFIX']."thr_exchange+");
		}
		$r = q("INSERT INTO ".$GLOBALS['DBHOST_TBL_PREFIX']."thr_exchange (reason_msg,th,frm,req_by) VALUES('".$this->reason_msg."',".$this->th.",".$this->frm.",".$this->req_by.")");
		$this->id = db_lastid($GLOBALS['DBHOST_TBL_PREFIX']."thr_exchange", $r);
		if ( $ll ) db_unlock();
		
		return $this->id;
	}
	
	function get($id)
	{
		qobj("SELECT * FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."thr_exchange WHERE id=".$id, $this);
		if( empty($this->id) ) exit("Invalid thread x-change id<br>\n");
	}
	
	function delete()
	{
		q("DELETE FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."thr_exchange WHERE id=".$this->id);
	}
}
?>