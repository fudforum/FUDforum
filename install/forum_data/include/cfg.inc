<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: cfg.inc,v 1.2 2003/04/29 18:29:35 hackie Exp $
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
	return str_replace("\n", '<{LF}>', str_replace("\r", '<{CF}>', $str));
}

function cfg_dec($str)
{
	return str_replace('<{LF}>', "\n", str_replace('<{CF}>', "\r", $str));
}
?>