<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: alt_var.inc.t,v 1.1.1.1 2002/06/17 23:00:09 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

function alt_var()
{
	$key = func_get_arg(0);
	if ( empty($GLOBALS['_ALTERNATOR_'][$key]) || $GLOBALS['_ALTERNATOR_'][$key] == func_num_args() ) $GLOBALS['_ALTERNATOR_'][$key] = 1;
	return func_get_arg($GLOBALS['_ALTERNATOR_'][$key]++);
}
?>