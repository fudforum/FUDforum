<?php
/***************************************************************************
* copyright            : (C) 2001-2004 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: msg_icon_cache.inc,v 1.1 2004/06/23 16:20:24 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; either version 2 of the License, or
* (at your option) any later version.
***************************************************************************/

function rebuild_icon_cache()
{
	$d = opendir($GLOBALS['WWW_ROOT_DISK'] . 'images/message_icons');
	if (!$d) {
		return;
	}
	readdir($d); readdir($d);
	
	$allowed_ext = array('.jpg' => 1, '.png' => 1, '.jpeg' => 1, '.gif' => 1);
	$iconl = array();
	
	while ($f = readdir($d)) {
		if (strlen($f) < 4 || !isset($allowed_ext[strtolower(strrchr($f, '.'))])) {
			continue;
		}
		$iconl[] = $f;
	}
	closedir($d);

	$fp = fopen($GLOBALS['TMP'].'icon_cache', "w");
	fwrite($fp, '<?php $ICON_L = '.var_export($iconl, 1).'; ?>');
	fclose($fp);

	rename($GLOBALS['TMP'].'icon_cache', $GLOBALS['FORUM_SETTINGS_PATH'].'icon_cache');
}
?>