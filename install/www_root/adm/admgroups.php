<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: admgroups.php,v 1.20 2003/05/02 00:32:35 hackie Exp $
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

	require ('GLOBALS.php');
	fud_use('adm.inc', true);
	fud_use('widgets.inc', true);
	fud_use('groups_adm.inc', true);	
	fud_use('groups.inc');

	$tbl = $GLOBALS['DBHOST_TBL_PREFIX'];

	$edit = isset($_GET['edit']) ? (int)$_GET['edit'] : (isset($_POST['edit']) ? (int)$_POST['edit'] : '');

	if (isset($_GET['del'])) {
		group_delete((int)$_GET['del']);
	}

	/* check for errors */
	if (isset($_POST['btn_submit'])) {
		foreach($GLOBALS['__GROUPS_INC']['permlist'] as $k) { 
			if ($_POST[$k] == 'I' && !$_POST['gr_inherit_id']) { 
				$error_reason = 'One of your permissions is set to Inherit, however you have not selected a group to inherit from';
				$error = 1;
				break; 
			}
		}
		if (!isset($_POST['gr_resource']) && (!$edit || $edit > 2)) {
			$error_reason = 'You must assign at least 1 resource to this group';
			$error = 1;
		}

		if (!isset($error)) {
			foreach ($GLOBALS['__GROUPS_INC']['permlist'] as $v) {
				$perms[$v] = $_POST[$v];
			}
			if (!$edit) { /* create new group */
				$gid = group_add((int)$_POST['gr_resource'][0], $_POST['gr_name'], (int)$_POST['gr_ramasks'], $perms, (int)$_POST['gr_inherit_id']);
				if (!$gid) {
					$error_reason = 'Failed to add group';
					$error = 1;
				} else if (count($_POST['gr_resource']) > 1) {
					unset($_POST['gr_resource'][0]);
					foreach ($_POST['gr_resource'] as $v) {
						q('INSERT INTO '.$tbl.'group_resources (resource_id, group_id) VALUES('.(int)$v.', '.$gid.')');
					}
					grp_rebuild_cache($gid);
				}
			} else if (($r = db_saq('SELECT id, forum_id FROM '.$tbl.'groups WHERE id='.$edit))) { /* update an existing group */
				/* check to ensure circular inheritence does not occur */
				if (!group_check_inheritence((int)$_POST['gr_inherit_id'])) {
					$gid = $r[0];
					$forum_id = $r[1];
					group_sync($gid, (isset($_POST['gr_name']) ? $_POST['gr_name'] : NULL), (int)$_POST['gr_inherit_id'], $perms);
					/* handle resources */
					if (!$forum_id) {
						q('DELETE FROM '.$tbl.'group_resources WHERE group_id='.$gid);
						foreach ($_POST['gr_resource'] as $v) {
							q('INSERT INTO '.$tbl.'group_resources (resource_id, group_id) VALUES('.(int)$v.', '.$gid.')');
						}
					}

					$edit = '';
					$_POST = $_GET = NULL;

					/* the group's permissions may be inherited by other groups, so we go looking
					 * for those groups updating their permissions as we go
					 */
					$ih_id[$gid] = $gid;
					while (list(,$v) = each($ih_id)) {
						if (($c = q('SELECT id FROM '.$tbl.'groups WHERE inherit_id='.$v))) {
							while ($r = db_rowarr($c)) {
								if (!isset($ih_id[$r[0]])) {
									$ih_id[$r[0]] = $r[0];
								}
							}
						}
						qf($c);
						grp_rebuild_cache($v);
					}
				} else {
					$error = 1;
					$error_reason = 'Circular Inheritence';
				}
			}
		}
		/* restore form values */
		if (isset($error)) {
			$gr_name = $_POST['gr_name'];
			$gr_inherit_id = $_POST['gr_inherit_id'];
			if (isset($_POST['gr_ramasks'])) {
				$gr_ramasks = $_POST['gr_ramasks'];
			}
			foreach($GLOBALS['__GROUPS_INC']['permlist'] as $k) {
				$perms[$k] = $_POST[$k];
			}
			if (isset($_POST['gr_resource'])) {
				foreach ($_POST['gr_resource'] as $v) {
					$gr_resource[$v] = $v;
				}
			}
			$data = db_sab('SELECT g.*, f.name AS fname FROM '.$tbl.'groups g LEFT JOIN '.$tbl.'forum f ON f.id=g.forum_id WHERE g.id='.$edit);
		}
	}
	if (!isset($error)) {
		if (isset($_GET['edit']) && ($data = db_sab('SELECT g.*, f.name AS fname FROM '.$tbl.'groups g LEFT JOIN '.$tbl.'forum f ON f.id=g.forum_id WHERE g.id='.$edit))) {
			$gr_name = $data->name;
			$gr_inherit_id = $data->inherit_id;
			foreach($GLOBALS['__GROUPS_INC']['permlist'] as $k) {
				$perms[$k] = $data->{$k};
			}
		} else {
			/* default form values */
			$gr_ramasks = $gr_name = '';
			$gr_inherit_id = 2;
			foreach($GLOBALS['__GROUPS_INC']['permlist'] as $k) {
				$perms[$k] = 'I';
			}
		}
	}

	require($WWW_ROOT_DISK . 'adm/admpanel.php');

	if (isset($err)) {
		echo '<font color="red">You have a circular dependancy!</font><br>';
	}
	if (isset($error_reason)) {
		echo '<font color="red">'.$error_reason.'</font><br>';
	}
