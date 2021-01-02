<?php
/**
* copyright            : (C) 2001-2021 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id$
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; version 2 of the License.
**/

	require('./GLOBALS.php');
	fud_use('adm.inc', true);
	fud_use('draw_select_opt.inc');
	fud_use('dbadmin.inc', true);	// For get_sql_disk_usage().
	fud_use('fs.inc', true);	// For fud_dir_space_usage().

	$tbl = $GLOBALS['DBHOST_TBL_PREFIX'];

	$forum_start = (int) q_singleval('SELECT MIN(post_stamp) FROM '. $tbl .'msg');

	if ($forum_start) {
		list($s_year,$s_month,$s_day) = explode(' ', date('Y n j', $forum_start));
		list($e_year,$e_month,$e_day) = explode(' ', date('Y n j', q_singleval('SELECT MAX(post_stamp) FROM '. $tbl .'msg')));
	} else {
		$dt = getdate(__request_timestamp__);
		$s_year  = $e_year  = $dt['year'];
		$s_month = $e_month = $dt['mon'];
		$s_day   = $e_day   = $dt['mday'];
	}

	$vl_m = $kl_m = implode("\n", range(1, 12));
	$vl_d = $kl_d = implode("\n", range(1, 31));
	$vl_y = $kl_y = implode("\n", range($s_year, ($e_year + 1)));

	$same_dir = (!strncmp($WWW_ROOT_DISK, $DATA_DIR, strlen($WWW_ROOT_DISK)) || !strncmp($DATA_DIR, $WWW_ROOT_DISK, strlen($DATA_DIR)));
	require($WWW_ROOT_DISK .'adm/header.php');
?>
<h2>Forum Statistics</h2>

