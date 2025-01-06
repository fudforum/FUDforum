<?php
/**
* copyright            : (C) 2001-2025 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id$
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; version 2 of the License.
**/

	require('./GLOBALS.php');
	fud_use('adm.inc', true);

	include($WWW_ROOT_DISK .'adm/header.php');
	$tbl = $GLOBALS['DBHOST_TBL_PREFIX'];

	// Reset most users ever online. 
	if (isset($_POST['btn_clear_online'])) {
		q('UPDATE '. $tbl .'stats_cache SET most_online = (online_users_reg+online_users_anon+online_users_hidden), most_online_time ='. __request_timestamp__);
		echo successify('The forum\'s "most online users" statistic was successfully reset.');
	}

	// Remove all forum sessions.
	if (isset($_POST['btn_clear_sessions'])) {
		q('DELETE FROM '. $tbl .'ses');
		echo successify('All forum sessions were cleared.');
		echo errorify('You (and all your users) will have to log in again!');
	}

	// Check Forum Version
	if (isset($_POST['btn_ver_check'])) {
		fud_use('url.inc', true);	// For get_remote_file().
		$verinfo = get_remote_file('https://raw.githubusercontent.com/wiki/fudforum/FUDforum/Current-version.md');

		if ($verinfo && strpos($verinfo, '::')) {
			// Write version to the forum's cache directory.
			file_put_contents($FORUM_SETTINGS_PATH .'latest_version', $verinfo);
		} else {
			echo errorify('Lookup failed. Data returned ['. $verinfo .'].');
		}
	}

?>
<h2>Forum Dashboard</h2>

<?php
	/* Check if consistency check is required. */
	if (@file_exists($GLOBALS['TMP'] .'RUN_CONSISTENCY_CHECK')) {
		echo '<div class="alert dismiss" title="Conistency Check">Please run the forum\'s <a href="consist.php?'. __adm_rsid .'">consistency checker</a>.<br /></div>';
	}

	/* Check for files that should be removed. */
	if (@file_exists($WWW_ROOT_DISK .'convert.php')) {
		echo '<div class="alert dismiss" title="'. $WWW_ROOT_DISK .'convert.php">Please <a href="../convert.php">run</a> and <a href="admbrowse.php?cur='. urlencode($WWW_ROOT_DISK) .'&amp;'. __adm_rsid .'#flagged">delete</a> the <em>converter</em> script before a hacker discovers it.<br /></div>';
	} elseif (@file_exists($WWW_ROOT_DISK .'install.php')) {
		echo '<div class="alert dismiss" title="'. $WWW_ROOT_DISK .'install.php">Please <a href="../install.php">run</a> or <a href="admbrowse.php?cur='. urlencode($WWW_ROOT_DISK) .'&amp;'. __adm_rsid .'#flagged">delete</a> the <em>install</em> script before a hacker discovers it.<br /></div>';
	} elseif (@file_exists($WWW_ROOT_DISK .'uninstall.php')) {
		echo '<div class="alert dismiss" title="'. $WWW_ROOT_DISK .'uninstall.php">Please <a href="../uninstall.php">run</a> or <a href="admbrowse.php?cur='. urlencode($WWW_ROOT_DISK) .'&amp;'. __adm_rsid .'#flagged">delete</a> the <em>uninstall</em> script before a hacker discovers it.<br /></div>';
	} elseif (@file_exists($WWW_ROOT_DISK .'upgrade.php')) {
		echo '<div class="alert dismiss" title="'. $WWW_ROOT_DISK .'upgrade.php">Please <a href="../upgrade.php">run</a> and <a href="admbrowse.php?cur='. urlencode($WWW_ROOT_DISK) .'&amp;'. __adm_rsid .'#flagged">delete</a> the <em>upgrade</em> script before a hacker discovers it.<br /></div>';
	} elseif  (@file_exists($WWW_ROOT_DISK .'unprotect.php')) {
		echo '<div class="alert dismiss" title="'. $WWW_ROOT_DISK .'unprotect.php">Please <a href="admbrowse.php?cur='. urlencode($WWW_ROOT_DISK) .'&amp;'. __adm_rsid .'#flagged">delete</a> the <em>unprotect</em> script before a hacker discovers it.<br /></div>';
	} elseif (@file_exists($WWW_ROOT_DISK .'fudforum_archive')) {
		echo '<div class="alert dismiss" title="'. $WWW_ROOT_DISK .'forum_archive">You should <a href="admbrowse.php?cur='. urlencode($WWW_ROOT_DISK) .'&amp;'. __adm_rsid .'#flagged">delete</a> the <em>fudforum_archive</em> when you are done installing/upgrading your forum.<br /></div>';
	}

	/* Forum files locked? */
	if (strncasecmp('win', PHP_OS, 3) && !($FUD_OPT_2 & 8388608)) {	// Not Windows and files not locked.
		echo '<div class="alert dismiss" title="Secure your forum files">You may want to <a href="admlock.php?'. __adm_rsid .'">lock</a> your forum files for improved security.<br /></div>';
	}

	/* Check server load. */
	if (function_exists('sys_getloadavg') && ($load = sys_getloadavg()) && $load[0] > 25) {
		echo '<div class="alert dismiss">You web server is quite busy (CPU load is '. $load[1] .'). This may impact your forum\'s performance!</div><br />';
	}

	/* Check forum version. */
	if (@file_exists($FORUM_SETTINGS_PATH .'latest_version')) {
		$lastcheck = filemtime($FORUM_SETTINGS_PATH .'latest_version');
		$verinfo = trim(file_get_contents($FORUM_SETTINGS_PATH .'latest_version'));
		$display_ver = substr($verinfo, 0, strpos($verinfo, '::'));
		if (version_compare($display_ver, $FORUM_VERSION, '>')) {
			echo '<div class="alert dismiss">You are running an old forum version. Please upgrade to FUDforum '. $display_ver .'<br /></div>';
		} elseif ($lastcheck > time() - 86400) {	// recently checked - 1 day.
			echo successify('You are on the latest version.');
		}
	}
