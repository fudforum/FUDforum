<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: rhost.inc.t,v 1.2 2002/11/07 18:27:36 hackie Exp $
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
	if (substr_count($name, '.') > 2) {
		$name = substr($name, 0, strrpos($name, '.'));
	}

	return $name;
}	
?>