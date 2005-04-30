<?php
/**
* copyright            : (C) 2001-2004 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: admslist.php,v 1.1 2005/04/30 17:21:48 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; either version 2 of the License, or
* (at your option) any later version.
**/

	require('./GLOBALS.php');
	fud_use('adm.inc', true);

function print_ul($qry)
{
	$i = 0;
	$c = uq($qry);
	while ($r = db_rowarr($c)) {
		echo ++$i.') <b>'.$r[1].'</b> <a href="../'.__fud_index_name__.'?t=usrinfo&id='.$r[0].'&'.__adm_rsidl.'">View Profile</a> | 
		<a href="admuser.php?act=1&usr_id='.$r[0].'&'.__adm_rsidl.'">Edit</a><br />';
	}
}
	require($WWW_ROOT_DISK . 'adm/admpanel.php');
?>
<h2>Users with elevated permissions</h2>
<table class="datatable">
<tr>
	<th class="fieldtopic">Administrators</th>
	<th>Moderators</th>
	<th>Group Leaders</th>
</tr>
<tr>
<td valign="top" class="resultrow1">
<?php
	print_ul("SELECT id, alias FROM ".$DBHOST_TBL_PREFIX."users WHERE users_opt & 1048576 ORDER BY alias");
?>
</td>
<td valign="top" class="resultrow2">
<?php
	print_ul("SELECT u.id, u.alias FROM ".$DBHOST_TBL_PREFIX."mod m
		INNER JOIN ".$DBHOST_TBL_PREFIX."users u ON u.id=m.user_id
		GROUP BY u.id ORDER BY u.alias");
?>
</td>
<td valign="top" class="resultrow1">
<?php
	print_ul("SELECT u.id, u.alias FROM ".$DBHOST_TBL_PREFIX."group_members g
		INNER JOIN ".$DBHOST_TBL_PREFIX."users u ON u.id=g.user_id
		WHERE group_members_opt & 131072
		GROUP BY u.id ORDER BY u.alias");
?>
</td>
</tr>
</table>
<?php require($WWW_ROOT_DISK . 'adm/admclose.html'); ?>