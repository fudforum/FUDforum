<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: admgrouplead.php,v 1.6 2002/07/22 14:53:37 hackie Exp $
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
	fud_use('cookies.inc');
	fud_use('time.inc');
	fud_use('adm.inc', TRUE);
	fud_use('users.inc');
	fud_use('users_reg.inc');
	fud_use('widgets.inc', TRUE);
	fud_use('groups.inc');
	fud_use('is_perms.inc');

	list($ses, $adm) = initadm();

	if ( empty($group_id) ) {
		header("Location: admgroups.php?rnd=".get_random_value()."&"._rsid);
		exit();
	}	

	$grp = new fud_group;

	if ( !empty($gr_leader) ) {
		$grp->get($group_id);
		$usr = new fud_user;
		if ( !($usr_id = get_id_by_alias($gr_leader)) ) {
			if( __dbtype__ == 'pgsql' ) $gr_leader = addslashes(str_replace('\\', '\\\\', stripslashes($gr_leader)));
			$r = q("SELECT alias FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."users WHERE alias LIKE '".strtolower($gr_leader)."%' LIMIT 100");
			if( __dbtype__ == 'pgsql' ) $gr_leader = stripslashes(str_replace('\\\\', '\\', $gr_leader));
			
			if ( db_count($r) ) {
				echo "<html>
					$gr_leader isn't found, perhaps you mean one of these?<br>
					<table border=0 cellspacing=0 cellpadding=3>
					";
				while ( $obj = db_rowobj($r) ) {
					echo "<tr><td><a href=\"admgrouplead.php?gr_leader=".urlencode($obj->alias)."&group_id=$group_id&"._rsid."\">$obj->alias</a></td></tr>";
				}
				echo "</table>";
			}
			exit();
		}		
		$usr->get_user_by_id($usr_id);
		/*
		$conflist=$grp->check_member_conflicts($usr->id, $noconf);
		if ( $noconf ) unset($conflist);

		if ( $conflist && empty($noconf) ) {
			echo "
				<html>
				unable to add user <b>$usr->alias</b>, permissions conflict with the currently existing set (listed below)<br>
				<table border=0 cellspacing=0 cellpadding=0>
			";
			
			foreach($conflist as $k => $v) echo "<tr><td>resource <b>$k</b> is used via <b>$v</b></td></tr>";

			echo '</table><a href="admgrouplead.php?group_id='.$grp->id.'&gr_leader='.$usr->alias.'&noconf=1&'._rsid.'">Override current permissions</a> <a href="admgrouplead.php?group_id='.$grp->id.'&rnd='.get_random_value().'&'._rsid.'">Cancel Action</a></html>';
			exit();
		}
		*/
		$grp->add_leader($usr->id);
		$grp->rebuild_cache($usr->id);
		header("Location: admgrouplead.php?group_id=$group_id&rnd=".get_random_value()."&"._rsid);
		exit();
	}
	$grp->get($group_id);

	if ( $del ) {
		$grp->delete_member($del);
		$grp->rebuild_cache($del);
		header("Location: admgrouplead.php?group_id=$group_id&rnd=".get_random_value()."&"._rsid);
		exit();
	}
include('admpanel.php'); 
?>
<a href="admgroups.php">Back to Groups</a>
<form method="post" action="admgrouplead.php"><?php echo _hs; ?>
<input type="hidden" value="<?php echo $group_id; ?>" name="group_id">
<table border=0 cellspacing=0 cellpadding=3>
<tr><td>Group Leader</td><td><input type="text" name="gr_leader" value="<?php echo $gr_leader; ?>"></td></tr>
<tr><td colspan=2 align=right><input type="submit" name="btn_submit" value="Add"></td></tr>
</table>

<table border=1 cellspacing=1 cellpadding=3>
<tr><td>Leader Login</td><td>Action</td></tr>
<?php
	$llist = $grp->get_leader_list();
	foreach($llist as $v)
		echo "<tr><td>$v->alias</td><td>[<a href=\"admgrouplead.php?group_id=$group_id&del=$v->user_id&"._rsid."\">Remove From Group</a>]</tr>\n";
?>
</table>
</form>
<?php require('admclose.html'); ?>