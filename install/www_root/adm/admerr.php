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

function print_log($path, $search)
{
	echo '<table class="resulttable">';
	echo '<thead><tr class="resulttopic">';
	echo '	<th>Time</th><th>Error Description</th>';
	echo '</tr></thead>';
	$fp = fopen($path, 'r');
	while (1) {
		// ?%-10d?%-10d?
		if (!($pfx = fread($fp, 23))) {
			break;
		}
		if ($pfx{0} != '?') { // For old log entries.
			continue;
		}
		list(,$s,$d,) = explode('?', $pfx);
		$err = fread($fp, (int)$s);
		if ($search && stripos($err, $search) === false) {	
			continue;	// Filter according to search criteria.
		}
		echo '<tr class="field"><td nowrap="nowrap" valign="top">'. gmdate('D M j G:i:s T Y', $d) .'</td><td>'. $err .'</td></tr>';
	}
	fclose($fp);
	echo '</table><br /><br />';
}

/* main */
	require('./GLOBALS.php');
	fud_use('adm.inc', true);
	require($WWW_ROOT_DISK .'adm/header.php');
	
	if (!empty($_GET['clear_sql_log'])) {
		@unlink($ERROR_PATH .'sql_errors');
		echo successify('SQL log successfully cleared.');
	} else if (!empty($_GET['clear_fud_log'])) {
		@unlink($ERROR_PATH .'fud_errors');
		echo successify('Error log successfully cleared.');
	}

	// Identify logs that will be displayed.
	$logcnt = $fud = $sql = 0;
	if (@file_exists($ERROR_PATH .'fud_errors') && filesize($ERROR_PATH .'fud_errors')) {
		$fud = 1;
		$logcnt++;
	}
	if (@file_exists($ERROR_PATH .'sql_errors') && filesize($ERROR_PATH .'sql_errors')) {
		$sql = 1;
		$logcnt++;
	}
?>

<h2>Error Log Viewer</h2>

<?php if ($logcnt) { ?>
<table width="95%" class="tutor"><tr><td>
Jump to:
	<?php if ($fud) { ?> [ <a href="#fud">FUDforum errors</a> ] <?php } ?>
	<?php if ($sql) { ?> [ <a href="#sql">SQL errors</a> ] <?php } ?>
</td><td align="right">
<form method="post" action="admerr.php">
	<?php echo _hs; ?>
	Search for:
	<?php $search = isset($_POST['search']) ? $_POST['search'] : ''; ?>
	<input type="text" name="search" value="<?php echo $search; ?>" />
	<input type="submit" value="Go" name="frm_submit" />
</form>
</td></tr></table>

<?php } ?>

<?php
	$err = 0;

	if ($fud) {
		echo '<h3><a name="fud">FUDforum Error Log</a> [ <a href="admerr.php?clear_fud_log=1&amp;'. __adm_rsid .'">clear log</a> ]</h3>';
		print_log($ERROR_PATH .'fud_errors', $search);
		$err = 1;
	}

	if ($sql) {
		echo '<h3><a name="sql">SQL Error Log</a> [ <a href="admerr.php?clear_sql_log=1&amp;'. __adm_rsid .'">clear log</a> ]</h3>';
		print_log($ERROR_PATH .'sql_errors', $search);
		$err = 1;
	}

	if (!$err) {
		echo '<p>All error logs are empty. Lucky you!</p>';
	}

	require($WWW_ROOT_DISK .'adm/footer.php');
?>
