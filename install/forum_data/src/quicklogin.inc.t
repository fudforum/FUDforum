<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: quicklogin.inc.t,v 1.3 2003/04/10 17:37:00 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

	if (__fud_real_user__) {
		$quick_login = '{TEMPLATE: quick_login_loged_in}';
	} else {
		$quick_login_cookie = $GLOBALS['SESSION_USE_URL'] == 'Y' ? '{TEMPLATE: quick_login_cookie}' : '';
		$quick_login = '{TEMPLATE: quick_login_on}';
	}
?>