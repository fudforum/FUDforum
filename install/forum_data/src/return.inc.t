<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: return.inc.t,v 1.12 2003/06/03 14:01:46 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

function check_return($returnto)
{
	if ($GLOBALS['USE_PATH_INFO'] == 'N' || empty($_SERVER['PATH_INFO'])) {
		if (!$returnto || !strncmp($returnto, 't=error', 7)) {
			header('Location: {ROOT}?t=index&'._rsidl);
		} else {
			if (strpos($returnto, 'S=') === FALSE && $GLOBALS['SESSION_USE_URL'] == 'Y') {
				header('Location: {ROOT}?'.$returnto.'&S='.s);
			} else {
				header('Location: {ROOT}?'.$returnto);
			}
		}
	} else {
		if (!$returnto || !strncmp($returnto, '/er/', 4)) {
			$pfx = '';
			if ($GLOBALS['TRACK_REFERRALS'] == 'Y') {
				$pfx .= _uid . '/';
			}
			if ($GLOBALS['SESSION_USE_URL'] == 'Y') {
				$pfx .= s . '/';
			}
			header('Location: {ROOT}/i/'.$pfx);
		} else {
			header('Location: {ROOT}'.$returnto);
		}
	}
	exit;
}
?>