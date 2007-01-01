<?php
/**
* copyright            : (C) 2001-2006 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: admgrouplead.php,v 1.40 2007/01/01 17:51:08 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; version 2 of the License.
**/

	require('./GLOBALS.php');
	fud_use('adm.inc', true);
	fud_use('groups.inc');
	fud_use('groups_adm.inc', true);

	$group_id = isset($_GET['group_id']) ? (int)$_GET['group_id'] : (isset($_POST['group_id']) ? (int)$_POST['group_id'] : '');
	$gr_leader = isset($_GET['gr_leader']) ? $_GET['gr_leader'] : (isset($_POST['gr_leader']) ? $_POST['gr_leader'] : '');

	if (!$group_id) {
		header('Location: '.$WWW_ROOT.'adm/admgroups.php?'.__adm_rsidl);
		exit;
	}

	$error = '';

	if (isset($_GET['del']) && ($del = (int)$_GET['del'])) {
		if (isset($_GET['ug'])) {
			q("UPDATE ".$DBHOST_TBL_PREFIX."group_members SET group_members_opt=group_members_opt & ~ 131072 WHERE user_id=".$del." AND group_id=".$group_id);
		} else {
			q("DELETE FROM ".$DBHOST_TBL_PREFIX."group_members WHERE user_id=".$del." AND group_id=".$group_id);
			grp_rebuild_cache(array($del));
		}
		rebuild_group_ldr_cache($del);
	} else if ($gr_leader) {
		$srch = char_fix(htmlspecialchars($gr_leader));

		if (($cnt = q_singleval("SELECT count(*) FROM ".$DBHOST_TBL_PREFIX."users WHERE alias="._esc($srch)))) {
			$c = q("SELECT id, alias FROM ".$DBHOST_TBL_PREFIX."users WHERE alias="._esc($srch));
		} else if (($cnt = q_singleval("SELECT count(*) FROM ".$DBHOST_TBL_PREFIX."users WHERE alias LIKE "._esc(addcslashes($srch,'\\').'%')))) {
			if ($cnt > 50) $cnt = 50;
			$c = q("SELECT id, alias FROM ".$DBHOST_TBL_PREFIX."users WHERE alias LIKE "._esc(addcslashes($srch,'\\').'%')." LIMIT 50");
		}

		switch ($cnt) {
			case 0:
				$error = 'Could not find a user who matches the "'.$srch.'" login mask';
				break;
			case 1:
				$r = db_rowarr($c);

				$opts = 65536|131072;
				$tgi = $group_id;
				$inh = db_saq("SELECT groups_opti, inherit_id, groups_opt FROM ".$DBHOST_TBL_PREFIX."groups WHERE id=".$tgi);
				$opts |= (int) $inh[2];				
				$ih_bits = (int) $inh[0];
				do {
					$tmp = db_saq("SELECT groups_opti, inherit_id, groups_opt FROM ".$DBHOST_TBL_PREFIX."groups WHERE id=".$inh[1]);					$ip_perms = $ih_bits &~ (int)$tmp[0];
					$opts |= (int)$tmp[2] & $ip_perms;
					$ih_bits = $ih_bits &~ $ip_perms;
					if (!$tgi || !$ih_bits) {
						break;
					}
					$inh[1] = $tmp[1];
				} while (($inh = db_saq("SELECT groups_opti, inherit_id FROM ".$DBHOST_TBL_PREFIX."groups WHERE id=".$tgi)));

				if (!db_li('INSERT INTO '.$DBHOST_TBL_PREFIX.'group_members (group_id, user_id, group_members_opt) VALUES('.$group_id.', '.$r[0].', '.$opts.')', $err)) {
					q("UPDATE ".$DBHOST_TBL_PREFIX."group_members SET group_members_opt=".$opts." WHERE user_id=".$r[0]." AND group_id=".$group_id);
				}

				rebuild_group_ldr_cache($r[0]);
				grp_rebuild_cache(array($r[0]));
				$gr_leader = '';
				break;
			default:
				/* more then 1 user found, draw a selection form */
				echo '<html><body bgcolor="#ffffff">There are '.$cnt.' users matching your search mask:<br><table border="0" cellspacing="0" cellpadding="3">';
				while ($r = db_rowarr($c)) {
					echo '<tr><td><a href="admgrouplead.php?gr_leader='.urlencode($r[1]).'&group_id='.$group_id.'&amp;'.__adm_rsid.'">'.$r[1].'</a></td></tr>';
				}
				unset($c);
				echo '</table></body></html>';
				exit;
		}
		unset($c);
	}

	require($WWW_ROOT_DISK . 'adm/admpanel.php');
?>
<a href="admgroups.php?<?php echo __adm_rsid; ?>">Back to Groups</a>
<?php
	if ($error) {
		echo '<br /><span class="alert">' . htmlspecialchars($error).'</span>';
	}
?>
<form method="post" action="admgrouplead.php"><?php echo _hs; ?>
<input type="hidden" value="<?php echo $group_id; ?>" name="group_id">
<table border="0" cellspacing="0" cellpadding="3">
<tr><td>Group Leader</td><td><input type="text" name="gr_leader" value="<?php echo char_fix(htmlspecialchars($gr_leader)); ?>"></td></tr>
<tr><td colspan="2" align="right"><input type="submit" name="btn_submit" value="Add"></td></tr>
</table>

<table border="1" cellspacing="1" cellpadding="3">
<tr><td>Leader Login</td><td>Action</td></tr>
<?php
	$c = uq('SELECT u.id, u.alias FROM '.$DBHOST_TBL_PREFIX.'group_members gm INNER JOIN '.$DBHOST_TBL_PREFIX.'users u ON u.id=gm.user_id WHERE gm.group_id='.$group_id.' AND gm.group_members_opt>=131072 AND (gm.group_members_opt & 131072) > 0');
	while ($r = db_rowarr($c)) {
		echo '<tr><td>'.$r[1].'</td><td>
		[<a href="admgrouplead.php?group_id='.$group_id.'&amp;del='.$r[0].'&amp;'.__adm_rsid.'&amp;ug=1">Remove Group Leader Permission</a>]
		[<a href="admgrouplead.php?group_id='.$group_id.'&amp;del='.$r[0].'&amp;'.__adm_rsid.'">Remove From Group</a>]
		</td></tr>';
	}
	unset($c);
?>
</table>
</form>
<?php require($WWW_ROOT_DISK . 'adm/admclose.html'); ?>