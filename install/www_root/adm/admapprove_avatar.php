<?php
/**
* copyright            : (C) 2001-2006 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: admapprove_avatar.php,v 1.26 2006/09/05 12:58:00 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; either version 2 of the License.
**/

	define('no_inline', 1);

	require('./GLOBALS.php');
	fud_use('adm.inc', true);
	fud_use('users_adm.inc', true);
	fud_use('ssu.inc');

	if (isset($_GET['usr_id'])) {
		usr_adm_avatar((int)$_GET['usr_id'], 0);
	} else if (isset($_GET['del'])) {
		usr_adm_avatar((int)$_GET['del'], 1);
	}

	require($WWW_ROOT_DISK . 'adm/admpanel.php');
?>
<h2>Avatar Approval System</h2>
<table class="datatable solidtable">
<?php
	$a = 0;

	$c = uq('SELECT id, avatar_loc, alias FROM '.$GLOBALS['DBHOST_TBL_PREFIX'].'users WHERE users_opt>=16777216 AND (users_opt & 16777216) > 0 ORDER BY id');
	while ($r = db_rowarr($c)) {
		$a = 1;
		echo '<tr class="field"><td>'.$r[2].'</td><td>[<a href="admapprove_avatar.php?usr_id='.$r[0].'&'.__adm_rsidl.'">Approve</a>] [<a href="admapprove_avatar.php?del='.$r[0].'&'.__adm_rsidl.'">Delete</a>]</td></tr>';
		echo '<tr class="field"><td align="center" colspan=2>'.$r[1].'</td></tr>';
	}
	unset($c);
?>
</table>
<?php
	if (!$a) {
		echo 'There are no avatars pending approval.';
	}

	require($WWW_ROOT_DISK . 'adm/admclose.html');
?>
