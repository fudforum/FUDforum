<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: rev_fmt.inc.t,v 1.4 2003/04/21 14:14:39 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

$GLOBALS['__REVERSE_ARRAY__'] = array('&amp;'=>'&', '&quot;'=>'"', '&lt;'=>'<', '&gt;'=>'>');

function reverse_fmt(&$data)
{
	$data = strtr($data, $GLOBALS['__REVERSE_ARRAY__']);
}
?>