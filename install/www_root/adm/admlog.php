<?php
/**
* copyright            : (C) 2001-2021 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id$
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; version 2 of the License.
**/

function check_data_avl($data)
{
	$data = trim($data);
	if (empty($data) && !strlen($data)) {
		return 'no longer in system';
	}

	return $data;
}

function return_thread_subject($id)
{
	$res = q_singleval('SELECT m.subject FROM '. $GLOBALS['DBHOST_TBL_PREFIX'] .'thread t INNER JOIN '. $GLOBALS['DBHOST_TBL_PREFIX'] .'msg m ON t.root_msg_id=m.id WHERE t.id='. $id);
	return check_data_avl($res);
}

function return_msg_subject($id)
{
	return check_data_avl(q_singleval('SELECT subject FROM '. $GLOBALS['DBHOST_TBL_PREFIX'] .'msg WHERE id='. $id));
}

function return_forum_name($id)
{
	return check_data_avl(q_singleval('SELECT name FROM '. $GLOBALS['DBHOST_TBL_PREFIX'] .'forum WHERE id='. $id));
}

function return_cat_name($id)
{
	return check_data_avl(q_singleval('SELECT name FROM '. $GLOBALS['DBHOST_TBL_PREFIX'] .'cat WHERE id='. $id));
}

function return_group_name($id)
{
	return check_data_avl(q_singleval('SELECT name FROM '. $GLOBALS['DBHOST_TBL_PREFIX'] .'groups WHERE id='. $id));
}

/* main */
	require('./GLOBALS.php');
	fud_use('adm.inc', true);

	require($WWW_ROOT_DISK .'adm/header.php');

	if (isset($_GET['clear'])) {
		q('DELETE FROM '. $DBHOST_TBL_PREFIX .'action_log');
		fud_use('logaction.inc');
		logaction(_uid, 'Cleared action log');
		echo successify('Action log was successfully cleared.');
	}

?>
<h2>Action Log Viewer</h2>

<fieldset class="fieldtopic">
<legend><b>Filter by user</b></legend>
<table width="100%"><tr><td>
<form method="post" action="admlog.php">
	<?php echo _hs; ?>
	<?php $log_user = isset($_POST['log_user']) ? $_POST['log_user'] : ''; ?>
	<input type="search" id="log_user" name="log_user" value="<?php echo $log_user; ?>" />
	<input type="submit" value="Go" name="frm_submit" />
</form>
<style>
	.ui-autocomplete-loading { background: white url("../theme/default/images/ajax-loader.gif") right center no-repeat; }
</style>
<script>
	jQuery(function() {
		jQuery("#log_user").autocomplete({
			source: "../index.php?t=autocomplete&lookup=alias", minLength: 1
		});
	});
</script>
</td><td align="right">
	[ <a href="admlog.php?clear=1&amp;<?php echo __adm_rsid; ?>">Clear Action Log</a> ]
</td></tr></table>
</fieldset>

<table class="resulttable fulltable">
<thead><tr class="resulttopic">
	<th>User</th><th>Action</th><th>Object</th><th>Time (<i>UTC</i>)</th>
