<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: admdelfrm.php,v 1.13 2003/10/05 22:19:50 hackie Exp $
****************************************************************************

****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

	require('./GLOBALS.php');
	fud_use('adm.inc', true);
	fud_use('forum_adm.inc', true);

	$tbl = $GLOBALS['DBHOST_TBL_PREFIX'];

	/* restore forum */
	if (isset($_POST['frm_id'], $_POST['dst_cat'])) {
		$pos = (int) q_singleval('SELECT MAX(view_order) FROM '.$tbl.'forum WHERE cat_id='.(int)$_POST['dst_cat']) + 1;
		q('UPDATE '.$tbl.'forum SET cat_id='.(int)$_POST['dst_cat'].', view_order='.$pos.' WHERE id='.(int)$_POST['frm_id']);
	} else if (isset($_GET['del']) && ($f = db_saq('SELECT id, thread_count, post_count, name FROM '.$tbl.'forum WHERE id='.(int)$_GET['del']))) {
		/* user considers deleting a forum, give them final confirmation check */
?>
<html>
<body bgcolor="#ffffff">
<div align="center">
<h3>You have selected to delete this forum</h3><br>
"<?php echo $f[3]; ?>" which contains <?php echo $f[1]; ?> topics with <?php echo $f[2]; ?> posts<br><br>
<h3>Are you sure this is what you want to do?</h3>
<form method="post" action="admdelfrm.php">
<?php echo _hs; ?>
<input type="hidden" name="del" value="<?php echo $f[0]; ?>">
<table border=0 cellspacing=0 cellpadding=2>
<tr><td><input type="submit" name="conf" value="Yes"></td><td><input type="submit" name="conf" value="No"></td></tr>
</table>
</form>
</div>
</body>
</html>
<?php
		exit;
	} else if (isset($_POST['del'], $_POST['conf']) && $_POST['conf'] == 'Yes') {
		/* let's delete this forum */
		frm_delete((int)$_POST['del']);
	}

	require($WWW_ROOT_DISK . 'adm/admpanel.php');
?>
<h2>Orphaned Forums</h2>
<table cellspacing=1 cellpadding=2>
<tr bgcolor="#e5ffe7">
	<td>Name</td>
	<td>Action</td>
	<td>Reassign To Category</td>
</tr>
<?php
	$i = 1;
	$cat_sel = create_cat_select('dst_cat', '', 0);
	$c = uq('SELECT id, name, descr FROM '.$tbl.'forum WHERE cat_id=0');
	while ($r = db_rowarr($c)) {
		$bgcolor = ($i++%2) ? ' bgcolor="#fffee5"' : '';
		echo '<tr '.$bgcolor.'><td>'.$r[1].'<br><font size="-2">'.$r[2].'</font></td><td valign="top" nowrap><a href="admdelfrm.php?del='.$r[0].'&'._rsidl.'">Delete</a></td><td valign="top" nowrap><form method="post" action="admdelfrm.php">'._hs.$cat_sel.' <input type="submit" name="frm_submit" value="Reassign"><input type="hidden" name="frm_id" value="'.$r[0].'"></form></td></tr>';
	}
	qf($c);
?>
</table>
<?php require($WWW_ROOT_DISK . 'adm/admclose.html'); ?>