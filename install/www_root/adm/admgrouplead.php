<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: admgrouplead.php,v 1.22 2003/10/06 20:00:24 hackie Exp $
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

	$group_id = isset($_GET['group_id']) ? (int)$_GET['group_id'] : (isset($_POST['group_id']) ? (int)$_POST['group_id'] : '');
	$gr_leader = isset($_GET['gr_leader']) ? $_GET['gr_leader'] : (isset($_POST['gr_leader']) ? $_POST['gr_leader'] : '');

	if (!$group_id) {
		header('Location: admgroups.php?'._rsidl);
		exit;
	}

	if (isset($_GET['del']) && ($del = (int)$_GET['del'])) {
		if (isset($_GET['ug'])) {
			q("UPDATE ".$DBHOST_TBL_PREFIX."group_members SET group_members_opt=group_members_opt & ~ 131072 WHERE user_id=".$del." AND group_id=".$group_id);
		} else {
			q("DELETE FROM ".$DBHOST_TBL_PREFIX."group_members WHERE user_id=".$del." AND group_id=".$group_id);
			grp_rebuild_cache(array($del));
		}
		rebuild_group_ldr_cache($del);
	} else if ($gr_leader) {
		$srch = addslashes(str_replace('\\', '\\\\', htmlspecialchars($gr_leader)));

		$c = q("SELECT id, alias FROM ".$DBHOST_TBL_PREFIX."users WHERE alias='".$srch."'");
		if (!db_count($c)) {
			qf($c);
			$c = q("SELECT id, alias FROM ".$DBHOST_TBL_PREFIX."users WHERE alias LIKE '".$srch."%' LIMIT 50");
		}
		switch (($cnt = db_count($c))) {
			case 0:
				$error = 'Could not find a user who matches the "'.$srch.'" login mask';
				break;
			case 1:
				$r = db_rowarr($c);
				if (__dbtype__ == 'mysql') {
					q('REPLACE INTO '.$DBHOST_TBL_PREFIX.'group_members (group_id, user_id, group_members_opt) SELECT id, '.$r[0].', groups_opt|65536|131072 FROM '.$DBHOST_TBL_PREFIX.'groups WHERE id='.$group_id);
				} else {
					$opt = q_singleval('groups_opt|65536|131072 FROM '.$DBHOST_TBL_PREFIX.'groups WHERE id='.$group_id);
					if (!db_li('INSERT INTO '.$DBHOST_TBL_PREFIX.'group_members (group_id, user_id, group_members_opt) SELECT id, '.$r[0].', groups_opt|65536|131072 FROM '.$DBHOST_TBL_PREFIX.'groups WHERE id='.$group_id)) {
						q("UPDATE {SQL_TABLE_PREFIX}group_members SET group_members_opt=".$opt." WHERE user_id=".$r[0]." AND group_id=".$group_id);
					}
				}
				rebuild_group_ldr_cache($r[0]);
				grp_rebuild_cache(array($r[0]));
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
	$c = uq('SELECT u.id, u.alias FROM '.$DBHOST_TBL_PREFIX.'group_members gm INNER JOIN '.$DBHOST_TBL_PREFIX.'users u ON u.id=gm.user_id WHERE gm.group_id='.$group_id.' AND gm.group_members_opt>=131072 AND (gm.group_members_opt & 131072) > 0');
	while ($r = db_rowarr($c)) {
		echo '<tr><td>'.$r[1].'</td><td>
		[<a href="admgrouplead.php?group_id='.$group_id.'&del='.$r[0].'&'._rsidl.'&ug=1">Remove Group Leader Permission</a>]
		[<a href="admgrouplead.php?group_id='.$group_id.'&del='.$r[0].'&'._rsidl.'">Remove From Group</a>]
		</td></tr>';
	}
	qf($c);
?>
</table>
</form>
<?php require($WWW_ROOT_DISK . 'adm/admclose.html'); ?>