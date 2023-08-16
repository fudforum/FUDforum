<?php
/**
* copyright            : (C) 2001-2023 Advanced Internet Designs Inc.
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

	$tbl = $GLOBALS['DBHOST_TBL_PREFIX'];
	require($WWW_ROOT_DISK .'adm/header.php');

	// Enable or disable BLOG_ENABLED.
	$help_ar = read_help();
	if (isset($_POST['form_posted'])) {
		if (isset($_POST['FUD_OPT_4_BLOG_ENABLED'])) {
			toggle_bin_setting('BLOG_ENABLED', $_POST['FUD_OPT_4_BLOG_ENABLED']);
		}
		if (isset($_POST['FUD_OPT_4_BLOG_HOMEPAGE'])) {
			toggle_bin_setting('BLOG_HOMEPAGE', $_POST['FUD_OPT_4_BLOG_HOMEPAGE']);
		}

		pf(successify('Settings successfully updated.'));
	}

	if (isset($_POST['frm_list'])) {
		$_POST['frm_list'] = array_unique($_POST['frm_list']);
		$frm_list = json_encode($_POST['frm_list']);
		q('DELETE FROM '. $tbl .'settings WHERE conf_name =\'blog_forum_list\'');
		q('INSERT INTO '. $tbl .'settings (conf_name, conf_value) VALUES(\'blog_forum_list\', '. _esc($frm_list) .')');
		pf(successify('Settings successfully updated.'));
	}
	$frm_list = q_singleval('SELECT conf_value FROM '. $tbl .'settings WHERE conf_name =\'blog_forum_list\'');
	$frm_list = json_decode($frm_list ?? '', true);

	if (!empty($_POST['btn_cancel'])) {
		unset($_POST);
	}

	if ($FUD_OPT_4 & 16) {
		pf('Visit your forum\'s <a href="../'. __fud_index_name__ .'?t=blog&amp;'. __adm_rsid .'">blog page</a>.');
	}
?>
<h2>Blog Manager</h2>
<form method="post" action="admblog.php">
<?php echo _hs ?>
<table class="datatable solidtable">
<?php
	print_bit_field('Blog Enabled',     'BLOG_ENABLED');
	print_bit_field('Set as main page', 'BLOG_HOMEPAGE');
?>
<tr class="fieldaction"><td colspan="2" align="right"><input type="submit" name="btn_submit" value="Set" /></td></tr>
</table>
<input type="hidden" name="form_posted" value="1" />
</form>

<h3>Blog configuration</h3>
<form method="post" action="admblog.php">
<?php echo _hs; ?>
	<table class="datatable">

	<tr class="field">
		<td valign="top">Forums to pull news from:<br /><font size="-2">(This ignores user groups, so anyone will be able to read these items regardless of their permissions.)</font></td>
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

				if ($k == $r[2]) {
					break;
				}
			}
			echo '<tr class="fieldtopic"><td colspan="6">'. $pfx .'<font size="-2">'. $i[1] .'</font></td></tr><tr class="field">';
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
		<td colspan="2" align="right">
			<input type="submit" name="btn_update" value="Update" />
		</td>
	</tr>
	
	</table>

<?php require($WWW_ROOT_DISK .'adm/footer.php'); ?>
