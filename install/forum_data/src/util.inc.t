<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: util.inc.t,v 1.1.1.1 2002/06/17 23:00:09 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/
	
function cache_buster()
{
	header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
        header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
	header("Cache-Control: no-cache, must-revalidate");
	header("Pragma: no-cache");
}

function trim_show_len($text,$type)
{
	if( strlen($text)>$GLOBALS["MAX_".$type."_SHOW"] ) $text = substr($text,0,$GLOBALS["MAX_".$type."_SHOW"]).'...';
	
	return $text;
}

function prepad($val, $to, $chr)
{
        $ln_pad = $to - strlen($val);
        
	for ( $i=0; $i<$ln_pad; $i++ ) $val = $chr.$val;

        return $val;
}
?>