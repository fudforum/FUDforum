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
	fud_use('glob.inc', true);
	fud_use('widgets.inc', true);
	fud_use('cron_adm.inc', true);

	if (!empty($_POST['btn_cancel'])) {
		unset($_POST);
	}

	$edit = isset($_GET['edit']) ? (int)$_GET['edit'] : (isset($_POST['edit']) ? (int)$_POST['edit'] : '');

	$help_ar = read_help();
	require($WWW_ROOT_DISK .'adm/header.php');

	$tbl  = $GLOBALS['DBHOST_TBL_PREFIX'];
	$path = $GLOBALS['DATA_DIR'].'scripts/';
	$php  = escapeshellcmd($GLOBALS['PHP_CLI']);

	// Job settings.
	if (!empty($_POST['btn_submit'])) {
		$php = str_replace('\\', '/', $_POST['CF_PHP_CLI']);
		if (!is_executable($php)) {
			echo errorify('PHP executable path is invalid.');
		} else {
			change_global_settings(array('PHP_CLI' => $php));
			$GLOBALS['PHP_CLI'] = $php;
			echo successify('PHP executable path successfully set.');
		}
	}

	// Add or edit a cron entry.
	if (isset($_POST['cron_submit']) && !empty($_POST['cron_def'])) {
		list($_POST['cron_name'], $_POST['cron_cmd']) = preg_split('/::/', $_POST['cron_def']);

		$cron = new fud_cron;
		if ($edit) {
			$cron->sync($edit);
			$edit = '';
			echo successify('Job schedule successfully updated.');
		} else {
			$cron->add();
			echo successify('Job schedule successfully added.');
		}
	}

	/* Remove a cron entry. */
	if (isset($_GET['del'])) {
		$cal = new fud_cron();
		$cal->delete($_GET['del']);
		echo successify('Schedule was successfully deleted.');
	}

	// Submit job to run in background.
	if (!empty($_GET['run'])) {
		$job    = (int) $_GET['run'];
		$cmd    = q_singleval('SELECT cmd FROM '. $tbl .'cron WHERE id='. $job);
		$script = escapeshellcmd($cmd);
		$output = ' > '. preg_replace('/\s+/', '_', $cmd) .'_'. $job .'.log';

		if (empty($php)) {
			echo errorify('ERROR: Please enter the PHP CLI executable below.');
		} elseif (!file_exists($php)) {
			echo errorify('ERROR: Command line PHP executable not found: '. $php .'.');
		//} elseif (!file_exists($path.$script)) {
		//	echo errorify('ERROR: Script not found: '. $path.$script .'.');
		} else {
			chdir($path) or die('ERROR: Unable to change to scripts directory '. $path);

			if (strncasecmp('win', PHP_OS, 3)) {	// Not Windows.
				// exec($php .' '. escapeshellarg($script) .' '. $job . $output . ' 2>&1 &');
				pclose(popen($php .' ./'. escapeshellarg($script) .' '. $job . $output . ' 2>&1 &', 'r'));
			} else {
				pclose(popen('start "FUDjob" /LOW /B "'. $php .'" '. escapeshellarg($script) .' '. $job . $output, 'r'));
			}
			echo successify('Job was submitted to run in background.');
		}
	}

	/* Set defaults. */
//	$jobs = "Generate sitemap\nBackup forum";
//	$defs = "Generate sitemap::sitemap.php\nBackup forum::admdump.php";
	$jobs = "Generate sitemap\nLookup latest version";
	$defs = "Generate sitemap::sitemap.php\nLookup latest version::vercheck.php";
	$c = uq('SELECT id, name, \'xmlagg\' FROM '. $tbl .'xmlagg UNION 
			 SELECT id, name, \'maillist\' FROM '. $tbl .'mlist WHERE mbox_server IS NOT NULL UNION 
			 SELECT id, newsgroup, \'nntp\' FROM '. $tbl .'nntp');
	while ($r = db_rowarr($c)) {
		$jobs .= "\n". $r[1] .' ('. $r[2] .')';
		$defs .= "\n". $r[1] .'::'. $r[2] .'.php '. $r[0];
	}
	if ($edit && ($c = db_arr_assoc('SELECT * FROM '.$tbl.'cron WHERE id='.$edit))) {
		foreach ($c as $k => $v) {
			${'cron_'.$k} = $v;
		}
		$cron_def = $cron_name .'::'. $cron_cmd;
	} else {
		$c = get_class_vars('fud_cron');
		foreach ($c as $k => $v) {
			${'cron_'.$k} = '';
		}
		$cron_def = '';
		$cron_minute = $cron_hour = $cron_dom = $cron_month = $cron_dow = '*';
	}
?>

<h2>Job Administration System</h2>
<div class="tutor">
	The Job Administration System can be used to run ad hoc import scripts (to load <a href="admmlist.php?<?php echo __adm_rsid; ?>">Mailing list messages</a>, <a href="admnntp.php?<?php echo __adm_rsid; ?>">USENET posts</a> or <a href="admxmlagg.php?<?php echo __adm_rsid; ?>">XML Feeds</a>) and view their output log files.
	These scripts are stored in <a href="admbrowse.php?cur=<?php echo urlencode($path).'&amp;'.__adm_rsid ?>"><?php echo realpath($path); ?></a>.
