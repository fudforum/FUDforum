<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: rview.php.t,v 1.6 2002/08/26 06:28:59 hackie Exp $
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
	
	if( preg_match('!&(goto|th|frm_id)=([0-9]+)!', $HTTP_SERVER_VARS["QUERY_STRING"], $m) ) {
		switch( $m[1] )
		{
			case 'th':
			case 'goto':
				$page = 't='.d_thread_view;
				break;
			case 'frm_id':
				$page = 't='.t_thread_view;
				break;
			default:
				$page = 't=index';
				break;
		}
		$HTTP_SERVER_VARS["QUERY_STRING"] = str_replace('t=rview', $page, $HTTP_SERVER_VARS["QUERY_STRING"]);
		header("Location: {ROOT}?".$HTTP_SERVER_VARS["QUERY_STRING"]);
		
		exit;
	}
	
	header("Location: {ROOT}?t=index&"._rsid);	
	exit;
?>