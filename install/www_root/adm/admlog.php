<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: admlog.php,v 1.3 2002/06/26 19:41:21 hackie Exp $
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
	fud_use('adm.inc', TRUE);
	
	list($ses, $usr) = initadm();
	
	fud_use('th.inc');
	fud_use('imsg.inc');
	fud_use('fileio.inc');
	fud_use('err.inc');
	fud_use('logaction.inc');
	fud_use('forum.inc');
	
	if ( $clear ) {
		clear_action_log();
		header("Location: admlog.php?"._rsid."&rand=".get_random_value());
		exit();
	}
	
	include('admpanel.php'); 
?>
<h2>Admin Log</h2>
<a href="admlog.php?clear=1&<? echo _rsid; ?>">Clear Log</a>
<table border=1 cellspacing=1 cellpadding=3>
<tr bgcolor="#bff8ff"><td>User</td><td>Action</td><td>Object</td><td>Time (<b>GMT</b>)</td></tr>
<?
	$r = q("SELECT fud_users.login,fud_action_log.* FROM ".$GLOBALS['MYSQL_TBL_PREFIX']."action_log LEFT JOIN ".$GLOBALS['MYSQL_TBL_PREFIX']."users ON ".$GLOBALS['MYSQL_TBL_PREFIX']."action_log.user_id=".$GLOBALS['MYSQL_TBL_PREFIX']."users.id ORDER BY logtime DESC");
	
	while ( $obj = db_rowobj($r) ) {
		$logtime = "<td>".gmdate("Y M d G:m:i (g A)", $obj->logtime)."</td>";
		
		switch ( $obj->a_res ) {
			case "THRMOVE":
				$thr = new fud_thread;
				$thr->get_by_id($obj->a_res_id);
				echo "<tr><td>$obj->login</td><td>Moved Thread</td><td>thread: $thr->subject</td>$logtime</tr>";
				break;
			case "DELREPORT":
				$msg = new fud_msg;
				$msg->get_by_id($obj->a_res_id);
				echo "<tr><td>$obj->login</td><td>Deleted Report</td><td>msg: $msg->subject</td>$logtime</tr>";
				break;
			case "THRLOCK":
				$thr = new fud_thread;
				@$thr->get_by_id($obj->a_res_id);
				echo "<tr><td>$obj->login</td><td>Locked Thread</td><td>thread: $thr->subject</td>$logtime</tr>";
				break;
			case "THRUNLOCK":
				$thr = new fud_thread;
				@$thr->get_by_id($obj->a_res_id);
				echo "<tr><td>$obj->login</td><td>Unlocked Thread</td><td>thread: $thr->subject</td>$logtime</tr>";
				break;
			case "THRXAPPROVE":
				$thr = new fud_thread;
				$thr->get_by_id($obj->a_res_id);
				echo "<tr><td>$obj->login</td><td>Approved Thread-X-Change</td><td>thread: $thr->subject</td>$logtime</tr>";
				break;
			case "THRXDECLINE":
				$thr = new fud_thread;
				$thr->get_by_id($obj->a_res_id);
				echo "<tr><td>$obj->login</td><td>Declined Thread-X-Change</td><td>thread: $thr->subject</td>$logtime</tr>";
				break;
			case "THRSPLIT":
				$thr = new fud_thread;
				$thr->get_by_id($obj->a_res_id);
				echo "<tr><td>$obj->login</td><td>Split Thread</td><td>thread: $thr->subject</td>$logtime</tr>";
				break;
			case "MSGEDIT":
				$msg = new fud_msg;
				@$msg->get_by_id($obj->a_res_id);
				echo "<tr><td>$obj->login</td><td>Edited Message</td><td>msg: $msg->subject</td>$logtime</tr>";
				break;
			case "DELMSG":
				echo "<tr><td>$obj->login</td><td>Deleted Message</td><td>$obj->logaction</td>$logtime</tr>";
				break;
			case "DELTHR":
				echo "<tr><td>$obj->login</td><td>Deleted Thread</td><td>$obj->logaction</td>$logtime</tr>";
				break;	
			case "ADDFORUM":
				$frm = new fud_forum;
				$frm->get($obj->a_res_id);
				echo "<tr><td>$obj->login</td><td>Created Forum</td><td>forum: $frm->name</td>$logtime</tr>";
				break;
			case "SYNCFORUM":
				$frm = new fud_forum;
				$frm->get($obj->a_res_id);
				echo "<tr><td>$obj->login</td><td>Updated Forum</td><td>forum: $frm->name</td>$logtime</tr>";
				break;
			case "DELFORUM":
				$frm = new fud_forum;
				$frm->get($obj->a_res_id);
				echo "<tr><td>$obj->login</td><td>Updated Forum</td><td>forum: $frm->name</td>$logtime</tr>";
				break;
			case "CHCATFORUM":
				$frm = new fud_forum;
				$frm->get($obj->a_res_id);
				echo "<tr><td>$obj->login</td><td>Changed Forum Category</td><td>forum: $frm->name</td>$logtime</tr>";
				break;
			case "WRONGPASSWD":
				echo "<tr><td>$obj->login</td><td>Failed login attempt for admin</td><td>From $obj->logaction</td>$logtime</tr>";
				break;
		}
	}
?>
</table>
<?php require('admclose.html'); ?>