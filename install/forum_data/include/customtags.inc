<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: customtags.inc,v 1.2 2003/10/05 22:19:56 hackie Exp $
****************************************************************************

****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

function ctag_rebuild_cache($id)
{
	$ct = '';
	$c = uq('SELECT name FROM '.$GLOBALS['DBHOST_TBL_PREFIX'].'custom_tags WHERE user_id='.$id);
	while ($r = db_rowarr($c)) {
		$ct .= addslashes($r[0]) . '<br>';
	}
	qf($c);
	$ct = $ct ? "'<font class=\"LevelText\">" . $ct  . "'" : 'NULL';
	q('UPDATE '.$GLOBALS['DBHOST_TBL_PREFIX'].'users SET custom_status='.$ct.' WHERE id='.$id);
}
?>