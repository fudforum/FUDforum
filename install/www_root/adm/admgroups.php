<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: admgroups.php,v 1.26 2003/09/30 15:37:08 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

	require ('./GLOBALS.php');
	fud_use('adm.inc', true);
	fud_use('widgets.inc', true);
	fud_use('groups_adm.inc', true);	
	fud_use('groups.inc');

	$edit = isset($_GET['edit']) ? (int)$_GET['edit'] : (isset($_POST['edit']) ? (int)$_POST['edit'] : 0);

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
						q('INSERT INTO '.$DBHOST_TBL_PREFIX.'group_resources (resource_id, group_id) VALUES('.(int)$v.', '.$gid.')');
					}
					grp_rebuild_cache($gid);
				}
			} else if (($r = db_saq('SELECT id, forum_id FROM '.$DBHOST_TBL_PREFIX.'groups WHERE id='.$edit))) { /* update an existing group */
				/* check to ensure circular inheritence does not occur */
				if (!group_check_inheritence((int)$_POST['gr_inherit_id'])) {
					$gid = $r[0];
					$forum_id = $r[1];
					group_sync($gid, (isset($_POST['gr_name']) ? $_POST['gr_name'] : null), (int)$_POST['gr_inherit_id'], $perms);
					/* handle resources */
					if (!$forum_id) {
						q('DELETE FROM '.$DBHOST_TBL_PREFIX.'group_resources WHERE group_id='.$gid);
						if (!is_array($_POST['gr_resource'])) {
							if (is_string($_POST['gr_resource'])) {
								$_POST['gr_resource'] = array($_POST['gr_resource']);
							}
						} else {
							foreach ($_POST['gr_resource'] as $v) {
								q('INSERT INTO '.$DBHOST_TBL_PREFIX.'group_resources (resource_id, group_id) VALUES('.(int)$v.', '.$gid.')');
							}
						}
					}

					$edit = '';
					$_POST = $_GET = null;

					/* the group's permissions may be inherited by other groups, so we go looking
					 * for those groups updating their permissions as we go
					 */
					$ih_id[$gid] = $gid;
					while (list(,$v) = each($ih_id)) {
						if (($c = q('SELECT id FROM '.$DBHOST_TBL_PREFIX.'groups WHERE inherit_id='.$v))) {
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
			$data = db_sab('SELECT g.*, f.name AS fname FROM '.$DBHOST_TBL_PREFIX.'groups g LEFT JOIN '.$DBHOST_TBL_PREFIX.'forum f ON f.id=g.forum_id WHERE g.id='.$edit);
		}
	}

	/* fetch all groups */
	$gl = array();
	$r = uq("SELECT g.id, g.name AS gn, g.inherit_id, g.groups_opt, g.groups_opti, f.name AS fname, g.forum_id FROM ".$DBHOST_TBL_PREFIX."groups g LEFT JOIN ".$DBHOST_TBL_PREFIX."forum f ON f.id=g.forum_id");
	while ($o = db_rowobj($r)) {
		$o = (array) $o;
		$gid = array_shift($o);
		$gl[$gid] = $o;
	}
	qf($r);

	if (!isset($error)) {
		if ($edit && isset($gl[$edit])) {
			list($gr_name, $gr_inherit_id, $perm, $permi, ) = each($gl[$edit]);
		} else {
			/* default form values */
			$gr_ramasks = $gr_name = '';
			$gr_inherit_id = 2;
			$perm = 0;
			$permi = 2147483647; /* inherit everything by default */
		}
	}

	require($WWW_ROOT_DISK . 'adm/admpanel.php');

	if (isset($err)) {
		echo errorify('You have a circular dependancy!');
	}
	if (isset($error_reason)) {
		echo errorify($error_reason);
	}
?>
<h2>Admin Group Manager: Add/Edit groups or group leaders</h2>
<form method="post" action="admgroups.php">
<table border=0 cellspacing=0>
<?php echo _hs; ?>
<input type="hidden" name="edit" value="<?php echo $edit; ?>">
<tr><td>Group Name: </td><td>
<?php
	if ($edit && ($edit < 3 || $gl[$edit]['forum_id'])) {
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
		if ($edit && $gl[$edit]['forum_id']) {
			echo 'FORUM: '.$gl[$edit]['fname'];
		} else {
			echo '<select MULTIPLE name="gr_resource[]" size=10>';
			if (!isset($_POST['edit']) && $edit) {
				$c = uq('SELECT resource_id FROM '.$DBHOST_TBL_PREFIX.'group_resources WHERE group_id='.$edit);
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
			$c = uq('SELECT f.id, f.name FROM '.$DBHOST_TBL_PREFIX.'forum f INNER JOIN '.$DBHOST_TBL_PREFIX.'cat c ON c.id=f.cat_id ORDER BY c.view_order, f.view_order');
			while ($r = db_rowarr($c)) {
				echo '<option value="'.$r[0].'"'.(isset($gr_resource[$r[0]]) ? ' selected' : '').'>'.$r[1].'</option>';
			}
			qf($c);
			echo '</select>';
		}
		echo '</td></tr><tr><td>Inherit From: </td><td><select name="gr_inherit_id"><option value="0">No where</option>';

		foreach ($gl as $k => $v) {
			echo '<option value="'.$k.'" '.($gr_inherit_id == $k ? ' selected' : '').'>'.$v['gn'].'</option>';
		}
		qf($c);

		echo '</select></td></tr>';
	}

	if (!$edit) {
		echo '<tr><td>Anonymous and Registered Masks</td><td>';
		draw_select('gr_ramasks', "No\nYes", "\n1", $gr_ramasks);
		echo '</td></tr>';
	}

	$hdr = array(
		'p_VISIBLE' => array(1, 'Visible'),
		'p_READ' => array(2, 'Read'),
		'p_POST' => array(4, 'Create new topics'),
		'p_REPLY' => array(8, 'Reply to messages'),
		'p_EDIT' => array(16, 'Edit messages'),
		'p_DEL' => array(32, 'Delete messages'),
		'p_STICKY' => array(64, 'Make topics sticky'),
		'p_POLL' => array(128, 'Create polls'),
		'p_VOTE' => array(256, 'Vote on polls'),
		'p_FILE' => array(512, 'Attach files'),
		'p_SPLIT' => array(1024, 'Split/Merge topics'),
		'p_MOVE' => array(2048, 'Move topics'),
		'p_SML' => array(4096, 'Use smilies/emoticons'),
		'p_IMG' => array(8192, 'Use [img] tags'),
		'p_RATE' => array(16384, 'Rate topics'),
		'p_LOCK' => array(32768, 'Lock/Unlock topics')
	);
?>
<tr><td valign="top" colspan=2 align="center"><font size="+2"><b>Maximum Permissions</b></font><br><font size="-1">(group leaders won't be able to assign permissions higher then these)</font></td></tr>
<tr><td><table cellspacing=2 cellpadding=2 border=0>
<?php	
	if ($edit && $gr_inherit_id) {
		echo '<tr><th nowrap><font size="+1">Permission</font></th><th><font size="+1">Value</font></th><th><font size="+1">Via Inheritance</font></th></tr>';
		$v1 = 1;
	} else {
		echo '<tr><th nowrap><font size="+1">Permission</font></th><th><font size="+1">Value</font></th></tr>';
		$v1 = 0;
	}

	foreach ($hdr as $k => $v) {
		echo '<tr><td>'.$v[1].'</td><td><select name="'.$k.'">';
		if ($permi & $v[0]) {
			echo '<option value="-'.$v[0].'" selected>Inherit</option>';
			echo '<option value="0">No</option><option value="'.$v[1].'">Yes</option>';
		} else {
			echo '<option value="-'.$v[0].'">Inherit</option>';
			if ($perm & $v[1]) {
				echo '<option value="0">No</option><option value="'.$v[0].'" selected>Yes</option>';
			} else {
				echo '<option value="0" selected>No</option><option value="'.$v[0].'">Yes</option>';
			}
		}
		echo '</select></td>';
		if ($v1) {
			echo '<td align="center">'.($perm & $v[1] ? 'Yes' : 'No').'</td>';
		}
		echo '</tr>';
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
<td valign="top"><b>Group Name</b></td>
<?php
	$src = array('!\s!', '!([A-Za-z]{1})!\e');
	$dst = array('', '\\1<br />');
	foreach ($hdr as $k => $v) {
		echo '<td valign="top"><b>';
		echo preg_replace('!([^0]{1})!e', "strtoupper('\\1').'<br />'", $v[1]);
		echo '</b></td>';
	}
?>
<td valign="top"><b>Leaders</b></td>
<td valign="top" align="center"><b>Actions</b></td>
</tr>
<?php
	/* fetch all group leaders */
	$c = uq('SELECT gm.group_id, u.alias FROM '.$DBHOST_TBL_PREFIX.'group_members gm INNER JOIN '.$DBHOST_TBL_PREFIX.'users u ON gm.user_id=u.id WHERE gm.group_members_opt >=131072');
	while ($r = db_rowarr($c)) {
		$gll[$r[0]][] = $r[1];
	}
	qf($c);
	
	foreach ($gl as $k => $v) {
		if (isset($gll[$k])) {
			$grl = '<font size="-1">(total: '.count($gll[$k]).')</font><br><select name="gr_leaders"><option>'.implode('</option>', $gll[$k]).'</option></select>';
		} else {
			$grl = 'No Leaders';
		}

		$del_link = !$v['forum_id'] ? '[<a href="admgroups.php?del='.$k.'&'._rsidl.'">Delete</a>]<br>' : '';
		$ih_name = $v['inherit_id'] ? '<br><font color="green" size="-1">Inherits from: '.$gl[$v['inherit_id']][0].'<font>' : '';

		$user_grp_mgr = ($k > 2) ? ' '.$del_link.'[<a href="admgrouplead.php?group_id='.$k.'&'._rsidl.'">Manage Leaders</a>] [<a href="../'.__fud_index_name__.'?t=groupmgr&group_id='.$k.'&'._rsidl.'" target=_new>Manage Users</a>]' : '';

		echo '<tr style="font-size: x-small;"><td><a name="g'.$k.'">'.$v['gn'].'</a></td>';
		foreach ($hdr as $v2) {
			echo '<td align="center">'.($v['groups_opt'] & $v2[0] ? '<font color="green">Y</font>' : '<font color="red">N</font>');
			if ($v['inherit_id'] && $v['groups_opti'] & $v2[0]) {
				echo ' <a href="#g'.$v['inherit_id'].'">(I: '.($v['groups_opti'] & $v2[0] ? '<font color="green">Y</font>' : '<font color="red">N</font>').')</a>';
			}
			echo '</td>';
		}
		echo '<td valign="middle" align="center">'.$grl.'</td> <td nowrap>[<a href="admgroups.php?edit='.$k.'&'._rsidl.'">Edit</a>] '.$user_grp_mgr.'</td></tr>';
	}
	qf($c);
?>
</table>
<?php require($WWW_ROOT_DISK . 'adm/admclose.html'); ?>