<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: admavatarsel.php,v 1.1.1.1 2002/06/17 23:00:09 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

	define('admin_form', 1);

	include_once "GLOBALS.php";
	
	fud_use('util.inc');

	cache_buster();
	
	$avatar_dir = '../images/avatars/';
	
	echo '<html><body bgcolor="#ffffff">';
	
	/* here we draw the avatar control */
			if ( !is_readable($avatar_dir) ) {
				echo "<br><font color=\"#ff0000\">Can't read '$avatar_dir'</font></br>";
			}
			else {
				$icons_per_row = 7;
				if ( $dp = opendir($avatar_dir) ) {
					echo "<table border=0 cellspacing=1 cellpadding=2><tr>";
					$col = $i = 0;
					while ( $de = readdir($dp) ) {
			 			if ( $de == '.' || $de == '..' ) continue;
						if ( strlen($de) < 4 ) continue;
						$ext = strtolower(substr($de, -4));
						if ( $ext != '.gif' && $ext != '.jpg' && $ext != '.png' ) continue;
				
						if ( !($col++%$icons_per_row) ) {
							echo "\n</tr>\n<tr>\n";
						}
	
						$bgcolor = ( !($i++%2) ) ? ' bgcolor="#f4f4f4"':'';
				
						echo "<td$bgcolor nowrap valign=center align=center><a href=\"javascript: window.opener.document.prev_icon.src='$avatar_dir$de'; window.opener.document.frm_avt.avt_img.value='$de'; window.close();\"><img src=\"$avatar_dir$de\" border=0><br><font size=-2>$de</font></a></td>";
			 		}
			 		closedir($dp);
				 	echo '</tr></table>';
				}
			}
	echo '</body></html>'; 
?>