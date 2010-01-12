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

	if (!empty($_GET['clear_sql_log'])) {
		@unlink($ERROR_PATH.'sql_errors');
		echo successify('SQL log successfully cleared.');
	} else if (!empty($_GET['clear_fud_log'])) {
		@unlink($ERROR_PATH.'fud_errors');
		echo successify('Error log successfully cleared.');
	}

	require($WWW_ROOT_DISK . 'adm/header.php');
?>
<h2>Error Log Viewer</h2>

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
		if ($pfx{0} != '?') { // for old log entries.
			continue;
		}
		list(,$s,$d,) = explode('?', $pfx);
		echo '<tr class="field"><td nowrap="nowrap" valign="top">'.gmdate('D M j G:i:s T Y', $d).'</td><td>'.fread($fp, (int)$s).'</td></tr>';
	}
	fclose($fp);
	echo '</table><br /><br />';
}

	if (@file_exists($ERROR_PATH.'fud_errors') && filesize($ERROR_PATH.'fud_errors')) {
		echo '<h4>FUDforum Error Log [ <a href="admerr.php?clear_fud_log=1&amp;'.__adm_rsid.'">clear log</a> ]</h4>';
		print_log($ERROR_PATH.'fud_errors');
		$err = 1;
	}

	if (@file_exists($ERROR_PATH.'sql_errors') && filesize($ERROR_PATH.'sql_errors')) {
		echo '<h4>SQL Error Log [ <a href="admerr.php?clear_sql_log=1&amp;'.__adm_rsid.'">clear log</a> ]</h4>';
		print_log($ERROR_PATH.'sql_errors');
		$err = 1;
	}

	if (!$err) {
		echo '<h4>All error logs are empty. Lucky you!</h4><br />';
	}

	require($WWW_ROOT_DISK . 'adm/footer.php');
?>
