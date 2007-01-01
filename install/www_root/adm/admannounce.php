<?php
/**
* copyright            : (C) 2001-2006 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: admannounce.php,v 1.25 2007/01/01 17:13:23 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; version 2 of the License.
**/

	require('./GLOBALS.php');
	fud_use('adm.inc', true);
	fud_use('widgets.inc', true);

	$tbl = $GLOBALS['DBHOST_TBL_PREFIX'];

function raw_date($dt)
{
	return array(substr($dt, 0, 4), substr($dt, 4, 2), substr($dt, -2));
}

function mk_date($y, $m, $d)
{
	return str_pad((int)$y, 4, '0', STR_PAD_LEFT) . str_pad((int)$m, 2, '0', STR_PAD_LEFT) . str_pad((int)$d, 2, '0', STR_PAD_LEFT);
}

	if (isset($_GET['del'])) {
		q('DELETE FROM '.$tbl.'announce WHERE id='.(int)$_GET['del']);
		q('DELETE FROM '.$tbl.'ann_forums WHERE ann_id='.(int)$_GET['del']);
	}

	if (isset($_GET['edit']) && ($an_d = db_sab('SELECT * FROM '.$tbl.'announce WHERE id='.(int)$_GET['edit']))) {
		list($d_year, $d_month, $d_day) = raw_date($an_d->date_started);
		list($d2_year, $d2_month, $d2_day) = raw_date($an_d->date_ended);
		$a_subject = $an_d->subject;
		$a_text = $an_d->text;
		$edit = (int)$_GET['edit'];
		$c = uq('SELECT forum_id FROM '.$tbl.'ann_forums WHERE ann_id='.(int)$_GET['edit']);
		while ($r = db_rowarr($c)) {
			$frm_list[$r[0]] = $r[0];
		}
		unset($c);
	} else if (isset($_POST['btn_none']) || isset($_POST['btn_all'])) {
		$vals = array('edit', 'a_subject', 'a_text', 'd_year', 'd_month', 'd_day', 'd2_year', 'd2_month', 'd2_day');
		foreach ($vals as $v) {
			${$v} = $_POST[$v];
		}
		if (isset($_POST['btn_all'])) {
			$c = uq('SELECT id FROM '.$tbl.'forum');
			while ($r = db_rowarr($c)) {
				$frm_list[$r[0]] = $r[0];
			}
			unset($c);
		}
	} else {
		$edit = $a_subject = $a_text = '';
		list($d_year, $d_month, $d_day) = explode(' ', gmdate('Y m d', __request_timestamp__));
		list($d2_year, $d2_month, $d2_day) =  explode(' ', gmdate('Y m d', (__request_timestamp__ + 86400)));
	}

	if (isset($_POST['btn_submit'])) {
		$id = db_qid('INSERT INTO '.$tbl.'announce (date_started, date_ended, subject, text) VALUES ('.mk_date($_POST['d_year'], $_POST['d_month'], $_POST['d_day']).', '.mk_date($_POST['d2_year'], $_POST['d2_month'], $_POST['d2_day']).', '._esc($_POST['a_subject']).', '._esc($_POST['a_text']).')');
	} else if (isset($_POST['btn_update'], $_POST['edit'])) {
		$id = (int)$_POST['edit'];
		q('UPDATE '.$tbl.'announce SET
			date_started='.mk_date($_POST['d_year'], $_POST['d_month'], $_POST['d_day']).',
			date_ended='.mk_date($_POST['d2_year'], $_POST['d2_month'], $_POST['d2_day']).',
			subject='._esc($_POST['a_subject']).',
			text='._esc($_POST['a_text']).'
			WHERE id='.$id);
	}

	if (isset($_POST['frm_list'], $id)) {
		$_POST['frm_list'] = array_unique($_POST['frm_list']);
		q('DELETE FROM '.$tbl.'ann_forums WHERE ann_id='.$id);
		foreach ($_POST['frm_list'] as $v) {
			q('INSERT INTO '.$tbl.'ann_forums (forum_id, ann_id) VALUES('.(int)$v.','.$id.')');
		}
		unset($frm_list);
	}

	require($WWW_ROOT_DISK . 'adm/admpanel.php');
?>
<h2>Announcement System</h2>
<form method="post" name="a_frm" action="admannounce.php">
<?php echo _hs; ?>
<table class="datatable">
	<tr class="field">
		<td valign="top">Forums</td>
		<td><table border="0" cellspacing="1" cellpadding="2">
			<tr><td colspan="5"><input type="submit" name="btn_none" value="None"> <input type="submit" name="btn_all" value="All"></td></tr>
<?php
	 require $FORUM_SETTINGS_PATH.'cat_cache.inc';
	$pfx = $oldc = ''; $row = 0;
	$c = uq('SELECT f.id, f.name, c.id FROM '.$tbl.'fc_view v INNER JOIN '.$tbl.'forum f ON f.id=v.f INNER JOIN '.$tbl.'cat c ON f.cat_id=c.id ORDER BY v.id');
	while ($r = db_rowarr($c)) {
		if ($oldc != $r[2]) {
			if ($row < 6) {
				echo '<tr><td colspan="'.(6 - $row).'"> </td></tr>';
			}
			while (list($k, $i) = each($GLOBALS['cat_cache'])) {
				$pfx = str_repeat('&nbsp;&nbsp;&nbsp;', $i[0]);

				echo '<tr class="fieldtopic"><td colspan=6>'.$pfx.'<font size=-2>'.$i[1].'</font></td></tr><tr class="field">';
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
		echo '<td>'.($row == 2 ? $pfx : '').create_checkbox('frm_list['.$r[0].']', $r[0], isset($frm_list[$r[0]])).' <font size=-2> '.$r[1].'</font></td>';
	}
	unset($c);
?>
		</tr></table>
		</td>
	</tr>

	<tr class="tutor">
		<td colspan="2">All dates are in GMT, current GMT date/time is: <?php echo gmdate('r', __request_timestamp__); ?></td>
	</tr>

	<tr class="field">
		<td>Starting Date:</td>
		<td>
			<table border="0" cellspacing="1" cellpadding="0">
				<tr><td><font size="-2">Month</font></td><td><font size="-2">Day</font></td><td><font size="-2">Year</font></td></tr>
				<tr><td><?php draw_month_select('d_month', 0, $d_month); ?></td><td><?php draw_day_select('d_day', 0, $d_day); ?></td><td><input type="text" name="d_year" value="<?php echo $d_year; ?>" size=5></td></tr>
			</table>
		</td>
	</tr>

	<tr class="field">
		<td>Ending Date:</td>
		<td>
			<table border="0" cellspacing="1" cellpadding="0">
				<tr><td><font size="-2">Month</font></td><td><font size="-2">Day</font></td><td><font size="-2">Year</font></td></tr>
				<tr><td><?php draw_month_select('d2_month', 0, $d2_month); ?></td><td><?php draw_day_select('d2_day', 0, $d2_day); ?></td><td><input type="text" name="d2_year" value="<?php echo $d2_year; ?>" size=5></td></tr>
			</table>
		</td>
	</tr>

	<tr class="field">
		<td>Subject:</td>
		<td><input type="text" name="a_subject" value="<?php echo htmlspecialchars($a_subject); ?>">
	</tr>

	<tr class="field">
		<td valign=top>Message:</td>
		<td><textarea cols=40 rows=10 name="a_text"><?php echo htmlspecialchars($a_text); ?></textarea></td>
	</tr>

	<tr class="field">
		<td colspan=2 align=right>
<?php
			if ($edit) {
				echo '<input type="submit" name="btn_cancel" value="Cancel"> <input type="submit" name="btn_update" value="Update">';
			} else {
				echo '<input type="submit" name="btn_submit" value="Add">';
			}
?>
		</td>
	</tr>

</table>
<input type="hidden" name="edit" value="<?php echo $edit; ?>">
</form>

<table class="resulttable fulltable">
<tr class="resulttopic">
	<td>Subject</td>
	<td>Body</td>
	<td>Starting Date</td>
	<td>Ending Date</td>
	<td>Action</td>
</tr>
<?php
	$c = uq('SELECT * FROM '.$tbl.'announce ORDER BY date_started');
	$i = 1;
	while ($r = db_rowobj($c)) {
		if ($edit == $r->id) {
			$bgcolor = ' class="resultrow1"';
		} else {
			$bgcolor = ($i++%2) ? ' class="resultrow2"' : ' class="resultrow1"';
		}
		$b = htmlspecialchars((strlen($r->text) > 25) ? substr($r->text, 0, 25).'...' : $r->text);
		$st_dt = raw_date($r->date_started);
		$st_dt = gmdate('F j, Y', gmmktime(1, 1, 1, $st_dt[1], $st_dt[2], $st_dt[0]));
		$en_dt = raw_date($r->date_ended);
		$en_dt = gmdate('F j, Y', gmmktime(1, 1, 1, $en_dt[1], $en_dt[2], $en_dt[0]));
		echo '<tr'.$bgcolor.'><td>'.$r->subject.'</td><td>'.$b.'</td><td>'.$st_dt.'</td><td>'.$en_dt.'</td><td>[<a href="admannounce.php?edit='.$r->id.'&'.__adm_rsid.'">Edit</a>] [<a href="admannounce.php?del='.$r->id.'&'.__adm_rsid.'">Delete</a>]</td></tr>';
	}
	unset($c);
?>
</table>
<?php require($WWW_ROOT_DISK . 'adm/admclose.html'); ?>
