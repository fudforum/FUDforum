<?php
/***************************************************************************
* copyright            : (C) 2001-2003 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: markread.php.t,v 1.6 2003/10/09 14:34:26 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it 
* under the terms of the GNU General Public License as published by the 
* Free Software Foundation; either version 2 of the License, or 
* (at your option) any later version.
***************************************************************************/

/*{PRE_HTML_PHP}*/
/*{POST_HTML_PHP}*/

	if (_uid) {
		if (!isset($_GET['id'])) {
			user_mark_all_read(_uid);
		} else if ((int)$_GET['id']) {
			user_mark_forum_read(_uid, (int)$_GET['id'], $usr->last_read);
		}
	}

	check_return($usr->returnto);
	exit();
?>