<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: rview.php.t,v 1.3 2002/07/30 14:34:37 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/	

	{PRE_HTML_PHP}	
	{POST_HTML_PHP}
	
	$HTTP_SERVER_VARS["QUERY_STRING"] = preg_replace('!t=([A-Za-z0-9]+)(&)?!', '', $HTTP_SERVER_VARS["QUERY_STRING"]);
	
	header("Location: {ROOT}?t=".d_thread_view.'&'.$HTTP_SERVER_VARS["QUERY_STRING"]);
	exit;
?>