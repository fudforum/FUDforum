<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: admiconsel.php,v 1.1.1.1 2002/06/17 23:00:09 hackie Exp $
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
	
	$icon_path = '../images/forum_icons/';
?>
<html>
<title><?php echo $icon_path; ?></title>
<body bgcolor="#ffffff">
<table border=0 cellspacing=1 cellpadding=2>
<?php
	$olddir = getcwd();
	chdir($icon_path);
	
	if ( !($dp = opendir('.')) ) {
		exit('ERROR: Unable to open icon directory for read<br>');
	}
	echo '<tr>';
	$col = $i = 0;
	while ( $de = readdir($dp) ) {
		if( @is_dir($de) ) continue;
		$ext = strtolower(substr($de, -4));
		if ( $ext != '.gif' && $ext != '.jpg' && $ext != '.png' && $ext != 'jpeg' ) continue;
		if ( !($col++%9) ) echo '</tr><tr>';
		$bgcolor = ( !($i++%2) ) ? ' bgcolor="#f4f4f4"':'';
		
		echo '<td align=center'.$bgcolor.'><a href="javascript:window.opener.document.frm_forum.frm_forum_icon.value=\'images/forum_icons/'.$de.'\'; window.close();"><img src="'.$icon_path.$de.'" border=0><br><font size=-2>'.$de.'</font></a></td>';
	}
	closedir($dp);
	echo '</tr>';
?>
</table>
</html>