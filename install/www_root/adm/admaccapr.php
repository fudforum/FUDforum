<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: admaccapr.php,v 1.1 2002/11/21 21:42:45 hackie Exp $
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

	include_once "GLOBALS.php";
	
	fud_use('db.inc');
	fud_use('adm.inc', true);
	fud_use('customtags.inc');
	fud_use('private.inc');
	
	list($ses, $usr) = initadm();

	if (!empty($HTTP_GET_VARS['apr']) && is_numeric($HTTP_GET_VARS['apr'])) {
		$user = new fud_user_adm;
		$user->id = $HTTP_GET_VARS['apr'];
		$user->approve_reg();
	} else if (!empty($HTTP_GET_VARS['rm']) && is_numeric($HTTP_GET_VARS['rm'])) {
		$user = new fud_user_adm;
		$user->id = $HTTP_GET_VARS['rm'];
		$user->delete_user();
	}

function print_if_avail($descr, $value)
{
	if (!empty($value)) {
		return $descr.': '.$value.'<br>';
	} else {
		return;
	}
}

	include('admpanel.php'); 	
?>	
<div style="font-size: xx-large; font-weight: bold;">Account Approval</div>
<?php
	 $r = q("SELECT * FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."users WHERE acc_status='P'");
	 if (db_count($r)) {
	 	echo '<table cellspacing=0 cellpadding=5 border=0><tr bgcolor="#bff8ff"><td><b>Account Information</b></td><td><b>Action</b></td></tr>';
		while ($obj = db_rowobj($r)) {
			echo '<tr><td style="font-size: smaller; border-bottom: 3px double black">
			Login: '.htmlspecialchars($obj->login).'<br>
			E-mail: '.$obj->email.'<br>
			Name: '.$obj->name.'<br>' .
			print_if_avail("Location", $obj->location) .
			print_if_avail("Interests", $obj->interests) .
			print_if_avail("Occupation", $obj->occupation) .
			print_if_avail("Gender", $obj->gender) .
			print_if_avail("ICQ UIN", $obj->icq) .
			print_if_avail("AIM", $obj->aim) .
			print_if_avail("MSN Messanger", $obj->msnm) .
			print_if_avail("Jabber", $obj->jabber) .
			print_if_avail("Birth Date", $obj->bday) .
			print_if_avail("Signature", $obj->sig) .
			'</td>
			<td valign="top" style="border-bottom: 3px double black">[ <a href="admaccapr.php?apr='.$obj->id.'&'._rsid.'">Approve Account</a> | <a href="admaccapr.php?rm='.$obj->id.'&'._rsid.'">Delete Account</a> ]</td></tr>';
		} 
		echo '</table>';
	 } else {
	 	echo "no accounts pending review";
	 }
	 qf($r);
	 
	 readfile("admclose.html");
?>