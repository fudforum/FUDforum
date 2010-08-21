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

	define('no_inline', 1);

	require('./GLOBALS.php');
	fud_use('adm.inc', true);
	fud_use('users_adm.inc', true);
	fud_use('ssu.inc');

	require($WWW_ROOT_DISK .'adm/header.php');

	if (isset($_GET['usr_id'])) {
		usr_adm_avatar((int)$_GET['usr_id'], 0);
		echo successify('Avatar was successfully approved.');
	} else if (isset($_GET['del'])) {
		usr_adm_avatar((int)$_GET['del'], 1);
		echo successify('Avatar was successfully deleted.');
	}
?>
<h2>Avatar Approval System</h2>
<table class="resulttable fulltable">
<thead><tr class="resulttopic">
	<th>User</th><th>Avatar</th><th>Action</th>
</tr></thead>
<?php
	$i = 0;

	$c = uq('SELECT id, avatar_loc, alias FROM '.$GLOBALS['DBHOST_TBL_PREFIX'].'users WHERE users_opt>=16777216 AND (users_opt & 16777216) > 0 ORDER BY id');
	while ($r = db_rowarr($c)) {
		echo '<tr class="field"><td>'.$r[2].'</td><td><center>'.$r[1].'</center></td><td>[<a href="admapprove_avatar.php?usr_id='.$r[0].'&amp;'.__adm_rsid.'">Approve</a>] [<a href="admapprove_avatar.php?del='.$r[0].'&amp;'.__adm_rsid.'">Delete</a>]</td></tr>';
		$i++;
	}
	unset($c);

	if (!$i) {
		echo '<tr class="field"><td colspan="3"><center>There are no avatars pending approval.</center></td></tr>';
	}
?>
</table>
<?php

	require($WWW_ROOT_DISK .'adm/footer.php');
?>
