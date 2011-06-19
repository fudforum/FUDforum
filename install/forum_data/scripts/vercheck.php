<?php
/**
* copyright            : (C) 2001-2011 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id$
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; version 2 of the License.
**/

	define('no_session', 1);

	if (strncmp($_SERVER['argv'][0], '.', 1)) {
		require (dirname($_SERVER['argv'][0]) .'/GLOBALS.php');
	} else {
		require (getcwd() .'/GLOBALS.php');
	}

	if ((bool)ini_get('allow_url_fopen') == FALSE) {
		die("Unable to check version. Please enable allow_url_fopen in your php.ini.\n");
	}

	if (file_exists($FORUM_SETTINGS_PATH .'latest_version')) {
		$lastcheck = filemtime($FORUM_SETTINGS_PATH .'latest_version');
		if ($lastcheck > time() - 86400) {	// 1 day.
			die("Skip. Forum version was recently checked.\n");
		}
	}

	echo "Busy looking up the latest forum version from FUDforum's wiki...\n";
	fud_use('url.inc', true);	// For get_remote_file().
	$verinfo = get_remote_file('http://cvs.prohost.org/index.php?title=Current_version&action=raw');

	if ($verinfo && strpos($verinfo, '::')) {
		// Write version to the forum's cache directory.
		file_put_contents($FORUM_SETTINGS_PATH .'latest_version', $verinfo);
	} else {
		die('Lookup failed. Data returned ['. $verinfo .'].');
	}

	list($latest_ver, $download_url) = explode('::', $verinfo);
	echo 'Current version: '. $FORUM_VERSION .', latest version is: '. $latest_ver ."\n";

	if (version_compare($latest_ver, $FORUM_VERSION, '>')) {
		echo 'Please upgrade to '. $latest_ver ." ASAP!\n";
		define('_uid', 1);
		fud_use('db.inc');
		fud_use('logaction.inc');
		fud_use('iemail.inc');
		send_email($NOTIFY_FROM, $ADMIN_EMAIL, 'New FUDforum version available', 'A new FUDforum version is now available. Please upgrade your site at '. $WWW_ROOT .' from '. $FORUM_VERSION .' to '. $latest_ver .' ASAP. The upgrade script can be downloaded from '. $download_url);
		// TODO: Download and unzip the upgrade script, ready for the admin to run.
	} else {
		echo "You are on the latest release.\n";
	}
?>
