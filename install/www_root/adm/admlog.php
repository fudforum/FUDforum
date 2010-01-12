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
	require($WWW_ROOT_DISK . 'adm/header.php');

	if (isset($_GET['clear'])) {
		q('DELETE FROM '.$DBHOST_TBL_PREFIX.'action_log');
		echo successify('Action log successfully cleared.');
	}

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
	$res = q_singleval('SELECT m.subject FROM '.$DBHOST_TBL_PREFIX.'thread t INNER JOIN '.$GLOBALS['DBHOST_TBL_PREFIX'].'msg m ON t.root_msg_id=m.id WHERE t.id='.$id);
	return check_data_avl($res);
}

function return_msg_subject($id)
{
	return check_data_avl(q_singleval('SELECT subject FROM '.$DBHOST_TBL_PREFIX.'msg WHERE id='.$id));
}

function return_forum_name($id)
{
	return check_data_avl(q_singleval('SELECT name FROM '.$DBHOST_TBL_PREFIX.'forum WHERE id='.$id));
}

?>
<h2>Action Log Viewer</h2>
[ <a href="admlog.php?clear=1&amp;<?php echo __adm_rsid; ?>">Clear Admin Log</a> ]
<table class="resulttable fulltable">
<tr class="resulttopic"><td>User</td><td>Action</td><td>Object</td><td>Time (<b>GMT</b>)</td></tr>
<?php
	$i = 0;
	$c = q('SELECT u.users_opt, u.alias, al.* FROM '.$DBHOST_TBL_PREFIX.'action_log al LEFT JOIN '.$DBHOST_TBL_PREFIX.'users u ON al.user_id=u.id ORDER BY logtime DESC');

	while ($obj = db_rowobj($c)) {
		$logtime = '<td>'.gmdate('D, d M Y H:i:s', $obj->logtime).'</td>';

		if ($obj->users_opt == null) {
			if ($obj->a_res == 'WRONGPASSWD') {
				$user_info = 'Unauthenticated User';
			} else {
				$user_info = 'User is no longer in the system.';
			}
		} else if ($obj->users_opt & 1048576) {
			$user_info = '<a href="../'.__fud_index_name__.'?t=usrinfo&amp;id='.$obj->user_id.'&amp;'.__adm_rsid.'">'.$obj->alias.'</a> <font size="-2">[Administrator]</font>';
		} else if ($obj->users_opt & 524288) {
			$user_info = '<a href="../'.__fud_index_name__.'?t=usrinfo&amp;id='.$obj->user_id.'&amp;'.__adm_rsid.'">'.$obj->alias.'</a> <font size="-2">[Moderator]</font>';
		} else {
			$user_info = '<a href="../'.__fud_index_name__.'?t=usrinfo&amp;id='.$obj->user_id.'&amp;'.__adm_rsid.'">'.$obj->alias.'</a> <font size="-2">[Priveleged User]</font>';
		}
		echo '<tr class="field"><td>'.$user_info.'</td>';

		switch ($obj->a_res) {
			case 'THRMOVE':
				echo '<td>Moved Topic</td><td>thread: '.return_thread_subject($obj->a_res_id).'</td>';
				break;
			case 'DELREPORT':
				echo '<td>Deleted Report</td><td>msg: '.return_msg_subject($obj->a_res_id).'</td>';
				break;
			case 'THRLOCK':
				echo '<td>Locked Topic</td><td>thread: '.return_thread_subject($obj->a_res_id).'</td>';
				break;
			case 'THRUNLOCK':
				echo '<td>Unlocked Topic</td><td>thread: '.return_thread_subject($obj->a_res_id).'</td>';
				break;
			case 'THRXREQUEST':
				echo '<td>Requested Topic-X-Change</td><td>thread: '.return_thread_subject($obj->a_res_id).'</td>';
				break;
			case 'THRXAPPROVE':
				echo '<td>Approved Topic-X-Change</td><td>thread: '.return_thread_subject($obj->a_res_id).'</td>';
				break;
			case 'THRXDECLINE':
				echo '<td>Declined Topic-X-Change</td><td>thread: '.return_thread_subject($obj->a_res_id).'</td>';
				break;
			case 'THRSPLIT':
				echo '<td>Split Topic</td><td>thread: '.return_thread_subject($obj->a_res_id).'</td>';
				break;
			case 'THRMERGE':
				echo '<td>Merged Topic</td><td>thread: "'.return_thread_subject($obj->a_res_id).'" is a result of merging "'.$obj->logaction.'" topics.</td>';
				break;
			case 'MSGEDIT':
				echo '<td>Edited Message</td><td>msg: '.return_msg_subject($obj->a_res_id).'</td>';
				break;
			case 'APPROVEMSG':
				echo '<td>Approved Message</td><td>msg: '.return_msg_subject($obj->a_res_id).'</td>';
				break;
			case 'DELMSG':
				echo '<td>Deleted Message</td><td>'.$obj->logaction.'</td>';
				break;
			case 'DELRATING':
				echo '<td>Deleted Rating</td><td>thread: '.return_thread_subject($obj->a_res_id).'</td>';
				break;
			case 'DELTHR':
				echo '<td>Deleted Topic</td><td>'.$obj->logaction.'</td>';
				break;
			case 'ADDFORUM':
				echo '<td>Created Forum</td><td>forum: '.return_forum_name($obj->a_res_id).'</td>';
				break;
			case 'SYNCFORUM':
				echo '<td>Updated Forum</td><td>forum: '.return_forum_name($obj->a_res_id).'</td>';
				break;
			case 'FRMMARKDEL':
				echo '<td>Deleted Forum</td><td>forum: '.$obj->logaction.'</td>';
				break;
			case 'CHCATFORUM':
				echo '<td>Changed Forum Category</td><td>forum: '.return_forum_name($obj->a_res_id).'</td>';
				break;
			case 'WRONGPASSWD':
				echo '<td>Failed login attempt</td><td>'.$obj->logaction.'</td>';
				break;
			case 'DELETE_USER':
				echo '<td>Removed user account</td><td>'.$obj->logaction.'</td>';
				break;
			case 'SEND_ECONF':
				echo '<td>Sent E-mail Confirmation</td><td>to user: '.$obj->logaction.'</td>';
				break;
			case 'ADM_RESET_PASSWD':
				echo '<td>Admin Reset Password</td><td>for user: '.$obj->logaction.'</td>';
				break;
			case 'ADM_SET_PASSWD':
				echo '<td>Admin Changed Password</td><td>for user: '.$obj->logaction.'</td>';
				break;
			case 'CHANGE_PASSWD':
				echo '<td>User Changed Own Password</td><td>ip address: '.$obj->logaction.'</td>';
				break;
			default:
				echo '<td>'. $obj->a_res .'</td><td>'.$obj->logaction.'</td>';
				break;
		}

		echo $logtime.'</tr>';
		$i++;
	}
	unset($c);
?>
<?php
	if (!$i) {
		echo '<tr class="field"><td colspan="4"><center>No records found.</center></td></tr>';
	}
	echo '</table>';

	require($WWW_ROOT_DISK . 'adm/footer.php');
?>
