<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: admgrouplead.php,v 1.13 2003/05/26 11:15:04 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

	require('./GLOBALS.php');
	fud_use('adm.inc', true);
	fud_use('groups.inc');
	fud_use('groups_adm.inc', true);

	$tbl = $GLOBALS['DBHOST_TBL_PREFIX'];

	$group_id = isset($_GET['group_id']) ? (int)$_GET['group_id'] : (isset($_POST['group_id']) ? (int)$_POST['group_id'] : '');
	$gr_leader = isset($_GET['gr_leader']) ? $_GET['gr_leader'] : (isset($_POST['gr_leader']) ? $_POST['gr_leader'] : '');
	
	if (!$group_id) {
		header('Location: admgroups.php?'._rsidl);
		exit;
	}	

	if (isset($_GET['del'])) {
		q('DELETE FROM '.$tbl.'group_members WHERE user_id='.(int)$_GET['del']);
		q('DELETE FROM '.$tbl.'group_cache WHERE user_id='.(int)$_GET['del']);
		rebuild_group_ldr_cache((int)$_GET['del']);
	} else if ($gr_leader) {
		$srch = addslashes(str_replace('\\', '\\\\', htmlspecialchars($gr_leader)));

		$c = q('SELECT id, alias FROM '.$tbl.'users WHERE alias LIKE \''.$srch.'%\' LIMIT 50');
		switch (($cnt = db_count($c))) {
			case 0:
				$error = 'Could not find a user who matches the "'.$srch.'" login mask';
				break;
			case 1:
				$r = db_rowarr($c);
				$flds = implode(',', $GLOBALS['__GROUPS_INC']['permlist']);
				q('INSERT INTO '.$tbl.'group_members ('.str_replace('p_', 'up_', $flds).', group_leader, group_id, user_id) SELECT '.$flds.', \'Y\', id, '.$r[0].' FROM '.$tbl.'groups WHERE id='.$group_id);
				rebuild_group_ldr_cache($r[0]);
				grp_rebuild_cache($group_id, $r[0]);
				$gr_leader = '';
				break;
			default:
				/* more then 1 user found, draw a selection form */
				echo '<html><body bgcolor="#ffffff">There are '.$cnt.' users matching your search mask:<br><table border=0 cellspacing=0 cellpadding=3>';
				while ($r = db_rowarr($c)) {
					echo '<tr><td><a href="admgrouplead.php?gr_leader='.urlencode($r[1]).'&group_id='.$group_id.'&'._rsidl.'">'.$r[1].'</a></td></tr>';
				}
				qf($c);
				echo '</table></body></html>';
				exit;
		}
		qf($c);
	}

	require($WWW_ROOT_DISK . 'adm/admpanel.php'); 
?>
<a href="admgroups.php?<?php echo _rsidl; ?>">Back to Groups</a>
<form method="post" action="admgrouplead.php"><?php echo _hs; ?>
<input type="hidden" value="<?php echo $group_id; ?>" name="group_id">
<table border=0 cellspacing=0 cellpadding=3>
<tr><td>Group Leader</td><td><input type="text" name="gr_leader" value="<?php echo $gr_leader; ?>"></td></tr>
<tr><td colspan=2 align=right><input type="submit" name="btn_submit" value="Add"></td></tr>
</table>

<table border=1 cellspacing=1 cellpadding=3>
<tr><td>Leader Login</td><td>Action</td></tr>
<?php
	$c = uq('SELECT u.id, u.alias FROM '.$tbl.'group_members gm INNER JOIN '.$tbl.'users u ON u.id=gm.user_id WHERE gm.group_id='.$group_id);
	while ($r = db_rowarr($c)) {
		echo '<tr><td>'.$r[1].'</td><td>[<a href="admgrouplead.php?group_id='.$group_id.'&del='.$r[0].'&'._rsidl.'">Remove From Group</a>]</td></tr>';
	}
	qf($c);
?>
</table>
</form>
<?php require($WWW_ROOT_DISK . 'adm/admclose.html'); ?>