<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: admstats.php,v 1.9 2002/09/18 20:52:08 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

	define('admin_form', 1);

	include_once "GLOBALS.php";
	
	fud_use('db.inc');
	fud_use('draw_select_opt.inc');
	fud_use('adm.inc', true);
	
	list($ses, $usr) = initadm();

function dir_space_usage($dirp)
{
	$disk_space=0;

	$curdir = getcwd();
	if( !@chdir($dirp) ) 
		return;
	if( !($dir = @opendir('.')) ) 
		return;
	
	readdir($dir); readdir($dir);
	
	while( $file = readdir($dir) ) {
		if( @is_link($file) ) 
			continue;
		else if( @is_dir($file) ) 
			$disk_space += dir_space_usage($file);
		else if( @is_file($file) )
			$disk_space += filesize($file);
	}
	
	closedir($dir);
	chdir($curdir);
	
	return $disk_space;
}

function get_sql_disk_usage()
{
	$ver = q_singleval("SELECT VERSION()");
	if( $ver[0] != 4 && substr($ver,0,4)!='3.23' ) return;
	
	$sql_size=0;
	
	$r = q("SHOW TABLE STATUS FROM ".$GLOBALS['DBHOST_DBNAME']);
	while( $obj = db_rowobj($r) ) {
		if( preg_match('!^'.$GLOBALS['DBHOST_TBL_PREFIX'].'!', $obj->Name) )
			$sql_size += $obj->Data_length+$obj->Index_length;
	}	
	qf($r);
	
	return $sql_size;
}
	
	$forum_start = INTVAL(q_singleval("SELECT MIN(post_stamp) FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."msg"));
	$days_ago = round((__request_timestamp__-$forum_start)/86400);

	list($s_year,$s_month,$s_day) = explode(" ", date("Y n j", q_singleval("SELECT MIN(post_stamp) FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."msg")));
	list($e_year,$e_month,$e_day) = explode(" ", date("Y n j", q_singleval("SELECT MAX(post_stamp) FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."msg")));
	for( $i=1; $i<13; $i++ ) $vl_m = $kl_m .= $i."\n";
	for( $i=1; $i<32; $i++ ) $vl_d = $kl_d .= $i."\n";
	for( $i=$s_year; $i<($e_year+1); $i++ ) $vl_y = $kl_y .= $i."\n";
	
	$disk_usage_array = array();
	$total_disk_usage = 0;

	$total_disk_usage += $disk_usage_array['DATA_DIR'] = dir_space_usage($INCLUDE);
	$total_disk_usage += $disk_usage_array['WWW_ROOT_DISK'] = dir_space_usage($WWW_ROOT_DISK);

	$sql_disk_usage = get_sql_disk_usage();
	
	$forum_stats = array();
	
	$forum_stats['MESSAGES'] = q_singleval("SELECT count(*) FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."msg");
	$forum_stats['THREADS'] = q_singleval("SELECT count(*) FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."thread");
	$forum_stats['PRIVATE_MESSAGES'] = q_singleval("SELECT count(*) FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."pmsg");
	$forum_stats['FORUMS'] = q_singleval("SELECT count(*) FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."forum");
	$forum_stats['CATEGORIES'] = q_singleval("SELECT count(*) FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."cat");
	$forum_stats['MEMBERS'] = q_singleval("SELECT count(*) FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."users");
	$forum_stats['ADMINS'] = q_singleval("SELECT count(*) FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."users WHERE is_mod='A'");
	$forum_stats['MODERATORS'] = q_singleval("SELECT count(*) FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."mod");
	$forum_stats['GROUPS'] = q_singleval("SELECT count(*) FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."groups");
	$forum_stats['GROUP_MEMBERS'] = q_singleval("SELECT count(*) FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."group_members");

	include('admpanel.php'); 
?>	
<div align="center" style="font-size: xx-large; font-weight: bold;">Statistics</div>
<?php 
	if( !empty($submitted) ) {
		$start_tm = mktime(1,1,1,$HTTP_POST_VARS['s_month'],$HTTP_POST_VARS['s_day'],$HTTP_POST_VARS['s_year']);
		$end_tm = mktime(1,1,1,$HTTP_POST_VARS['e_month'],$HTTP_POST_VARS['e_day'],$HTTP_POST_VARS['e_year']);
		
		$day_list = array();
		
		switch( $sep )
		{
			case 'week':
				$g_type = 'weekly';
				$fmt='YmW';
				break;
			case 'month':
				$g_type = 'monthly';
				$fmt='Ym';
				break;
			case 'year':
				$g_type = 'yearly';
				$fmt='Y';
				break;
			default:
				$g_type = 'daily';
				$fmt='Ymd';
		}
		
		switch( $type ) 
		{
			case 'msg':
				$g_title = 'Messages posted from <b>'.date("F d, Y", $start_tm).'</b> to <b>'.date("F d, Y", $end_tm).'</b>';
				$r = q("SELECT post_stamp FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."msg WHERE post_stamp>".$start_tm." AND post_stamp<".$end_tm);
				break;
			case 'thr':
				$g_title = 'Topics created from <b>'.date("F d, Y", $start_tm).'</b> to <b>'.date("F d, Y", $end_tm).'</b>';
				$r = q("SELECT post_stamp FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."thread INNER JOIN ".$GLOBALS['DBHOST_TBL_PREFIX']."msg ON ".$GLOBALS['DBHOST_TBL_PREFIX']."thread.root_msg_id=".$GLOBALS['DBHOST_TBL_PREFIX']."msg.id WHERE post_stamp>".$start_tm." AND post_stamp<".$end_tm);
				break;
			case 'usr':
				$g_title = 'Registered users from <b>'.date("F d, Y", $start_tm).'</b> to <b>'.date("F d, Y", $end_tm).'</b>';
				$r = q("SELECT join_date FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."users WHERE join_date>".$start_tm." AND join_date<".$end_tm);
				break;
		}			
			
		while( list($mps) = db_rowarr($r) ) {
			$ds = date($fmt, $mps);
			if( !isset($day_list[$ds][0]) ) 
				$day_list[$ds][0] = 1;
			else
				$day_list[$ds][0]++;

			$day_list[$ds][1] = $mps;
		}
		qf($r);
	
		$tmp = $day_list;
		rsort($tmp);
		
		foreach($tmp as $max_value) break;
		$max_value = $max_value[0];
		unset($tmp);
		
		echo '<br><div align="center" style="font-size: xx-small;">'.$g_title.' ('.$g_type.')</div>';
		echo '<table cellspacing=1 cellpadding=0 border=0 align="center">';
		$ttl=0;
		$unit = ceil($max_value/100);
		foreach($day_list as $k => $v) {
			$len = round($v[0]/$unit)*3;
			echo '<tr><td style="font-size: xx-small;">'.date("F d, Y", $v[1]).'</td><td width="100" bgcolor="#000000"><img style="background-color: #ff0000;" src="../blank.gif" height=5 width='.$len.'></td><td style="font-size: xx-small;">('.$v[0].')</td></tr>';
			$ttl += $v[0];
		}
		echo '<tr style="font-size: xx-small;"><td><b>Total:</b></td><td colspan=2 align="right">'.$ttl.'</td></tr>';
		
		echo '</table><br>';
	}
	else {
		$HTTP_POST_VARS['s_year'] = $s_year;
		$HTTP_POST_VARS['s_month'] = $s_month;
		$HTTP_POST_VARS['s_day'] = $s_day;
		$HTTP_POST_VARS['e_year'] = $e_year;
		$HTTP_POST_VARS['e_month'] = $e_month;
		$HTTP_POST_VARS['e_day'] = $e_day;
	}
?>
<table cellspacing=2 cellpadding=2 border=0 align="center">
<form action="admstats.php" method="post">
<tr>
	<td valign="top"><b>From: </b></td>
	<td align="center"><font size="-1">month</font><br><select name="s_month"><?php echo tmpl_draw_select_opt($vl_m, $kl_m, $HTTP_POST_VARS['s_month'], '', ''); ?></select></td>
	<td align="center"><font size="-1">day</font><br><select name="s_day"><?php echo tmpl_draw_select_opt($vl_d, $kl_d, $HTTP_POST_VARS['s_day'], '', ''); ?></select></td>
	<td align="center"><font size="-1">year</font><br><select name="s_year"><?php echo tmpl_draw_select_opt($vl_y, $kl_y, $HTTP_POST_VARS['s_year'], '', ''); ?></select></td>
</tr>
<tr>
	<td valign="top"><b>To: </b></td>
	<td align="center"><font size="-1">month</font><br><select name="e_month"><?php echo tmpl_draw_select_opt($vl_m, $kl_m, $HTTP_POST_VARS['e_month'], '', ''); ?></select></td>
	<td align="center"><font size="-1">day</font><br><select name="e_day"><?php echo tmpl_draw_select_opt($vl_d, $kl_d, $HTTP_POST_VARS['e_day'], '', ''); ?></select></td>
	<td align="center"><font size="-1">year</font><br><select name="e_year"><?php echo tmpl_draw_select_opt($vl_y, $kl_y, $HTTP_POST_VARS['e_year'], '', ''); ?></select></td>
</tr>
<tr>
	<td valign="top"><b>Level of detail: </b></td>
	<td colspan=3><select name="sep"><?php echo tmpl_draw_select_opt("day\nweek\nmonth\nyear", "Day\nWeek\nMonth\nYear", $sep, '', ''); ?></select></td>
</tr>
<tr>
	<td valign="top"><b>Graph Data: </b></td>
	<td colspan=3><select name="type"><?php echo tmpl_draw_select_opt("msg\nthr\nusr", "Posted Messages\nCreated Topics\nRegistered users", $type, '', ''); ?></select></td>
</tr>
<tr><td colspan=4 align="right"><input type="submit" name="submit" value="Submit"></td></tr>
<?php echo _hs; ?>
<input type="hidden" name="submitted" value="1">
</form>
</table>
<br>
<h4>Disk Usage</h4>
<table width="100%" border=0 cellspacing=1 cellpadding=3 style="border: 1px #000000 solid;">
<tr>
	<td><b>FUDforum Include Directory:</b><br><font size="-1"><b><?php echo $INCLUDE; ?></b><br>this is where all the forum's function files are stored</font></td>
	<td width=100>&nbsp;</td>
	<td align="right" valign="top"><?php echo number_format(sprintf("%.2f", $disk_usage_array['INCLUDE']/1024)); ?> Kb</td>
</tr>

<tr>
	<td><b>Web Dir:</b><br><font size="-1"><b><?php echo $WWW_ROOT_DISK; ?></b><br>this is where all the forum's web browseable files are stored</font></td>
	<td width=100>&nbsp;</td>
	<td align="right" valign="top"><?php echo number_format(sprintf("%.2f", $disk_usage_array['WWW_ROOT_DISK']/1024)); ?> Kb</td>
</tr>

<tr>
	<td><b>Template Dir:</b><br><font size="-1"><b><?php echo $DATA_DIR; ?></b><br>this is where the forum's internal data files are stored</font></td>
	<td width=100>&nbsp;</td>
	<td align="right" valign="top"><?php echo number_format(sprintf("%.2f", $disk_usage_array['DATA_DIR']/1024)); ?> Kb</td>
</tr>

<tr bgcolor="#bff8ff">
	<td colspan=2><b>Total Disk Usage:</b></td>
	<td align="right" valign="top"><?php echo number_format(sprintf("%.2f", $total_disk_usage/1024)); ?> Kb</td>
</tr>
<?php if( $sql_disk_usage ) { ?>
<tr bgcolor="#bff8ff">
        <td colspan=2><b>MySQL Disk Usage:</b><br><font style="font-size: xx-small;">may not be 100% accurate, depends on MySQL version.</font></td>
	<td align="right" valign="top"><?php echo number_format(sprintf("%.2f", $sql_disk_usage/1024)); ?> Kb</td>
</tr>
<?php } ?>
</table>

<h4>Forum Statistics</h4>
<table width="100%" border=0 cellspacing=1 cellpadding=3 style="border: 1px #000000 solid;">
<tr>
	<td><b>Messages:</b></td>
	<td width=100>&nbsp;</td>
	<td align="right" valign="top"><?php echo $forum_stats['MESSAGES']; ?></td>
	<td colspan=2 width=100>&nbsp;</td>
</tr>

<tr>	
	<td valign="top"><b>Topics:</b></td>
	<td width=100>&nbsp;</td>
	<td align="right" valign="top"><?php echo $forum_stats['THREADS']; ?></td>
	<td width=100>&nbsp;</td>
	<td><font size="-1"><b><?php echo @sprintf("%.2f", $forum_stats['MESSAGES']/$forum_stats['THREADS']); ?></b> messages per topic</font></td>
</tr>

<tr>	
	<td valign="top"><b>Forums:</b></td>
	<td width=100>&nbsp;</td>
	<td align="right" valign="top"><?php echo $forum_stats['FORUMS']; ?></td>
	<td width=100>&nbsp;</td>
	<td><font size="-1">
		<b><?php echo @sprintf("%.2f", $forum_stats['MESSAGES']/$forum_stats['FORUMS']); ?></b> messages per forum<br>
		<b><?php echo @sprintf("%.2f", $forum_stats['THREADS']/$forum_stats['FORUMS']); ?></b> topics per forum
	</font></td>
</tr>

<tr>	
	<td valign="top"><b>Categories:</b></td>
	<td width=100>&nbsp;</td>
	<td align="right" valign="top"><?php echo $forum_stats['CATEGORIES']; ?></td>
	<td width=100>&nbsp;</td>
	<td><font size="-1">
		<b><?php echo @sprintf("%.2f", $forum_stats['MESSAGES']/$forum_stats['CATEGORIES']); ?></b> messages per category<br>
		<b><?php echo @sprintf("%.2f", $forum_stats['THREADS']/$forum_stats['CATEGORIES']); ?></b> topics per category<br>
		<b><?php echo @sprintf("%.2f", $forum_stats['FORUMS']/$forum_stats['CATEGORIES']); ?></b> forums per category
	</font></td>
</tr>

<tr>
	<td><b>Private Messages:</b></td>
	<td width=100>&nbsp;</td>
	<td align="right" valign="top"><?php echo $forum_stats['PRIVATE_MESSAGES']; ?></td>
	<td colspan=2 width=100>&nbsp;</td>
</tr>

<tr>	
	<td valign="top"><b>Users:</b></td>
	<td width=100>&nbsp;</td>
	<td align="right" valign="top"><?php echo $forum_stats['MEMBERS']; ?></td>
	<td width=100>&nbsp;</td>
	<td><font size="-1">
		<b><?php echo @sprintf("%.2f", $forum_stats['MESSAGES']/$forum_stats['MEMBERS']); ?></b> messages per user<br>
		<b><?php echo @sprintf("%.2f", $forum_stats['THREADS']/$forum_stats['MEMBERS']); ?></b> topics per user<br>
		<b><?php echo @sprintf("%.2f", $forum_stats['PRIVATE_MESSAGES']/$forum_stats['MEMBERS']); ?></b> private messages per user
	</font></td>
</tr>

<tr>	
	<td valign="top"><b>Moderators:</b></td>
	<td width=100>&nbsp;</td>
	<td align="right" valign="top"><?php echo $forum_stats['MODERATORS']; ?></td>
	<td width=100>&nbsp;</td>
	<td><font size="-1">
		<b><?php echo @sprintf("%.2f", $forum_stats['MODERATORS']/$forum_stats['MEMBERS']); ?>%</b> of all users<br>
		<b><?php echo @sprintf("%.2f", $forum_stats['MODERATORS']/$forum_stats['FORUMS']); ?></b> per forum
	</font></td>
</tr>

<tr>	
	<td valign="top"><b>Administrators:</b></td>
	<td width=100>&nbsp;</td>
	<td align="right" valign="top"><?php echo $forum_stats['ADMINS']; ?></td>
	<td width=100>&nbsp;</td>
	<td><font size="-1"><b><?php echo @sprintf("%.2f", $forum_stats['ADMINS']/$forum_stats['MEMBERS']); ?>%</b> of all users</font></td>
</tr>

<tr>	
	<td valign="top"><b>User Groups:</b></td>
	<td width=100>&nbsp;</td>
	<td align="right" valign="top"><?php echo $forum_stats['GROUPS']; ?></td>
	<td width=100>&nbsp;</td>
	<td><font size="-1"><b><?php echo @sprintf("%.2f", $forum_stats['GROUP_MEMBERS']/$forum_stats['GROUPS']); ?></b> members per group</font></td>
</tr>
</table>
<?php readfile("admclose.html"); ?>