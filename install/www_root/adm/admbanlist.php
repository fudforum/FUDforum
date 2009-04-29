<?php
/**
* copyright            : (C) 2001-2009 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: admbanlist.php,v 1.10 2009/04/29 20:06:35 frank Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; version 2 of the License.
**/

	require('./GLOBALS.php');
	fud_use('adm.inc', true);

	require($WWW_ROOT_DISK . 'adm/admpanel.php');
?>
<h2>Banned User List</h2>
<table class="datatable">
<tr>
	<th class="fieldtopic">User Login (Alias)</th>
	<th class="fieldtopic">E-mail</th>
	<th class="fieldtopic">Ban Expiry</th>
	<th class="fieldtopic">Tools</th>
</tr>
<?php
	$c = uq("SELECT id, login, alias, email, ban_expiry FROM ".$DBHOST_TBL_PREFIX."users WHERE (users_opt & 65536) > 0 ORDER BY alias");
	while ($r = db_rowarr($c)) {
		echo '<tr><td class="resultrow1">' . htmlspecialchars($r[1]).' ( '.$r[2].' ) </td>';
		echo '<td>' . htmlspecialchars($r[3]).'</td>';
		echo '<td class="resultrow1">' . ($r[4] ? date("r", $r[4]) : 'never').'</td>';
		echo '<td><a href="admuser.php?act=1&amp;usr_id='.$r[0].'&amp;'.__adm_rsid.'">Edit</a></td></tr>';
	}
	unset($c);
?>
</table>
<?php require($WWW_ROOT_DISK . 'adm/admclose.html'); ?>
