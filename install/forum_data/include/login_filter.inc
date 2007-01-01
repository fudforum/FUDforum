<?php
/**
* copyright            : (C) 2001-2007 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: login_filter.inc,v 1.12 2007/01/01 18:23:42 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; version 2 of the License.
**/

function login_cache_rebuild()
{
	$c = uq('SELECT login FROM '.$GLOBALS['DBHOST_TBL_PREFIX'].'blocked_logins');
	while ($r = db_rowarr($c)) {
		$a[] = '/' . addcslashes($r[0], '\'/\\') . '/i';
	}
	unset($c);
	$data = '$GLOBALS[\'__FUD_LGN_FILTER__\'] = array(' . (isset($a) ? "'" . implode("', '", $a) . "'" : ''). ');';

	$fp = fopen($GLOBALS['FORUM_SETTINGS_PATH'] . 'login_filter_cache', 'w');
	fwrite($fp, '<?php ' . $data . ' ?>');
	fclose($fp);
}
?>