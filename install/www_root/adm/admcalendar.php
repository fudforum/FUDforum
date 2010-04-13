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
	fud_use('calendar_adm.inc', true);
	fud_use('glob.inc', true);
	fud_use('widgets.inc', true);

	$tbl = $GLOBALS['DBHOST_TBL_PREFIX'];

	/* Export calendar as an VCal calendar file. */
	if (isset($_GET['export'])) {
		header('Content-Type: text/x-vCalendar; charset=utf-8');
		header('Content-Disposition: inline; filename=forum.vcs');
		$cal = new fud_calendar;
		echo $cal->export();
		exit;
	}

	require($WWW_ROOT_DISK . 'adm/header.php');

	// Enable or disable CALENDAR_ENABLED.
	$help_ar = read_help();
	if (isset($_POST['form_posted'])) {
		if (isset($_POST['FUD_OPT_3_CALENDAR_ENABLED'])) {
			if ($_POST['FUD_OPT_3_CALENDAR_ENABLED'] & 134217728) {
				$FUD_OPT_3 |= 134217728;
				echo successify('The forum\'s calendar was successfully enabled.<br />Visit the <a href="../'. __fud_index_name__ . '?t=cal&amp;'. __adm_rsid .'">calendar</a>.');
			} else {
				$FUD_OPT_3 &= ~134217728;
				echo successify('The forum\'s calendar was successfully disabled.');
			}
			change_global_settings(array('FUD_OPT_3' => $FUD_OPT_3));
		}
	}

	if (!empty($_POST['btn_cancel'])) {
		unset($_POST);
	}

	$edit = isset($_GET['edit']) ? (int)$_GET['edit'] : (isset($_POST['edit']) ? (int)$_POST['edit'] : '');

	if (isset($_POST['frm_submit'])) {
		$error = 0;
		$day = $_POST['cal_day'];
		if ( (int)$day < 1 || (int)$day > 31) {
			$error = 1;
			echo errorify('Invalid day specified.');
		}
		$month = $_POST['cal_month'];
		if ( $month != '*' && ((int)$month < 1 || (int)$month > 12)) {
			$error = 1;
			echo errorify('Invalid month specified.');
		}
		$year = $_POST['cal_year'];
		if ( $year != '*' && ((int)$year <= 0 || (int)$year > 3000)) {
			$error = 1;
			echo errorify('Invalid year specified.');
		}

		$descr = htmlspecialchars($_POST['cal_descr']);
		if ( empty($descr) ) {
			$error = 1;
			echo errorify('No description was specified for the event.');
		}

		$link = htmlspecialchars($_POST['cal_link']);

		if ($edit && !$error) {
			$cal = new fud_calendar;
			$cal->sync($edit);
			$edit = '';	
			echo successify('Event was successfully updated.');
		} else if (!$error) {
			$cal = new fud_calendar;
			$cal->add();
			echo successify('Event was successfully added.');
		}
	}

	/* Remove a calendar event. */
	if (isset($_GET['del'])) {
		$cal = new fud_calendar();
		$cal->delete($_GET['del']);
		echo successify('Event was successfully deleted.');
	}

	/* Set defaults. */
	if ($edit && ($c = db_arr_assoc('SELECT * FROM '.$tbl.'calendar WHERE id='.$edit))) {
		foreach ($c as $k => $v) {
			${'cal_'.$k} = $v;
		}
	} else {
		$c = get_class_vars('fud_calendar');
		foreach ($c as $k => $v) {
			${'cal_'.$k} = '';
		}
		$cal_year = (int)date('Y');
		$cal_month = (int)date('m');
		$cal_day = (int)date('d');
	}
?>
<h2>Calendar Manager</h2>
<form method="post" action="admcalendar.php" autocomplete="off">
<?php echo _hs ?>
<table class="datatable solidtable">
<?php
	print_bit_field('Calendar Enabled', 'CALENDAR_ENABLED');
?>
<tr class="fieldaction"><td colspan="2" align="right"><input type="submit" name="btn_submit" value="Set" /></td></tr>
</table>
<input type="hidden" name="form_posted" value="1" />
</form>

<?php
echo '<h3>'. ($edit ? '<a name="edit">Edit Event:</a>' : 'Add New Event:') .'</h3>';
?>
<form method="post" id="frm_forum" action="admcalendar.php">
<?php echo _hs; ?>
<table class="datatable">
	<tr class="field">
		<td>Year:<br /><font size="-2">Enter '*' for every year (recurring event).</font></td>
		<td><input type="text" name="cal_year" value="<?php echo $cal_year; ?>" /></td>
	</tr>

	<tr class="field">
		<td>Month:<br /><font size="-2">Enter '*' for every month (recurring event).</font></td>
		<td><input type="text" name="cal_month" value="<?php echo $cal_month; ?>" /></td>
	</tr>

	<tr class="field">
		<td>Day:<br /><font size="-2"></font></td>
		<td><input type="text" name="cal_day" value="<?php echo $cal_day; ?>" /></td>
	</tr>

	<tr class="field">
		<td>Description:<br /><font size="-2">Description to appear in calendar.</font></td>
		<td><textarea name="cal_descr" cols="40" rows="2"><?php echo $cal_descr; ?></textarea></td>
	</tr>

	<tr class="field">
		<td>Link to:<br /><font size="-2">URL to more info about the event (optional).</font></td>
		<td><input type="text" name="cal_link" value="<?php echo $cal_link; ?>" size="40" /></td>
	</tr>

	<tr class="fieldaction">
		<td colspan="2" align="right">
<?php
	if ($edit) {
		echo '<input type="hidden" name="edit" value="'.$edit.'" />';
		echo '<input type="submit" value="Cancel" name="btn_cancel" /> ';
	}
?>
			<input type="submit" value="<?php echo ($edit ? 'Update Event' : 'Add Event'); ?>" name="frm_submit" />
		</td>
	</tr>
</table>
</form>

<h3>Defined events:</h3>
<table class="resulttable fulltable">
<thead><tr class="resulttopic">
	<th>Year</th><th>Month</th><th>Day</th><th>Description</th><th>Link</th><th>Action</th>
</tr></thead>
<?php
	$i = 0;
	$c = uq('SELECT id, year, month, day, descr, link FROM '.$tbl.'calendar LIMIT 100');
	while ($r = db_rowarr($c)) {
		$i++;
		$bgcolor = ($edit == $r[0]) ? ' class="resultrow3"' : (($i%2) ? ' class="resultrow1"' : ' class="resultrow2"');
		echo '<tr'.$bgcolor.'><td>'.$r[1].'</td><td>'.$r[2].'</td><td>'.$r[3].'</td><td>'.$r[4].'</td><td>'.$r[5].'</td><td><a href="admcalendar.php?edit='.$r[0].'&amp;'.__adm_rsid.'#edit">Edit</a> | <a href="admcalendar.php?del='.$r[0].'&amp;'.__adm_rsid.'">Delete</a></td></tr>';
	}
	unset($c);
	if (!$i) {
		echo '<tr class="field"><td colspan="6"><center>No calender events found. Define some above.</center></td></tr>';
	}
?>
</table>
<?php if ($GLOBALS['FUD_OPT_3'] & 134217728) { /* CALENDAR_ENABLED */ ?>
	[ <a href="../<?php echo __fud_index_name__;?>?t=cal&amp;>?php echo __adm_rsid; ?>">View calendar</a> ]
<?php } ?>
[ <a href="admcalendar.php?export=all&amp;<?php echo __adm_rsid; ?>">Export as vCal file</a> ]

<?php require($WWW_ROOT_DISK . 'adm/footer.php'); ?>
