<?php
/***************************************************************************
* copyright            : (C) 2001-2003 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: ulink.inc.t,v 1.6 2003/11/14 10:50:20 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; either version 2 of the License, or
* (at your option) any later version.
***************************************************************************/

function draw_user_link($login, $type, $custom_color='')
{
	if ($custom_color) {
		return '{TEMPLATE: ulink_custom_color}';
	}

	if (!($type & 1572864)) {
		return '{TEMPLATE: ulink_reg_user}';
	} else if ($type & 1048576) {
		return '{TEMPLATE: ulink_adm_user}';
	} else if ($type & 524288) {
		return '{TEMPLATE: ulink_mod_user}';
	}
}
?>