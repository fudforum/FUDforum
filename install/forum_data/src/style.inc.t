<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: style.inc.t,v 1.1.1.1 2002/06/17 23:00:09 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

function set_row_color_alt($val)
{
	if ( $val ) 
		$GLOBALS['__STYLE_INC']['COLOR_ALT'] = 1;
	else 
		$GLOBALS['__STYLE_INC']['COLOR_ALT'] = 0;
}

function ROW_BGCOLOR($reset='')
{
	if( $reset ) $GLOBALS['__STYLE_INC']['ROW_ALTCOLOR']=0;

	if ( !isset($GLOBALS['__STYLE_INC']['ROW_ALTCOLOR']) ) $GLOBALS['__STYLE_INC']['ROW_ALTCOLOR'] = 0;
	if ( $GLOBALS['__STYLE_INC']['COLOR_ALT'] ) $bgcolor = ' class="'.(( $GLOBALS['__STYLE_INC']['ROW_ALTCOLOR']++%2 )?'RowStyleA':'RowStyleB').'"';
	
	return $bgcolor;
}
?>