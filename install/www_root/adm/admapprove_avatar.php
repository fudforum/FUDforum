<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: admapprove_avatar.php,v 1.9 2003/04/28 12:58:08 hackie Exp $
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
	define('no_inline', 1);

	require('GLOBALS.php');
	fud_use('adm.inc', true);
	fud_use('ssu.inc');

	if (isset($_GET['usr_id'])) {
		usr_approve_avatar((int)$_GET['usr_id']);
	} else if (isset($_GET['del'])) {
		usr_unapprove_avatar((int)$_GET['del']);
	}

	require($WWW_ROOT_DISK . 'adm/admpanel.php'); 
?>
<h2>Avatar Approval System</h2>	
<table border=0 cellspacing=0 cellpadding=3>
<?php
	$a = 0;
	
	$c = uq('SELECT id, avatar_loc, alias FROM '.$GLOBALS['DBHOST_TBL_PREFIX'].'users WHERE avatar_approved=\'N\' ORDER BY id');
	while ($r = db_rowarr($c)) {
		$a = 1;
		echo '<tr bgcolor="#bff8ff"><td>'.$r[2].'</td><td>[<a href="admapprove_avatar.php?usr_id='.$r[0].'&'._rsidl.'">Approve</a>] [<a href="admapprove_avatar.php?del='.$r[0].'&'._rsidl.'">Delete</a>]</td></tr>';
		echo '<tr bgcolor="#bff8ff"><td align="center" colspan=2>'.$r[1].'</td></tr>';
	}
	qf($c);
?>
</table>
<?php
	if (!$a) {
		echo 'There are no avatars pending approval.';
	}

	require($WWW_ROOT_DISK . 'adm/admclose.html');
?>