<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: alt_var.inc.t,v 1.4 2003/04/21 16:26:28 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

function alt_var($key)
{
	if (!isset($GLOBALS['_ALTERNATOR_'][$key])) {
		$args = func_get_args(); unset($args[0]);
		$GLOBALS['_ALTERNATOR_'][$key] = array('p' => 0, 't' => count($args), 'v' => array_values($args)); 
	} else if ($GLOBALS['_ALTERNATOR_'][$key]['p'] == $GLOBALS['_ALTERNATOR_'][$key]['t']) {
		$GLOBALS['_ALTERNATOR_'][$key]['p'] = 0;
	}

	return $GLOBALS['_ALTERNATOR_'][$key]['v'][$GLOBALS['_ALTERNATOR_'][$key]['p']++];
}
?>