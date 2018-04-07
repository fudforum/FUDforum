<?php
/**
* copyright            : (C) 2001-2018 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id$
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; version 2 of the License.
**/

function raw_date($dt)
{
	return array(substr($dt, 0, 4), substr($dt, 4, 2), substr($dt, -2));
}

function mk_date($y, $m, $d)
{
	return str_pad((int)$y, 4, '0', STR_PAD_LEFT) . str_pad((int)$m, 2, '0', STR_PAD_LEFT) . str_pad((int)$d, 2, '0', STR_PAD_LEFT);
}

/* main */
	require('./GLOBALS.php');
	fud_use('adm.inc', true);
	fud_use('widgets.inc', true);
	fud_use('announce_adm.inc', true);

	require($WWW_ROOT_DISK .'adm/header.php');
	$tbl = $GLOBALS['DBHOST_TBL_PREFIX'];

	/* Remove an announcement. */
	if (isset($_GET['del'])) {
		fud_announce::delete( $_GET['del'] );
		pf(successify('Announcement successfully deleted.'));
	}

	if (isset($_GET['edit']) && ($an_d = db_sab('SELECT * FROM '. $tbl .'announce WHERE id='. (int)$_GET['edit']))) {
		list($announce_year1, $announce_month1, $announce_day1) = raw_date($an_d->date_started);
		list($announce_year2, $announce_month2, $announce_day2) = raw_date($an_d->date_ended);
		$announce_subject = $an_d->subject;
		$announce_text = $an_d->text;
		$announce_opt = $an_d->ann_opt;
		$edit = (int)$_GET['edit'];
		$c = uq('SELECT forum_id FROM '. $tbl .'ann_forums WHERE ann_id='. (int)$_GET['edit']);
		while ($r = db_rowarr($c)) {
			$frm_list[$r[0]] = $r[0];
		}
		unset($c);
	} else {
		$edit = $announce_subject = $announce_text = '';
		$announce_opt = 0;
		list($announce_year1, $announce_month1, $announce_day1) = explode(' ', gmdate('Y m d', __request_timestamp__));
		list($announce_year2, $announce_month2, $announce_day2) = explode(' ', gmdate('Y m d', (__request_timestamp__ + 86400)));	// 86400 seconds in a day.
	}

	if (isset($_POST['btn_submit'])) {
		$ann = new fud_announce;
		$id = $ann->add();
		pf(successify('Announcement successfully added.'));
	} else if (isset($_POST['btn_update'], $_POST['edit'])) {
		$ann = new fud_announce;
		$id = (int)$_POST['edit'];
		$ann->sync($id);
		pf(successify('Announcement successfully updated.'));
	}

	if (isset($_POST['frm_list'], $id)) {
		$_POST['frm_list'] = array_unique($_POST['frm_list']);
		q('DELETE FROM '. $tbl .'ann_forums WHERE ann_id='. $id);
		foreach ($_POST['frm_list'] as $v) {
			q('INSERT INTO '. $tbl .'ann_forums (forum_id, ann_id) VALUES('. (int)$v .','. $id .')');
		}
		unset($frm_list);
	}
?>
<h2>Announcement System</h2>

<h3><?php echo $edit ? '<a name="edit">Edit Announcement:</a>' : 'Add New Announcement:'; ?></h3>
<form method="post" id="announce_frm" action="admannounce.php">
<?php echo _hs; ?>
<table class="datatable">

	<tr class="field">
		<td>Show to:<br /><font size="-2">(who can see it)</font></td>
		<td>
			<?php draw_select('announce_opt[]', "All users\nAll logged in users\nAnonymous users", "0\n2\n4", ($announce_opt & (2|4))); ?>
		</td>
	</tr>

	<tr class="field">
		<td>Show on:<br /><font size="-2">(besides the selected forums)</font></td>
		<td>
			<?php draw_select('announce_opt[]', "Selected forums only\nFront Page", "0\n1", ($announce_opt & (1))); ?>
		</td>
	</tr>

	<tr class="field">
		<td valign="top">Forums:<br /><font size="-2">(display announcement here)</font></td>
		<td><table border="0" cellspacing="1" cellpadding="2">
			<tr><td colspan="5">
				<small>
				[ <a href="#" id="none" onclick="jQuery('input:checkbox').prop('checked', false);">None</a> ]
				[ <a href="#" id="all"  onclick="jQuery('input:checkbox').prop('checked', true);" >All</a> ]
				</small>
			</td></tr>