?>

<div class="tutor">
Welcome to your forum's Admin Control Panel. From here you can control how your forum looks and behaves. To continue, please click on one of the links in the left sidebar of the window. First time users should start with the <b><a href="admglobal.php?<?php echo __adm_rsid; ?>">Global Settings Manager</a></b>.
</div>

<table border="0"><tr><td width="50%" valign="top">

	<h4>Getting help:</h4>
	FUDforum's documentation is available on our <b><a href="https://github.com/fudforum/FUDforum/wiki">development and documentation wiki</a></b>. Please report any problems on the <b><a href="https://github.com/fudforum/FUDforum/discussions">support forum</a></b>.

</td><td width="50%" valign="top">

	<h4>Versions:</h4>
	<?php if (!isset($display_ver)) { ?>
		<b>FUDforum</b>: <?php echo $FORUM_VERSION; ?><br />
	<?php } elseif (version_compare($display_ver, $FORUM_VERSION, '>')) { ?>
		<b>FUDforum</b>: <?php echo $FORUM_VERSION; ?> <span style="color:red"> - please upgrade!</span><br />
	<?php } else { ?>
		<b>FUDforum</b>: <?php echo $FORUM_VERSION; ?> <span style="color:green"> - latest version</span><br />
	<?php } ?>
	<b>PHP</b>: <?php echo PHP_VERSION; ?><br />
	<b>Database</b>: <?php echo __dbtype__ .' '. db_version() .' ('. $GLOBALS['DBHOST_DBTYPE'] .')'; ?><br />
	<b>Operating system</b>: <?php echo (@php_uname() ? php_uname('s') .' '. php_uname('r') : 'n/a') ?><br />

	<span style="float:right;"><a href="admsysinfo.php?<?php echo __adm_rsid; ?>">More... &raquo;</a></span>

</td></tr><tr><td width="50%" valign="top">

	<div id="chart_div1" style="width:400px; height:300px; display:none;"></div>

</td><td width="50%" valign="top">

	<div id="chart_div2" style="width:400px; height:300px; display:none;"></div>

</td></tr>
</table>

