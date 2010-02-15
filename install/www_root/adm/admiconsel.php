<?php
/**
* copyright            : (C) 2001-2010 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id$
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; version 2 of the License.
**/

	require('./GLOBALS.php');
	fud_use('adm.inc', true);

function print_image_list($dir,$js_field,$type)
{
	global $usr;

	$web_dir = $GLOBALS['WWW_ROOT'] . $dir . '/';
	$path = $GLOBALS['WWW_ROOT_DISK'] . $dir;
?>
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
				<img src="'.$web_dir.$f.'" border="0" alt="avatar" /><br /><font size="-2">'.$f.'</font></a></td>';
		}
	} else if (!is_readable($path)) {
		echo '<td>Unable to open '.$path.' for reading.</td>';
	} else {
		echo '<td>No '.$type.' images are available.</td>';
	}
	echo '</tr></table>';
}

/* main */
	define('popup', 1);
	require($WWW_ROOT_DISK .'adm/header.php');

	if (!isset($_GET['type']) || $_GET['type'] < 1 || $_GET['type'] > 5) {
		errorify('Invalid image selection type.');
	}

	switch ($_GET['type']) {
		case 1: /* Forum icon selection. */
			print_image_list('images/forum_icons', "forms['frm_forum'].frm_forum_icon", 'Forum Icon');
			break;
		case 2: /* Mime icon selection. */
			print_image_list('images/mime', "forms['frm_mime'].mime_icon", 'Mime Icon');
			break;
		case 3: /* Emoticon/smiley selection. */
			print_image_list('images/smiley_icons', "forms['frm_sml'].sml_img", 'Smiley/Emoticon');
			break;
		case 4: /* Avatar selection. */
			print_image_list('images/avatars/', "forms['frm_avt'].avt_img", 'Built-In Avatars');
			break;
		case 5: /* Message icon selection. */
			print_image_list('images/message_icons', "forms['frm_forum'].frm_forum_icon", 'Message Icon');
			break;
	}

	require($WWW_ROOT_DISK .'adm/footer.php');
?>
