<?php
/**
* copyright            : (C) 2001-2006 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: draw_select_opt.inc.t,v 1.12 2006/09/19 14:37:55 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; version 2 of the License.
**/

function tmpl_draw_select_opt($values, $names, $selected)
{
	$vls = explode("\n", $values);
	$nms = explode("\n", $names);

	if (count($vls) != count($nms)) {
		exit("FATAL ERROR: inconsistent number of values inside a select<br>\n");
	}

	$options = '';
	foreach ($vls as $k => $v) {
		$options .= '{TEMPLATE: sel_option}';
	}

	return '{TEMPLATE: option_area}';
}
?>