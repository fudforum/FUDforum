<?php
/**
* copyright            : (C) 2001-2009 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: draw_forum_path.inc.t,v 1.8 2009/01/29 18:37:17 frank Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; version 2 of the License.
**/

require $GLOBALS['FORUM_SETTINGS_PATH'].'cat_cache.inc';

function draw_forum_path($cid, $fn='', $fid=0, $tn='')
{
	global $cat_par, $cat_cache;

	$data = '';
	do {
		$data = '{TEMPLATE: dfp_cat_link}' . $data;
	} while (($cid = $cat_par[$cid]) > 0);

	if ($fid) {
		$data .= '{TEMPLATE: dfp_forum_lnk}';
	} else if ($fn) {
		$data .= '{TEMPLATE: dfp_forum_no_lnk}';
	}

	return '{TEMPLATE: forum_path}';
}
?>