<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: mime_adm.inc,v 1.2 2002/06/26 19:47:18 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

class fud_mime 
{
	var $id=NULL;
	var $fl_ext=NULL;
	var $mime_hdr=NULL;
	var $descr=NULL;
	var $icon=NULL;
	
	function get($id)
	{
		qobj("SELECT * FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."mime WHERE id=".$id, $this);
		if( empty($this->id) ) exit("ERROR: Couldn't find a MIME with an id of <b>$id</b> in the database<br>\n");
	}
	
	function add()
	{
		if ( !db_locked() ) {
			$ll = 1;
			db_lock($GLOBALS['DBHOST_TBL_PREFIX']."mime+");
		}
		$r = q("INSERT INTO ".$GLOBALS['DBHOST_TBL_PREFIX']."mime (fl_ext,mime_hdr,descr,icon) VALUES('".$this->fl_ext."','".$this->mime_hdr."','".$this->descr."','".$this->icon."')");
		$this->id = db_lastid($GLOBALS['DBHOST_TBL_PREFIX']."mime", $r);
		if ( $ll ) db_unlock();
		
		return $this->id;
	}
	
	function sync($id)
	{
		q("UPDATE ".$GLOBALS['DBHOST_TBL_PREFIX']."mime SET fl_ext='".$this->fl_ext."', mime_hdr='".$this->mime_hdr."', descr='".$this->descr."', icon='".$this->icon."' WHERE id=".$id);	
	}
	
	function delete()
	{
		q("DELETE FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."mime WHERE id=".$this->id);
		if( $this->icon != 'unknown.gif' && !bq("SELECT id FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."mime WHERE icon='".$this->icon."' LIMIT 1") ) @unlink('../images/mime/'.$this->icon);
	}
}