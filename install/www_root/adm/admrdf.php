<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: admrdf.php,v 1.1 2003/05/15 18:21:34 hackie Exp $
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
	fud_use('glob.inc', true);
	fud_use('widgets.inc', true);
	fud_use('draw_select_opt.inc');
	require($DATA_DIR . 'include/RDF.php');

function print_yn_field($descr, $help, $field)
{
	$str = !isset($GLOBALS[$field]) ? 'Y' : $GLOBALS[$field];
	echo '<tr bgcolor="#bff8ff"><td>'.$descr.': <br><font size="-1">'.$help.'</font></td><td valign="top">'.create_select('CF_'.$field, "Yes\nNo", "Y\nN", $str).'</td></tr>';
}
	
function print_string_field($descr, $help, $field, $is_int=0)
{
	if (!isset($GLOBALS[$field])) {
		$str = !$is_int ? '' : '0';
	} else {
		$str = !$is_int ? htmlspecialchars($GLOBALS[$field]) : (int)$GLOBALS[$field];
	}
	echo '<tr bgcolor="#bff8ff"><td>'.$descr.': <br><font size="-1">'.$help.'</td><td valign="top"><input type="text" name="CF_'.$field.'" value="'.$str.'"></td></tr>';
}


	if (isset($_POST['form_posted'])) {
		foreach ($_POST as $k => $v) {
			if (strncmp($k, 'CF_', 3)) {
				continue;
			}
			$k = substr($k, 3);
			if (!isset($GLOBALS[$k]) || $GLOBALS[$k] != $v) {
				$ch_list[$k] = $v;
			}
		}
		if (isset($ch_list)) {
			change_global_settings($ch_list, 'RDF.php');
			/* put the settings 'live' so they can be seen on the form */
			foreach ($ch_list as $k => $v) {
				$GLOBALS[$k] = $v;
			}
		}
	}

	require($WWW_ROOT_DISK . 'adm/admpanel.php'); 
?>
<h2>RDF Feed Configuration</h2>
<form method="post" action="admrdf.php">
<table border=0 cellspacing=1 cellpadding=3>
<?php
	print_yn_field('RDF Feed Enabled', 'Whether or not to enable RDF feed of the forum\'s data', 'RDF_ENABLED');
	print_yn_field('RDF Authentication', 'Whether or not to perform permission checks to determine if the user has access the requested data.', 'AUTH');
	print_string_field('User id', 'By default when perform authentication, the forum will treat validate the user as anonymous, however of increased or lowered permission you can specify exactly which user will the RDF feed be authenticated as. This field allows you to enter the id of that user.', 'AUTH_ID');
	print_string_field('Maximum number of result', 'The maximum number of results that can be fetched within a single request through the RDF feed', 'MAX_N_RESULTS');
?>
<tr bgcolor="#bff8ff"><td colspan=2 align=right><input type="submit" name="btn_submit" value="Change Settings"></td></tr>
</table>
<input type="hidden" name="form_posted" value="1">
</form>
<?php require($WWW_ROOT_DISK . 'adm/admclose.html'); ?>