<?php
/**
* copyright            : (C) 2001-2006 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: admgeoip.php,v 1.1 2006/08/01 01:22:09 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; either version 2 of the License, or
* (at your option) any later version.
**/

	require('./GLOBALS.php');
	fud_use('adm.inc', true);
	fud_use('glob.inc', true);
	fud_use('widgets.inc', true);
	fud_use('draw_select_opt.inc');

	$format_names = array('', 'GeoIP', 'IP-to-Country');
	$format_vals = array('', 'GEO', 'IP2C');
	$i = 0;

	require($WWW_ROOT_DISK . 'adm/admpanel.php');

	$upload_ok = 0;
	if (!empty($_POST['format']) && !empty($_FILES['file'])) {
		if (!in_array($_POST['format'], $format_vals)) {
			echo '<span class="alert">Invalid File Format</span>';
		} else if ($_FILES['file']['error']) {
			echo '<span class="alert">File Upload Failed</span>';
		} else {
			$upload_ok = 1;
		}
	}
?>
<style type="text/css">
.o { color: orange }
</style>
<script>
function changeCaption(txt)
{
	document.getElementById('progress').firstChild.nodeValue = txt;
}
</script>
<span id="progress"> <span>
<h2>Geo-Location Configuration</h2>
<form method="post" action="admgeoip.php" enctype="multipart/form-data"><?php echo _hs; ?>
<table class="datatable solidtable">
	<tr><td class="tutor" colspan="2">
This control panel allows you to upload a CSV database containing ip-range to country associates necessary for the utilization of FUDforum's
Geo-Location feature.<p />
The two database format supported are GeoIP, which can be freely downloaded from 
<a href="http://www.maxmind.com/app/geoip_country" target="_blank">http://www.maxmind.com/app/geoip_country</a> or the IP-To-Country 
database freely available from 
<a href="http://ip-to-country.webhosting.info/node/view/6" target="_blank">http://ip-to-country.webhosting.info/node/view/6</a>.
<p />
The expected internal formats are as follows:<br />
GeoIP - "2.6.190.56","2.6.190.63",<span class="o">"33996344"</span>,<span class="o">"33996351"</span>,<span class="o">"GB"</span>,<span class="o">"United Kingdom"</span><br />
IP-2-Country - <span class="o">"33996344"</span>,<span class="o">"33996351"</span>,<span class="o">"GB"</span>,"GBR",<span class="o">"UNITED KINGDOM"</span><br />
<p />	
The fields marked in orange are the ones the forum cares about, the rest of the fields are not relavent.	
<p />
<b>The import process usually takes a few minutes.</b>
	</td></tr>
	<tr>
		<td>Database Format</td>
		<td><select name="format"><?php echo tmpl_draw_select_opt(implode("\n", $format_vals), implode("\n", $format_names), '', '', ''); ?></select></td>
	</tr>
	<tr>
		<td>Data File</td>
		<td><input type="file" name="file" /></td>
	</tr>
	<tr class="fieldaction"><td colspan="2" align="center"><input type="submit" name="btn_submit" value="Upload IP Database"></td></tr>	
<table>
</form>
<?php 
	require($WWW_ROOT_DISK . 'adm/admclose.html');

	if ($upload_ok) {
		while (@ob_end_flush());
		flush();
		q("DELETE FROM ".$DBHOST_TBL_PREFIX."geoip");
		$fp = fopen($_FILES['file']['tmp_name'], 'r');
		while ($l = fgetcsv($fp, 100000)) {
			if ($_POST['format'] == 'GEO') {
				if (isset($l[2], $l[3], $l[4], $l[5])) {
					q("INSERT INTO ".$DBHOST_TBL_PREFIX."geoip (ips, ipe, cc, country) VALUES("._esc($l[2]).","._esc($l[3]).","._esc($l[4]).","._esc($l[5]).")");
					++$i;
				}
			} else if (isset($l[0], $l[1], $l[2], $l[4])) { // IP2C
				q("INSERT INTO ".$DBHOST_TBL_PREFIX."geoip (ips, ipe, cc, country) VALUES("._esc($l[0]).","._esc($l[1]).","._esc($l[2]).","._esc($l[4]).")");
				++$i;
			}
			if (!($i % 500)) {
				echo '<script>changeCaption("'.$i.' entries were imported!");</script>';
				echo "\n";
				flush();
			}
		}
		fclose($fp);
		echo '<script>changeCaption("Import Completed, '.$i.' entries were imported!");</script>';
	}
