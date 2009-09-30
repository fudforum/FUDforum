<?php
/**
* copyright            : (C) 2001-2009 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: admbanlist.php,v 1.12 2009/09/30 16:47:32 frank Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; version 2 of the License.
**/

	require('./GLOBALS.php');
	fud_use('adm.inc', true);

	require($WWW_ROOT_DISK . 'adm/header.php');
?>

<h2>Banned User List</h2>
<p>The following users are currently banned from using the forum:</p>
<table class="resulttable fulltable">
<tr class="resulttopic">
	<th>User Login (Alias)</th>
	<th>E-mail</th>
	<th>Ban Expiry</th>
	<th>Actions</th>
</tr>
<?php
	$c = uq("SELECT id, login, alias, email, ban_expiry FROM ".$DBHOST_TBL_PREFIX."users WHERE (users_opt & 65536) > 0 ORDER BY alias");
	$i = 0;
	while ($r = db_rowarr($c)) {
		$bgcolor = ($i++%2) ? ' class="resultrow2"' : ' class="resultrow1"';
		echo '<tr '. $bgcolor .'"><td>'. htmlspecialchars($r[1]) .' ( '. $r[2] .' ) </td>';
		echo '<td>'. htmlspecialchars($r[3]).'</td>';
		echo '<td>'. ($r[4] ? date("r", $r[4]) : 'never').'</td>';
		echo '<td><a href="admuser.php?act=1&amp;usr_id='.$r[0].'&amp;'.__adm_rsid.'">Edit</a></td></tr>';
	}
	unset($c);
	if (!$i) {
		echo '<tr><td colspan="4" align="center">No banned users found.</td></tr>';
	}
?>
</table>
<?php require($WWW_ROOT_DISK . 'adm/footer.php'); ?>
