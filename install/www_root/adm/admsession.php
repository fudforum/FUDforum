<?php
/**
* copyright            : (C) 2001-2013 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: admsessions.php 4994 2010-09-02 17:33:29Z naudefj $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; version 2 of the License.
**/

	require('./GLOBALS.php');
	fud_use('adm.inc', true);

	require($WWW_ROOT_DISK .'adm/header.php');
?>

<h2>Forum Sessions</h2>

<p><b>Top Actions:</b></p>
<table class="resulttable fulltable">
<thead><tr class="resulttopic">
	<th width="70%">Action</th>
	<th width="30%">Count</th>
</tr></thead>
<?php
	$c = uq(q_limit('SELECT action, count(*) FROM '. $DBHOST_TBL_PREFIX .'ses s GROUP BY action ORDER BY count(*) DESC', 10));
	$i = 0;
	while ($r = db_rowarr($c)) {
		$r[0] = preg_replace('/href="/', 'href="'. $WWW_ROOT, $r[0]); // Fix URL.
		$bgcolor = ($i++%2) ? ' class="resultrow1"' : ' class="resultrow2"';
		echo '<tr'. $bgcolor .'"><td>'. $r[0] .'</td>';
		echo '<td>'. $r[1]  .'</td>';
	}
	unset($c);
?>
</table>

<p><b>Top IP Addresses:</b></p>
<table class="resulttable fulltable">
<thead><tr class="resulttopic">
	<th width="70%">IP Address</th>
	<th width="30%">Count</th>
</tr></thead>
<?php
	$c = uq(q_limit('SELECT ip_addr, count(*) FROM '. $DBHOST_TBL_PREFIX .'ses s GROUP BY ip_addr ORDER BY count(*) DESC', 10));
	$i = 0;
	while ($r = db_rowarr($c)) {
		$bgcolor = ($i++%2) ? ' class="resultrow1"' : ' class="resultrow2"';
		echo '<tr'. $bgcolor .'"><td><a href="../'. __fud_index_name__ .'?t=ip&amp;ip='. $r[0] .'&amp;'. __adm_rsid .'" title="Analyse IP usage">'. $r[0] .'</a></td>';	
		echo '<td>'. $r[1]  .'</td>';
	}
	unset($c);
?>
</table>

<p><b>Top User Agents:</b></p>
<table class="resulttable fulltable">
<thead><tr class="resulttopic">
	<th width="70%">User Agent</th>
	<th width="30%">Count</th>
</tr></thead>
<?php
	$c = uq(q_limit('SELECT useragent, count(*) FROM '. $DBHOST_TBL_PREFIX .'ses s GROUP BY useragent ORDER BY count(*) DESC', 10));
	$i = 0;
	while ($r = db_rowarr($c)) {
		$bgcolor = ($i++%2) ? ' class="resultrow1"' : ' class="resultrow2"';
		echo '<tr'. $bgcolor .'"><td>'. $r[0] .'</td>';
		echo '<td>'. $r[1]  .'</td>';
	}
	unset($c);
?>
</table>

<p><b>Detailed session records:</b></p>
<table class="resulttable fulltable">
<thead><tr class="resulttopic">
	<th>User</th>
	<th>Action</th>
	<th>IP Address</th>
	<th>User Agent</th>
</tr></thead>
<?php
	$c = uq(q_limit('SELECT u.alias, action, ip_addr, useragent FROM '. $DBHOST_TBL_PREFIX .'ses s LEFT JOIN '. $DBHOST_TBL_PREFIX .'users u ON s.user_id=u.id ORDER BY time_sec', 1000));
	$i = 0;
	while ($r = db_rowarr($c)) {
		$r[1] = preg_replace('/href="/', 'href="'. $WWW_ROOT, $r[1]); // Fix URL.
		$bgcolor = ($i++%2) ? ' class="resultrow1"' : ' class="resultrow2"';
		echo '<tr'. $bgcolor .'"><td>'. (empty($r[0]) ? $ANON_NICK : $r[0]) .'</td>';
		echo '<td>'. $r[1]  .'</td>';
		echo '<td>'. $r[2] .'</td>';
		echo '<td>'. $r[3] .'</td>';
	}
	unset($c);
	if (!$i) {
		echo '<tr><td colspan="4" align="center">None found.</td></tr>';
	} else if ($i >= 1000) {
		echo '<tr><td colspan="4" align="center">Only '. $i .' rows listed.</td></tr>';
	}
?>
</table>
<p><a href="admuser.php?<?php echo __adm_rsid; ?>">&laquo; Back to User Administration System</a></p>
<?php require($WWW_ROOT_DISK .'adm/footer.php'); ?>

