<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: return.inc.t,v 1.8 2003/04/02 15:39:11 hackie Exp $
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
	if (!$returnto) {
		header('Location: '.$GLOBALS['WWW_ROOT'].'?t=index&'._rsidl);
	} else {
		header('Location: '.$GLOBALS['WWW_ROOT'].'?'.$returnto);
	}
	exit;
}
?>