<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: admgroups.php,v 1.11 2002/09/18 20:52:08 hackie Exp $
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
	fud_use('widgets.inc', true);	
	fud_use('groups.inc');
	fud_use('forum.inc');
	fud_use('adm.inc', true);
	
	list($ses, $adm) = initadm();
	
	if ( $btn_cancel ) {
		header("Location: admgroups.php?rnd=".get_random_value()."&"._rsidl);
		exit();
	}
		
	/* check for errors */
	$error = 0;
	if ( $btn_submit ) {
		foreach($GLOBALS['__GROUPS_INC']['permlist'] as $k => $v) { 
			if ( $HTTP_POST_VARS[$k] == 'I' && !$HTTP_POST_VARS['gr_inherit_id'] ) { 
				$error_reason = "One of your permissions is set to Inherit, however you have not selected a group to inherit from";
				$error = 1; 
				break; 
			}
		}
	}
	
	$grp = new fud_group;
	if ( $btn_submit && !$error  ) {
		if ( empty($edit) ) { /* create new group */
			$grp->name = $gr_name;
			$grp->joinmode = $gr_joinmode;
			$grp->inherit_id=$gr_inherit_id;
			$grp->fetch_perms('');
			$grp->add(NULL, NULL, NULL, $gr_ramasks);
		}
		else { /* update an existing group */
			$grp->get($edit);
			if( !$grp->check_circular_inh($gr_inherit_id) ) 
				exit('Circular Inheritence');
			$grp->fetch_perms('');
			$grp->name = $gr_name;
			$grp->inherit_id=$gr_inherit_id;
			$grp->joinmode = $gr_joinmode;
			$grp->sync();
		}
		
		
		if ( !$grp->res ) $grp->res = 'NONE';
		/* make rslist */
		if ( $grp->res == 'NONE' && is_array($HTTP_POST_VARS['gr_resource']) ) {
			foreach($HTTP_POST_VARS['gr_resource'] as $k => $v) { 
				list($type, $id) = explode(':', $v);
				$gr_list[$type][$id] = $id;
			}
			$grp->add_resource_list($gr_list);
		}
		
		/* determine how long this could take */
		if ( q_singleval("SELECT COUNT(*) FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."group_members WHERE group_id=$grp->id") > 100 ) $dlg = 1;
		if ( $dlg ) {
			$ts = __request_timestamp__;
			echo "<html>
			Please wait, this might take few minutes<br>\n
			<br>
			";
			flush();
		}
		
		$grp->rebuild_cache();
		if ( $dlg ) {
			$te = __request_timestamp__;
			$tt = (($te-$ts)/60);
			echo "Done, return to <a href=\"admgroups.php?rnd=".get_random_value()."\">group manager</a><br><font size=-1>cache for group $grp->name rebuilt in $tt min (".($te-$ts)." sec)</font>\n</html>";
		}
		else
		header("Location: admgroups.php?rnd=".get_random_value()."&"._rsidl);
		exit();
	}
	
	if ( $del ) {
		$grp->get($del);
		if ( $grp->res == 'NONE' ) $grp->delete();
		header("Location: admgroups.php?rnd=".get_random_value()."&"._rsidl);
		exit();
	}
	
	if ( $edit ) {
		$grp->get($edit);
		if ( !$prevloaded ) {
			$gr_name = $grp->name;
			$gr_joinmode = $grp->joinmode;
		}
		
		$perms = $grp->get_perms();
		if ( !($pret = $grp->resolve_perms(false)) ) $err = 1;
		$in_perms = $pret['perms'];
		
		if ( $prevloaded ) {
			$grp->inherit_id = $gr_inherit_id;
			foreach($HTTP_POST_VARS as $k => $v) {
				if ( substr($k, 0, 2) != 'p_' ) continue;
				$perms[$k] = $v;
			}
		}
	}
	
	if ( $rebuild ) {
		$grp->get($rebuild);
		$grp->rebuild_cache();
		exit("done");
	}
	
	if ( $rebuildall ) {
		rebuild_group_cache();
		exit('done');
	}
	include('admpanel.php'); 
?>
<table border=0 cellspacing=0>
<?php
	if ( $err ) {
		echo "<font color=\"red\">You have a circular dependancy!</font><br>";
	}
	
	if ( $error_reason ) {
		echo "<font color=\"red\">$error_reason</font><br>";
	}
	
?>
<form method="post" action="admgroups.php?rnd=<?php echo get_random_value(); ?>">
<?php echo _hs; ?>
<input type="hidden" name="edit" value="<?php echo $edit; ?>">
<tr><td>Group Name: </td><td><input type="text" name="gr_name" value="<?php echo $gr_name; ?>"></td></tr>
<?php if( $grp->res != 'global' ) { ?>
<tr><td valign=top>Group Resources: </td>
	<td>
	<?php if ( $edit && $grp->res!='NONE' ) {
		switch ( $grp->res ) {
			case "forum":
				$frm = new fud_forum;
				$frm->get($grp->res_id);
				echo "FORUM: ".$frm->name;
				break;
		}
	
	} else { ?>
	<select MULTIPLE name="gr_resource[]" size=10>
	<?php 
		if ( !empty($edit) && !$prevloaded) {
			$grp->get($edit);
			$rslist = $grp->get_resources_by_rsid();
		}
		else if ( $prevloaded ) {
			 if ( $grp->res == 'NONE' && is_array($HTTP_POST_VARS['gr_resource']) ) {
				foreach($HTTP_POST_VARS['gr_resource'] as $k => $v) {
					list($type, $id) = explode(':', $v);
					$rslist[$type][$id] = $id;
				}
			 }
			                                                                                                                                                                           
		}
		
		$r = q("SELECT ".$GLOBALS['DBHOST_TBL_PREFIX']."forum.id, ".$GLOBALS['DBHOST_TBL_PREFIX']."forum.name FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."forum INNER JOIN ".$GLOBALS['DBHOST_TBL_PREFIX']."cat ON ".$GLOBALS['DBHOST_TBL_PREFIX']."forum.cat_id=".$GLOBALS['DBHOST_TBL_PREFIX']."cat.id ORDER BY ".$GLOBALS['DBHOST_TBL_PREFIX']."cat.view_order, ".$GLOBALS['DBHOST_TBL_PREFIX']."forum.view_order");
		while ( $obj = db_rowobj($r) ) {
			$rsname = "forum:$obj->id";
			if ( isset($rslist['forum'][$obj->id]) ) 
				$selected = ' selected';
			else
				$selected = '';
			echo '<option value="'.$rsname.'"'.$selected.'>FORUM: '.$obj->name.'</option>';
		}
		
	?>
	</select>
	<?php } ?>
	</td>
</tr>
<tr><td>Inherit From: </td>
	<td>
	<select name="gr_inherit_id">
	<option value="0">No where</option>
	<?php
		$r = q("SELECT * FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."groups ORDER BY id");
		while ( $obj = db_rowobj($r) ) {
			if ( !empty($edit) && $edit==$obj->id ) continue;
			if ( !empty($edit) && $obj->id==$grp->inherit_id ) 
				$selected = ' selected';
			else
				$selected = '';
			echo "<option value=\"$obj->id\"$selected>$obj->name</option>\n";
		}
	?>
	</select>
	</td>
</tr>
<?php }

if ( !$edit ) {
?>
<tr>
	<td>Anonymous and Registered Masks</td>
	<td><? draw_select('gr_ramasks', "No\nYes", "\n1", $gr_ramasks); ?></td>
</tr>
<?php
} /* !$edit */

	$perm_header = '
	    <td align=center>Visible</td>
	    <td align=center>Read</td>
	    <td align=center>Post</td>
	    <td align=center>Reply</td>
	    <td align=center>Edit</td>
	    <td align=center>Delete</td>
	    <td align=center>Sticky posts</td>
	    <td align=center>Create polls</td>
	    <td align=center>Attach files</td>
	    <td align=center>Vote</td>
	    <td align=center>Rate topics</td>
	    <td align=center>Split topics</td>
	    <td align=center>Lock topics</td>
	    <td align=center>Move topics</td>
	    <td align=center>Use smilies</td>
	    <td align=center>Use images tags</td>
	    ';
?>
<tr><td valign=top>Permissions: </td><td>
	<?php if ( !empty($edit) ) { ?>
	<table border=1 cellspacing=1 cellpadding=3>
	    <tr><td colspan=16 align=middle>Via Inheritance</td></tr>
	    <?php 
	    	echo "<tr style=\"font-size: x-small;\">$perm_header</tr>";
	    	echo "<tr>".draw_perm_table($pret)."</tr>\n"; 
	    ?>
	</table>
	<?php } ?>
	<table border=1 cellspacing=1 cellpadding=3>
	    <tr><?php
	    	echo "<tr style=\"font-size: x-small;\">$perm_header</tr>";
	    	echo "<tr>".draw_permissions('', $perms)."</tr>"; 
	    ?>
	</table>
	</td>
</tr>
<?php
	/* <tr><td>Join Permissions:</td><td><?php draw_select(gr_joinmode, "Closed Group\nPublic Join\nModerated Join", "NONE\nPUBLIC\nMODERATED", $gr_joinmode); ?></td></tr> */
?>	
<tr><td colspan=2 align=left>
<?php if ( !empty($edit) ) echo '<input type="submit" name="btn_cancel" value="Cancel"> '; ?>
<input type="submit" name="btn_submit" value="<?php echo (empty($edit))?'Add':'Update'; ?>"></td></tr>
<input type="hidden" name="prevloaded" value="1">
</form>
</table>
<br><br>
<table border=1 cellspacing=1 cellpadding=3>
<tr style="font-size: x-small;">
<td>Group Name</td>
<?php echo $perm_header; ?>
<td>Leaders</td>
<td align="center">Actions</td>
</tr>
<?php

	$grp_p = new fud_group;
	$r = q("SELECT * FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."groups ORDER BY id");
	while ( $obj = db_rowobj($r) ) {
		$grp_p->id = $obj->id;
		$pret = $grp_p->resolve_perms();
		$str = draw_perm_table($pret);
		
		$ur = q("SELECT ".$GLOBALS['DBHOST_TBL_PREFIX']."users.alias FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."group_members LEFT JOIN ".$GLOBALS['DBHOST_TBL_PREFIX']."users ON ".$GLOBALS['DBHOST_TBL_PREFIX']."group_members.user_id=".$GLOBALS['DBHOST_TBL_PREFIX']."users.id WHERE ".$GLOBALS['DBHOST_TBL_PREFIX']."group_members.group_id=$obj->id AND group_leader='Y'");
		if ( $cnt=db_count($ur) ) {
			$sel =  "<font size=-1>(total: $cnt)</font><br><select>";
			while ( $uobj = db_rowobj($ur) ) {
				$sel .= '<option>'.$uobj->alias.'</option>';
			}
			$sel .= '</select>';
		}
		else $sel = 'No Leaders';
		qf($ur);
		
		if ( $obj->res == 'NONE' )
			$del_link = "[<a href=\"admgroups.php?del=$obj->id&rnd=".get_random_value()."&"._rsid."\">Delete</a>]<br>";
		else
			$del_link = '';
		
		if ( $obj->id > 2 ) 
			$user_grp_mgr = " ".$del_link."[<a href=\"admgrouplead.php?group_id=$obj->id&rnd=".get_random_value()."&"._rsid."\">Manage Leaders</a>] [<a href=\"../".__fud_index_name__."?t=groupmgr&group_id=$obj->id&"._rsid."\" target=_new>Manage Users</a>]";
			
		echo "<tr style=\"font-size: x-small;\">
			<td>$obj->name</td> $str <td valign=middle align=middle>$sel</td> <td nowrap>[<a href=\"admgroups.php?edit=$obj->id&rand=".get_random_value()."&"._rsid."\">Edit</a>] $user_grp_mgr</td></tr>";
	}
	qf($r);
?>
</table>
<?php require('admclose.html'); ?>