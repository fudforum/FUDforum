<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: admlog.php,v 1.13 2002/12/05 21:04:18 hackie Exp $
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
	
	list($ses, $usr) = initadm();
	
	fud_use('fileio.inc');
	fud_use('err.inc');
	fud_use('logaction.inc');
	
	if ( $clear ) {
		clear_action_log();
		header("Location: admlog.php?"._rsidl."&rand=".get_random_value());
		exit();
	}

function check_data_avl($data)
{
	$data = trim($data);
	if( empty($data) && !strlen($data) ) 
		return 'no longer in the system';

	return $data;
}

function return_thread_subject($id)
{
	$res = q_singleval("SELECT subject FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."thread INNER JOIN ".$GLOBALS['DBHOST_TBL_PREFIX']."msg ON ".$GLOBALS['DBHOST_TBL_PREFIX']."thread.root_msg_id=".$GLOBALS['DBHOST_TBL_PREFIX']."msg.id WHERE ".$GLOBALS['DBHOST_TBL_PREFIX']."thread.id=".$id);
	return check_data_avl($res);
}
	
	include('admpanel.php'); 
?>
<h2>Admin Log</h2>
<a href="admlog.php?clear=1&<?php echo _rsid; ?>">Clear Log</a>
<table border=1 cellspacing=1 cellpadding=3>
<tr bgcolor="#bff8ff"><td>User</td><td>Action</td><td>Object</td><td>Time (<b>GMT</b>)</td></tr>
<?php
	$r = q("SELECT ".$GLOBALS['DBHOST_TBL_PREFIX']."users.is_mod, ".$GLOBALS['DBHOST_TBL_PREFIX']."users.alias, ".$GLOBALS['DBHOST_TBL_PREFIX']."action_log.* FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."action_log LEFT JOIN ".$GLOBALS['DBHOST_TBL_PREFIX']."users ON ".$GLOBALS['DBHOST_TBL_PREFIX']."action_log.user_id=".$GLOBALS['DBHOST_TBL_PREFIX']."users.id ORDER BY logtime DESC");
	
	while ( $obj = db_rowobj($r) ) {
		$logtime = '<td>'.gmdate('D, d M Y H:i:s', $obj->logtime).'</td>';
		
		switch( $obj->is_mod )
		{
			case 'A':
				$user_info = '<a href="../'.__fud_index_name__.'?t=usrinfo&id='.$obj->user_id.'&'._rsidl.'">'.$obj->alias.'</a> <font size="-2">[Administrator]</font>';
				break;
			case 'M':
				$user_info = '<a href="../'.__fud_index_name__.'?t=usrinfo&id='.$obj->user_id.'&'._rsidl.'">'.$obj->alias.'</a> <font size="-2">[Moderator]</font>';
				break;
			case NULL:
				$user_info = 'User no longer exists';
				break;	
			default:
				$user_info = '<a href="../'.__fud_index_name__.'?t=usrinfo&id='.$obj->user_id.'&'._rsidl.'">'.$obj->alias.'</a> <font size="-2">[Priveleged User]</font>';
				break;
		}				
		
		switch ( $obj->a_res ) {
			case "THRMOVE":
				echo '<tr><td>'.$user_info.'</td><td>Moved Topic</td><td>thread: '.return_thread_subject($obj->a_res_id).'</td>'.$logtime.'</tr>';
				break;
			case "DELREPORT":
				$subject = check_data_avl(q_singleval("SELECT subject FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."msg WHERE id=".$obj->a_res_id));
				echo '<tr><td>'.$user_info.'</td><td>Deleted Report</td><td>msg: '.$subject.'</td>'.$logtime.'</tr>';
				break;
			case "THRLOCK":
				echo '<tr><td>'.$user_info.'</td><td>Locked Topic</td><td>thread: '.return_thread_subject($obj->a_res_id).'</td>'.$logtime.'</tr>';
				break;
			case "THRUNLOCK":
				echo '<tr><td>'.$user_info.'</td><td>Unlocked Topic</td><td>thread: '.return_thread_subject($obj->a_res_id).'</td>'.$logtime.'</tr>';
				break;
			case "THRXAPPROVE":
				echo '<tr><td>'.$user_info.'</td><td>Approved Topic-X-Change</td><td>thread: '.return_thread_subject($obj->a_res_id).'</td>'.$logtime.'</tr>';
				break;
			case "THRXDECLINE":
				echo '<tr><td>'.$user_info.'</td><td>Declined Topic-X-Change</td><td>thread: '.return_thread_subject($obj->a_res_id).'</td>'.$logtime.'</tr>';
				break;
			case "THRSPLIT":
				echo '<tr><td>'.$user_info.'</td><td>Split Topic</td><td>thread: '.return_thread_subject($obj->a_res_id).'</td>'.$logtime.'</tr>';
				break;
			case "MSGEDIT":
				$subject = check_data_avl(q_singleval("SELECT subject FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."msg WHERE id=".$obj->a_res_id));
				echo '<tr><td>'.$user_info.'</td><td>Edited Message</td><td>msg: '.$subject.'</td>'.$logtime.'</tr>';
				break;
			case "DELMSG":
				echo '<tr><td>'.$user_info.'</td><td>Deleted Message</td><td>'.$obj->logaction.'</td>'.$logtime.'</tr>';
				break;
			case "DELTHR":
				echo '<tr><td>'.$user_info.'</td><td>Deleted Topic</td><td>'.$obj->logaction.'</td>'.$logtime.'</tr>';
				break;	
			case "ADDFORUM":
				$frm_name = check_data_avl(q_singleval("SELECT name FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."forum WHERE id=".$obj->a_res_id));
				echo '<tr><td>'.$user_info.'</td><td>Created Forum</td><td>forum: '.$frm_name.'</td>'.$logtime.'</tr>';
				break;
			case "SYNCFORUM":
				$frm_name = check_data_avl(q_singleval("SELECT name FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."forum WHERE id=".$obj->a_res_id));
				echo '<tr><td>'.$user_info.'</td><td>Updated Forum</td><td>forum: '.$frm_name.'</td>'.$logtime.'</tr>';
				break;
			case "DELFORUM":
				echo '<tr><td>'.$user_info.'</td><td>Deleted Forum</td><td>forum: '.$obj->logaction.'</td>'.$logtime.'</tr>';
				break;
			case "CHCATFORUM":
				$frm_name = check_data_avl(q_singleval("SELECT name FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."forum WHERE id=".$obj->a_res_id));
				echo '<tr><td>'.$user_info.'</td><td>Changed Forum Category</td><td>forum: '.$frm_name.'</td>'.$logtime.'</tr>';
				break;
			case "WRONGPASSWD":
				echo '<tr><td>'.$user_info.'</td><td>Failed login attempt for admin</td><td>From '.$obj->logaction.'</td>'.$logtime.'</tr>';
				break;
			case "DELETE_USER":
				echo '<tr><td>'.$user_info.'</td><td>Removed user account</td><td>'.$obj->logaction.'</td>'.$logtime.'</tr>';
				break;
		}
	}
	qf($r);
?>
</table>
<?php require('admclose.html'); ?>