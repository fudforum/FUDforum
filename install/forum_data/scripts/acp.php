#!/usr/bin/php -q
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

	ini_set('memory_limit', '128M');
	define('no_session', 1);

	if (!ini_get('register_argc_argv')) {
		exit("Please enable the 'register_argc_argv' php.ini directive.\n");
	}
	if ($_SERVER['argc'] < 2) {
		exit("Please specify the Admin Control Panel action to perform.\n");
	}

	if (strncmp($_SERVER['argv'][0], '.', 1)) {
		require (dirname($_SERVER['argv'][0]) .'/GLOBALS.php');
	} else {
		require (getcwd() .'/GLOBALS.php');
	}

	$php  = escapeshellcmd($GLOBALS['PHP_CLI']);
	if (empty($php) || !is_executable($php)) {
		throw new Exception('PHP CLI Executable not set.');
	}

	$action = $_SERVER['argv'][1];
	switch ($action) {
		case 'backup':
			$acp  = 'admdump.php';
			$args = $GLOBALS['TMP'] .'FUDforum_'. strftime('%d_%m_%Y_%I_%M', __request_timestamp__) .'.fud';
			if (extension_loaded('zlib')) {
				$args .= '.gz compress';
			}
			break;
		case 'dbcheck':
			$acp  = 'consist.php';
			$args = 'optimize';
			break;
		case 'consist':
			$acp  = 'consist.php';
			$args = 'check';
			break;
		default:
			echo('Invalid action!');
			exit(-1);
	}

	system($php .' '. $GLOBALS['WWW_ROOT_DISK'] .'/adm/'. $acp .' '. $args);
?>
