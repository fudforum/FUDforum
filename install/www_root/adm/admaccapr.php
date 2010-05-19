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
	fud_use('users_adm.inc', true);

	if (isset($_GET['apr'])) {
		if (($r = db_sab('SELECT email, login FROM '.$DBHOST_TBL_PREFIX.'users WHERE id='.(int)$_GET['apr']))) {
			fud_use('adm_acc.inc');
			fud_use('iemail.inc');
			q('UPDATE '.$DBHOST_TBL_PREFIX.'users SET users_opt=users_opt & ~ 2097152 WHERE id='.(int)$_GET['apr']);
			send_email($NOTIFY_FROM, $r->email, $account_accepted_s, $account_accepted);
		}
	} else if (isset($_GET['rm']) && (int)$_GET['rm'] != 1) {
		if (($r = db_sab('SELECT email, login FROM '.$DBHOST_TBL_PREFIX.'users WHERE id='.(int)$_GET['rm']))) {
			fud_use('adm_acc.inc');
			fud_use('iemail.inc');
			send_email($NOTIFY_FROM, $r->email, $account_rejected_s, $account_rejected);
			usr_delete((int)$_GET['rm']);
		}
	}

function print_if_avail($descr, $value, $no_html=1)
{
	if (!empty($value)) {
		if ($no_html) {
			$value = htmlspecialchars($value);
		}
		return '<tr><td>'.$descr.':</td><td>'.$value.'</td></tr>';
	}
}

	require($WWW_ROOT_DISK . 'adm/header.php');
?>
<h2>Account Approval</h2>
<p>Approve or delete users who have registered (if 'New Account Moderation' is enabled in the Global Settings Manager).</p>
<table class="resulttable fulltable">
<thead><tr class="resulttopic">
	<th>Account Information</th><th align="center">Action</th>
</tr></thead>
<?php
	$i = 0;
	$c = uq('SELECT * FROM '.$DBHOST_TBL_PREFIX.'users WHERE users_opt>=2097152 AND (users_opt & 2097152) > 0 AND id > 0');
	while ($obj = db_rowobj($c)) {
		$bgcolor = ($i++%2) ? ' class="resultrow2"' : ' class="resultrow1"';
		echo '<tr'. $bgcolor .'"><td class="field"><table width="100%" '. $bgcolor .'>'.
		print_if_avail('Login', $obj->login) .
		print_if_avail('E-mail', $obj->email) .
		print_if_avail('Name', $obj->name) .
		print_if_avail('Location', $obj->location) .
		print_if_avail('Occupation', $obj->occupation) .
		print_if_avail('Interests', $obj->interests) .
		print_if_avail('Avatar', ($obj->avatar_loc), 0) .
		print_if_avail('Gender', ($obj->users_opt & 1024 ? 'Male' : ($obj->users_opt & 512 ? 'Unspecified' : 'Female'))) .
		print_if_avail('Homepage', $obj->home_page) .
		print_if_avail('Image', $obj->user_image) .
		print_if_avail('Biography', $obj->bio) .
		print_if_avail('ICQ', $obj->icq) .
		print_if_avail('AIM Handle', $obj->aim) .
		print_if_avail('Yahoo Messenger', $obj->yahoo) .
		print_if_avail('MSN Messenger', $obj->msnm) .
		print_if_avail('Jabber Handle', $obj->jabber) .
		print_if_avail('Google Chat/IM Handle', $obj->google) .
		print_if_avail('Skype Handle', $obj->skype) .
		print_if_avail('Twitter Handle', $obj->twitter) .
		print_if_avail('Signature', $obj->sig, 0) .
		print_if_avail('IP Address', long2ip($obj->reg_ip), 0) .
		'</table></td>
		<td class="fieldaction">[ <a href="admaccapr.php?apr='.$obj->id.'&amp;'.__adm_rsid.'">Approve Account</a> | <a href="admaccapr.php?rm='.$obj->id.'&amp;'.__adm_rsid.'">Delete Account</a> ]</td></tr>';
	}
	unset($c);
	if (!$i) {
		echo '<tr><td colspan="2" align="center">No pending accounts found.</td></tr>';
	}
?>
</table>
<p><a href="admuser.php?<?php echo __adm_rsid; ?>">&laquo; Back to User Administration System</a></p>
<?php require($WWW_ROOT_DISK . 'adm/footer.php'); ?>
