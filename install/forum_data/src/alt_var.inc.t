<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: alt_var.inc.t,v 1.3 2003/04/20 10:45:19 hackie Exp $
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
		$GLOBALS['_ALTERNATOR_'][$key]['p'] = 0;
		$GLOBALS['_ALTERNATOR_'][$key]['v'] = func_get_args();
		unset($GLOBALS['_ALTERNATOR_'][$key]['v'][0]);
		$GLOBALS['_ALTERNATOR_'][$key]['t'] = count($GLOBALS['_ALTERNATOR_'][$key]['v']);
	} else if ($GLOBALS['_ALTERNATOR_'][$key]['p'] == $GLOBALS['_ALTERNATOR_'][$key]['t']) {
		$GLOBALS['_ALTERNATOR_'][$key]['p'] = 0;
	}

	return $GLOBALS['_ALTERNATOR_'][$key]['v'][$GLOBALS['_ALTERNATOR_'][$key]['p']++];
}
?>