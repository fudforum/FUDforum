<?php
/**
* copyright            : (C) 2001-2004 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: ipfilter.inc,v 1.7 2004/11/24 19:53:34 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; either version 2 of the License, or
* (at your option) any later version.
**/

function ip_cache_rebuild()
{
	$data = '';
	$c = uq('SELECT ca, cb, cc, cd FROM '.$GLOBALS['DBHOST_TBL_PREFIX'].'ip_block');
	while ($r = db_rowarr($c)) {
		$data .= '$GLOBALS[\'__FUD_IP_FILTER__\']['.$r[0].']['.$r[1].']['.$r[2].']['.$r[3].'] = 1;';
	}
	if (!$data) {
		$data = '$GLOBALS[\'__FUD_IP_FILTER__\'] = array(); ';
	}

	$fp = fopen($GLOBALS['FORUM_SETTINGS_PATH'] . 'ip_filter_cache', 'w');
	fwrite($fp, '<?php ' . $data . ' ?>');
	fclose($fp);
}
?>