<?php
	require $FORUM_SETTINGS_PATH .'cat_cache.inc';
	$pfx = $oldc = ''; $row = 0;
	$c = uq('SELECT f.id, f.name, c.id FROM '. $tbl .'fc_view v INNER JOIN '. $tbl .'forum f ON f.id=v.f INNER JOIN '. $tbl .'cat c ON f.cat_id=c.id ORDER BY v.id');
	while ($r = db_rowarr($c)) {
		if ($oldc != $r[2]) {
			if ($row < 6) {
				echo '<tr><td colspan="'. (6 - $row) .'"> </td></tr>';
			}
			foreach ($GLOBALS['cat_cache'] as $k => $i) {
				$pfx = str_repeat('&nbsp;&nbsp;&nbsp;', $i[0]);

				echo '<tr class="fieldtopic"><td colspan="6">'. $pfx .'<font size="-2">'. $i[1] .'</font></td></tr><tr class="field">';
				if ($k == $r[2]) {
					break;
				}
			}
			$oldc = $r[2];
			$row = 1;
		}
		if ($row >= 6) {
			$row = 2;
			echo '</tr><tr class="field">';
		} else {
			++$row;
		}
		echo '<td><label>'. ($row == 2 ? $pfx : '') . create_checkbox('frm_list['. $r[0] .']', $r[0], isset($frm_list[$r[0]])) .' <font size="-2"> '. $r[1] .'</font></label></td>';
	}
	unset($c);
?>
		</tr></table>
		</td>
	</tr>

	<tr class="field">
		<td>Starting date:</td>
		<td>
			<table border="0" cellspacing="1" cellpadding="0">
				<tr>
					<td><font size="-2">Month</font></td>
					<td><font size="-2">Day</font></td>
					<td><font size="-2">Year</font></td>
				</tr>
				<tr>
					<td><?php draw_month_select('announce_month1', 0, $announce_month1); ?></td>
					<td><?php draw_day_select('announce_day1', 0, $announce_day1); ?></td>
					<td><input type="number" name="announce_year1" value="<?php echo $announce_year1; ?>" size="5" /></td>
				</tr>
			</table>
		</td>
	</tr>

	<tr class="field">
		<td>Ending date:</td>
		<td>
			<table border="0" cellspacing="1" cellpadding="0">
				<tr align="left">
					<td><font size="-2">Month</font></td>
					<td><font size="-2">Day</font></td>
					<td><font size="-2">Year</font></td>
				</tr>
				<tr>
					<td><?php draw_month_select('announce_month2', 0, $announce_month2); ?></td>
					<td><?php draw_day_select('announce_day2', 0, $announce_day2); ?></td>
					<td><input type="number" name="announce_year2" value="<?php echo $announce_year2; ?>" size="5" /></td>
				</tr>
			</table>
			<small>All dates are in UTC, current UTC date/time is: <?php echo gmdate('r', __request_timestamp__); ?></small>
		</td>
	</tr>

	<tr class="field">
		<td>Subject:</td>
		<td><input type="text" name="announce_subject" value="<?php echo htmlspecialchars($announce_subject); ?>" size="40" /></td>
	</tr>

	<tr class="field">
		<td valign="top">Message body:<br /><font size="-2">(HTML allowed)</font></td>
		<td><textarea cols="60" rows="5" name="announce_text"><?php echo htmlspecialchars($announce_text); ?></textarea></td>
	</tr>

	<tr class="field">
		<td colspan="2" align="right">
<?php
			if ($edit) {
				echo '<input type="submit" name="btn_cancel" value="Cancel"> <input type="submit" name="btn_update" value="Update" />';
			} else {
				echo '<input type="submit" name="btn_submit" value="Add" />';
			}
?>
		</td>
	</tr>

</table>
<input type="hidden" name="edit" value="<?php echo $edit; ?>" />
</form>

<h3>Defined Announcements:</h3>
<table class="resulttable fulltable">
<thead><tr class="resulttopic">
	<th>Subject</th>
	<th>Body</th>
	<th>Starting date</th>
	<th>Ending date</th>
	<th>Action</th>
</tr></thead>
<?php
	$c = uq('SELECT * FROM '. $tbl .'announce ORDER BY date_started');
	$i = 0;
	while ($r = db_rowobj($c)) {
		$i++;
		$bgcolor = ($edit == $r->id) ? ' class="resultrow3"' : (($i%2) ? ' class="resultrow1"' : ' class="resultrow2"');

		$b = htmlspecialchars((strlen($r->text) > 25) ? substr($r->text, 0, 25) .'...' : $r->text);
		$st_dt = raw_date($r->date_started);
		$st_dt = gmdate('F j, Y', gmmktime(1, 1, 1, $st_dt[1], $st_dt[2], $st_dt[0]));
		$en_dt = raw_date($r->date_ended);
		$en_dt = gmdate('F j, Y', gmmktime(1, 1, 1, $en_dt[1], $en_dt[2], $en_dt[0]));
		echo '<tr'. $bgcolor .'><td>'. $r->subject .'</td><td>'. $b .'</td><td>'. $st_dt .'</td><td>'. $en_dt .'</td><td>[<a href="admannounce.php?edit='. $r->id .'&amp;'. __adm_rsid .'#edit">Edit</a>] [<a href="admannounce.php?del='. $r->id .'&amp;'. __adm_rsid .'">Delete</a>]</td></tr>';
	}
	unset($c);
	if (!$i) {
		echo '<tr class="field"><td colspan="5" align="center">No announcements found. Define some above.</td></tr>';
	}
?>
</table>
<?php require($WWW_ROOT_DISK .'adm/footer.php'); ?>

