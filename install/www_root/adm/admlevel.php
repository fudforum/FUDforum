<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: admlevel.php,v 1.7 2003/04/29 14:19:37 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

	define('admin_form', 1);
	
	require('GLOBALS.php');
	fud_use('adm.inc', true);
	fud_use('widgets.inc', true);

	$tbl = $GLOBALS['DBHOST_TBL_PREFIX'];

	if (isset($_POST['lev_submit'])) {
		q('INSERT INTO '.$tbl.'level (name, img, pri, post_count) VALUES (\''.addslashes($_POST['lev_name']).'\', '.strnull(addslashes($_POST['lev_img'])).', \''.addslashes($_POST['lev_pri']).'\', '.(int)$_POST['lev_post_count'].')');
	} else if (isset($_POST['edit'], $_POST['lev_update'])) {
		q('UPDATE '.$tbl.'level SET 
			name=\''.addslashes($_POST['lev_name']).'\',
			img='.strnull(addslashes($_POST['lev_img'])).',
			pri=\''.addslashes($_POST['lev_pri']).'\',
			post_count='.(int)$_POST['lev_post_count'].'
		WHERE id='.(int)$_POST['edit']);	
	} else if (isset($_GET['edit'])) {
		$edit = (int)$_GET['edit'];
		list($lev_name, $lev_img, $lev_pri,$lev_post_count) = db_saq('SELECT name, img, pri, post_count FROM '.$tbl.'level WHERE id='.(int)$_GET['id']);
	} else {
		$edit = $lev_name = $lev_img = $lev_pri = $lev_post_count = '';
	}

	if (isset($_GET['del'])) {
		q('DELETE FROM '.$tbl.'level WHERE id='.(int)$_GET['del']);
	}

	if (isset($_GET['rebuild_levels'])) {
		db_lock($tbl.'users WRITE, '.$tbl.'level WRITE');
		$pl = 2000000000;
		$c = q('SELECT id, post_count FROM '.$tbl.'level ORDER BY post_count DESC');
		while ($r = db_rowarr($c)) {
			q('UPDATE '.$tbl.'users SET level_id='.$r[0].' WHERE posted_msg_count<'.$pl.' AND posted_msg_count>='.$r[1]);
			$pl = $r[1];
		}
		qf($r);
		db_unlock();
	}

	require($WWW_ROOT_DISK . 'adm/admpanel.php'); 
?>
<h2>Rank Manager</h2>
<div align="center"><font size="+1" color="#ff0000">If you've made any modification to the user ranks<br>YOU MUST RUN CACHE REBUILDER by &gt;&gt; <a href="admlevel.php?rebuild_levels=1&<?php echo _rsid; ?>">clicking here</a> &lt;&lt;</font></div>
<form method="post" name="lev_form" action="admlevel.php">
<?php echo _hs; ?>
<table border=0 cellspacing=1 cellpadding=2>
	<tr bgcolor="#bff8ff">
		<td>Rank Name</td>
		<td><input type="text" name="lev_name" value="<?php echo htmlspecialchars($lev_name); ?>"></td>
	</tr>
	<tr bgcolor="#bff8ff">
		<td>Rank Image<br><font size="-1">URL to the image<font></td>
		<td><input type="text" name="lev_img" value="<?php echo htmlspecialchars($lev_img); ?>"><br>
	</tr>
	
	<tr bgcolor="#bff8ff">
		<td>Which Image to Show:</td>
		<td><?php draw_select("lev_pri", "Avatar & Rank Image\nAvatar Only\nRank Image Only", "B\nA\nL", $lev_pri); ?></td>
	</tr>
	
	<tr bgcolor="#bff8ff">
		<td>Post Count</td>
		<td><input type="text" name="lev_post_count" value="<?php echo $lev_post_count; ?>" size=11 maxLength=10></td>
	</tr>
	
	<tr>
		<td colspan=2 bgcolor="#bff8ff" align=right>
<?php
			if (!$edit) {
				echo '<input type="submit" name="lev_submit" value="Add Level">';
			} else {
				echo '<input type="submit" name="lev_cancel" value="Cancel"> <input type="submit" name="lev_update" value="Update">';
			}
?>
		</td>
	</tr>
</table>
</form>

<table border=0 cellspacing=1 cellpadding=1>
<tr bgcolor="#e5ffe7">
	<td>Name</td>
	<td>Post Count</td>
	<td>Action</td>
</tr>
<?php
	$c = uq('SELECT * FROM '.$tbl.'level ORDER BY post_count');
	$i = 1;
	while ($r = db_rowobj($c)) {
		if ($edit == $r->id) {
			$bgcolor = ' bgcolor="#ffb5b5"';
		} else {
			$bgcolor = ($i++%2) ? ' bgcolor="#fffee5"' : '';
		}
		echo '<tr'.$bgcolor.'><td>'.$r->name.'</td><td align=center>'.$r->post_count.'</td><td><a href="admlevel.php?edit='.$r->id.'&'._rsidl.'">Edit</a> | <a href="admlevel.php?del='.$r->id.'&'._rsidl.'">Delete</a></td></tr>';
	}
	qf($c);
?>
</table>
<?php require($WWW_ROOT_DISK . 'adm/admclose.html'); ?>