<?php
/***************************************************************************
* copyright            : (C) 2001-2003 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: customtags.inc,v 1.4 2003/10/10 01:02:09 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it 
* under the terms of the GNU General Public License as published by the 
* Free Software Foundation; either version 2 of the License, or 
* (at your option) any later version.
***************************************************************************/

function ctag_rebuild_cache($id)
{
	$ct = '';
	$c = uq('SELECT name FROM '.$GLOBALS['DBHOST_TBL_PREFIX'].'custom_tags WHERE user_id='.$id);
	while ($r = db_rowarr($c)) {
		$ct .= ', ' . addslashes($r[0]);
	}
	qf($c);
	q("UPDATE ".$GLOBALS["DBHOST_TBL_PREFIX"]."users SET custom_status='".$ct."' WHERE id=".$id);
}
?>