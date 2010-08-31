<?php
/**
* copyright            : (C) 2001-2010 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id$
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; version 2 of the License.
**/

	require('./GLOBALS.php');
	fud_use('adm.inc', true);
	fud_use('glob.inc', true);
	fud_use('widgets.inc', true);
	fud_use('draw_select_opt.inc');

	require($WWW_ROOT_DISK .'adm/header.php');
	$help_ar = read_help();

	// Enable or disable ENABLE_GEO_LOCATION & UPDATE_GEOLOC_ON_LOGIN.
	if (isset($_POST['form_posted'])) {
		if (isset($_POST['FUD_OPT_3_ENABLE_GEO_LOCATION']) || isset($_POST['FUD_OPT_3_UPDATE_GEOLOC_ON_LOGIN'])) {
			if ($_POST['FUD_OPT_3_ENABLE_GEO_LOCATION'] & 524288) {
				$FUD_OPT_3 |= 524288;
			} else {
				$FUD_OPT_3 &= ~524288;
			}
			if ($_POST['FUD_OPT_3_UPDATE_GEOLOC_ON_LOGIN'] & 2097152) {
				$FUD_OPT_3 |= 2097152;
			} else {
				$FUD_OPT_3 &= ~2097152;
			}
			change_global_settings(array('FUD_OPT_3' => $FUD_OPT_3));
			echo successify('Settings successfully updated.');
		}
	}

	$format_names = array('', 'GeoIP', 'IP-to-Country');
	$format_vals = array('', 'GEO', 'IP2C');
	$i = 0;

	$upload_ok = 0;
	if (!empty($_POST['format']) && !empty($_FILES['file'])) {
		if (!in_array($_POST['format'], $format_vals)) {
			echo errorify('Invalid File Format.');
		} else if ($_FILES['file']['error']) {
			echo errorify('File upload failed! Please check that <i>file_uploads</i> is enabled in your php.ini file and ensure that the <i>upload_max_filesize</i> setting is bigger than the file\'s size.');
		} else {
			$upload_ok = 1;
		}
	}
?>
<style type="text/css">
.o { color: orange }
</style>
<script type="text/javascript">
/* <![CDATA[ */
function changeCaption(txt)
{
	document.getElementById('progress').firstChild.nodeValue = txt;
}
/* ]]> */
</script>
<span id="progress" style="font-color: green;"> </span>

<h2>Geolocation Configuration</h2>
<p class="tutor">
This control panel allows you to upload a CSV database containing ip-range to country associates necessary for the utilization of FUDforum's
Geo-Location feature.</p>

<h3>Settings</h3>
<form method="post" action="admgeoip.php"><?php echo _hs ?>
<table class="datatable solidtable">
<?php
	print_bit_field('Enable Geo-Location', 'ENABLE_GEO_LOCATION');
	print_bit_field('Update Geo-Location on login', 'UPDATE_GEOLOC_ON_LOGIN');
?>
<tr class="fieldaction"><td colspan="2" align="right"><input type="submit" name="btn_submit" value="Change settings" /></td></tr>
</table>
<input type="hidden" name="form_posted" value="1" />
</form>

<h3>Upload IP database</h3>
<form method="post" action="admgeoip.php" enctype="multipart/form-data"><?php echo _hs; ?>
<table class="datatable solidtable">
	<tr><td class="tutor" colspan="2">
<p>The two database format supported are GeoIP, which can be freely downloaded from 
<a href="http://www.maxmind.com/app/geoip_country" target="_blank">http://www.maxmind.com/app/geoip_country</a> or the IP-To-Country 
database freely available from 
<a href="http://ip-to-country.webhosting.info/node/view/6" target="_blank">http://ip-to-country.webhosting.info/node/view/6</a>.
Please note that commercial offerings are also available that will give better results.</p>

<p style="font-size: small;">The expected internal formats are as follows:<br />
GeoIP - "2.6.190.56","2.6.190.63",<span class="o">"33996344"</span>,<span class="o">"33996351"</span>,<span class="o">"GB"</span>,<span class="o">"United Kingdom"</span><br />
IP-2-Country - <span class="o">"33996344"</span>,<span class="o">"33996351"</span>,<span class="o">"GB"</span>,"GBR",<span class="o">"UNITED KINGDOM"</span><br />
The fields marked in orange are the ones the forum cares about, the rest of the fields are not relevant.</p>

<b>The import process usually takes a few minutes.</b>
	</td></tr>
	<tr class="field">
		<td>Database format</td>
		<td><select name="format"><?php echo tmpl_draw_select_opt(implode("\n", $format_vals), implode("\n", $format_names), '', '', ''); ?></select></td>
	</tr>
	<tr class="field">
		<td>Data file</td>
		<td><input type="file" name="file" /></td>
	</tr>
	<tr class="fieldaction"><td colspan="2" align="center"><input type="submit" name="btn_submit" value="Upload IP Database" /></td></tr>