?>
<h2>Admin Group Manager: Add/Edit groups or group leaders</h2>
<form method="post" action="admgroups.php">
<table border=0 cellspacing=0>
<?php echo _hs; ?>
<input type="hidden" name="edit" value="<?php echo $edit; ?>">
<tr><td>Group Name: </td><td>
<?php
	if ($edit && ($edit < 3 || $data->forum_id)) {
		echo $gr_name;
		echo '<input type="hidden" name="gr_resource" value="1">';
	} else {
		echo '<input type="text" name="gr_name" value="'.htmlspecialchars($gr_name).'">';
	}
?>
</td></tr>
<?php
	if (!$edit || $edit > 2) {
		echo '<tr><td valign=top>Group Resources: </td><td>';
		if ($edit && $data->forum_id) {
			echo 'FORUM: '.$data->fname;
		} else {
			echo '<select MULTIPLE name="gr_resource[]" size=10>';
			if (!isset($_POST['edit']) && $edit) {
				$c = uq('SELECT resource_id FROM '.$tbl.'group_resources WHERE group_id='.$edit);
				while ($r = db_rowarr($c)) {
					$gr_resource[$r[0]] = $r[0];
				}
				qf($c);
			} else if (isset($_POST['edit'], $_POST['gr_resource'])) {
				foreach ($_POST['gr_resource'] as $v) {
					$gr_resource[$v] = $v;
				}
			} else {
				$gr_resource = array();
			}
			$c = uq('SELECT f.id, f.name FROM '.$tbl.'forum f INNER JOIN '.$tbl.'cat c ON c.id=f.cat_id ORDER BY c.view_order, f.view_order');
			while ($r = db_rowarr($c)) {
				echo '<option value="'.$r[0].'"'.(isset($gr_resource[$r[0]]) ? ' selected' : '').'>'.$r[1].'</option>';
			}
			qf($c);
			echo '</select>';
		}
		echo '</td></tr><tr><td>Inherit From: </td><td><select name="gr_inherit_id"><option value="0">No where</option>';

		$c = uq('SELECT id, name FROM '.$tbl.'groups ORDER BY id');
		while ($r = db_rowarr($c)) {
			echo '<option value="'.$r[0].'" '.($gr_inherit_id == $r[0] ? ' selected' : '').'>'.$r[1].'</option>';
		}
		qf($c);

		echo '</select></td></tr>';
	}

	if (!$edit) {
		echo '<tr><td>Anonymous and Registered Masks</td><td>';
		draw_select('gr_ramasks', "No\nYes", "\n1", $gr_ramasks);
		echo '</td></tr>';
	}

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
	$hdr = array(
		'p_VISIBLE' => 'Visible',
		'p_READ' => 'Read',
		'p_POST' => 'Create new topics',
		'p_REPLY' => 'Reply to messages',
		'p_EDIT' => 'Edit messages',
		'p_DEL' => 'Delete messages',
		'p_STICKY' => 'Make topics stiky',
		'p_POLL' => 'Create polls',
		'p_VOTE' => 'Vote on polls',
		'p_FILE' => 'Attach files',
		'p_SPLIT' => 'Split topics',
		'p_MOVE' => 'Move topics',
		'p_SML' => 'Use smilies/emoticons',
		'p_IMG' => 'Use [img] tags',
		'p_RATE' => 'Rate topics',
		'p_LOCK' => 'Lock/Unlock topics'
	);
