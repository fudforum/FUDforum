<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: return.inc.t,v 1.6 2002/08/09 09:25:40 hackie Exp $
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
        	$url_bits = parse_url($GLOBALS['returnto']);
        	$GLOBALS['returnto']='{ROOT}?'._rsidl;
		
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