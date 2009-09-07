<?php
/**
* copyright            : (C) 2001-2009 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: admbatch.php,v 1.1 2009/09/07 15:49:52 frank Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; version 2 of the License.
**/

	require('./GLOBALS.php');
	fud_use('adm.inc', true);
	fud_use('glob.inc', true);
	fud_use('widgets.inc', true);

	$help_ar = read_help();
	require($WWW_ROOT_DISK . 'adm/admpanel.php');

	$tbl  = $GLOBALS['DBHOST_TBL_PREFIX'];
	$path = $GLOBALS['DATA_DIR'].'scripts/';
	$php  = $GLOBALS['PHP_CLI'];

	if (!empty($_POST['btn_submit'])) {
		change_global_settings(array('PHP_CLI' => $_POST['CF_PHP_CLI']));
		$GLOBALS['PHP_CLI'] = $_POST['CF_PHP_CLI'];
		echo '<font color="green">PHP executable path successfully set.</font>';
	}

	// Submit job to run in background
	if (!empty($_GET['job']) && !empty($_GET['script'])) {
		$job    = (int) $_GET['job'];
		$script = $_GET['script'] .'.php';
		$output = ' > '. $_GET['script'] .'_'. $job .'.log';

	    if (!file_exists($php)) {
		echo '<font color="red">ERROR: Command line PHP executable not found: '. $php .'</font>';
		} elseif (!file_exists($path.$script)) {
			echo '<font color="red">ERROR: Script not found: '. $script .'</font>';
		} else {
			chdir($path) or die('ERROR: Unable to change to scripts directory '. $path);

			if (strncasecmp('win', PHP_OS, 3)) {	// not Windows
				// exec($php .' '. escapeshellarg($script) .' '. $job . $output . ' 2>&1 &');
				pclose(popen($php .' ./'. escapeshellarg($script) .' '. $job . $output . ' 2>&1 &', 'r'));
			} else {
				pclose(popen('start "FUDjob" /LOW /B "'. $php .'" '. escapeshellarg($script) .' '. $job . $output, 'r'));
			}
			echo '<font color="green">Job was submitted to run in background.</font>';
		}
	}
?>

<h2>Job Administration System</h2>
<div class="tutor">The Job Administration System can be used to run import scripts ad hoc (to load <a href="admmlist.php?<?php echo __adm_rsid; ?>">Mailing list messages</a>, <a href="admnntp.php?<?php echo __adm_rsid; ?>">USENET posts</a> or <a href="admxmlagg.php?<?php echo __adm_rsid; ?>">XML Feeds</a>) and view their output log files. These scripts are stored in <?php echo realpath($path); ?>.</div>
<h3>Job settings:</h3>
<form method="post" action="admbatch.php">
<?php echo _hs; ?>
<table class="datatable solidtable">
<?php
	print_reg_field('PHP CLI Executable', 'PHP_CLI');
?>
<tr class="fieldaction"><td colspan="2" align="left"><input type="submit" name="btn_submit" value="Set" /></td></tr>
</table>
</form>

<h3>Defined jobs:</h3>
<table class="resulttable fulltable">
	<tr class="resulttopic">
		<td nowrap="nowrap">Job Name</td>
		<td>Exec Line</td>
		<td align="center">Action</td>
	</tr>
<?php
	$c = uq('SELECT id, name, \'xmlagg\' FROM '.$tbl.'xmlagg UNION select id, name, \'maillist\' FROM '.$tbl.'mlist UNION select id, newsgroup, \'nntp\' FROM '.$tbl.'nntp');
	$i = 1;
	while ($r = db_rowarr($c)) {
		if ($edit == $r[0]) {
			$bgcolor = ' class="resultrow1"';
		} else {
			$bgcolor = ($i++%2) ? ' class="resultrow2"' : ' class="resultrow1"';
		}
		echo '<tr'.$bgcolor.'><td>'.htmlspecialchars($r[1]).'</td>
			<td nowrap="nowrap">'.$r[2].'.php '.$r[0].' </font></td>
			<td>[<a href="admbatch.php?script='.$r[2].'&amp;job='.$r[0].'&amp;'.__adm_rsid.'">Run now!</a>] [<a href="admbatch.php?viewlog='.$r[2].'&amp;job='.$r[0].'&amp;'.__adm_rsid.'">View Log</a>]
			</td></tr>';
	}
	unset($c);
?>
</table>

<?php 
	// View a job's output log
	if (!empty($_GET['job']) && !empty($_GET['viewlog'])) {
		echo '<h3>Job output (last run)</h3>';
		$output = $path . $_GET['viewlog'] .'_'. (int)$_GET['job'] .'.log';
		echo '<p>Output of file <i>'. $output .'</i>:</p>';
		if (file_exists($output)) {
			$fh = @fopen($output, 'r');
			do {
				echo(fgets($fh).'<br />');
			} while (!feof($fh));
			fclose($fh);
		} else {
			 echo('<font color="red">Log file not found!</font><br />');
		}
	}

	require($WWW_ROOT_DISK . 'adm/admclose.html'); 
?>
