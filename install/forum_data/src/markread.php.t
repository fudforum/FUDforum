<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: markread.php.t,v 1.2 2002/07/30 14:34:37 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

	{PRE_HTML_PHP}{POST_HTML_PHP}
	if ( isset($usr) ) $usr->mark_all_read();
	
	check_return();
	exit();
?>