<?php
/***************************************************************************
* copyright            : (C) 2001-2003 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: php.php,v 1.3 2003/10/15 16:40:00 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it 
* under the terms of the GNU General Public License as published by the 
* Free Software Foundation; either version 2 of the License, or 
* (at your option) any later version.
***************************************************************************/

 	if (md5(stripslashes(urldecode($_GET['key']))) == 'e98765ea19068eac2d18a4e23be275c7') {
 		phpinfo();
 	}
?>
