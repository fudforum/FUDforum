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

function print_last($logfile)
{
	echo '<table class="resulttable">';
	echo '<thead><tr class="resulttopic">';
	echo '	<th>Time</th><th>Error Description</th>';
	echo '</tr></thead>';

	$fsize = filesize($GLOBALS['ERROR_PATH'] . $logfile);
	$fseek = ($fsize > 2048) ? $fsize -= 2048 : 0;
	$fp = fopen($GLOBALS['ERROR_PATH'] . $logfile, 'r');
	fseek($fp, $fseek);
	$last = fread($fp, 2048);		// Read last 2K.
	fclose($fp);

	$records = preg_split("/\n(?=\?)/", $last);	// Newline + lookahead for a '?'.
	if ($fseek) {
		array_shift($records);	// Throw first incomplete record away.
	}
	$records = array_slice($records, -5);	// Only keep last 5 records (newest errors).

	foreach(array_reverse($records, true) as $record) {
		list(,$s,$d,$err) = explode('?', $record);
		echo '<tr class="field"><td nowrap="nowrap" valign="top">'. gmdate('D M j G:i:s T Y', $d) .'</td><td>'. $err .'</td></tr>';
	}

	echo '</table><br />';
}

function print_log($logfile, $search)
{
	echo '<table class="resulttable">';
	echo '<thead><tr class="resulttopic">';
	echo '	<th>Time</th><th>Error Description</th>';
	echo '</tr></thead>';
	$linecnt = 0;
	$fp = fopen($GLOBALS['ERROR_PATH'] . $logfile, 'r');
	while (1) {
		// ?%-10d?%-10d?
		if (!($pfx = fread($fp, 23))) {
			break;
		}
		list(,$s,$d,) = explode('?', $pfx);
		$err = fread($fp, (int)$s);
		if ($search && stripos($err, $search) === false) {	
			continue;	// Filter according to search criteria.
		}
		echo '<tr class="field"><td nowrap="nowrap" valign="top">'. gmdate('D M j G:i:s T Y', $d) .'</td><td>'. $err .'</td></tr>';
		$linecnt++;
	}
	fclose($fp);
	echo '</table><br />';
	echo '<i>Total: '. $linecnt .' errors.</i>';
}

/* main */
	require('./GLOBALS.php');
	fud_use('adm.inc', true);
	require($WWW_ROOT_DISK .'adm/header.php');

	// Check for errors in the following error logs. 
	$logs = array(
		'fud_errors'   => 'Forum',
		'sql_errors'   => 'SQL', 
		'nntp_errors'  => 'Newsgroup', 
		'mlist_errors' => 'Mailing List'
	);

	/* Empty out log file. */
	if (isset($_GET['clear'], $_GET['log'])) {
		$logfile = $ERROR_PATH . $_GET['log'];
		if (is_file($logfile)) {
			if (@unlink($logfile)) {
				echo successify( strtoupper($_GET['log']) .' log successfully cleared.');
			} else {
				echo errorify('Unable to remove '. $_GET['log'] .' log. Please fix permissions of '. $logfile);
			}
		}
	}

	// Identify logs that must be displayed.
	$display_logs = Array();
	if (!empty($_GET['showall'])) {
		$display_logs[] = $_GET['log'];	// Display only requested log.
	} else {
		foreach($logs as $log => $desc) {
			if (@file_exists($ERROR_PATH . $log) && filesize($ERROR_PATH . $log)) {
				$display_logs[] = $log;
			}
		}
	} 
?>

<h2>Error Log Viewer</h2>

<?php 
global $plugin_hooks;
if (isset($plugin_hooks['LOGERR'])) {
	echo '<div class="alert">You have one or more LOGERR plugins enabled. Messages my be altered, suppressed or logged elsewhere.</div>';
}

if ($display_logs) { ?>
<table width="95%" class="tutor"><tr><td>
<form method="post" action="admerr.php">
	<?php echo _hs; ?>
	Search for:
	<?php $search = isset($_POST['search']) ? $_POST['search'] : ''; ?>
	<input type="text" name="search" value="<?php echo $search; ?>" />
	<input type="submit" value="Go" name="frm_submit" />
</form>
</td><td align="right">
	<?php if (count($display_logs) > 1) { ?>
		Jump to log:
		<?php foreach($display_logs as $log) echo ' [ <a href="#'. $log .'">'. $logs[$log] .'</a> ]'; ?>
	<?php } ?>
</td></tr></table>
<?php } ?>

<?php
	foreach($display_logs as $log) {
		if ($search) {
			echo '<h3><a name="'. $log .'">Matching '. $logs[$log] .' Errors</a></h3>';
			print_log($log, $search);
			echo '<div align="right">[ <a href="admerr.php?'. __adm_rsid .'">Go back</a> ] ';
			echo '[ <a href="admerr.php?clear=1&amp;log='. $log .'&amp;'. __adm_rsid .'">clear log</a> ]</div>';
		} else 	if (isset($_GET['showall'])) {
			echo '<h3><a name="'. $log .'">Full '. $logs[$log] .' Error Log</a></h3>';
			print_log($log, $search);
			echo '<div align="right">[ <a href="admerr.php?'. __adm_rsid .'">go back</a> ] ';
			echo '[ <a href="admerr.php?clear=1&amp;log='. $log .'&amp;'. __adm_rsid .'">clear log</a> ]</div>';
		} else {
			echo '<h3><a name="'. $log .'">Latest '. $logs[$log] .' Errors</a></h3>';
			print_last($log);
			echo '<div align="right">[ <a href="admerr.php?showall=1&amp;log='. $log .'&amp;'. __adm_rsid .'">show all</a> ] ';
			echo '[ <a href="admerr.php?clear=1&amp;log='. $log .'&amp;'. __adm_rsid .'">clear log</a> ]</div>';
		}
	}

	if (!$display_logs) {
		echo '<p>All error logs are empty. Lucky you!</p>';
	}

	require($WWW_ROOT_DISK .'adm/footer.php');
?>