<?php
	if (isset($_POST['submitted'])) {
		$start_tm = mktime(1, 1, 1, $_POST['s_month'], $_POST['s_day'], $_POST['s_year']);
		$end_tm = mktime(1, 1, 1, $_POST['e_month'], $_POST['e_day'], $_POST['e_year']);

		$day_list = array();

		switch ($_POST['sep']) {
			case 'week':
				$g_type = 'weekly';
				$fmt = 'YW';
				break;
			case 'month':
				$g_type = 'monthly';
				$fmt = 'Ym';
				break;
			case 'year':
				$g_type = 'yearly';
				$fmt = 'Y';
				break;
			case 'hour':
				$g_type = 'hourly';
				$fmt = 'YmdG';
				break;
			default:
				$g_type = 'daily';
				$fmt = 'Ymd';
		}

		$lmt = array();
		if (!empty($_POST['lmt'])) {
			if ($_POST['lmt'][0] == 'c') {
				$lmt = db_all('SELECT id FROM '. $tbl .'forum WHERE cat_id='. (int)substr($_POST['lmt'],1));
			} else if ((int)$_POST['lmt'] > 0) {
				$lmt = array((int)$_POST['lmt']);
			}
		}

		switch ($_POST['type']) {
			case 'msg':
				$g_title = 'Messages posted';
				if (!$lmt) {
					$c = uq('SELECT post_stamp FROM '. $tbl .'msg WHERE post_stamp BETWEEN '. $start_tm .' AND '. $end_tm);
				} else {
					$c = uq('SELECT m.post_stamp FROM '. $tbl .'msg m INNER JOIN '. $tbl .'thread t ON m.thread_id=t.id WHERE m.post_stamp>'. $start_tm .' AND m.post_stamp<'. $end_tm .' AND t.forum_id IN('. implode(',', $lmt) .')');
				}
				break;
			case 'thr':
				$g_title = 'Topics created';
				$c = uq('SELECT post_stamp FROM '. $tbl .'thread INNER JOIN '. $tbl .'msg ON '. $tbl .'thread.root_msg_id='. $tbl .'msg.id WHERE post_stamp BETWEEN '. $start_tm .' AND '. $end_tm . ($lmt ? ' AND forum_id IN('. implode(',', $lmt) .') ' : ''));
				break;
			case 'usr':
				$g_title = 'Registered users';
				$c = uq('SELECT join_date FROM '. $tbl .'users WHERE join_date>'. $start_tm .' AND join_date<'. $end_tm);
				break;
		}
		$g_title .= ' from <b>'.date('F d, Y', $start_tm).'</b> to <b>'.date('F d, Y', $end_tm).'</b>';

		while ($r = db_rowarr($c)) {
			$ds = (int) date($fmt, $r[0]);
			if (!isset($day_list[$ds])) {
				$day_list[$ds] = 1;
				if ($_POST['sep'] != 'month') {
					$tm = $r[0];
				} else {
					$ts = getdate($r[0]);
					$tm = mktime(0,1,1,$ts['mon'],1,$ts['year']);
				}
				$details[$ds] = $tm;
				$r[0];
			} else {
				$day_list[$ds]++;
			}
		}
		unset($c);

		ksort($day_list, SORT_NUMERIC);

		if (count($day_list)) {
			$max_value = max($day_list);
		} else {
			$max_value = 0;
		}

		$ttl = 0;
		$unit = ceil($max_value/100);
		$date_str = 'F d, Y';
		if ($_POST['sep'] == 'hour') {
			$date_str .= ' H:i';
		} else if ($_POST['sep'] == 'year') {
			$date_str = 'F Y';
		}

		echo '<script src="style/jquery.charts.js"></script>';
		echo '<table style="display: none;" id="graph">
			<caption>'. $g_title .' ('. $g_type .')</caption>
			<thead><tr><th>Date</th><th>Value</th></tr></thead>
			<tbody>';

		foreach($day_list as $k => $v) {
			echo '<tr><td>'. date($date_str, $details[$k]) .'</td><td>'. $v .'</td></tr>';
			$ttl += $v;
		}
		echo '</tbody>';
		echo '<tfoot><tr><td>Total</td><td>'. $ttl .'</td></tr></tfoot>';
		echo '</table>';
		echo '<script>jQuery("#graph").charts();</script>';
	} else {
		$_POST['s_year']  = $s_year;
		$_POST['s_month'] = $s_month;
		$_POST['s_day']   = $s_day;
		$_POST['e_year']  = $e_year;
		$_POST['e_month'] = $e_month;
		$_POST['e_day']   = $e_day + 1;
		$_POST['type']    = $_POST['sep'] = '';

		$disk_usage_array = array();
		$total_disk_usage = 0;

		$total_disk_usage += $disk_usage_array['DATA_DIR'] = fud_dir_space_usage($DATA_DIR);

		if (!$same_dir) {
			$total_disk_usage += $disk_usage_array['WWW_ROOT_DISK'] = fud_dir_space_usage($WWW_ROOT_DISK);
		} else {
			$disk_usage_array['WWW_ROOT_DISK'] = $disk_usage_array['DATA_DIR'];
		}

		$sql_disk_usage = get_sql_disk_usage();

		$forum_stats['MESSAGES']         = q_singleval('SELECT count(*) FROM '. $tbl .'msg');
		$forum_stats['THREADS']          = q_singleval('SELECT count(*) FROM '. $tbl .'thread');
		$forum_stats['PRIVATE_MESSAGES'] = q_singleval('SELECT count(*) FROM '. $tbl .'pmsg');
		$forum_stats['FORUMS']           = q_singleval('SELECT count(*) FROM '. $tbl .'forum');
		$forum_stats['CATEGORIES']       = q_singleval('SELECT count(*) FROM '. $tbl .'cat');
		$forum_stats['MEMBERS']          = q_singleval('SELECT count(*) FROM '. $tbl .'users');
		$forum_stats['ADMINS']           = q_singleval('SELECT count(*) FROM '. $tbl .'users WHERE users_opt>=1048576 AND '. q_bitand('users_opt', 1048576) .' > 0');
		$forum_stats['MODERATORS']       = q_singleval('SELECT count(DISTINCT(user_id)) FROM '. $tbl .'mod');
		$forum_stats['GROUPS']           = q_singleval('SELECT count(*) FROM '. $tbl .'groups');
		$forum_stats['GROUP_MEMBERS']    = q_singleval('SELECT count(*) FROM '. $tbl .'group_members');
	}

	$is_a          = 1;
	$forum_limiter = '';
	require $FORUM_SETTINGS_PATH .'cat_cache.inc';
	include_once $INCLUDE . fud_theme .'search_forum_sel.inc';
?>
<h4>Draw bar graph:</h4>
<form action="admstats.php" method="post">
<table class="datatable">
<tr class="field">
	<td valign="top"><b>From: </b></td>
	<td align="center"><font size="-1">month</font><br /><select name="s_month"><?php echo tmpl_draw_select_opt($vl_m, $kl_m, $_POST['s_month']); ?></select></td>
	<td align="center"><font size="-1">day</font><br /><select name="s_day"><?php echo tmpl_draw_select_opt($vl_d, $kl_d, $_POST['s_day']); ?></select></td>
	<td align="center"><font size="-1">year</font><br /><select name="s_year"><?php echo tmpl_draw_select_opt($vl_y, $kl_y, $_POST['s_year']); ?></select></td>
