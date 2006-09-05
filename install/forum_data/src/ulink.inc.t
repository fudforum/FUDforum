<?php
/**
* copyright            : (C) 2001-2006 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: ulink.inc.t,v 1.11 2006/09/05 13:16:49 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; either version 2 of the License.
**/

function draw_user_link($login, $type, $custom_color='')
{
	if ($custom_color) {
		return '{TEMPLATE: ulink_custom_color}';
	}

	switch ($type & 1572864) {
		case 0:
		default:
			return '{TEMPLATE: ulink_reg_user}';
		case 1048576:
			return '{TEMPLATE: ulink_adm_user}';
		case 524288:
			return '{TEMPLATE: ulink_mod_user}';
	}
}
?>