<?php
/**
* copyright            : (C) 2001-2009 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: admiconsel.php,v 1.22 2009/01/29 18:37:40 frank Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; version 2 of the License.
**/

	require('./GLOBALS.php');
	fud_use('adm.inc', 1);

function print_image_list($dir,$js_field,$type)
{
	$web_dir = $GLOBALS['WWW_ROOT'] . $dir . '/';
	$path = $GLOBALS['WWW_ROOT_DISK'] . $dir;
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" xml:lang="en">
<head>
<?php echo '<title>'.$GLOBALS['FORUM_TITLE'].': Admin Control Panel - '.$type.' Selection</title>' ?>
<meta http-equiv="Content-Type" content="text/html; charset=<?php 
if (file_exists($DATA_DIR.'thm/'.$usr->theme_name.'/i18n/'.$usr->lang.'/charset')) {
	echo trim(file_get_contents($DATA_DIR.'thm/'.$usr->theme_name.'/i18n/'.$usr->lang.'/charset'));
} else if (file_exists($DATA_DIR.'thm/default/i18n/'.$usr->lang.'/charset')) {
	echo trim(file_get_contents($DATA_DIR.'thm/default/i18n/'.$usr->lang.'/charset'));
} else {
	echo 'utf-8';
}
?>" />
</head>
<body bgcolor="#ffffff">
<table border="0" cellspacing="1" cellpadding="2">
<tr>
<?php

	if (($files = glob($path.'/{*.jpg,*.gif,*.png,*.jpeg}', GLOB_BRACE|GLOB_NOSORT))) {
		$col = 0;
		foreach ($files as $file) {
			$bgcolor = !($col % 2) ? ' bgcolor="#f4f4f4"' : '';
			$f = basename($file);
			if (!($col++%9) && ($col!=1)) {
				echo '</tr><tr>';
			}
			echo '<td align="center"'.$bgcolor.'><a href="javascript:
					window.opener.document.'.$js_field.'.value=\''.$f.'\';
					if (window.opener.document.prev_icon) 
						window.opener.document.prev_icon.src=\''.$web_dir.$f.'\';
					window.close();">
				<img src="'.$web_dir.$f.'" border="0" alt="avatar"><br /><font size="-2">'.$f.'</font></a></td>';
		}
	} else if (!is_readable($path)) {
		echo '<td>Unable to open '.$path.' for reading.</td>';
	} else {
		echo '<td>No '.$type.' images are available.</td>';
	}
}

	if (!isset($_GET['type']) || $_GET['type'] < 1 || $_GET['type'] > 5) {
		exit("Invalid image selection type.<br />\n");
	}

	switch ($_GET['type']) {
		case 1: /* forum icon selection */
			print_image_list('images/forum_icons', 'frm_forum.frm_forum_icon', 'Forum Icon');
			break;
		case 2: /* mime icon selection */
			print_image_list('images/mime', 'frm_mime.mime_icon', 'Mime Icon');
			break;
		case 3: /* emoticon/smiley selection */
			print_image_list('images/smiley_icons', 'frm_sml.sml_img', 'Smiley/Emoticon');
			break;
		case 4: /* avatar selection */
			print_image_list('images/avatars/', "forms['frm_avt'].avt_img", 'Built-In Avatars');
			break;
		case 5: /* message icon selection */
			print_image_list('images/message_icons', 'frm_forum.frm_forum_icon', 'Message Icon');
			break;
	}
?>
</tr>
</table>
</body>
</html>