</tr>
<tr class="field">
	<td valign="top"><b>To: </b></td>
	<td align="center"><font size="-1">month</font><br /><select name="e_month"><?php echo tmpl_draw_select_opt($vl_m, $kl_m, $_POST['e_month']); ?></select></td>
	<td align="center"><font size="-1">day</font><br /><select name="e_day"><?php echo tmpl_draw_select_opt($vl_d, $kl_d, $_POST['e_day']); ?></select></td>
	<td align="center"><font size="-1">year</font><br /><select name="e_year"><?php echo tmpl_draw_select_opt($vl_y, $kl_y, $_POST['e_year']); ?></select></td>
</tr>
<tr class="field">
	<td valign="top"><b>Level of detail: </b></td>
	<td colspan="3"><select name="sep"><?php echo tmpl_draw_select_opt("hour\nday\nweek\nmonth\nyear", "Hour\nDay\nWeek\nMonth\nYear", $_POST['sep']); ?></select></td>
</tr>
<tr class="field">
	<td valign="top"><b>Graph Data: </b></td>
	<td colspan="3"><select name="type"><?php echo tmpl_draw_select_opt("msg\nthr\nusr", "Posted Messages\nCreated Topics\nRegistered users", $_POST['type']); ?></select></td>
</tr>
<tr class="field">
	<td valign="top"><b>Limit to Forum/Category: </b></td>
	<td colspan="3"><select name="lmt"><option>-- Unrestricted --</option><?php echo $forum_limit_data; ?></select></td>
</tr>
<tr class="field"><td colspan="4" align="right"><input type="submit" name="submit" value="Submit" /><?php echo _hs; ?>
<input type="hidden" name="submitted" value="1" /></td></tr>
</table>
</form>

