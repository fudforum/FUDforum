<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: alt_var.inc.t,v 1.2 2002/07/30 22:56:32 hackie Exp $
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
	if( !isset($GLOBALS['_ALTERNATOR_'][$key]) ) {
		$GLOBALS['_ALTERNATOR_'][$key]['p'] = 0;
		$GLOBALS['_ALTERNATOR_'][$key]['t'] = func_num_args()-1;
		for($i=1;$i<$GLOBALS['_ALTERNATOR_'][$key]['t']+1; $i++ ) $GLOBALS['_ALTERNATOR_'][$key]['v'][] = func_get_arg($i);
	}
	else if( $GLOBALS['_ALTERNATOR_'][$key]['p'] == $GLOBALS['_ALTERNATOR_'][$key]['t'] )
		$GLOBALS['_ALTERNATOR_'][$key]['p'] = 0;

	return $GLOBALS['_ALTERNATOR_'][$key]['v'][$GLOBALS['_ALTERNATOR_'][$key]['p']++];
}
?>