<?php
/**
* copyright            : (C) 2001-2006 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: fileio.inc.t,v 1.16 2006/01/17 23:00:54 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; either version 2 of the License, or
* (at your option) any later version.
**/

function read_msg_body($off, $len, $id)
{
	if ($off == -1 && $FUD_OPT_3 & 32768) { // fetch from DB
		return q_singleval('SELECT data FROM {SQL_TABLE_PREFIX}msg_store WHERE id='.$id);
	}

	if (!$len) {
		return;
	}

	if (!isset($GLOBALS['__MSG_FP__'][$id])) {
		$GLOBALS['__MSG_FP__'][$id] = fopen($GLOBALS['MSG_STORE_DIR'].'msg_'.$id, 'rb');
	}

	fseek($GLOBALS['__MSG_FP__'][$id], $off);
	return fread($GLOBALS['__MSG_FP__'][$id], $len);
}
?>