<?php
// Calculate values for pie chart.
if (isset($total_disk_usage)) {
	$tot = $sql_disk_usage + (int)$total_disk_usage;
	$db  = $sql_disk_usage / $tot *100;
	$data  = $disk_usage_array['DATA_DIR']      / $tot *100;
	$web = $disk_usage_array['WWW_ROOT_DISK'] / $tot *100;
	if ($db < 1) $db = 1;
?>
<h4>Disk Usage:</h4>
<table class="resulttable fulltable">
<?php
	if (!$same_dir) {
?>
<tr class="field">
	<td><b>Web directories:</b><br /><span style="font-size: x-small;"><?php echo $WWW_ROOT_DISK; ?><br />This is where all the forum's web browseable files are stored</span></td>
	<td align="right" valign="top"><?php echo number_format($disk_usage_array['WWW_ROOT_DISK']/1024); ?> KB</td>
	<td rowspan="3" width="250px"> <img src="https://chart.googleapis.com/chart?cht=p3&amp;chs=250x100&amp;chd=t:<?php echo (int)$db .','. (int)$web .','. (int)$data; ?>&amp;chl=DB|Web files|Data files" align="right" /> <td>
</tr>

<tr class="field">
	<td><b>Data directories:</b><br /><span style="font-size: x-small;"><?php echo $DATA_DIR; ?><br />This is where the forum's internal data files are stored.</span></td>
	<td align="right" valign="top"><?php echo number_format($disk_usage_array['DATA_DIR']/1024); ?> KB</td>
</tr>
<?php
	} else { /* $same_dir */
?>
<tr class="field">
	<td><b>Forum directories:</b><br /><span style="font-size: x-small;"><?php echo $WWW_ROOT_DISK; ?><br />This is where the forum's files are stored.</span></td>
	<td align="right" valign="top"><?php echo number_format($total_disk_usage/1024); ?> KB</td>
	<td rowspan="3" width="250px"> <img src="https://chart.googleapis.com/chart?cht=p3&amp;chs=250x100&amp;chd=t:<?php echo (int)$db .','. (int)$web; ?>&amp;chl=DB|Files" align="right" /> <td>
</tr>
<?php
	}
	if ($sql_disk_usage) { ?>
<tr class="field">
        <td><b>Database space:</b><br /><span style="font-size: x-small;">Estimated size of the forum's tables and indexes.</span></td>
	<td align="right" valign="top"><?php echo number_format($sql_disk_usage/1024); ?> KB</td>
</tr>
<?php	} ?>
	<tr class="fieldtopic">
	<td><b>Total disk space:</b></td>
	<td align="right" valign="top"><?php echo number_format(($total_disk_usage+$sql_disk_usage)/1024); ?> KB</td>
</tr>
</table>

<h4>Forum Statistics:</h4>
<table class="resulttable fulltable">
<tr class="field">
	<td><b>Messages:</b></td>
	<td align="right" valign="top"><?php echo $forum_stats['MESSAGES']; ?></td>
	<td width="100">&nbsp;</td>
	<td></td>
</tr>

<tr class="field">
	<td valign="top"><b>Topics:</b></td>
	<td align="right" valign="top"><?php echo $forum_stats['THREADS']; ?></td>
	<td width="100">&nbsp;</td>
	<td><font size="-1"><b><?php echo @sprintf('%.2f', $forum_stats['MESSAGES']/$forum_stats['THREADS']); ?></b> messages per topic</font></td>
</tr>

<tr class="field">
	<td valign="top"><b>Forums:</b></td>
	<td align="right" valign="top"><?php echo $forum_stats['FORUMS']; ?></td>
	<td width="100">&nbsp;</td>
	<td><font size="-1">
		<b><?php echo @sprintf('%.2f', $forum_stats['MESSAGES']/$forum_stats['FORUMS']); ?></b> messages per forum<br />
		<b><?php echo @sprintf('%.2f', $forum_stats['THREADS']/$forum_stats['FORUMS']); ?></b> topics per forum
	</font></td>
</tr>

<tr class="field">
	<td valign="top"><b>Categories:</b></td>
	<td align="right" valign="top"><?php echo $forum_stats['CATEGORIES']; ?></td>
	<td width="100">&nbsp;</td>
	<td><font size="-1">
		<b><?php echo @sprintf('%.2f', $forum_stats['MESSAGES']/$forum_stats['CATEGORIES']); ?></b> messages per category<br />
		<b><?php echo @sprintf('%.2f', $forum_stats['THREADS']/$forum_stats['CATEGORIES']); ?></b> topics per category<br />
		<b><?php echo @sprintf('%.2f', $forum_stats['FORUMS']/$forum_stats['CATEGORIES']); ?></b> forums per category
	</font></td>
</tr>

<tr class="field">
	<td><b>Private Messages:</b></td>
	<td align="right" valign="top"><?php echo $forum_stats['PRIVATE_MESSAGES']; ?></td>
	<td width="100">&nbsp;</td>
	<td></td>
</tr>

<tr class="field">
	<td valign="top"><b>Users:</b></td>
	<td align="right" valign="top"><?php echo $forum_stats['MEMBERS']; ?></td>
	<td width="100">&nbsp;</td>
	<td><font size="-1">
		<b><?php echo @sprintf('%.2f', $forum_stats['MESSAGES']/$forum_stats['MEMBERS']); ?></b> messages per user<br />
		<b><?php echo @sprintf('%.2f', $forum_stats['THREADS']/$forum_stats['MEMBERS']); ?></b> topics per user<br />
		<b><?php echo @sprintf('%.2f', $forum_stats['PRIVATE_MESSAGES']/$forum_stats['MEMBERS']); ?></b> private messages per user
	</font></td>
</tr>

<tr class="field">
	<td valign="top"><b>Moderators:</b></td>
	<td align="right" valign="top"><?php echo $forum_stats['MODERATORS']; ?></td>
	<td width="100">&nbsp;</td>
	<td><font size="-1">
		<b><?php echo @sprintf('%.2f', ($forum_stats['MODERATORS']/$forum_stats['MEMBERS'])*100); ?>%</b> of all users<br />
		<b><?php echo @sprintf('%.2f', $forum_stats['MODERATORS']/$forum_stats['FORUMS']); ?></b> per forum
	</font></td>
</tr>

<tr class="field">
	<td valign="top"><b>Administrators:</b></td>
	<td align="right" valign="top"><?php echo $forum_stats['ADMINS']; ?></td>
	<td width="100">&nbsp;</td>
	<td><font size="-1"><b><?php echo @sprintf('%.2f', $forum_stats['ADMINS']/$forum_stats['MEMBERS']*100); ?>%</b> of all users</font></td>
</tr>

<tr class="field">
	<td valign="top"><b>User Groups:</b></td>
	<td align="right" valign="top"><?php echo $forum_stats['GROUPS']; ?></td>
	<td width="100">&nbsp;</td>
	<td><font size="-1"><b><?php echo @sprintf('%.2f', $forum_stats['GROUP_MEMBERS']/$forum_stats['GROUPS']); ?></b> members per group</font></td>
</tr>
</table>
<?php
	} /* !isset($total_disk_usage) */

	require($WWW_ROOT_DISK .'adm/footer.php');
?>
