<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: rhost.inc.t,v 1.1.1.1 2002/06/17 23:00:09 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/
	
function get_host($ip)
{
	if( empty($ip) ) return;

	$name = gethostbyaddr($ip);
	if( $name != $ip ) {
		if ( count(explode('.', $name)) > 2 ) 
			$name = substr($name, strpos($name, '.'));
	}		
	else
		$name = NULL;		

	return $name;
}	
?>