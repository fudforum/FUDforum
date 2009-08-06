<?php
/**
* copyright            : (C) 2001-2009 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: admphpinfo.php,v 1.9 2009/08/06 18:00:55 frank Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; version 2 of the License.
**/

	require('./GLOBALS.php');
	fud_use('adm.inc', true);
	require($WWW_ROOT_DISK . 'adm/admpanel.php');

	ob_start();
	phpinfo(INFO_GENERAL|INFO_CONFIGURATION|INFO_MODULES|INFO_ENVIRONMENT|INFO_VARIABLES);
	$info = ob_get_clean();                                                                                        

	$info = preg_replace('%^.*<body>(.*)</body>.*$%ms', '$1', $info);

	echo '<h2>System Configuration</h2>';
	echo '<p><b>This page lists information about the PHP version installed on this server.<br />It may contain sensitive information and <u>should be kept private</u>!</b></p>';
	echo '<table class="phpinfo">'.$info.'</table>';

	require($WWW_ROOT_DISK . 'adm/admclose.html');
?>
