<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: return.inc.t,v 1.5 2002/08/08 22:28:26 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

function create_return()
{
	return '<input type="hidden" name="returnto" value="'.htmlspecialchars(urldecode($GLOBALS['returnto'])).'">';
}
        
function check_return()
{
        if ( empty($GLOBALS['returnto']) ) 
        	$GLOBALS['returnto']='{ROOT}?'._rsidl;
        else {
        	$GLOBALS['returnto']='{ROOT}?'._rsidl;
        	$url_bits = parse_url($GLOBALS['returnto']);
		
		if( $url_bits['query'] ) {
			parse_str(str_replace('&amp;', '&', $url_bits['query']), $url_args);
			if( is_array($url_args) ) {
				foreach( $url_args as $k => $v ) {
					if( $k == 'S' || $k == 'rid' ) continue;
					$GLOBALS['returnto'] .= '&'.$k.'='.urlencode($v);
				}	
			}
		}
        }
        header("Location: ".$GLOBALS['returnto']);
	exit();
}
?>