</tr></thead>
<?php
	$i = 0;
	$c = q('SELECT u.users_opt, u.alias, al.* FROM '. $DBHOST_TBL_PREFIX .'action_log al LEFT JOIN '. $DBHOST_TBL_PREFIX .'users u ON al.user_id=u.id ORDER BY logtime DESC');

	while ($obj = db_rowobj($c)) {
		if (!empty($log_user) && $log_user !== ($obj->alias)) {
			continue;	// Filter log entry.
		}
		
		// Encode HTML in logaction.
		$obj->logaction = htmlentities($obj->logaction);

		$logtime = '<td>'. gmdate('D, d M Y H:i:s', $obj->logtime) .'</td>';

		if ($obj->users_opt == null) {
			if ($obj->a_res == 'WRONGPASSWD') {
				$user_info = 'Unauthenticated user';
			} else {
				$user_info = 'New or deleted user';
			}
		} else if ($obj->users_opt & 1048576) {
			$user_info = '<a href="admuser.php?usr_id='. $obj->user_id .'&amp;act=m&amp;'. __adm_rsid .'">'. $obj->alias .'</a> <span class="tiny">[Administrator]</span>';
		} else if ($obj->users_opt & 524288) {
			$user_info = '<a href="admuser.php?usr_id='. $obj->user_id .'&amp;act=m&amp;'. __adm_rsid .'">'. $obj->alias .'</a> <span class="tiny">[Moderator]</span>';
		} else {
			$user_info = '<a href="admuser.php?usr_id='. $obj->user_id .'&amp;act=m&amp;'. __adm_rsid .'">'. $obj->alias .'</a> <span class="tiny">[Priveleged User]</span>';
		}
		echo '<tr class="field"><td>'. $user_info .'</td>';

		switch ($obj->a_res) {
			case 'THRMOVE':
				echo '<td>Moved Topic</td><td>thread: '. return_thread_subject($obj->a_res_id) .'</td>';
				break;
			case 'DELREPORT':
				echo '<td>Deleted Report</td><td>msg: '. return_msg_subject($obj->a_res_id) .'</td>';
				break;
			case 'THRLOCK':
				echo '<td>Locked Topic</td><td>thread: '. return_thread_subject($obj->a_res_id) .'</td>';
				break;
			case 'THRUNLOCK':
				echo '<td>Unlocked Topic</td><td>thread: '. return_thread_subject($obj->a_res_id) .'</td>';
				break;
			case 'THRXREQUEST':
				echo '<td>Requested Topic-X-Change</td><td>thread: '. return_thread_subject($obj->a_res_id) .'</td>';
				break;
			case 'THRXAPPROVE':
				echo '<td>Approved Topic-X-Change</td><td>thread: '. return_thread_subject($obj->a_res_id) .'</td>';
				break;
			case 'THRXDECLINE':
				echo '<td>Declined Topic-X-Change</td><td>thread: '. return_thread_subject($obj->a_res_id) .'</td>';
				break;
			case 'THRSPLIT':
				echo '<td>Split Topic</td><td>thread: '. return_thread_subject($obj->a_res_id) .'</td>';
				break;
			case 'THRMERGE':
				echo '<td>Merged Topic</td><td>thread: "'. return_thread_subject($obj->a_res_id) .'" is a result of merging "'. $obj->logaction .'" topics.</td>';
				break;
			case 'MSGEDIT':
				echo '<td>Edited Message</td><td>msg: '. return_msg_subject($obj->a_res_id) .'</td>';
				break;
			case 'APPROVEMSG':
				echo '<td>Approved Message</td><td>msg: '. return_msg_subject($obj->a_res_id) .'</td>';
				break;
			case 'DELMSG':
				echo '<td>Deleted Message</td><td>'. $obj->logaction .'</td>';
				break;
			case 'DELRATING':
				echo '<td>Deleted Rating</td><td>thread: '. return_thread_subject($obj->a_res_id) .'</td>';
				break;
			case 'DELTHR':
				echo '<td>Deleted Topic</td><td>'. $obj->logaction .'</td>';
				break;
			case 'ADDFORUM':
				echo '<td>Created Forum</td><td>forum: '. return_forum_name($obj->a_res_id) .'</td>';
				break;
			case 'SYNCFORUM':
				echo '<td>Updated Forum</td><td>forum: '. return_forum_name($obj->a_res_id) .'</td>';
				break;
			case 'FRMMARKDEL':
				echo '<td>Deleted Forum</td><td>forum: '. return_forum_name($obj->a_res_id) .'</td>';
				break;
			case 'CHCATFORUM':
				echo '<td>Changed Forum Category</td><td>forum: '. return_forum_name($obj->a_res_id) .'</td>';
				break;
			case 'ADDCAT':
				echo '<td>Created Category</td><td>cat: '. return_cat_name($obj->a_res_id) .'</td>';
				break;
			case 'DELCAT':
				echo '<td>Deleted Category</td><td>cat: '. return_cat_name($obj->a_res_id) .'</td>';
				break;
			case 'ADDGRP':
				echo '<td>Added member</td><td>"'. $obj->logaction .'" in group "'. return_group_name($obj->a_res_id) .'"</td>';
				break;
			case 'DELGRP':
				echo '<td>Deleted member</td><td>"'. $obj->logaction .'" in group "'. return_group_name($obj->a_res_id) .'"</td>';
				break;
			case 'EDITGRP':
				echo '<td>Edited member</td><td>"'. $obj->logaction .'" in group "'. return_group_name($obj->a_res_id) .'"</td>';
				break;
			case 'WRONGPASSWD':
				echo '<td>Failed login attempt</td><td>'. $obj->logaction .'</td>';
				break;
			case 'CREATE_USER':
				echo '<td>Created user account</td><td>'. $obj->logaction .'</td>';
				break;
			case 'DELETE_USER':
				echo '<td>Removed user account</td><td>'. $obj->logaction .'</td>';
				break;
			case 'MERGE_USER':
				echo '<td>Merged and removed user account</td><td>'. $obj->logaction .'</td>';
				break;
			case 'CHANGE_USER':
				echo '<td>Changed user login</td><td>'. $obj->logaction .'</td>';
				break;
			case 'SEND_ECONF':
				echo '<td>Sent E-mail Confirmation</td><td>to user: '. $obj->logaction .'</td>';
				break;
			case 'ADM_RESET_PASSWD':
				echo '<td>Admin Reset Password</td><td>for user: '. $obj->logaction .'</td>';
				break;
			case 'ADM_SET_PASSWD':
				echo '<td>Admin Changed Password</td><td>for user: '. $obj->logaction .'</td>';
				break;
			case 'CHANGE_PASSWD':
				echo '<td>User Changed Own Password</td><td>ip address: '. $obj->logaction .'</td>';
				break;
			case 'EMAILCONFIRMED':
				echo '<td>E-Mail adress confirmed</td><td>'. $obj->logaction .'</td>';
				break;
			default:
				echo '<td>'. $obj->a_res .'</td><td>'. $obj->logaction .'</td>';
				break;
		}

		echo $logtime.'</tr>';
		$i++;
	}
	unset($c);

	if (!$i) {
		echo '<tr class="field"><td colspan="4"><center>No records found.</center></td></tr>';
	}
	echo '</table>';

	require($WWW_ROOT_DISK .'adm/footer.php');
?>
