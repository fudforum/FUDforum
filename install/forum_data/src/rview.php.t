<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: rview.php.t,v 1.5 2002/08/23 01:02:02 hackie Exp $
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
	
	if( preg_match('!&(th|frm_id)=([0-9]+)(&)?!', $HTTP_SERVER_VARS["QUERY_STRING"], $m) ) {
		switch( $m[1] )
		{
			case 'msg':
			case 'tree':
				$page = d_thread_view;
				break;
			case 'thread':
			case 'threadt':
				$page = t_thread_view;
				break;
			default:
				$page = 'index';
				break;
		}
		$HTTP_SERVER_VARS["QUERY_STRING"] = str_replace('t=rview', 't='.$page);
		header("Location: {ROOT}?t".$HTTP_SERVER_VARS["QUERY_STRING"]);
		
		exit;
	}
	
	header("Location: {ROOT}?t=index&"._rsid);	
	exit;
?>