?>
<tr><td valign="top" colspan=2 align="center"><font size="+2"><b>Maximum Permissions</b></font><br><font size="-1">(group leaders won't be able to assign permissions higher then these)</font></td></tr>
<tr><td><table cellspacing=2 cellpadding=2 border=0>
<?php	
	if ($edit && $gr_inherit_id) {
		echo '<tr><th nowrap><font size="+1">Permission</font></th><th><font size="+1">Value</font></th><th><font size="+1">Via Inheritance</font></th></tr>';
		grp_resolve_perms($data);
		$vi = 1;
	} else {
		echo '<tr><th nowrap><font size="+1">Permission</font></th><th><font size="+1">Value</font></th></tr>';
	}
	foreach ($GLOBALS['__GROUPS_INC']['permlist'] as $v) {
		echo '<tr><td>'.$hdr[$v].'</td><td><select name="'.$v.'">
			  <option value="I"'.($perms[$v] == 'I' ? ' selected': '').'>Inherit</option>
			  <option value="Y"'.($perms[$v] == 'Y' ? ' selected': '').'>Yes</option>
			  <option value="N"'.($perms[$v] == 'N' ? ' selected': '').'>No</option>
		</select></td>' . (isset($vi) ? '<td align="center">' . ($data->{$v} == 'Y' ? 'Yes' : 'No').'</td>' : '').'</tr>';
	}
?>
</table></td></tr>
<tr><td colspan=2 align=left>
<?php 
	if ($edit) {
		echo '<input type="submit" name="btn_cancel" value="Cancel"> ';
	}
?>
<input type="submit" name="btn_submit" value="<?php echo (!$edit ? 'Add' : 'Update'); ?>"></td></tr>
<input type="hidden" name="prevloaded" value="1">
</table>
</form>

<table border=1 cellspacing=1 cellpadding=3>
<tr style="font-size: x-small;">
<td>Group Name</td>
<?php echo $perm_header; ?>
<td>Leaders</td>
<td align="center">Actions</td>
</tr>
<?php
	/* fetch all group leaders */
	$c = uq('SELECT gm.group_id, u.alias FROM '.$tbl.'group_members gm INNER JOIN '.$tbl.'users u ON gm.user_id=u.id WHERE gm.group_leader=\'Y\'');
	while ($r = db_rowarr($c)) {
		$gl[$r[0]][] = $r[1];
	}
	qf($c);
	
	$c = uq('SELECT g.*, g2.name AS ih_name FROM '.$tbl.'groups g LEFT JOIN '.$tbl.'groups g2 ON g.inherit_id=g2.id ORDER BY g.id');
	while ($obj = db_rowobj($c)) {
		if (isset($gl[$obj->id])) {
			$grl = '<font size="-1">(total: '.count($gl[$obj->id]).')</font><br><select name="gr_leaders"><option>'.implode('</option>', $gl[$obj->id]).'</option></select>';
		} else {
			$grl = 'No Leaders';
		}

		$del_link = !$obj->forum_id ? '[<a href="admgroups.php?del='.$obj->id.'&'._rsidl.'">Delete</a>]<br>' : '';
		$ih_name = $obj->ih_name ? '<br><font color="green" size="-1">Inherits from: '.$obj->ih_name.'<font>' : '';
		
		$user_grp_mgr = ($obj->id > 2) ? ' '.$del_link.'[<a href="admgrouplead.php?group_id='.$obj->id.'&'._rsidl.'">Manage Leaders</a>] [<a href="../'.__fud_index_name__.'?t=groupmgr&group_id='.$obj->id.'&'._rsidl.'" target=_new>Manage Users</a>]' : '';

		echo '<tr style="font-size: x-small;"><td>'.$obj->name.$ih_name.'</td> '.draw_perm_table($obj).' <td valign=middle align=middle>'.$grl.'</td> <td nowrap>[<a href="admgroups.php?edit='.$obj->id.'&'._rsidl.'">Edit</a>] '.$user_grp_mgr.'</td></tr>';
	}
	qf($c);
?>
</table>
<?php require($WWW_ROOT_DISK . 'adm/admclose.html'); ?>