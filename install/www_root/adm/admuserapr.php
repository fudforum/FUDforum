<?php
/**
* copyright            : (C) 2001-2022 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id$
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; version 2 of the License.
**/

/** Print a user property if it has a value. */
function print_if_avail($descr, $value, $no_html=1)
{
	if (!empty($value)) {
		if ($no_html) {
			$value = htmlspecialchars($value);
		}
		return '<tr><td width="30%">'. $descr .':</td><td>'. $value .'</td></tr>';
	}
}

/* main */
	require('./GLOBALS.php');
	fud_use('adm.inc', true);
	fud_use('users_adm.inc', true);

	require($WWW_ROOT_DISK .'adm/header.php');

	// Map GET (single account) to POST (selected accounts) requests.
	if (isset($_GET['apr'])) {
		$_POST['approve_selected'] = 1;
		$_POST['account'] = array($_GET['apr']);
	} else if (isset($_GET['rm'])) {
		$_POST['delete_selected'] = 1;
		$_POST['account'] = array($_GET['rm']);
	}

	// Approve selected accounts.
	if (isset($_POST['approve_selected'], $_POST['account'])) {
		foreach ($_POST['account'] as $key => $account) {
			if (($r = db_sab('SELECT email, login FROM '. $DBHOST_TBL_PREFIX .'users WHERE id='. (int)$account))) {
				fud_use('adm_acc.inc');
				usr_approve((int)$account);

				fud_use('iemail.inc');
				send_email($NOTIFY_FROM, $r->email, $account_accepted_s, $account_accepted);

				pf(successify('Account '. $r->login .' approved and user notified.'));
			}
		}
	}
	// Delete selected accounts.
	if (isset($_POST['delete_selected'], $_POST['account'])) {
		foreach ($_POST['account'] as $key => $account) {
			if (($r = db_sab('SELECT email, login, users_opt FROM '. $DBHOST_TBL_PREFIX .'users WHERE id='. (int)$account))) {
				// We should never delete Anonymous, admin or spider users.
				if ($account == 1 || ($r->users_opt & 1048576) || ($r->users_opt & 1073741824)) {
					pf(errorify('Account '. $r->login .' cannot be deleted!'));
					continue;
				}

				fud_use('adm_acc.inc');
				usr_delete((int)$account);

				fud_use('iemail.inc');
				send_email($NOTIFY_FROM, $r->email, $account_rejected_s, $account_rejected);

				pf(successify('Account '. $r->login .' deleted and user notified.'));
			}
		}
	}
?>
<h2>Account Approval</h2>
<p>
<?php
	if (!($FUD_OPT_2 & 1024)) {
		echo '"New Account Moderation" is disabled in the Global Settings Manager. Enable it to queue new accounts here for approval.';
	} else {
		echo '"New Account Moderation" is enabled. Users accounts will be queued here for approval:';
	}
?>
</p>
<form method="post" action="admuserapr.php">
<table class="resulttable fulltable">
<thead><tr class="resulttopic">
	<th>Account Information</th><th align="center" width="20%">Action</th>
</tr></thead>
<?php
	$i = 0;
	$c = uq('SELECT * FROM '. $DBHOST_TBL_PREFIX .'users WHERE users_opt>=2097152 AND '. q_bitand('users_opt', 2097152) .' > 0 AND id > 0');
	while ($r = db_rowobj($c)) {
		$i++;
		echo '<tr class="field"><td><table width="100%">' .
		print_if_avail('Login',                 $r->login) .
		print_if_avail('E-mail',                $r->email) .
		print_if_avail('Name',                  $r->name) .
		print_if_avail('Location',              $r->location) .
		print_if_avail('Occupation',            $r->occupation) .
		print_if_avail('Interests',             $r->interests) .
		print_if_avail('Avatar',               ($r->avatar_loc), 0) .
		print_if_avail('Gender',               ($r->users_opt & 1024 ? 'Male' : ($r->users_opt & 512 ? '' : 'Female'))) .
		print_if_avail('Homepage',              $r->home_page) .
		print_if_avail('Image',                 $r->user_image) .
		print_if_avail('Biography',             $r->bio) .
		print_if_avail('ICQ',                   $r->icq) .
		print_if_avail('Facebook',              $r->facebook) .
		print_if_avail('Yahoo Messenger',       $r->yahoo) .
		print_if_avail('Jabber Handle',         $r->jabber) .
		print_if_avail('Google Chat/IM Handle', $r->google) .
		print_if_avail('Skype Handle',          $r->skype) .
		print_if_avail('Twitter Handle',        $r->twitter) .
		print_if_avail('Signature',             $r->sig, 0) .
		print_if_avail('IP Address', '<a href="../'. __fud_index_name__ .'?t=ip&amp;ip='. $r->registration_ip .'&amp;'. __adm_rsid .'" title="Analyse IP usage">'.              $r->registration_ip, 0) .'</a>' .
		'</table></td>
		<td>
			<input type="checkbox" id="account[]" name="account[]" value="'. $r->id .'" /><br />
			[ <a href="admuserapr.php?apr='. $r->id .'&amp;'. __adm_rsid .'">Approve</a> ]<br />
			[ <a href="admuserapr.php?rm='.  $r->id .'&amp;'. __adm_rsid .'">Delete</a> ]
		</td></tr>';
	}
	echo '<tr><td colspan="2" align="center">';
	if (!$i) {
		echo 'No pending accounts found.';
	} else {
		echo '<input type="submit" name="approve_selected" value="Approve selected" /> &nbsp; ';
		echo '<input type="submit" name="delete_selected"  value="Delete selected"  />';
		echo _hs;
	}
?>
	</td></tr>
</table>
</form>
<br />
<p><a href="admuser.php?<?php echo __adm_rsid; ?>">&laquo; Back to User Administration System</a></p>
<?php require($WWW_ROOT_DISK .'adm/footer.php'); ?>
