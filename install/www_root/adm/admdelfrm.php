<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: admdelfrm.php,v 1.6 2002/08/13 11:34:58 hackie Exp $
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
	define('msg_edit', 1);

	include_once "GLOBALS.php";
		
	fud_use('forum.inc');
	fud_use('imsg.inc');
	fud_use('imsg_edt.inc');
	fud_use('th.inc');
	fud_use('fileio.inc');
	fud_use('cat.inc');
	fud_use('util.inc');
	fud_use('adm.inc', TRUE);
	fud_use('groups.inc');
	fud_use('ipoll.inc');
	
	list($ses, $usr) = initadm();
	
	cache_buster();

	$frm = new fud_forum_adm;
		
	if ( @count($HTTP_POST_VARS) > 1 ) {
		foreach($HTTP_POST_VARS as $key => $val) { 
			if ( substr($key, 0, strlen('frm_submit')) == 'frm_submit' ) {
				list($dummy, $dummy2, $frm_id) = explode('_', $key);
				if ( isset($HTTP_POST_VARS['frm_cat_'.$frm_id]) ) {
					$new_cat = $HTTP_POST_VARS['frm_cat_'.$frm_id];
					$frm->chcat($frm_id, $new_cat);
					break;
				}
			}
		}
	}
	
	if ( !empty($act) && $act=='del' && !empty($del_id) ) {
		if ( !empty($conf) && $conf=='Yes' ) {
			$frm->delete($del_id);
			header("Location: admdelfrm.php?"._rsidl);
			exit();
		}
		else if ( !empty($conf) && $conf == 'No' ) {
			header("Location: admdelfrm.php?"._rsidl);
			exit();
		}
		else {
			$frm->get($del_id);
			echo "
			<html><body bgcolor=\"#ffffff\">
				<div align=center>
				<h3>You have selected to delete this forum</h3><br>
				\"$frm->name\" which contains $frm->thread_count topics with $frm->post_count posts<br><br>
				<h3>Are you sure this is what you want to do?</h3> 
				
				<form method=\"post\" action=\"admdelfrm.php\">
					"._hs."
					<input type=\"hidden\" name=\"act\" value=\"del\">
					<input type=\"hidden\" name=\"del_id\" value=\"$del_id\">
					<table border=0 cellspacing=0 cellpadding=2>
					<tr><td><input type=\"submit\" name=\"conf\" value=\"Yes\"></td><td><input type=\"submit\" name=\"conf\" value=\"No\"></td></tr>
					</table>
				</form>
				</div>
			</html>
			";
			exit();
		}
	}
	

include('admpanel.php'); 
?>
<h2>Orphaned Forums</h2>
<form method="post">
<?php echo _hs; ?>
<table cellspacing=1 cellpadding=2>
<tr bgcolor="#e5ffe7">
	<td>Category Name</td>
	<td>Description</td>
	<td>Hidden</td>
	<td>Action</td>
	<td>Reassign To Category</td>
</tr>
<?php
	$frm->get_cat_forums(0);
	$frm->resetfrm();
	
	$i=1;
	while ( $frm->nextfrm() ) {
		$bgcolor = (($i++)%2) ? ' bgcolor="#fffee5"':'';
		echo "<tr$bgcolor><td>".$frm->name."</td><td>$frm->descr</td><td>$frm->hidden</td><td><a href=\"admdelfrm.php?act=del&del_id=$frm->id&"._rsid."\">Delete</a></td><td>";
		draw_cat_select('frm_cat_'.$frm->id, $GLOBALS['frm_cat_'.$frm->id]);
		echo "<input type=\"submit\" name=\"frm_submit_$frm->id\" value=\"Reassign\"></td></tr>\n";
	}
	
?>
</table>
</form>
<?php require('admclose.html'); ?>