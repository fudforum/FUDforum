<?php
/***************************************************************************
* copyright            : (C) 2001-2004 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: admiconsel.php,v 1.11 2004/10/26 21:08:02 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; either version 2 of the License, or
* (at your option) any later version.
***************************************************************************/

	require('./GLOBALS.php');
	fud_use('adm.inc', true);
?>
<html>
<title>Forum Icon Selection</title>
<body bgcolor="#ffffff">
<table border=0 cellspacing=1 cellpadding=2>
<tr>
<?php
	$path = $WWW_ROOT . 'images/forum_icons/';

	if (($files = glob($WWW_ROOT_DISK. 'images/forum_icons/{*.jpg,*.gif,*.png,*.jpeg}', GLOB_BRACE|GLOB_NOSORT))) {
		$col = $i = 0;
		foreach ($files as $file) {
			$f = basename($file);
			if (!($col++%9)) {
				echo '</tr><tr>';
			}
			$bgcolor = !($i++%2) ? ' bgcolor="#f4f4f4"' : '';

			echo '<td align="center"'.$bgcolor.'><a href="javascript:window.opener.document.frm_forum.frm_forum_icon.value=\'images/forum_icons/'.$f.'\'; window.close();"><img src="'.$path.$f.'" border=0><br><font size=-2>'.$f.'</font></a></td>';
		}
	} else if ($files === FALSE && !is_readable($path)) {
		echo '<td>Unable to open '.$path.' for reading.</td>';
	}
?>
</tr>
</table>
</html>