<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: rview.php.t,v 1.4 2002/08/23 00:11:37 hackie Exp $
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
	
	if( preg_match('!t=([A-Za-z0-9]+)(&)?!', $HTTP_SERVER_VARS["QUERY_STRING"], $m) ) {
		$HTTP_SERVER_VARS["QUERY_STRING"] = preg_replace('!t=([A-Za-z0-9]+)(&)?!', '', $HTTP_SERVER_VARS["QUERY_STRING"]);
		
		switch( $m[1] )
		{
			case 'thread':
			case 'threadt':
				header("Location: {ROOT}?t=".t_thread_view.'&'.$HTTP_SERVER_VARS["QUERY_STRING"]);
				exit;
				break;
			case 'msg':
			case 'tree':
				header("Location: {ROOT}?t=".d_thread_view.'&'.$HTTP_SERVER_VARS["QUERY_STRING"]);
				exit;
				break;
		}
	}	
	header("Location: {ROOT}?t=index&"._rsid);	
	exit;
?>