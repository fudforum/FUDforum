<?php
/***************************************************************************
* copyright            : (C) 2001-2003 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: admaprune.php,v 1.1 2003/10/16 14:30:01 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it 
* under the terms of the GNU General Public License as published by the 
* Free Software Foundation; either version 2 of the License, or 
* (at your option) any later version.
***************************************************************************/

	@set_time_limit(6000);

	require('./GLOBALS.php');
	fud_use('adm.inc', true);
	fud_use('widgets.inc', true);

	if (isset($_POST['btn_prune']) && !empty($_POST['thread_age'])) {
		/* figure out our limit if any */
		if ($_POST['forumsel'] == '0') {
			$lmt = '';
			$msg = '<font color="red">from all forums</font>';
		} else if (!strncmp($_POST['forumsel'], 'cat_', 4)) {
			$c = uq('SELECT id FROM '.$DBHOST_TBL_PREFIX.'forum WHERE cat_id='.(int)substr($_POST['forumsel'], 4));
			while ($r = db_rowarr($c)) {
				$l[] = $r[0];
			}
			if ($lmt = implode(',', $l)) {
				$lmt = ' AND forum_id IN('.$lmt.') ';
			}
			$msg = '<font color="red">from all forums in category "'.q_singleval('SELECT name FROM '.$DBHOST_TBL_PREFIX.'cat WHERE id='.(int)substr($_POST['forumsel'], 4)).'"</font>';
		} else {
			$lmt = ' AND forum_id='.(int)$_POST['forumsel'].' ';
			$msg = '<font color="red">from forum "'.q_singleval('SELECT name FROM '.$DBHOST_TBL_PREFIX.'forum WHERE id='.(int)$_POST['forumsel']).'"</font>';
		}
		$back = __request_timestamp__ - $_POST['units'] * $_POST['thread_age'];

		if (!isset($_POST['btn_conf'])) {
			if ($_POST['type'] == '0' || $_POST['type'] == '1') {
				$pa_cnt = q_singleval("SELECT count(*) FROM ".$DBHOST_TBL_PREFIX."pmsg m INNER JOIN ".$DBHOST_TBL_PREFIX."attach a ON a.message_id=m.id AND a.attach_opt=1 WHERE m.post_stamp < ".$back);
			} else {
				$pa_cnt = 0;
			}
			if ($_POST['type'] == '0' || $_POST['type'] == '2') {
				$a_cnt = q_singleval("SELECT count(*) FROM ".$DBHOST_TBL_PREFIX."msg m INNER JOIN ".$DBHOST_TBL_PREFIX."thread t ON t.id=m.thread_id INNER JOIN ".$DBHOST_TBL_PREFIX."attach a ON a.message_id=m.id AND a.attach_opt=0 WHERE m.post_stamp < ".$back.$lmt);
			} else {
				$a_cnt = 0;
			}
?>
<html>
<body bgcolor="white">
<div align=center>You are about to delete <font color="red"><?php echo $a_cnt; ?></font> public file attachments AND <font color="red"><?php echo $pa_cnt; ?></font> private file attachments.
<br />That were posted before <font color="red"><?php echo strftime('%Y-%m-%d %T', $back); ?></font> <?php echo $msg; ?><br><br>
			Are you sure you want to do this?<br>
			<form method="post">
			<input type="hidden" name="btn_prune" value="1">
			<?php echo _hs; ?>
			<input type="hidden" name="thread_age" value="<?php echo $_POST['thread_age']; ?>">
			<input type="hidden" name="units" value="<?php echo $_POST['units']; ?>">
			<input type="hidden" name="type" value="<?php echo $_POST['type']; ?>">
			<input type="hidden" name="forumsel" value="<?php echo $_POST['forumsel']; ?>">
			<input type="submit" name="btn_conf" value="Yes">
			<input type="submit" name="btn_cancel" value="No">
			</form>
</div>
</body>
</html>
<?php
			exit;
		} else {
			$limit = time() - $_POST['units'] * $_POST['thread_age'];
			$al = $ml = array();

			if ($_POST['type'] == '0' || $_POST['type'] == '2') {
				$c = uq("SELECT a.message_id, a.location, a.id 
					FROM ".$DBHOST_TBL_PREFIX."msg m
					INNER JOIN ".$DBHOST_TBL_PREFIX."thread t ON t.id=m.thread_id
					INNER JOIN ".$DBHOST_TBL_PREFIX."attach a ON a.message_id=m.id AND a.attach_opt=0 
					WHERE m.post_stamp < ".$back.$lmt);
				while ($r = db_rowarr($c)) {
					@unlink($r[1]);
					$al[] = $r[2];
					$ml[] = $r[0];
				}
				if ($ml) {
					q("UPDATE ".$DBHOST_TBL_PREFIX."msg SET attach_cnt=0, attach_cache=NULL WHERE id IN(".implode(',', $ml).")");
				}
				$ml = array();
			}
			if ($_POST['type'] == '0' || $_POST['type'] == '1') {
				$c = uq("SELECT a.message_id, a.location, a.id
					FROM ".$DBHOST_TBL_PREFIX."pmsg m
					INNER JOIN ".$DBHOST_TBL_PREFIX."attach a ON a.message_id=m.id AND a.attach_opt=1 
					WHERE m.post_stamp < ".$back);
				while ($r = db_rowarr($c)) {
					@unlink($r[1]);
					$al[] = $r[2];
					$ml[] = $r[0];
				}
				if ($ml) {
					q("UPDATE ".$DBHOST_TBL_PREFIX."pmsg SET attach_cnt=0 WHERE id IN(".implode(',', $ml).")");
				}
			}
			if ($al) {
				q("DELETE FROM ".$DBHOST_TBL_PREFIX."attach WHERE id IN(".implode(',', $al).")");
			}
			unset($c, $r, $al, $ml); 
		}
	}

	require($WWW_ROOT_DISK . 'adm/admpanel.php');
?>
<h2>Attachment Topic Prunning</h2>
<form method="post" action="admaprune.php">
<table border=0 cellspacing=1 callpadding=3>
<tr>
	<td bgcolor="#bff8ff" nowrap>Attachments Older Then:</td>
	<td bgcolor="#bff8ff"><input type="text" name="thread_age"></td>
	<td bgcolor="#bff8ff" nowrap><?php draw_select("units", "Day(s)\nWeek(s)\nMonth(s)\nYear(s)", "86400\n604800\n2635200\n31622400", '86400'); ?>&nbsp;&nbsp;ago</td>
</tr>
<tr>
	<td bgcolor="#bff8ff" nowrap>Attachment Type:</td>
	<td colspan=2 bgcolor="#bff8ff" nowrap><?php draw_select("type", "All\nPrivate Only\nPublic Only", "0\n1\n2", '0'); ?></td>
</tr>
<tr>
	<td bgcolor="#bff8ff">Limit to forum:<font size="-1"><br />(not applicable for private attachment removal)</font></td>
	<td colspan=2 bgcolor="#bff8ff" nowrap>
	<?php
		$oldc = '';
		$c = uq('SELECT f.id, f.name, c.name, c.id FROM '.$DBHOST_TBL_PREFIX.'forum f INNER JOIN '.$DBHOST_TBL_PREFIX.'cat c ON f.cat_id=c.id ORDER BY c.view_order, f.view_order');
		echo '<select name="forumsel"><option value="0">- All Forums -</option>';
		while ($r = db_rowarr($c)) {
			if ($oldc != $r[3]) {
				echo '<option value="cat_'.$r[3].'">'.$r[2].'</option>';
				$oldc = $r[3];
			}
			echo '<option value="'.$r[0].'">&nbsp;&nbsp;-&nbsp;'.$r[1].'</option>';
		}
		echo '</select>';
	?>
</tr>

<tr>
	<td bgcolor="#bff8ff" align=right colspan=3><input type="submit" name="btn_prune" value="Prune"></td>
</tr>
</table>
<?php echo _hs; ?>
</form>
<?php require($WWW_ROOT_DISK . 'adm/admclose.html'); ?>