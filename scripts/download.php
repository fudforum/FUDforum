<?php
	/* Settings */
	$SQL_DB = "cvs.sqlite";
	$DWN_PATH = "";

	include_once "header.inc";

function print_latest($r, $is_s)
{
	echo '<h5 style="color: green">
<b>Latest '.($is_s ? 'Stable' : 'Development').' Release</b> (<font color="red">'. $r['name'].'</font>) 
[<a href="download.php?di='.$r['id'].'&i=1">Installer</a>] 
[<a href="download.php?di='.$r['id'].'&u=1">Upgrade Script</a>]
<br /><font color="black" size="-2">Released On: '.date("r", $r['release_date']).'</font>
		</h5>';
}

function release_list($rl, $is_s)
{
	echo '<font size="+1"><b>Older '.($is_s ? 'Stable' : 'Development').' Releases</b></font><br /><form method="get" action="download.php"><select name="di">';
	foreach ($rl as $r) {
		echo '<option value="'.$r['id'].'">'.$r['name'].' ('.date("M j, Y", $r['release_date']).')</option>';
	}
	echo '</select>&nbsp;&nbsp;<input type="submit" value="Download" name="submit"></form>';
}

function print_dwn_lnk($r, $is_i, $is_c=0)
{
	global $db;
	global $DWN_PATH;

	if ($is_c) {
		$dw = sqlite_array_query($db, "SELECT file_name, md5_checksum FROM fud_down_md5 WHERE file_name LIKE '".sqlite_escape_string($r['ar_name']).".%'", SQLITE_NUM);

		echo '<h5 style="color: green"><b>'.$r['name'].'</b> (<font color="red">Compatible with FUDforum versions '.$r['conv_version'].'</font>)<br />';
		foreach ($dw as $d) {
			echo '<a href="'.$DWN_PATH.$d[0].'">'.$d[0].'</a> (<font color="black">md5 checksum:</font> '.$d[1].')<br />';
		}
		echo '</h5>';

		return;
	}

	if (!$is_i && !strncmp($r['ar_name'], 'FUDforum_', strlen('FUDforum_'))) {
		$r['ar_name'] = str_replace('_2', '_upgrade_2', $r['ar_name']);
	}

	if ($r['is_zlib']) {
		$dw = sqlite_array_query($db, "SELECT file_name, md5_checksum FROM fud_down_md5 WHERE file_name LIKE '".sqlite_escape_string(str_replace('_2', '_zl_2', $r['ar_name'])).".%'", SQLITE_NUM);

		echo '<h5 style="color: green"><b>'.$r['name'].' '.($is_i ? 'installer' : 'upgrade script').'</b> (<font color="red">with zlib compression</font>)<br />';
		foreach ($dw as $d) {
			echo '<a href="'.$DWN_PATH.$d[0].'">'.$d[0].'</a> (<font color="black">md5 checksum:</font> '.$d[1].')<br />';
		}
		echo '</h5>';
	}

	$dw = sqlite_array_query($db, "SELECT file_name, md5_checksum FROM fud_down_md5 WHERE file_name LIKE '".sqlite_escape_string($r['ar_name']).".%'", SQLITE_NUM);

	echo '<h5 style="color: green"><b>'.$r['name'].' '.($is_i ? 'installer' : 'upgrade script').'</b> (<font color="red">no zlib compression</font>)<br />';
	foreach ($dw as $d) {
		echo '<a href="'.$DWN_PATH.$d[0].'">'.$d[0].'</a> (<font color="black">md5 checksum:</font> '.$d[1].')<br />';
	}
	echo '</h5>';
}

	$rdata = $cdata = '';

	$db = sqlite_open($SQL_DB, 0444);

	if (isset($_GET['di'])) {
		if (($rdata = sqlite_array_query($db, 'SELECT * FROM fud_down WHERE id='.(int)$_GET['di'], SQLITE_ASSOC))) {
			$rdata = $rdata[0];
		}
	} else if (isset($_GET['ci'])) {
		if (($cdata = sqlite_array_query($db, 'SELECT * FROM fud_conv WHERE id='.(int)$_GET['ci'], SQLITE_ASSOC))) {
			$cdata = $cdata[0];
		}
	}
	
	if (!$cdata && !$rdata) {
		$lsa = sqlite_array_query($db, 'SELECT * FROM fud_down WHERE is_beta=0 ORDER BY release_date DESC', SQLITE_ASSOC);
		$ls = array_shift($lsa);
		
		/* move absolete versions out */
		$obs = array_splice($lsa, -2);

		$lba = sqlite_array_query($db, 'SELECT * FROM fud_down WHERE is_beta=1 ORDER BY release_date DESC', SQLITE_ASSOC);
		$lb = array_shift($lba);

		$main = 1;
	} else {
		$main = 0;
	}
