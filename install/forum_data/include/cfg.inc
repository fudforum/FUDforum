<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: cfg.inc,v 1.1 2002/06/26 19:44:35 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

function cfg_enc($str)
{
	$str = str_replace("\n", '<{LF}>', $str);
	$str = str_replace("\r", '<{CF}>', $str);
	return $str;
}

function cfg_dec($str)
{
	$str = str_replace('<{LF}>', "\n", $str);
	$str = str_replace('<{CF}>', "\r", $str);
		
	return $str;
}
?>