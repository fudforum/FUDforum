<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: draw_select_opt.inc.t,v 1.1.1.1 2002/06/17 23:00:09 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

function tmpl_draw_select_opt($values, $names, $selected, $normal_tmpl, $selected_tmpl)
{
	$vls = explode("\n", $values);
	$nms = explode("\n", $names);
	
	$a = count($vls);
	
	if( $a != count($nms) ) exit("FATAL ERROR: inconsistent number of values inside a select<br>\n");
	
	$options = '';
	for( $i=0; $i<$a; $i++ ) {
		if( $vls[$i]!=$selected ) 
			$options .= '{TEMPLATE: selected_option}';
		else
			$options .= '{TEMPLATE: unselected_option}';
	}
	return '{TEMPLATE: option_area}';
}
?>