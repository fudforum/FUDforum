<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: ulink.inc.t,v 1.1 2003/01/13 10:48:20 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

function draw_user_link($login, $type, $custom_color='')
{
	if (!empty($custom_color)) {
		return '{TEMPLATE: ulink_custom_color}';
	}

	switch ($type)
	{
		case 'N':
			return '{TEMPLATE: ulink_reg_user}';
			break;
		case 'Y':
			return '{TEMPLATE: ulink_mod_user}';
			break;
		case 'A':
			return '{TEMPLATE: ulink_adm_user}';
			break;
	}
}
?>