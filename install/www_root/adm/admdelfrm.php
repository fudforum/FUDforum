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
	$tbl = $GLOBALS['DBHOST_TBL_PREFIX'];

	fud_use('adm.inc', true);
	fud_use('forum_adm.inc', true);

	require($WWW_ROOT_DISK . 'adm/header.php');

	/* Restore forum. */
	if (isset($_POST['frm_id'], $_POST['dst_cat'])) {
		$pos = (int) q_singleval('SELECT MAX(view_order) FROM '.$tbl.'forum WHERE cat_id='.(int)$_POST['dst_cat']) + 1;
		q('UPDATE '.$tbl.'forum SET cat_id='.(int)$_POST['dst_cat'].', view_order='.$pos.' WHERE id='.(int)$_POST['frm_id']);
		fud_use('cat.inc', true);
		rebuild_forum_cat_order();
		echo successify('Forum was successfully restored.');
	} else if (isset($_GET['del']) && ($f = db_saq('SELECT id, thread_count, post_count, name FROM '.$tbl.'forum WHERE id='.(int)$_GET['del']))) {
		/* User considers deleting a forum, give them final confirmation check. */
?>
<div align="center">
<h3>You have selected to permanently delete this forum</h3><br />
"<?php echo $f[3]; ?>" which contains <?php echo $f[1]; ?> topics with <?php echo $f[2]; ?> posts<br /><br />
<h3>Are you sure this is what you want to do?</h3>
<form method="post" action="admdelfrm.php">
<?php echo _hs; ?>
<input type="hidden" name="del" value="<?php echo $f[0]; ?>" />
<table border="0" cellspacing="0" cellpadding="2">
<tr><td><input type="submit" name="conf" value="Yes" /></td><td><input type="submit" name="conf" value="No" /></td></tr>
</table>
</form>
</div>
<?php
		exit;
	} else if (isset($_POST['del'], $_POST['conf']) && $_POST['conf'] == 'Yes') {
		/* Let's delete this forum. */
		frm_delete((int)$_POST['del']);
		echo succesify('Forum was successfully deleted.');
	}
?>
<h2>Orphaned Forums</h2>
<p>The following forums were deleted and are in the trash bin (not visible to users). You can permanently delete them or reassign them to a category.</p>
<table class="resulttable fulltable">
<thead><tr class="resulttopic">
	<th width="50%">Forum Name</th>
	<th width="10%">Action</th>
	<th width="40%">Reassign To Category</th>
</tr></thead>
<?php
	$i = 0;
	$cat_sel = create_cat_select('dst_cat', '', 0);
	$c = uq('SELECT id, name, descr FROM '.$tbl.'forum WHERE cat_id=0');
	while ($r = db_rowarr($c)) {
		$bgcolor = ($i++%2) ? ' class="resultrow1"' : ' class="resultrow2"';
		echo '<tr '.$bgcolor.'><td>'.$r[1].'<br /><font size="-2">'.$r[2].'</font></td><td valign="top" nowrap="nowrap"><a href="admdelfrm.php?del='.$r[0].'&amp;'.__adm_rsid.'">Delete</a></td><td valign="top" nowrap="nowrap"><form method="post" action="admdelfrm.php">'._hs.$cat_sel.' <input type="submit" name="frm_submit" value="Reassign" /><input type="hidden" name="frm_id" value="'.$r[0].'" /></form></td></tr>';
	}
	unset($c);
	if (!$i) {
		echo '<tr class="field"><td colspan="3"><center>No deleted forums found.</center></td></tr>';
	}
?>
</table>
<?php require($WWW_ROOT_DISK . 'adm/footer.php'); ?>
