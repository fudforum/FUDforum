<?php
/***************************************************************************
* copyright            : (C) 2001-2004 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: admsysinfo.php,v 1.18 2004/01/04 16:38:32 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it 
* under the terms of the GNU General Public License as published by the 
* Free Software Foundation; either version 2 of the License, or 
* (at your option) any later version.
***************************************************************************/

	require('./GLOBALS.php');
	fud_use('adm.inc', true);

function get_php_setting($val)
{
	$r =  (ini_get($val) == '1' ? 1 : 0);

	return $r ? 'ON' : 'OFF';
}

function get_server_software()
{
	if (isset($_SERVER['SERVER_SOFTWARE'])) {
		return $_SERVER['SERVER_SOFTWARE'];
	} else if (($sf = getenv('SERVER_SOFTWARE'))) {
		return $sf;
	} else {
		return 'n/a';
	}
}

	require($WWW_ROOT_DISK . 'adm/admpanel.php');
?>
<h2>System Configuration</h2>
<table class="datatable">
<tr>
	<td><b>PHP built On:</b></td>
	<td><?php echo php_uname(); ?></td>
</tr>
<tr>
	<td><b>Database Version:</b></td>
	<td><?php echo q_singleval('SELECT VERSION()'); ?></td>
</tr>
<tr>
	<td><b>PHP Version:</b></td>
	<td><?php echo PHP_VERSION; ?></td>
</tr>
<tr>
	<td><b>Web Server:</b></td>
	<td><?php echo get_server_software(); ?></td>
</tr>
<tr>
	<td><b>WebServer to PHP interface:</b></td>
	<td><?php echo php_sapi_name(); ?></td>
</tr>
<tr>
	<td><b>Forum Version:</b></td>
	<td><?php echo $FORUM_VERSION; ?></td>
</tr>
<tr>
	<td valign="top"><b>Relavent PHP Settings:</b></td>
	<td>
		<table cellspacing=1 cellpadding=1 border=0>
			<tr>
				<td>Safe Mode:</td>
				<td><?php echo get_php_setting('safe_mode'); ?></td>
			</tr>
			<tr>
				<td>Open basedir:</td>
				<td><?php echo (($ob = ini_get('open_basedir')) ? $ob : 'none'); ?></td>
			</tr>
			<tr>
				<td>Display Errors:</td>
				<td><?php echo get_php_setting('display_errors'); ?></td>
			</tr>
			<tr>
				<td>File Uploads:</td>
				<td><?php echo get_php_setting('file_uploads'); ?></td>
			</tr>
			<tr>
				<td>Magic Quotes:</td>
				<td><?php echo get_php_setting('magic_quotes_gpc'); ?></td>
			</tr>
			<tr>
				<td>Register Globals:</td>
				<td><?php echo get_php_setting('register_globals'); ?></td>
			</tr>
			<tr>
				<td>Output Buffering:</td>
				<td><?php echo (is_numeric(ini_get('output_buffering')) ? 'Yes' : 'No'); ?></td>
			</tr>
			<tr>
				<td>Disabled Functions:</td>
				<td><?php echo (($df=ini_get('disable_functions'))?$df:'none'); ?></td>
			</tr>
			<tr>
				<td>PDF Support:</td>
				<td><?php echo extension_loaded('pdf') ? 'Yes' : 'No'; ?></td>
			</tr>
			<tr>
				<td>Tokenizer Support:</td>
				<td><?php echo extension_loaded('tokenizer') ? 'Yes' : 'No'; ?></td>
			</tr>
			<tr>
				<td>PSpell Support:</td>
				<td><?php echo extension_loaded('pspell') ? 'Yes' : 'No'; ?></td>
			</tr>
			<tr>
				<td>Zlib Support:</td>
				<td><?php echo extension_loaded('zlib') ? 'Yes' : 'No'; ?></td>
			</tr>
		</table>
	</td>
</tr>
</table>
<?php require($WWW_ROOT_DISK . 'adm/admclose.html'); ?>
