<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: unprotect.php,v 1.3 2003/05/26 10:26:38 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

function fud_ini_get($opt)
{
	return (ini_get($opt) == '1' ? 1 : 0);
}

	if (fud_ini_get('safe_mode') && basename(__FILE__) != 'safe_unprotect.php') {
		$c = getcwd();
		copy($c . '/unprotect.php', $c . '/safe_unprotect.php');
		header('Location: safe_unprotect.php');
		exit;
	}
?>
<html>
<head>
	<title>FUDforum Unlock Files Script</title>
</head>
<body bgcolor="white">
<?php

function fud_unlock($dir)
{
	if (!($d = opendir($dir))) {
		echo '<b>Could not open directory "'.$dir.'"<br>';
		return;
	}
	readdir($d); readdir($d);
	while ($f = readdir($d)) {
	 	switch (filetype($dir . '/' . $f)) {
	 		case 'file':
	 		case 'link':
	 			if (!chmod($dir . '/' . $f, 0666)) {
	 				echo '<b>Could not unlock file "'.$dir . '/' . $f.'"<br>';
	 			}
	 			break;
	 		case 'dir':
	 			fud_unlock($dir . '/' . $f);
	 			break;
	 	}
	}
	closedir($d);
	if (!chmod($dir, 0777)) {
		echo '<b>Could not unlock directory "'.$dir.'"<br>';
	}
}

	require "GLOBALS.php";
	
	umask(0);
	fud_unlock(substr($DATA_DIR, 0, -1));
	if ($DATA_DIR != $WWW_ROOT_DISK) {
		fud_unlock(substr($WWW_ROOT_DISK, 0, -1));
	}
	@unlink($GLOBALS['ERROR_PATH'].'FILE_LOCK');
	if (basename(__FILE__) == 'safe_unprotect.php') {
		@unlink('safe_unprotect.php');
	}
?>
<div align="center">
Your forum is now unlocked.
</div>
</body>
</html>