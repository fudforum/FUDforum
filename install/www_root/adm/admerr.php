<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: admerr.php,v 1.10 2003/10/05 22:19:50 hackie Exp $
****************************************************************************

****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

	require('./GLOBALS.php');
	fud_use('adm.inc', true);

	if (!empty($_GET['clear_sql_log'])) {
		@unlink($GLOBALS['ERROR_PATH'].'sql_errors');
	} else if (!empty($_GET['clear_fud_log'])) {
		@unlink($GLOBALS['ERROR_PATH'].'fud_errors');
	}

	require($WWW_ROOT_DISK . 'adm/admpanel.php');
?>
<h2>Error Log Browser</h2>

<?php
	$err = 0;

	if (@file_exists($GLOBALS['ERROR_PATH'].'fud_errors') && filesize($GLOBALS['ERROR_PATH'].'fud_errors')) {
		echo '<h4>FUDforum Error Log [<a href="admerr.php?clear_fud_log=1&'._rsid.'">clear log</a>]</h4>';
		echo '<table border=1 cellspacing=1 cellpadding=3><tr bgcolor="#bff8ff"><td>Time</td><td>Error Description</td></tr>';

		$errors = file($GLOBALS['ERROR_PATH'].'fud_errors');
		foreach ($errors as $error) {
			list($time, $msg) = explode('] ', substr($error, 1));
			echo '<tr><td nowrap valign="top">'.$time.'</td><td>'.base64_decode($msg).'</td></tr>';
		}
		echo '</table><br /><br />';
		$err = 1;
	}

	if (@file_exists($GLOBALS['ERROR_PATH'].'sql_errors') && filesize($GLOBALS['ERROR_PATH'].'sql_errors')) {
		echo '<h4>SQL Error Log [<a href="admerr.php?clear_sql_log=1&'._rsid.'">clear log</a>]</h4>';
		echo '<table border=1 cellspacing=1 cellpadding=3><tr bgcolor="#bff8ff"><td>Time</td><td>Error Description</td></tr>';

		$errors = file($GLOBALS['ERROR_PATH'].'sql_errors');
		foreach ($errors as $error) {
			list($time, $msg) = explode('] ', substr($error, 1));
			echo '<tr><td nowrap valign="top">'.$time.'</td><td>'.base64_decode($msg).'</td></tr>';
		}
		echo '</table><br /><br />';
		$err = 1;
	}

	if (!$err) {
		echo '<h4>Error logs are currently empty</h4><br />';
	}

	require($WWW_ROOT_DISK . 'adm/admclose.html');
?>