<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: url.inc.t,v 1.2 2002/08/24 12:16:36 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

function url_check($url)
{
	$url = trim($url);

	if( !$url ) return;

	if( strcasecmp($url[0], 'h') == 0 ) {
		if( strcasecmp(substr($url, 0, 7), 'http://') ) 
			return 'http://'.$url;
	}		
	else {
		if( strcasecmp(substr($url, 0, 6), 'ftp://') ) 
			return 'http://'.$url;
	}
	return $url;
}
?>