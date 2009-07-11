<?php
/**
* copyright            : (C) 2001-2009 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: admxmlagg.php,v 1.1 2009/07/11 09:55:24 frank Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; version 2 of the License.
**/

	require('./GLOBALS.php');
	fud_use('adm.inc', true);
	fud_use('widgets.inc', true);
	fud_use('xmlagg_adm.inc', true);

	require($WWW_ROOT_DISK . 'adm/admpanel.php');
	
	$tbl = $GLOBALS['DBHOST_TBL_PREFIX'];
	$edit = isset($_GET['edit']) ? (int)$_GET['edit'] : (isset($_POST['edit']) ? (int)$_POST['edit'] : '');

	if (!empty($_POST['xmlagg_forum_id'])) {
		$xmlagg_adm = new fud_xmlagg_adm;
		if ($edit) {
			$xmlagg_adm->sync($edit);
			echo '<font color="green">Aggregation rule successfully updated.</font>';
			$edit = '';
		} else {
			$xmlagg_adm->add();
			echo '<font color="green">Aggregation rule successfully added (see list at bottom of page).</font>';
		}
	} else if (isset($_GET['del'])) {
		xmlagg_del((int)$_GET['del']);
		echo '<font color="green">Aggregation rule successfully deleted.</font>';
	} else if (isset($_GET['trk']) && ($nn = db_sab('SELECT * FROM '.$tbl.'xmlagg WHERE id='.(int)$_GET['trk']))) {
		xmlagg_reset((int)$_GET['trk']);
		echo '<font color="green">Aggregation tracker was successfully cleard.</font>';
	}

	if (isset($_GET['edit']) && $edit && ($o = db_sab('SELECT * FROM '.$tbl.'xmlagg WHERE id='.$edit))) {
		foreach ($o as $k => $v) {
			${'xmlagg_' . $k} = $v;
		}
	} else { /* Set the some default values */
		foreach (get_class_vars('fud_xmlagg_adm') as $k => $v) {
			${'xmlagg_' . $k} = $v;
		}
	}

	if ($FUD_OPT_2 & 8388608 && strncasecmp('win', PHP_OS, 3)) {	// Forum is locked and not windows
		echo '<div class="alert">You may need to <a href="admlock.php?'.__adm_rsid.'">unlock</a> the forum\'s files before you can run the XML importing script(s).</div>';
	}
?>
<h2>XML Aggregation</h2>
<form method="post" id="frm_forum" action="admxmlagg.php">
<?php echo _hs; ?>
<table class="datatable">
	<tr class="field">
		<td>XML Feed Name:<br /><font size="-1">A short but descriptive name for this XML feed.</font></td>
		<td><input type="text" name="xmlagg_name" value="<?php echo htmlspecialchars($xmlagg_name); ?>" maxlength="255" /></td>
	</tr>
	
	<tr class="field">
		<td>XML Feed URL:<br /><font size="-1">The URL of the XML (RDF, RSS or ATOM) feed you want to load.</font></td>
		<td><input type="text" name="xmlagg_url" value="<?php echo htmlspecialchars($xmlagg_url); ?>" maxlength="255" /></td>
	</tr>

	<tr>
		<td colspan="2"><br /></td>
	</tr>

	<tr class="field">
		<td>
			Forum:<br />
			<font size="-1">Articles imported from the feed will be imported into this forum.
			It is <b>**highly recommended**</b> that you setup a separate forum for each feed.</font>
		</td>
		<td><select name="xmlagg_forum_id"><option></option>
		<?php
			$c = uq('SELECT f.id, f.name, c.name
				FROM '.$tbl.'forum f
				INNER JOIN '.$tbl.'cat c ON f.cat_id=c.id
				ORDER BY c.parent, c.view_order, f.view_order');
			while ($r = db_rowarr($c)) {
				echo '<option value="'.$r[0].'"'.($r[0] != $xmlagg_forum_id ? '' : ' selected="selected"').'>'.$r[2].' &raquo; '.$r[1].'</option>';
			}
			unset($c);
		?>
		</select></td>
	</tr>

	<tr class="field">
		<td>
			Moderate Feed Posts:<br />
			<font size="-1">Any article from the feed would first need to be approved by moderator(s) before
			they are made visible on the forum.</font>
		</td>
		<td><?php draw_select('xmlagg_xmlagg_post_apr', "No\nYes", "0\n1", ($xmlagg_xmlagg_opt & 1 ? 1 : 0)); ?></td>
	</tr>

	<tr class="field">
		<td>
			Create New Users:<br />
			<font size="-1">When importing articles from a feed, should a new user be created for every article
			author, who cannot be matched against an existing forum user. If this option is set to 'No', then all
			imported newsgroup messages who's authors cannot be matched against existing forum members will be attributed
			to the anonymous user.</font>
		</td>
		<td><?php draw_select('xmlagg_create_users', "No\nYes", "0\n2", ($xmlagg_xmlagg_opt & 2 ? 2 : 0)); ?></td>
	</tr>

	<tr class="field">
		<td>
			Skip Non-Forum Users:<br />
			<font size="-1">When importing articles, should the articles posted by users who cannot be matched
			to existing forum members be ignored.</font>
		</td>
		<td><?php draw_select('xmlagg_skip_non_forum_users', "Yes\nNo", "4\n0", ($xmlagg_xmlagg_opt & 4 ? 4 : 0)); ?></td>
	</tr>

	<tr class="fieldaction">
		<td colspan="2" align="right">
			<?php if ($edit) echo '<input type="submit" value="Cancel" name="btn_cancel" />&nbsp;'; ?>
			<input type="submit" value="<?php echo ($edit ? 'Update Aggregation Rule' : 'Add Aggregation Rule'); ?>" name="xmlagg_submit" />
		</td>
	</tr>
</table>
<input type="hidden" name="edit" value="<?php echo $edit; ?>" />
</form>
<br /><br />
<table class="resulttable fulltable">
	<tr class="resulttopic">
		<td nowrap="nowrap">Aggregation Rule</td>
		<td>Forum</td>
		<td>Exec Line</td>
		<td align="center">Action</td>
	</tr>
<?php
	$c = uq('SELECT x.id, x.url, f.name FROM '.$tbl.'xmlagg x INNER JOIN '.$tbl.'forum f ON x.forum_id=f.id');
	$i = 1;
	while ($r = db_rowarr($c)) {
		if ($edit == $r[0]) {
			$bgcolor = ' class="resultrow1"';
		} else {
			$bgcolor = ($i++%2) ? ' class="resultrow2"' : ' class="resultrow1"';
		}
		echo '<tr'.$bgcolor.'><td>'.htmlspecialchars($r[1]).'</td><td>'.$r[2].'</td>
			<td nowrap="nowrap"><font size="-1">'.$GLOBALS['DATA_DIR'].'scripts/xmlagg.php '.$r[0].' </font></td>
			<td>[<a href="admxmlagg.php?edit='.$r[0].'&amp;'.__adm_rsid.'">Edit</a>] [<a href="admxmlagg.php?del='.$r[0].'&amp;'.__adm_rsid.'">Delete</a>]
			[<a href="admxmlagg.php?trk='.$r[0].'&amp;'.__adm_rsid.'">Clear Tracker</a>]</td></tr>';
	}
	unset($c);
?>
</table>
<br /><br />
<b>***Notes***</b><br />
Exec Line parameter in the table above shows the execution line that you will need to place in your cron.
<br />
Cron example:
<pre>
*/2 * * * * /home/forum/forum/scripts/xmlagg.php 1
</pre>
<?php require($WWW_ROOT_DISK . 'adm/admclose.html'); ?>
