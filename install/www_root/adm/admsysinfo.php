<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: admsysinfo.php,v 1.3 2002/06/26 19:41:21 hackie Exp $
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
	
	fud_use('adm.inc', TRUE);

function get_php_setting($val)
{
	return (ini_get($val) ? 'ON' : 'OFF');
}

function get_server_software()
{
	if( !empty($GLOBALS['HTTP_ENV_VARS']['SERVER_SOFTWARE']) ) 
		return $GLOBALS['HTTP_ENV_VARS']['SERVER_SOFTWARE'];
	else if( !empty($GLOBALS['HTTP_SERVER_VARS']['SERVER_SOFTWARE']) ) 
		return $GLOBALS['HTTP_SERVER_VARS']['SERVER_SOFTWARE'];
	else
		return 'n/a';	
}

function get_os()
{
	if( function_exists("php_uname") ) 
		return php_uname();
	else if( ($ret = exec("uname -a")) ) 
		return $ret;
	else
		return 'unknown';	
}
	
	list($ses, $usr) = initadm();

	include "admpanel.php";
?>
<h2>System Configuration</h2>
<table cellspacing=3 cellpadding=1 border=0>
<tr>
	<td><b>PHP built On:</b></td>
	<td><?php echo get_os(); ?></td>
</tr>
<tr>
	<td><b>MySQL Version:</b></td>
	<td><?php echo q_singleval("SELECT VERSION()"); ?></td>
</tr>
<tr>
	<td><b>PHP Version:</b></td>
	<td><?php echo phpversion(); ?></td>
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
				<td><?php echo get_php_setting("safe_mode"); ?></td>
			</tr>
			<tr>
				<td>Display Errors:</td>
				<td><?php echo get_php_setting("display_errors"); ?></td>
			</tr>
			<tr>
				<td>File Uploads:</td>
				<td><?php echo get_php_setting("file_uploads"); ?></td>
			</tr>
			<tr>
				<td>Magic Quotes:</td>
				<td><?php echo get_php_setting("magic_quotes_gpc"); ?></td>
			</tr>
			<tr>
				<td>Register Globals:</td>
				<td><?php echo get_php_setting("register_globals"); ?></td>
			</tr>
			<tr>
				<td>Output Buffering:</td>
				<td><?php echo get_php_setting("output_buffering"); ?></td>
			</tr>
			<tr>
				<td>Disabled Functions:</td>
				<td><?php echo (($df=ini_get("disable_functions"))?$df:'none'); ?></td>
			</tr>
			<tr>
				<td>PSpell Support:</td>
				<td><?php echo function_exists("pspell_new") ? 'Yes' : 'No'; ?></td>
			</tr>
		</table>
	</td>
</tr>
</table>
<?php readfile("admclose.html"); ?>