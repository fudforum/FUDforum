<?php
/**
* copyright            : (C) 2001-2006 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: admaprune.php,v 1.15 2006/11/29 15:36:16 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; version 2 of the License.
**/

	@set_time_limit(6000);

	require('./GLOBALS.php');
	fud_use('adm.inc', true);
	fud_use('widgets.inc', true);

	if (isset($_POST['btn_prune']) && !empty($_POST['thread_age'])) {
	$lmt = ' AND (thread_opt & (2|4)) = 0 ';
		
		/* figure out our limit if any */
		if ($_POST['forumsel'] == '0') {
			$msg = '<font color="red">from all forums</font>';
		} else if (!strncmp($_POST['forumsel'], 'cat_', 4)) {
			$l = db_all('SELECT id FROM '.$DBHOST_TBL_PREFIX.'forum WHERE cat_id='.(int)substr($_POST['forumsel'], 4));
			if ($l) {
				$lmt .= ' AND forum_id IN('.implode(',', $l).') ';
			}
			$msg = '<font color="red">from all forums in category "'.q_singleval('SELECT name FROM '.$DBHOST_TBL_PREFIX.'cat WHERE id='.(int)substr($_POST['forumsel'], 4)).'"</font>';
		} else {
			$lmt .= ' AND forum_id='.(int)$_POST['forumsel'].' ';
			$msg = '<font color="red">from forum "'.q_singleval('SELECT name FROM '.$DBHOST_TBL_PREFIX.'forum WHERE id='.(int)$_POST['forumsel']).'"</font>';
		}
		$back = __request_timestamp__ - $_POST['units'] * $_POST['thread_age'];

		if (!isset($_POST['btn_conf']) && $back > 0) {
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
		} else if ($back > 0) {
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
				unset($c);
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
				unset($c);
				if ($ml) {
					q("UPDATE ".$DBHOST_TBL_PREFIX."pmsg SET attach_cnt=0 WHERE id IN(".implode(',', $ml).")");
				}
			}
			if ($al) {
				q("DELETE FROM ".$DBHOST_TBL_PREFIX."attach WHERE id IN(".implode(',', $al).")");
			}
			unset($c, $r, $al, $ml);
		} else if ($back < 1) {
			echo '<div style="text-align:center; font-size: large; font-weight: bolder; color: darkred">You\'ve selected a date too far in the past.</div>';
		}
	}

	require($WWW_ROOT_DISK . 'adm/admpanel.php');
?>
<h2>Attachment Prunning</h2>

This utility allows you to remove all attachments posted prior to the<br>
specified date. For example if you enter a value of 10 and select "days"<br> 
this form will offer to delete attachments olders then 10 days.<p>

<form name="adpa" method="post" action="admaprune.php">
<table class="datatable">
<tr class="field">
	<td nowrap>Attachments Older Then:</td>
	<td ><input type="text" name="thread_age" tabindex="1"></td>
	<td nowrap><?php draw_select("units", "Day(s)\nWeek(s)\nMonth(s)\nYear(s)", "86400\n604800\n2635200\n31622400", '86400'); ?>&nbsp;&nbsp;ago</td>
</tr>

<tr class="field">
	<td nowrap>Attachment Type:</td>
	<td colspan=2 nowrap><?php draw_select("type", "All\nPrivate Only\nPublic Only", "0\n1\n2", '0'); ?></td>
</tr>

<tr class="field">
	<td >Limit to forum:<font size="-1"><br />(not applicable for private attachment removal)</font></td>
	<td colspan=2 nowrap>
	<?php
		$oldc = '';
		$c = uq('SELECT f.id, f.name, c.name, c.id FROM '.$DBHOST_TBL_PREFIX.'forum f INNER JOIN '.$DBHOST_TBL_PREFIX.'cat c ON f.cat_id=c.id ORDER BY c.parent, c.view_order, f.view_order');
		echo '<select name="forumsel"><option value="0">- All Forums -</option>';
		while ($r = db_rowarr($c)) {
			if ($oldc != $r[3]) {
				echo '<option value="cat_'.$r[3].'">'.$r[2].'</option>';
				$oldc = $r[3];
			}
			echo '<option value="'.$r[0].'">&nbsp;&nbsp;-&nbsp;'.$r[1].'</option>';
		}
		unset($c);
		echo '</select>';
	?>
</tr>

<tr class="fieldaction">
	<td align=right colspan=3><input tabindex="2" type="submit" name="btn_prune" value="Prune"></td>
</tr>
</table>
<?php echo _hs; ?>
</form>
<script>
<!--
document.adpa.thread_age.focus();
//-->
</script>
<?php require($WWW_ROOT_DISK . 'adm/admclose.html'); ?>
