<?php
/***************************************************************************
* copyright            : (C) 2001-2004 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: quicklogin.inc.t,v 1.9 2004/08/16 19:51:06 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; either version 2 of the License, or
* (at your option) any later version.
***************************************************************************/

	$auto_c = $GLOBALS['FUD_OPT_3'] & 256 ? ' autocomplete="off"' : '';

	if (__fud_real_user__) {
		$quick_login = '{TEMPLATE: quick_login_loged_in}';
	} else {
		$quick_login_cookie = $FUD_OPT_1 & 128 ? '{TEMPLATE: quick_login_cookie}' : '';
		$quick_login = '{TEMPLATE: quick_login_on}';
	}
?>