</table>
</form>

<h3>Cache control</h3>
<form method="post" action="admgeoip.php" enctype="multipart/form-data"><?php echo _hs; ?>
<table class="datatable solidtable">
	<tr><td class="tutor" colspan="2">
When enabling Geo-Location functionality on an existing forum it is recommended that the user and message location caches are rebuilt,
without them, old messages will not have a flag appearing beside them. Please note that this is a <b>SLOW</b> process, which may take a few
hours on a large forum.
	</td></tr>
	<tr class="fieldaction"><td colspan="2" align="center">
		<input type="submit" name="rebuild_user_geoip" value="Rebuild User Cache" />
		<input type="submit" name="rebuild_msg_geoip" value="Rebuild Message Cache" />
	</td></tr>
</table>
</form>
<?php 
	require($WWW_ROOT_DISK .'adm/footer.php');

	if ($upload_ok) {
		while (@ob_end_flush());
		flush();
		q('DELETE FROM '. $DBHOST_TBL_PREFIX .'geoip');
		$fp = fopen($_FILES['file']['tmp_name'], 'r');
		while ($l = fgetcsv($fp, 100000)) {
			if ($_POST['format'] == 'GEO') {
				if (isset($l[2], $l[3], $l[4], $l[5])) {
					q('INSERT INTO '. $DBHOST_TBL_PREFIX .'geoip (ips, ipe, cc, country) VALUES('. _esc($l[2]) .','. _esc($l[3]) .','. _esc(strtolower($l[4])) .','. _esc($l[5]) .')');
					++$i;
				}
			} else if (isset($l[0], $l[1], $l[2], $l[4])) { // IP2C
				q('INSERT INTO '. $DBHOST_TBL_PREFIX .'geoip (ips, ipe, cc, country) VALUES('. _esc($l[0]) .','. _esc($l[1]) .','. _esc(strtolower($l[2])) .',' ._esc($l[4]) .')');
				++$i;
			}
			if (!($i % 100)) {
				echo '<script type="text/javascript">changeCaption("'. $i .' entries were imported!");</script>';
				echo "\n";
				flush();
			}
		}
		fclose($fp);
		echo '<script type="text/javascript">changeCaption("Import Completed, '. $i .' entries were imported!");</script>';
	} else if (!empty($_POST['rebuild_user_geoip'])) {
		while (@ob_end_flush());
		flush();
		$c = q('SELECT id, COALESCE(last_known_ip, reg_ip) FROM '. $DBHOST_TBL_PREFIX .'users');
		while ($r = db_rowarr($c)) {
			++$i;
			if (!$r[1] || (!$flag = db_saq('SELECT cc, country FROM '. $DBHOST_TBL_PREFIX .'geoip WHERE '. sprintf('%u', $r[1]) .' BETWEEN ips AND ipe'))) {
				continue;
			}
			q('UPDATE '. $DBHOST_TBL_PREFIX .'users SET flag_cc='. _esc($flag[0]) .', flag_country='. _esc($flag[1]) .' WHERE id='. $r[0]);
			if (!($i % 100)) {
				echo '<script type="text/javascript">changeCaption("'. $i .' user geo-location cache entries updated.");</script>';
				echo "\n";
				flush();
			}
		}
		echo '<script type="text/javascript">changeCaption("'. $i .' user geo-location cache entries updated.");</script>';
	} else if (!empty($_POST['rebuild_msg_geoip'])) {
		while (@ob_end_flush());
		flush();
		$c = q('SELECT distinct(ip_addr) FROM '. $DBHOST_TBL_PREFIX .'msg');
		while ($r = db_rowarr($c)) {
			++$i;
			if ($r[0] == '0.0.0.0' || (!$flag = db_saq('SELECT cc, country FROM '. $DBHOST_TBL_PREFIX .'geoip WHERE '. sprintf('%u', ip2long($r[0])) .' BETWEEN ips AND ipe'))) {
				continue;
			}
			q('UPDATE '. $DBHOST_TBL_PREFIX .'msg SET flag_cc='. _esc($flag[0]) .', flag_country='. _esc($flag[1]) .' WHERE ip_addr='. _esc($r[0]));
			if (!($i % 100)) {
				echo '<script type="text/javascript">changeCaption("'. $i .' message geo-location cache entries updated.");</script>';
				echo "\n";
				flush();
			}
		}
		echo '<script type="text/javascript">changeCaption("'. $i .' message geo-location cache entries updated.");</script>';
	}
