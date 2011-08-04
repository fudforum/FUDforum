<?php
/**
* copyright            : (C) 2001-2011 Advanced Internet Designs Inc.
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
	fud_use('job.inc', true);

	if (!empty($_POST['btn_cancel'])) {
		unset($_POST);
	}

	$edit = isset($_GET['edit']) ? (int)$_GET['edit'] : (isset($_POST['edit']) ? (int)$_POST['edit'] : '');

	$help_ar = read_help();
	require($WWW_ROOT_DISK .'adm/header.php');

	$tbl  = $GLOBALS['DBHOST_TBL_PREFIX'];
	$path = $GLOBALS['DATA_DIR'] .'scripts/';
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

	// Add or edit a job entry.
	if (isset($_POST['job_submit']) && !empty($_POST['job_def'])) {
		list($_POST['job_name'], $_POST['job_cmd']) = preg_split('/::/', $_POST['job_def']);

		$job = new fud_job;
		if ($edit) {
			$job->sync($edit);
			$edit = '';
			echo successify('Job schedule was successfully updated.');
		} else {
			$job->add();
			echo successify('Job schedule was successfully added.');
		}
	}

	/* Remove a job entry. */
	if (isset($_GET['del'])) {
		$job = new fud_job();
		$job->delete($_GET['del']);
		echo successify('Job was successfully unscheduled.');
	}

	/* Submit job to run in background. */
	if (!empty($_GET['run'])) {
		$job = new fud_job();
		try {
			$job->submit((int) $_GET['run']);
			echo successify('Job was submitted to run in background.');
		} catch (Exception $e) {
			pf(errorify('Unable to submit: '. $e->getMessage()));
		}
	}

	/* Set defaults. */
	$jobs = "Backup forum\nCheck for new forum versions\nCheck forum consistency\nGenerate sitemap\nOptimize database tables";
	$defs = "Backup forum::acp.php backup\nCheck for new forum versions::vercheck.php\nCheck forum consistency::acp.php consist\nGenerate sitemap::sitemap.php\nOptimize database tables::acp.php dbcheck";
	$c = uq('SELECT id, name, \'xmlagg\' FROM '. $tbl .'xmlagg UNION 
			 SELECT id, name, \'maillist\' FROM '. $tbl .'mlist WHERE mbox_server != \'\' AND mbox_server IS NOT NULL UNION 
			 SELECT id, newsgroup, \'nntp\' FROM '. $tbl .'nntp');
	while ($r = db_rowarr($c)) {
		$jobs .= "\n". $r[1] .' ('. $r[2] .' import)';
		$defs .= "\n". $r[1] .'::'. $r[2] .'.php '. $r[0];
	}
	if ($edit && ($c = db_arr_assoc('SELECT * FROM '. $tbl .'jobs WHERE id='. $edit))) {
		foreach ($c as $k => $v) {
			${'job_'. $k} = $v;
		}
		$job_def = $job_name .'::'. $job_cmd;
	} else {
		$c = get_class_vars('fud_job');
		foreach ($c as $k => $v) {
			${'job_'. $k} = '';
		}
		$job_def = '';
		$job_start_minute = $job_start_hour = $job_start_dom = $job_start_month = $job_start_dow = '*';
	}
?>

<h2>Job Administration System</h2>
<div class="tutor">
	The Job Administration System can be used to schedule tasks or run ad hoc scripts (for example, to load <a href="admmlist.php?<?php echo __adm_rsid; ?>">Mailing list messages</a>, <a href="admnntp.php?<?php echo __adm_rsid; ?>">USENET posts</a> or <a href="admxmlagg.php?<?php echo __adm_rsid; ?>">XML Feeds</a>) and view their output log files.
	These scripts are stored in <a href="admbrowse.php?cur=<?php echo urlencode($path) .'&amp;'. __adm_rsid ?>"><?php echo realpath($path); ?></a>.
</div>
<h3>Job settings:</h3>
<form method="post" action="admjobs.php">
<?php echo _hs; ?>
<table class="datatable solidtable">
<?php
	print_reg_field('PHP CLI Executable', 'PHP_CLI');
?>
<tr class="field"><td>Last cron run:<br /><font size="-1">Last time cron.php was executed.</font></td><td>
<?php
        $jobfile = $GLOBALS['ERROR_PATH'] .'LAST_CRON_RUN';
        if (file_exists($jobfile)) {
		$last = filemtime($jobfile);
		if ($last < __request_timestamp__ - (24*60*60)) {	// Longer than 1 day ago?
			echo errorify(fdate($last));
		} else {
			echo successify(fdate($last));
		}
	} else {
		echo errorify('Never! Please schedule <i>cron.php</i> to run periodic jobs.');
	}
?></td></tr>
<tr class="fieldaction"><td colspan="2" align="right"><input type="submit" name="btn_submit" value="Set" /></td></tr>
</table>
</form>

<?php
	echo '<h3>'. ($edit ? '<a name="edit">Edit Job Schedule:</a>' : 'Add Job Schedule:') .'</h3>';
