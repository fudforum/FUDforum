<?php
/**
* copyright            : (C) 2001-2009 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: index.php,v 1.14 2009/05/08 06:11:16 frank Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; version 2 of the License.
**/
	require('./GLOBALS.php');
	fud_use('adm.inc', true);

	include($WWW_ROOT_DISK . 'adm/admpanel.php');
?>
<h2>Forum Dashboard</h2>

<?php	
	if (@file_exists($WWW_ROOT_DISK.'install.php')) {
		echo '<div class="alert">You still haven\'t removed the installation script at '.$WWW_ROOT_DISK.'install.php. Please do so now before a hacker destroys your forum!</div>';
	}
	if (@file_exists($WWW_ROOT_DISK.'uninstall.php')) {
		echo '<div class="alert">You still haven\'t removed the uninstall script at '.$WWW_ROOT_DISK.'uninstall.php. Please do so now before a hacker destroys your forum!</div>';
	}
	if (@file_exists($WWW_ROOT_DISK.'upgrade.php')) {
		echo '<div class="alert">You still haven\'t removed the upgrade script at '.$WWW_ROOT_DISK.'upgrade.php. Please do so now before a hacker destroys your forum!</div>';
	}
?>

<div class="tutor">
Welcome to your forum's Admin Control Panel. From here you can control how your forum looks and behaves. To continue, please click on one of the links in the left sidebar of the window. First time users should start with the <b><a href="admglobal.php?<?php echo __adm_rsid; ?>">Global Settings Manager</a></b>.
</div>

<table border="0"><tr><td width="50%" valign="top">

<h4>Getting help:</h4>
FUDforum's documentation is available on our <b><a href="http://cvs.prohost.org/">development and documentation wiki</a></b>. Please report any problems on the support forum at <b><a href="http://fudforum.org">fudforum.org</a></b>.

</td><td width="50%" valign="top">

<h4>Versions:</h4>
<b>FUDforum</b>: <?php echo $FORUM_VERSION; ?><br />
<b>PHP</b>: <?php echo PHP_VERSION; ?><br />
<b>Database</b>: <?php echo get_version(); ?><br />
<span style="float:right;"><a href="admsysinfo.php?<?php echo __adm_rsid; ?>">More... &raquo;</a></span>

</td></tr></table>

<?php
	$tbl = $GLOBALS['DBHOST_TBL_PREFIX'];
	$forum_stats['MESSAGES'] = q_singleval('SELECT count(*) FROM '.$tbl.'msg');
	$forum_stats['THREADS'] = q_singleval('SELECT count(*) FROM '.$tbl.'thread');
	$forum_stats['PRIVATE_MESSAGES'] = q_singleval('SELECT count(*) FROM '.$tbl.'pmsg');
	$forum_stats['FORUMS'] = q_singleval('SELECT count(*) FROM '.$tbl.'forum');
	$forum_stats['CATEGORIES'] = q_singleval('SELECT count(*) FROM '.$tbl.'cat');
	$forum_stats['MEMBERS'] = q_singleval('SELECT count(*) FROM '.$tbl.'users');
	$forum_stats['ADMINS'] = q_singleval('SELECT count(*) FROM '.$tbl.'users WHERE users_opt>=1048576 AND (users_opt & 1048576) > 0');
	$forum_stats['MODERATORS'] = q_singleval('SELECT count(DISTINCT(user_id)) FROM '.$tbl.'mod');
	$forum_stats['GROUPS'] = q_singleval('SELECT count(*) FROM '.$tbl.'groups');
	$forum_stats['GROUP_MEMBERS'] = q_singleval('SELECT count(*) FROM '.$tbl.'group_members');
?>

<h4>Forum statistics</h4>
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
	<td><font size="-1"><b><?php echo @sprintf("%.2f", $forum_stats['MESSAGES']/$forum_stats['THREADS']); ?></b> messages per topic</font></td>
</tr>

<tr class="field">
	<td valign="top"><b>Forums:</b></td>
	<td align="right" valign="top"><?php echo $forum_stats['FORUMS']; ?></td>
	<td width="100">&nbsp;</td>
	<td><font size="-1">
		<b><?php echo @sprintf("%.2f", $forum_stats['MESSAGES']/$forum_stats['FORUMS']); ?></b> messages per forum<br />
		<b><?php echo @sprintf("%.2f", $forum_stats['THREADS']/$forum_stats['FORUMS']); ?></b> topics per forum
	</font></td>
</tr>

<tr class="field">
	<td valign="top"><b>Categories:</b></td>
	<td align="right" valign="top"><?php echo $forum_stats['CATEGORIES']; ?></td>
	<td width="100">&nbsp;</td>
	<td><font size="-1">
		<b><?php echo @sprintf("%.2f", $forum_stats['MESSAGES']/$forum_stats['CATEGORIES']); ?></b> messages per category<br />
		<b><?php echo @sprintf("%.2f", $forum_stats['THREADS']/$forum_stats['CATEGORIES']); ?></b> topics per category<br />
		<b><?php echo @sprintf("%.2f", $forum_stats['FORUMS']/$forum_stats['CATEGORIES']); ?></b> forums per category
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
		<b><?php echo @sprintf("%.2f", $forum_stats['MESSAGES']/$forum_stats['MEMBERS']); ?></b> messages per user<br />
		<b><?php echo @sprintf("%.2f", $forum_stats['THREADS']/$forum_stats['MEMBERS']); ?></b> topics per user<br />
		<b><?php echo @sprintf("%.2f", $forum_stats['PRIVATE_MESSAGES']/$forum_stats['MEMBERS']); ?></b> private messages per user
	</font></td>
</tr>

<tr class="field">
	<td valign="top"><b>Moderators:</b></td>
	<td align="right" valign="top"><?php echo $forum_stats['MODERATORS']; ?></td>
	<td width="100">&nbsp;</td>
	<td><font size="-1">
		<b><?php echo @sprintf("%.2f", ($forum_stats['MODERATORS']/$forum_stats['MEMBERS'])*100); ?>%</b> of all users<br />
		<b><?php echo @sprintf("%.2f", $forum_stats['MODERATORS']/$forum_stats['FORUMS']); ?></b> per forum
	</font></td>
</tr>

<tr class="field">
	<td valign="top"><b>Administrators:</b></td>
	<td align="right" valign="top"><?php echo $forum_stats['ADMINS']; ?></td>
	<td width="100">&nbsp;</td>
	<td><font size="-1"><b><?php echo @sprintf("%.2f", $forum_stats['ADMINS']/$forum_stats['MEMBERS']); ?>%</b> of all users</font></td>
</tr>

<tr class="field">
	<td valign="top"><b>User Groups:</b></td>
	<td align="right" valign="top"><?php echo $forum_stats['GROUPS']; ?></td>
	<td width="100">&nbsp;</td>
	<td><font size="-1"><b><?php echo @sprintf("%.2f", $forum_stats['GROUP_MEMBERS']/$forum_stats['GROUPS']); ?></b> members per group</font></td>
</tr>
</table>
<span style="float:right;"><a href="admstats.php?<?php echo __adm_rsid; ?>">More... &raquo;</a></span>
<br />

<?php require($WWW_ROOT_DISK . 'adm/admclose.html'); ?>