</div>
<h3>Job settings:</h3>
<form method="post" action="admbatch.php">
<?php echo _hs; ?>
<table class="datatable solidtable">
<?php
	print_reg_field('PHP CLI Executable', 'PHP_CLI');
?>
<tr class="fieldaction"><td colspan="2" align="right"><input type="submit" name="btn_submit" value="Set" /></td></tr>
</table>
</form>

<?php
	echo '<h3>'. ($edit ? '<a name="edit">Edit Job Schedule:</a>' : 'Add Job Schedule:') .'</h3>';
?>
Notes: * means EVERY.
<form method="post" action="admbatch.php">
<?php echo _hs; ?>
<table class="datatable">
	<tr class="field">
		<td>Job:</td>
		<td><?php draw_select('cron_def', $jobs, $defs, $cron_def); ?></td>
	</tr>
	<tr class="field">
		<td>Minute (0 - 59):</td>
		<td><input type="text" name="cron_minute" value="<?php echo $cron_minute; ?>" maxlength="50" /></td>
	</tr>
	<tr class="field">
		<td>Hour (0 - 23):</td>
		<td><input type="text" name="cron_hour" value="<?php echo $cron_hour; ?>" maxlength="50" /></td>
	</tr>
	<tr class="field">
		<td>Day of month (1 - 31):</td>
		<td><input type="text" name="cron_dom" value="<?php echo $cron_dom; ?>" maxlength="50" /></td>
	</tr>
	<tr class="field">
		<td>Month (1 - 12):</td>
		<td><input type="text" name="cron_month" value="<?php echo $cron_month; ?>" maxlength="50" /></td>
	</tr>
	<tr class="field">
		<td>Day of week (0 - 7, Sunday=0 or 7):</td>
		<td><input type="text" name="cron_dow" value="<?php echo $cron_dow; ?>" maxlength="50" /></td>
	</tr>

	<tr class="fieldaction">
		<td colspan="2" align="right">
<?php
	if ($edit) {
		echo '<input type="hidden" value="'.$edit.'" name="edit" />';
		echo '<input type="submit" name="btn_cancel" value="Cancel" />&nbsp;';
	}
?>
			<input type="submit" value="<?php echo ($edit ? 'Update Schedule' : 'Add Schedule'); ?>" name="cron_submit" />
		</td>
	</tr>
</table>
</form>

<h3>Scheduled jobs:</h3>
<table class="resulttable fulltable">
<thead><tr class="resulttopic">
	<th nowrap="nowrap">Job Name</th>
	<th>Schedule</th>
	<th>Exec Line</th>
	<th align="center">Action</th>
</tr></thead>
<?php
	$c = uq('SELECT * FROM '. $tbl .'cron');
	$i = 0;
	while ($r = db_rowarr($c)) {
		$i++;
		$bgcolor = ($edit == $r[0]) ? ' class="resultrow3"' : (($i%2) ? ' class="resultrow1"' : ' class="resultrow2"');
		echo '<tr'.$bgcolor.'><td>'.htmlspecialchars($r[1]).'</td>
			<td nowrap="nowrap">'.$r[2].' '.$r[3].' '.$r[4].' '.$r[5].' '.$r[6].' </font></td>
			<td nowrap="nowrap">'.$r[7].' </font></td>
			<td><small>
				[<a href="admbatch.php?edit='.$r[0].'&amp;'.__adm_rsid.'#edit">Edit</a>]
				[<a href="admbatch.php?del='.$r[0].'&amp;'.__adm_rsid.'">Delete</a>]
				[<a href="admbatch.php?run='.$r[0].'&amp;'.__adm_rsid.'">Run now!</a>]
				[<a href="admbatch.php?log='.$r[0].'&amp;'.__adm_rsid.'#output">View Log</a>]
			</small></td></tr>';
	}
	unset($c);
	if (!$i) {
		echo '<tr class="field"><td colspan="4" align="center">No jobs defined.</td></tr>';
	}
?>
</table>

<?php 
	// View a job's output log.
	if (!empty($_GET['log'])) {
		echo '<h3><a name="output">Job output (last run)</a></h3>';
		$job    = (int) $_GET['log'];
		$cmd    = q_singleval('SELECT cmd FROM '. $tbl .'cron WHERE id='. $job);
		$output = $path . preg_replace('/\s+/', '_', $cmd) .'_'. $job .'.log';
		if (file_exists($output)) {
			echo 'Job log: <i>'. $output .'</i><br />';
			echo 'Last updated: <i>'. date('d M Y H:i', filemtime($output)) .'</i>';
			echo '<pre><code>';
			$fh = @fopen($output, 'r');
			do {
				echo(fgets($fh));
			} while (!feof($fh));
			fclose($fh);
			echo '</code></pre>';
		} else {
			 echo(errorify('No previous job log found.<br />The job haven\'t executed or haven\'t produced any output.'));
		}
	}

	require($WWW_ROOT_DISK .'adm/footer.php'); 
?>
