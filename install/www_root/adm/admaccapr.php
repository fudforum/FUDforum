<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: admaccapr.php,v 1.2 2003/04/29 14:59:18 hackie Exp $
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
	fud_use('users_adm.inc', true);

	if (isset($_GET['apr'])) {
		q('UPDATE '.$GLOBALS['DBHOST_TBL_PREFIX'].'users SET acc_status=\'A\' WHERE id='.(int)$_GET['apr']);
	} else if (isset($_GET['rm'])) {
		usr_delete((int)$_GET['rm']);
	}

function print_if_avail($descr, $value, $no_html=1)
{
	if (!empty($value)) {
		if ($no_html) {
			$value = htmlspecialchars($value);
		}
		return $descr.': '.$value.'<br>';
	} else {
		return;
	}
}

	require($WWW_ROOT_DISK . 'adm/admpanel.php'); 	
?>	
<div style="font-size: xx-large; font-weight: bold;">Account Approval</div>
<table cellspacing=0 cellpadding=5 border=0><tr bgcolor="#bff8ff"><td><b>Account Information</b></td><td><b>Action</b></td></tr>
<?php
	$c = uq('SELECT * FROM '.$GLOBALS['DBHOST_TBL_PREFIX'].'users WHERE acc_status=\'P\'');
	while ($obj = db_rowobj($c)) {
		echo '<tr><td style="font-size: smaller; border-bottom: 3px double black">'.
		print_if_avail('Login', $obj->login) .
		print_if_avail('E-mail', $obj->email) .
		print_if_avail('Name', $obj->name) .
		print_if_avail('Location', $obj->location) .
		print_if_avail('Interests', $obj->interests) .
		print_if_avail('Occupation', $obj->occupation) .
		print_if_avail('Gender', $obj->gender) .
		print_if_avail('ICQ UIN', $obj->icq) .
		print_if_avail('AIM', $obj->aim) .
		print_if_avail('MSN Messanger', $obj->msnm) .
		print_if_avail('Jabber', $obj->jabber) .
		print_if_avail('Birth Date', $obj->bday) .
		print_if_avail('Signature', $obj->sig, 0) .
		'</td>
		<td valign="top" style="border-bottom: 3px double black">[ <a href="admaccapr.php?apr='.$obj->id.'&'._rsidl.'">Approve Account</a> | <a href="admaccapr.php?rm='.$obj->id.'&'._rsidl.'">Delete Account</a> ]</td></tr>';
	}
	qf($c);
?>
</table>
<?php require($WWW_ROOT_DISK . 'adm/admclose.html'); ?>