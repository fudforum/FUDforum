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
	fud_use('private.inc');

	$tbl = $GLOBALS['DBHOST_TBL_PREFIX'];
	$folders = array(1=>'inbox', 2=>'saved', 4=>'draft', 3=>'sent', 5=>'trash');

	require($WWW_ROOT_DISK . 'adm/header.php');

	// Delete PM from all mailboxes.
	if (isset($_GET['delmsg']) && ($r = db_saq('SELECT ouser_id, subject FROM '. $tbl .'pmsg WHERE id='. (int)$_GET['delmsg']))) {
		$c = uq('SELECT p.id, p.fldr, u.alias FROM '. $tbl .'pmsg p INNER JOIN '. $tbl .'users u ON p.duser_id=u.id WHERE p.ouser_id='. $r[0] .' AND p.subject='. _esc($r[1]));
		while ($r2 = db_rowarr($c)) {
			q('DELETE FROM '.$tbl.'pmsg WHERE id='.$r2[0]);
			echo successify('Delete private message from '. $r2[2] .'\'s '. $folders[$r2[1]] .' folder.');
		}
		unset($r, $r2);
	}
	
	if (!empty($_POST['user']) || !empty($_GET['user'])) {
		$user = empty($_POST['user']) ? $_GET['user'] : $_POST['user'];
	}
?>
<h2>Private Message Manager</h2>
<div class="tutor">
	This control panel should only be used to identify and remove private spam messages.
	Please respect people's privacy and keep their private conversations private!
</div>
<br />

<center><form method="post" action="admpmspy.php"><?php echo _hs; ?>
<b>Filter by user:</b>
<input type="text" name="user" value="<?php if (isset($user)) echo $user; ?>" />
<input type="submit" value="Go" name="btn_filter" />
</form></center>

<?php
	if (isset($_GET['msg']) && ($r = db_saq('SELECT p.foff, p.length, p.subject, p.to_list, p.post_stamp, u.alias FROM '.$tbl.'pmsg p INNER JOIN '.$tbl.'users u ON p.ouser_id=u.id WHERE p.id='.(int)$_GET['msg']))) {
		echo '<h2>Message: '.$r[2].'</h2>';
		echo '<table class="resulttable fulltable">';
		echo '<tr class="resulttopic"><td><b>From:</b> '.$r[5].'</td>';
		echo '                        <td><b>To:</b> '.$r[3].'</td>';
		echo '                        <td><b>Date:</b> '. gmdate('d M Y G:i', $r[4]) .'</td></tr>';
		$data = read_pmsg_body($r[0], $r[1]);
		echo '<tr><td colspan="3">'. $data .'</td></tr>';
		echo '<tr><td colspan="3">';
		echo '<form method="get" action="admpmspy.php" style="float:right;">'. _hs;
		echo '  <input type="hidden" value="'. $_GET['msg'] .'" name="delmsg" />';
		echo '  <input type="submit" value="Delete from all mailboxes" name="btn_delete" />';
		echo '</form></td></tr></table><br />';
	}
?>

<table class="resulttable fulltable">
<thead><tr class="resulttopic">
	<th>From</th><th>To</th><th>Folder</th><th>Subject</th><th>Posted</th><th>Actions</th>
</tr></thead>
<?php
	$i = 0;
	if (!empty($_POST['user']) || !empty($_GET['user'])) {
		echo '<h2>Private messages sent by user: '.$user.'</h2>';
		$cond = 'WHERE u.alias = '. _esc($user) . ' AND p.fldr=3';
	} else {
		echo '<h2>Recently sent private messages</h2>';
		$user = '';
		$cond = 'WHERE p.fldr=3';
	}
	$c = uq('SELECT p.id, p.to_list, p.fldr, p.subject, p.post_stamp, u.alias FROM '.$tbl.'pmsg p
		INNER JOIN '.$tbl.'users u ON p.ouser_id=u.id '.$cond.' ORDER BY p.post_stamp DESC LIMIT 100');
	while ($r = db_rowarr($c)) {
		$bgcolor = ($i++%2) ? ' class="resultrow2"' : ' class="resultrow1"';
		echo '<tr'. $bgcolor .'">';
		echo '<td>'. $r[5] .'</td>';
		echo '<td>'. $r[1] .'</td>';
		echo '<td>'. $folders[$r[2]] .'</td>';
		echo '<td><a href="admpmspy.php?msg='. $r[0] .'&amp;user='. $user .'&amp;'. __adm_rsid .'">'. $r[3] .'</a></td>';
		echo '<td>'. gmdate('d M Y G:i', $r[4]) .'</td>';
		echo '<td><a href="admpmspy.php?msg='. $r[0] .'&amp;user='. $user .'&amp;'. __adm_rsid .'">View</a> | <a href="admpmspy.php?delmsg='. $r[0] .'&amp;user='. $user .'&amp;'. __adm_rsid .'">Delete</a></td>';
		echo '</tr>';
	}

	unset($c);
	if (!$i) {
		echo '<tr class="field"><td colspan="6"><center>No private messages found.</center></td></tr>';
	}
?>
</table>

<?php require($WWW_ROOT_DISK . 'adm/footer.php'); ?>
