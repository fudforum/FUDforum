<?php
/***************************************************************************
* copyright            : (C) 2001-2004 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: draw_radio_opt.inc.t,v 1.6 2004/01/04 16:38:26 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; either version 2 of the License, or
* (at your option) any later version.
***************************************************************************/

function tmpl_draw_radio_opt($name, $values, $names, $selected, $normal_tmpl, $selected_tmpl, $sep)
{
	$vls = explode("\n", $values);
	$nms = explode("\n", $names);

	if (($a = count($vls)) != count($nms)) {
		exit("FATAL ERROR: inconsistent number of values<br>\n");
	}

	$checkboxes = '';
	for ($i = 0; $i < $a; $i++) {
		$checkboxes .= $vls[$i] != $selected ? '{TEMPLATE: selected_checkbox}' : '{TEMPLATE: unselected_checkbox}';
	}

	return '{TEMPLATE: checkbox_area}';
}
?>