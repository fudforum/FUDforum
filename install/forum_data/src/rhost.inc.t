<?php
/**
* copyright            : (C) 2001-2006 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: rhost.inc.t,v 1.15 2006/09/19 14:37:55 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; version 2 of the License.
**/

function get_host($ip)
{
	if (!$ip || $ip == '0.0.0.0') {
		return;
	}

	$name = gethostbyaddr($ip);

	if ($name == $ip) {
		$name = substr($name, 0, strrpos($name, '.')) . '*';
	} else if (substr_count($name, '.') > 1) {
		$name = '*' . substr($name, strpos($name, '.')+1);
	}

	return $name;
}
?>