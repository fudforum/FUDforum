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
	fud_use('widgets.inc', true);
	fud_use('xmlagg_adm.inc', true);

	require($WWW_ROOT_DISK . 'adm/header.php');
	$tbl = $GLOBALS['DBHOST_TBL_PREFIX'];

	if (!empty($_POST['btn_cancel'])) {
		unset($_POST);
	}
	
	$edit = isset($_GET['edit']) ? (int)$_GET['edit'] : (isset($_POST['edit']) ? (int)$_POST['edit'] : '');

	if (!empty($_POST['xmlagg_name']) && !empty($_POST['xmlagg_forum_id'])) {

		/* Validate URL's protocol. */
		$protocol = substr($_POST['xmlagg_url'], 0, strpos($_POST['xmlagg_url'], '://'));
		if (!in_array($protocol, stream_get_wrappers())) {
			echo errorify('Feed URL doesn\'t have a protocol or protocol ('. $protocol .'://) is not supported.');
			echo 'Supported protocols: '. implode(stream_get_wrappers(), ', ') ."\n";
		} else {
			$xmlagg_adm = new fud_xmlagg_adm;
			if ($edit) {
				$xmlagg_adm->sync($edit);
				echo successify('Aggregation rule successfully updated.');
				$edit = '';
			} else {
				$xmlagg_adm->add();
				echo successify('Aggregation rule successfully added (see <a href="#list">list</a> at bottom of page).');
			}
			unset($_POST);
		}
	} else if (isset($_GET['del'])) {
		xmlagg_del((int)$_GET['del']);
		echo successify('Aggregation rule successfully deleted.');
	} else if (isset($_GET['trk']) && ($nn = db_sab('SELECT * FROM '.$tbl.'xmlagg WHERE id='.(int)$_GET['trk']))) {
		xmlagg_reset((int)$_GET['trk']);
		echo successify('Aggregation tracker was successfully cleared. The next load will start with the oldest availale article.');
	}

	if (isset($_GET['edit']) && $edit && ($o = db_sab('SELECT * FROM '.$tbl.'xmlagg WHERE id='.$edit))) {
		foreach ($o as $k => $v) {
			${'xmlagg_' . $k} = $v;
		}
	} else { /* Set the some default values. */
		foreach (get_class_vars('fud_xmlagg_adm') as $k => $v) {
			if (isset($_POST['xmlagg_' . $k])) {
				${'xmlagg_' . $k} = $_POST['xmlagg_' . $k];
			} else {
				${'xmlagg_' . $k} = $v;
			}
		}
	}
?>
<h2>XML Aggregation</h2>

<h3><?php echo $edit ? '<a name="edit">Edit Rule:</a>' : 'Add New Rule:'; ?></h3>
<form method="post" id="frm_forum" action="admxmlagg.php">
<?php echo _hs; ?>
<table class="datatable">
	<tr class="field">
		<td>XML Feed Name:<br /><font size="-1">A short but descriptive name for this XML feed.</font></td>
		<td><input type="text" name="xmlagg_name" value="<?php echo htmlspecialchars($xmlagg_name); ?>" size="30" maxlength="255" /></td>
	</tr>
	
	<tr class="field">
		<td>XML Feed URL:<br /><font size="-1">The URL of the XML (RDF, RSS or ATOM) feed you want to load.</font></td>
		<td><input type="text" name="xmlagg_url" value="<?php echo htmlspecialchars($xmlagg_url); ?>" size="30" maxlength="255" /></td>
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

	<tr class="field">
		<td>
			Slow Reply Match:<br />
			<font size="-1">If this option is enabled the forum will try to determine if an article is a reply
			by comparing it's subject to subjects of existing messages in the forum.</font>
		</td>
		<td><?php draw_select('xmlagg_complex_reply_match', "No\nYes", "0\n8", ($xmlagg_xmlagg_opt & 8 ? 8 : 0)); ?></td>
	</tr>

	<tr class="field">
		<td>Post Signature:<br />
			<font size="-1">A string of text to append to the end of every aggregated article. Use <i>{link}</i> to refer to the article's URL.</font>
		</td>
		<td><textarea name="xmlagg_custom_sig" rows="5" cols="30"><?php echo htmlspecialchars($xmlagg_custom_sig); ?></textarea></td>
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

<h3><a name="list">Available rules</a></h3>
<table class="resulttable fulltable">
<thead><tr class="resulttopic">
	<th nowrap="nowrap">Aggregation Rule</th>
	<th>Forum</th>
	<th>Exec Line</th>
	<th><abbr title="Date of last imported article. Used to track articles and prevent loading of duplicate content.">Last article</abbr></th>
	<th align="center">Action</th>
</tr></thead>
<?php
	$c = uq('SELECT x.id, x.url, x.name, x.last_load_date, f.name FROM '.$tbl.'xmlagg x INNER JOIN '.$tbl.'forum f ON x.forum_id=f.id');
	$i = 0;
	while ($r = db_rowarr($c)) {
		$i++;
		$bgcolor = ($edit == $r[0]) ? ' class="resultrow3"' : (($i%2) ? ' class="resultrow1"' : ' class="resultrow2"');

		echo '<tr'.$bgcolor.'><td>'.htmlspecialchars($r[2]).'</td><td>'.$r[4].'</td>
			<td nowrap="nowrap">xmlagg.php '.$r[0].'</td>
			<td nowrap="nowrap">'.gmdate('d M Y G:i', $r[3]).'</td>
			<td>[<a href="admxmlagg.php?edit='.$r[0].'&amp;'.__adm_rsid.'#edit">Edit</a>] [<a href="admxmlagg.php?del='.$r[0].'&amp;'.__adm_rsid.'">Delete</a>]
			[<a href="admxmlagg.php?trk='.$r[0].'&amp;'.__adm_rsid.'">Reset date</a>]</td></tr>';
	}
	unset($c);
	if (!$i) {
		echo '<tr class="field"><td colspan="5" align="center">No rules. Define some above.</td></tr>';
	}
?>
</table>
<br /><br />
<b>***Notes***</b><br />
The <i>Exec Line</i> in the table above shows the execution line that you will need to place in your system's job scheduler.
Windows users can use <a href="http://en.wikipedia.org/wiki/Schtasks" target="_new">schtasks.exe</a>.
Here is a Linux <a href="http://en.wikipedia.org/wiki/Cron" target="_new">cron</a> example:
<pre>
0 * * * * <?php echo realpath($GLOBALS['DATA_DIR'].'scripts/xmlagg.php'); ?> 1
</pre>
<?php require($WWW_ROOT_DISK . 'adm/footer.php'); ?>