?>
<table bgcolor="#FFFFFF" width="100%" border="0" cellspacing="0" cellpadding="2">
<tr><td bgcolor="#FFFFFF"><img src="blank.gif" height=1 width=1 border=0></td></tr>
<tr><td class="dashed">
<table width="100%" cellspacing=0 cellpadding=2 border=0>
<tr>
	<td>
<?php
if ($main) { /* main page */
	if ($ls['release_date'] > $lb['release_date']) {
		$lr = $ls;
	} else {
		$lr = $lb;
	}
	echo '<h2 align="center" style="color: blue; padding: 2px;">Latest Release: '.$lr['name'].'<br /><font size="-2">Released On: '.date("r", $lr['release_date']).' (<a href="http://cvs.prohost.org/c/index.cgi/FUDforum/timeline">View ChangeLog</a>)</font></h2>';

	/* stable releases */
	print_latest($ls, 1);
	release_list($lsa, 1);
	
	/* dev releases */
	print_latest($lb, 0);
	release_list($lba, 0);

	/* handle conversion scripts */
	echo '<br /><font size="+1"><b>Conversion Scripts From Other Bulletin Boards &amp; Forums</b></font>';
	echo '<form method="get" action="download.php"><select name="ci">';
	$ca = sqlite_array_query($db, 'SELECT * FROM fud_conv ORDER BY tag', SQLITE_ASSOC);
	foreach ($ca as $c) {
		echo '<option value="'.$c['id'].'">'.$c['name'].' (to FUDforum v'.$c['conv_version'].')</option>';
	}
	echo '</select>&nbsp;&nbsp;<input type="submit" value="Download" name="submit"></form>';

	/* absolete version (1.2.X) conversion scripts */
	echo '<br /><font size="+1"><b>Upgrade scripts from obsolete versions of FUDforum</b></font>';
	foreach ($obs as $r) {
		echo '<h5 style="color: green; padding: 2px; margin: 0px;"><font color="red">'.$r['name'].'</font>[<a href="download.php?di='.$r['id'].'&u=1">Upgrade Script</a>]<br /><font color="black" size="-2">Released On: '.date("r", $r['release_date']).'</font></h5>';
	}
} else { /* download form */
	if ($rdata) { /* installer/upgrade */
		echo '<h2 align="center" style="color: blue; padding: 2px;">Download: <font size="+1">'.$rdata['name'].'</font></h2>';

		/* determine which download links to show */
		if (isset($_GET['i'])) {
			$i = 1; $u = 0;
		} else if (isset($_GET['u'])) {
			$i = 0; $u = 1;
		} else {
			$i = $u = 1;
		}

		if ($i) {
			echo '<h4 align="center" style="color: blue; padding: 2px;">Installation Script</h4>';
			print_dwn_lnk($rdata, 1);
		}
		if ($u) {
			echo '<h4 align="center" style="color: blue; padding: 2px;">Upgrade Script</h4>';
			print_dwn_lnk($rdata, 0);
		}		
	} else { /* conversion script */
		echo '<h2 align="center" style="color: blue; padding: 2px;">Download: <font size="+1">'.$cdata['name'].'</font></h2>';
		print_dwn_lnk($cdata, 0, 1);
	}
	
	echo '<font size="+1"><a href="download.php">Return to Download Manager</a></font>';
}
?>

</td></tr></table> 

<? include_once "footer.inc"; ?>