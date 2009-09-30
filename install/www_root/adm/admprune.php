<?php
/**
* copyright            : (C) 2001-2009 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: admprune.php,v 1.47 2009/09/30 16:47:33 frank Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; version 2 of the License.
**/

	@set_time_limit(6000);

	require('./GLOBALS.php');
	fud_use('adm.inc', true);
	fud_use('widgets.inc', true);
	fud_use('imsg_edt.inc');
	fud_use('th.inc');
	fud_use('ipoll.inc');
	fud_use('attach.inc');
	fud_use('th_adm.inc');

	if (isset($_GET['usr_id'])) {
		$usr_id = (int) $_GET['usr_id'];
	} else if (isset($_POST['usr_id'])) {
		$usr_id = (int) $_POST['usr_id'];
	} else {
		$usr_id = 0;
	}

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
			/* count the number of messages & topics that will be affected */
			if (!$usr_id) {
				$topic_cnt = q_singleval('SELECT count(*) FROM '.$DBHOST_TBL_PREFIX.'thread WHERE last_post_date<'.$back.$lmt);
				$msg_cnt = q_singleval('SELECT SUM(replies) FROM '.$DBHOST_TBL_PREFIX.'thread WHERE last_post_date<'.$back.$lmt) + $topic_cnt;
				$umsg = '';
			} else {
				$topic_cnt = q_singleval("SELECT count(*) FROM ".$DBHOST_TBL_PREFIX."thread t INNER JOIN ".$DBHOST_TBL_PREFIX."msg m ON t.root_msg_id=m.id WHERE m.poster_id=".$usr_id." AND t.last_post_date<".$back.$lmt);
				$msg_cnt = q_singleval("SELECT count(*) FROM ".$DBHOST_TBL_PREFIX."msg m INNER JOIN ".$DBHOST_TBL_PREFIX."thread t ON m.thread_id=t.id WHERE m.poster_id=".$usr_id." AND t.last_post_date<".$back.$lmt);
				$umsg = ' <font color="red">posted by "'.q_singleval("SELECT alias FROM ".$DBHOST_TBL_PREFIX."users WHERE id=".$usr_id).'"</font>';
			}
			require($WWW_ROOT_DISK . 'adm/header.php');
?>
<div align="center">You are about to delete <font color="red"><?php echo $topic_cnt; ?></font> topics containing <font color="red"><?php echo $msg_cnt; ?></font> messages,
which were posted before <font color="red"><?php echo strftime('%Y-%m-%d %T', $back); ?></font> <?php echo $umsg . $msg; ?><br /><br />
			Are you sure you want to do this?<br />
			<form method="post" action="">
			<input type="hidden" name="btn_prune" value="1" />
			<?php echo _hs; ?>
			<input type="hidden" name="thread_age" value="<?php echo $_POST['thread_age']; ?>" />
			<input type="hidden" name="units" value="<?php echo $_POST['units']; ?>" />
			<input type="hidden" name="usr_id" value="<?php echo $usr_id; ?>" />
			<input type="hidden" name="forumsel" value="<?php echo $_POST['forumsel']; ?>" />
			<input type="submit" name="btn_conf" value="Yes" />
			<input type="submit" name="btn_cancel" value="No" />
			</form>
</div>
<?php
			require($WWW_ROOT_DISK . 'adm/footer.php');
			exit;
		} else if ($back > 0) {
			$frm_list = array();

			if (!$usr_id) {
				$c = q('SELECT root_msg_id, forum_id FROM '.$DBHOST_TBL_PREFIX.'thread WHERE last_post_date<'.$back.$lmt);
				while ($r = db_rowarr($c)) {
					fud_msg_edit::delete(false, $r[0], 1);
					$frm_list[$r[1]] = $r[1];
				}
			} else {
				$msg_tbl = $DBHOST_TBL_PREFIX."msg";
				$th_tbl = $DBHOST_TBL_PREFIX."thread";
				$c = q("SELECT {$msg_tbl}.id, {$th_tbl}.forum_id FROM {$msg_tbl} INNER JOIN {$th_tbl} ON {$msg_tbl}.thread_id={$th_tbl}.id WHERE poster_id=".$usr_id." AND last_post_date<".$back.$lmt);
				while ($r = db_rowarr($c)) {
					fud_msg_edit::delete(false, $r[0]);
					$frm_list[$r[1]] = $r[1];
				}
			}
			unset($r);

			foreach ($frm_list as $v) {
				rebuild_forum_view_ttl($v);
			}
			echo '<h2 color="red">It is highly recommended that you run a consitency checker after pruning.</h2>';
		} else if ($back < 1) {
			$first_msg = q_singleval("SELECT MIN(post_stamp) FROM ".$DBHOST_TBL_PREFIX."msg");
			echo '<div style="text-align:center; font-size: large; font-weight: bolder; color: darkred">You\'ve selected a date too far in the past,'
			.($first_msg ? '<br />the earliest forum message was posted on '.date("r", $first_msg) : '')
			.'.</div>';
		}
	}

	require($WWW_ROOT_DISK . 'adm/header.php');
?>
<h2>Topic Pruning</h2>

<p>This utility allows you to remove all topics, where the last message<br />
inside the topic was posted prior to the specified date. For example <br />
if you enter a value of 10 and select "days" this form will offer to <br />
delete topics with no messages in the last 10 days.</p>

<form id="adp" method="post" action="admprune.php">
<table class="datatable">
<?php
	if ($usr_id) {
		echo '<tr class="field">';
		echo '<td nowrap="nowrap">By Author:</td>';
		echo '<td colspan="2">'.q_singleval("SELECT alias FROM ".$DBHOST_TBL_PREFIX."users WHERE id=".$usr_id).'</td>';
		echo '</tr>';
	}
?>
<tr class="field">
	<td nowrap="nowrap">Topics with last post made:</td>
	<td ><input tabindex="1" type="text" name="thread_age" /></td>
	<td nowrap="nowrap"><?php draw_select("units", "Day(s)\nWeek(s)\nMonth(s)\nYear(s)", "86400\n604800\n2635200\n31622400", '86400'); ?>&nbsp;&nbsp;ago</td>
</tr>

<tr class="field">
	<td >Limit to forum:</td>
	<td colspan="2" nowrap="nowrap">
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
		/* echo '<option value="P">- Private Messages -</option>'; */
		echo '</select>';
	?>
</td></tr>

<tr class="field">
	<td align="right" colspan="3"><input tabindex="2" type="submit" name="btn_prune" value="Prune" /></td>
</tr>
</table>
<?php echo _hs; ?>
<input type="hidden" name="usr_id" value="<?php echo $usr_id; ?>" />
</form>
<script type="text/javascript">
/* <![CDATA[ */
document.forms['adp'].thread_age.focus();
/* ]]> */
</script>
<?php require($WWW_ROOT_DISK . 'adm/footer.php'); ?>