?>
<b>Note:</b> * means EVERY.
<form method="post" action="admjobs.php">
<?php echo _hs; ?>
<table class="datatable">
	<tr class="field">
		<td>Job:</td>
		<td><?php draw_select('job_def', $jobs, $defs, $job_def); ?></td>
	</tr>
	<tr class="field">
		<td>Minute (0 - 59):</td>
		<td><input type="text" name="job_start_minute" value="<?php echo $job_start_minute; ?>" maxlength="50" /></td>
	</tr>
	<tr class="field">
		<td>Hour (0 - 23):</td>
		<td><input type="text" name="job_start_hour" value="<?php echo $job_start_hour; ?>" maxlength="50" /></td>
	</tr>
	<tr class="field">
		<td>Day of month (1 - 31):</td>
		<td><input type="text" name="job_start_dom" value="<?php echo $job_start_dom; ?>" maxlength="50" /></td>
	</tr>
	<tr class="field">
		<td>Month (1 - 12):</td>
		<td><input type="text" name="job_start_month" value="<?php echo $job_start_month; ?>" maxlength="50" /></td>
	</tr>
	<tr class="field">
		<td>Day of week (0 - 7, Sunday=0 or 7):</td>
		<td><input type="text" name="job_start_dow" value="<?php echo $job_start_dow; ?>" maxlength="50" /></td>
	</tr>
	<tr class="field">
		<td>Status:</td>
		<td><?php draw_select('job_job_opt', "Enabled\nDisabled", "0\n1", $job_job_opt); ?></td>
	</tr>

	<tr class="fieldaction">
		<td colspan="2" align="right">
<?php
	if ($edit) {
		echo '<input type="hidden" value="'. $edit .'" name="edit" />';
		echo '<input type="submit" name="btn_cancel" value="Cancel" />&nbsp;';
	}
?>
			<input type="submit" value="<?php echo ($edit ? 'Update Job' : 'Add Job'); ?>" name="job_submit" />
		</td>
	</tr>
</table>
</form>

<h3><a name="list">Scheduled jobs:</a></h3>
<table class="resulttable fulltable">
<thead><tr class="resulttopic">
	<th nowrap="nowrap">Job Name</th>
	<th>Last run</th>
	<th>Next run</th>
	<th>Running?</th>
	<th align="center">Action</th>
</tr></thead>
<?php
	$c = uq('SELECT id, name, lastrun, nextrun, locked, job_opt FROM '. $tbl .'jobs');
	$i = 0;
	while ($r = db_rowobj($c)) {
		$i++;
		$bgcolor = ($edit == $r->id) ? ' class="resultrow3"' : (($i%2) ? ' class="resultrow1"' : ' class="resultrow2"');

		$running = 0;
		if ((!empty($_GET['run']) && $r->id == $_GET['run']) ||			// Just submitted.
		    ($r->locked != 0 && $r->locked > __request_timestamp__ - 600)) {	// Locked less than 10min ago.
			$running = 1;
		}

		echo '<tr'. $bgcolor .'><td>'. htmlspecialchars($r->name) .'</td>
			<td nowrap="nowrap">'. ($r->lastrun ? fdate($r->lastrun, 'd M Y H:i') : 'Never') .'</td>
			<td nowrap="nowrap">'. ( ($r->job_opt &1) ? 'Disabled' : ($r->nextrun ? fdate($r->nextrun, 'd M Y H:i') : 'n/a')) .'</td>
			<td nowrap="nowrap">'. ($r->locked  ? 'Yes ('. (__request_timestamp__ - $r->locked) .' sec)' : 'No') .'</td>
			<td><small>
				[<a href="admjobs.php?edit='. $r->id .'&amp;'. __adm_rsid .'#edit">Edit</a>]
				[<a href="admjobs.php?del='.  $r->id .'&amp;'. __adm_rsid .'">Delete</a>] &nbsp;|&nbsp; '.
				(($running || empty($GLOBALS['PHP_CLI'])) ? '' : '[<a href="admjobs.php?run='. $r->id .'&amp;'. __adm_rsid .'#list">Run now!</a>]') .'
				[<a href="admjobs.php?log='.  $r->id .'&amp;'. __adm_rsid .'&amp;rand='. rand() .'#output">View Log</a>]
			</small></td></tr>';
	}
	unset($c);
	if (!$i) {
		echo '<tr class="field"><td colspan="5" align="center">No jobs defined.</td></tr>';
	}
?>
</table>

<?php
	// View a job's output log.
	if (!empty($_GET['log'])) {
		echo '<h3><a name="output">Job output (last run)</a></h3>';
		$job    = (int) $_GET['log'];
		list($cmd, $locked) = db_saq('SELECT cmd, locked FROM '. $tbl .'jobs WHERE id='. $job);
		if (preg_match('/(.*)\s+(.*)/', $cmd, $m) && isset($m[1])) {
			$script = escapeshellcmd($m[1]);
		} else {
			$script = escapeshellcmd($cmd);
		}
		if ($locked) {
			echo(errorify('The job is still running. Output may be old or incomplete.'));
		}
		$output = $path . $script .'_'. $job .'.log';
		if (file_exists($output)) {
			echo 'Job log: <i>'. $output .'</i><br />';
			echo '<pre style="white-space: pre-wrap;"><code>';
			$fh = @fopen($output, 'r');
			do {
				echo(fgets($fh));
			} while (!feof($fh));
			fclose($fh);
			echo '</code></pre>';
		} else {
			 echo(errorify('Job log not found.<br />The job haven\'t executed or haven\'t produced any output.'));
		}
	}

	require($WWW_ROOT_DISK .'adm/footer.php'); 
?>
