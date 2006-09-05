<?php
/**
* copyright            : (C) 2001-2006 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: admerr.php,v 1.22 2006/09/05 12:58:00 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; either version 2 of the License.
**/

	require('./GLOBALS.php');
	fud_use('adm.inc', true);

	if (!empty($_GET['clear_sql_log'])) {
		@unlink($ERROR_PATH.'sql_errors');
	} else if (!empty($_GET['clear_fud_log'])) {
		@unlink($ERROR_PATH.'fud_errors');
	}

	require($WWW_ROOT_DISK . 'adm/admpanel.php');
?>
<h2>Error Log Browser</h2>

<?php
	$err = 0;

function print_log($path)
{
	echo '<table class="resulttable"><tr class="resulttopic"><td>Time</td><td>Error Description</td></tr>';
	$fp = fopen($path, 'r');
	while (1) {
		// ?%-10d?%-10d?
		if (!($pfx = fread($fp, 23))) {
			break;
		}
		if ($pfx{0} != '?') { // for old log entries
			continue;
		}
		list(,$s,$d,) = explode('?', $pfx);
		echo '<tr class="field"><td nowrap valign="top">'.gmdate('D M j G:i:s T Y', $d).'</td><td>'.fread($fp, $s).'</td></tr>';
	}
	fclose($fp);
	echo '</table><br /><br />';
}

	if (@file_exists($ERROR_PATH.'fud_errors') && filesize($ERROR_PATH.'fud_errors')) {
		echo '<h4>FUDforum Error Log [<a href="admerr.php?clear_fud_log=1&'.__adm_rsidl.'">clear log</a>]</h4>';
		print_log($ERROR_PATH.'fud_errors');
		$err = 1;
	}

	if (@file_exists($ERROR_PATH.'sql_errors') && filesize($ERROR_PATH.'sql_errors')) {
		echo '<h4>SQL Error Log [<a href="admerr.php?clear_sql_log=1&'.__adm_rsidl.'">clear log</a>]</h4>';
		print_log($ERROR_PATH.'sql_errors');
		$err = 1;
	}

	if (!$err) {
		echo '<h4>Error logs are currently empty</h4><br />';
	}

	require($WWW_ROOT_DISK . 'adm/admclose.html');
?>