<?php
$day_list = array(date('D', strtotime('today'))   => 0, 
		date('D', strtotime('-1 day'))  => 0,
		date('D', strtotime('-2 days')) => 0,
		date('D', strtotime('-3 days')) => 0,
		date('D', strtotime('-4 days')) => 0,
		date('D', strtotime('-5 days')) => 0,
		date('D', strtotime('-6 days')) => 0);

$messages_per_day = $day_list;	// Copy.
$c = uq('SELECT post_stamp FROM '. $tbl .'msg WHERE post_stamp > '. (__request_timestamp__ - 86400*6)); // Last 6 days.
while ($r = db_rowarr($c)) {
	$messages_per_day[ date('D', $r[0]) ] += 1;
}
$messages_per_day = array_values($messages_per_day);

$registrations_per_day = $day_list;	// Copy again.
$c = uq('SELECT join_date FROM '. $tbl .'users WHERE id!=1 AND '. q_bitand('users_opt', 1073741824) .'= 0 AND join_date > '. (__request_timestamp__ - 86400*6)); // Last 6 days, exclude Anon & spider users.
while ($r = db_rowarr($c)) {
	$registrations_per_day[ date('D', $r[0]) ] += 1;
}
$registrations_per_day = array_values($registrations_per_day); 
?>

<script src="https://www.google.com/jsapi"></script>
<script async="async">
// jQuery(document).ready(function () {
	google.load("visualization", "1", {packages:["corechart"]});
	google.setOnLoadCallback(drawChart);
	function drawChart() {
		var data = new google.visualization.DataTable();
		data.addColumn('string', 'Days ago');
		data.addColumn('number', 'Messages');
		data.addRows([
			['today',     <?php echo $messages_per_day[0] ?>],
			['yesterday', <?php echo $messages_per_day[1] ?>],
			['-2 days',   <?php echo $messages_per_day[2] ?>],
			['-3 days',   <?php echo $messages_per_day[3] ?>],
			['-4 days',   <?php echo $messages_per_day[4] ?>],
			['-5 days',   <?php echo $messages_per_day[5] ?>]
		]);
		var chart = new google.visualization.ColumnChart(document.getElementById('chart_div1'));
		chart.draw(data, {
			width: 400, height: 300, title: 'Recent messages', legend: 'none',
			colors: ['#A2C180','#004411'],
			chartArea: {left:30,top:30},
			hAxis: {title: 'Days ago', titleTextStyle: {color: 'darkgreen'}}
		});

		var data = new google.visualization.DataTable();
		data.addColumn('string', 'Days ago');
		data.addColumn('number', 'Users');
		data.addRows([
			['today',     <?php echo $registrations_per_day[0] ?>],
			['yesterday', <?php echo $registrations_per_day[1] ?>],
			['-2 days',   <?php echo $registrations_per_day[2] ?>],
			['-3 days',   <?php echo $registrations_per_day[3] ?>],
			['-4 days',   <?php echo $registrations_per_day[4] ?>],
			['-5 days',   <?php echo $registrations_per_day[5] ?>]
		]);
		var chart = new google.visualization.ColumnChart(document.getElementById('chart_div2'));
		chart.draw(data, {
			width: 400, height: 300, title: 'Recent registrations', legend: 'none',
			colors: ['#A2C180','#004411'],
			chartArea: {left:30,top:30},
			hAxis: {title: 'Days ago', titleTextStyle: {color: 'darkgreen'}}
		});
	}
	jQuery("#chart_div1, #chart_div2").show();
</script>

<?php
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
?>

<h4>Forum statistics:</h4>
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
<span style="float:right;"><a href="admstats.php?<?php echo __adm_rsid; ?>">More... &raquo;</a></span>
<br /><br />

<hr />
<form method="post" action="index.php"><?php echo _hs; ?>
<button name="btn_clear_online">Reset the 'most online users' counter</button>
<button name="btn_clear_sessions">Clear ALL Forum Sessions</button>
<button name="btn_ver_check">Check Forum Version</button>
</form>
<br />

<?php require($WWW_ROOT_DISK .'adm/footer.php'); ?>
