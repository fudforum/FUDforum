<?php
/***************************************************************************
* copyright            : (C) 2001-2003 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: quicklogin.inc.t,v 1.6 2003/10/09 14:34:26 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it 
* under the terms of the GNU General Public License as published by the 
* Free Software Foundation; either version 2 of the License, or 
* (at your option) any later version.
***************************************************************************/

	if (__fud_real_user__) {
		$quick_login = '{TEMPLATE: quick_login_loged_in}';
	} else {
		$quick_login_cookie = $FUD_OPT_1 & 128 ? '{TEMPLATE: quick_login_cookie}' : '';
		$quick_login = '{TEMPLATE: quick_login_on}';
	}
?>