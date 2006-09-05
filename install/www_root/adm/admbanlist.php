<?php
/**
* copyright            : (C) 2001-2006 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: admbanlist.php,v 1.5 2006/09/05 12:58:00 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; either version 2 of the License.
**/

	require('./GLOBALS.php');
	fud_use('adm.inc', true);

	require($WWW_ROOT_DISK . 'adm/admpanel.php');
?>
<h2>Banned Users</h2>
<table class="datatable">
<tr>
	<th class="fieldtopic">User Login (Alias)</th>
	<th class="fieldtopic">E-mail</th>
	<th class="fieldtopic">Ban Expiry</th>
	<th class="fieldtopic">Tools</th>
</tr>
<?php
	$c = uq("SELECT login, alias, email, ban_expiry FROM ".$DBHOST_TBL_PREFIX."users WHERE (users_opt & 65536) > 0 ORDER BY alias");
	while ($r = db_rowarr($c)) {
		echo '<tr><td class="resultrow1">' . htmlspecialchars($r[0]).' ( '.$r[1].' ) </td>';
		echo '<td>' . htmlspecialchars($r[2]).'</td>';
		echo '<td class="resultrow1">' . ($r[3] ? date("r", $r[3]) : 'never').'</td>';
		echo '<td><a href="admuser.php?act=1&usr_id='.$r[0].'&'.__adm_rsidl.'">Edit</a></td></tr>';
	}
	unset($c);
?>
</table>
<?php require($WWW_ROOT_DISK . 'adm/admclose.html'); ?>