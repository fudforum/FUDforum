<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: admiconsel.php,v 1.3 2003/05/12 16:49:55 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

	require('GLOBALS.php');
	fud_use('adm.inc', true);
?>
<html>
<title>Forum Icon Selection</title>
<body bgcolor="#ffffff">
<table border=0 cellspacing=1 cellpadding=2>
<?php
	$imp = $WWW_ROOT . 'images/forum_icons/';
	$ima = array('.jpg' => 1, '.jpeg' => 1, '.gif' => 1, '.png' => 1);

	if (!($dp = opendir($WWW_ROOT_DISK . 'images/forum_icons'))) {
		exit('ERROR: Unable to open icon directory for read');
	}
	readdir($dp); readdir($dp);
	echo '<tr>';
	$col = $i = 0;
	while ($f = readdir($dp)) {
		if (!isset($ima[strtolower(strchr($f, '.'))])) {
			continue;
		}
		if (!($col++%9)) {
			echo '</tr><tr>';
		}
		$bgcolor = !($i++%2) ? ' bgcolor="#f4f4f4"' : '';
		
		echo '<td align="center"'.$bgcolor.'><a href="javascript:window.opener.document.frm_forum.frm_forum_icon.value=\'images/forum_icons/'.$f.'\'; window.close();"><img src="'.$imp.$f.'" border=0><br><font size=-2>'.$f.'</font></a></td>';
	}
	closedir($dp);
	echo '</tr>';
?>
</table>
</html>