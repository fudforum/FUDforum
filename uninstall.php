<?php
/***************************************************************************
* copyright            : (C) 2001-2004 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: uninstall.php,v 1.8 2004/10/06 16:36:15 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it 
* under the terms of the GNU General Public License as published by the 
* Free Software Foundation; either version 2 of the License, or 
* (at your option) any later version.
***************************************************************************/

function fud_ini_get($opt)
{
	return (ini_get($opt) == '1' ? 1 : 0);
}
	set_magic_quotes_runtime(0);

	define('SAFE_MODE', fud_ini_get('safe_mode'));

	if (count($_POST) && $_POST['SERVER_DATA_ROOT']) {
		if (SAFE_MODE && basename(__FILE__) != 'uninstall_safe.php') {
			$c = getcwd();
			copy($c . '/uninstall.php', $c . '/uninstall_safe.php');
			header('Location: '.dirname($_SERVER['SCRIPT_NAME']).'/uninstall_safe.php?SERVER_DATA_ROOT='.urlencode($_POST['SERVER_DATA_ROOT']).'&SERVER_ROOT='.urlencode($_POST['SERVER_ROOT']));
			exit;
		}
		$SERVER_DATA_ROOT = rtrim($_POST['SERVER_DATA_ROOT'], '\\/ ');
		$SERVER_ROOT = rtrim($_POST['SERVER_ROOT'], '\\/ ');
	} else if (SAFE_MODE && !empty($_GET['SERVER_DATA_ROOT'])) {
		$SERVER_DATA_ROOT = rtrim($_GET['SERVER_DATA_ROOT'], '\\/ ');
		$SERVER_ROOT = rtrim($_GET['SERVER_ROOT'], '\\/ ');
	}
?>
<html>
<head>
	<title>FUDforum Un-installer</title>
</head>
<body bgcolor="white">
<?php

function print_error($msg)
{
	exit('<font color="red">'.$msg.'</font></body></html>');
}

function fud_rmdir($dir)
{
	$dirs = array(realpath($dir));

	while (list(,$v) = each($dirs)) {
		if (!($files = glob($v.'/*'))) {
			continue;
		}
		foreach ($files as $file) {
			if (is_dir($file) && !is_link($file)) {
				$dirs[] = $file;
			} else if (!unlink($file)) {
				echo '<b>Could not delete file "'.$file.'"<br>';
			}
		}
	}
	
	$dirs = array_reverse($dirs);
	
	foreach ($dirs as $dir) {
		if (!rmdir($dir)) {
			echo '<b>Could not delete directory "'.$dir.'"<br>';
		}
	}
}

	if (isset($SERVER_DATA_ROOT)) {
		if (!is_dir($SERVER_DATA_ROOT)) {
			print_error('Directory "'.$SERVER_DATA_ROOT.'" does not exist');
		}

		/* try to read GLOBALS.php to determine database settings so that databases can be cleaned up */
		if (file_exists($SERVER_DATA_ROOT . 'include/GLOBALS.php')) {
			$data = file_get_contents($SERVER_DATA_ROOT . 'include/GLOBALS.php');
			$s = strpos($data, '*/') + 2;
			$data = substr($data, $s, (strpos($data, 'DO NOT EDIT FILE BEYOND THIS POINT UNLESS YOU KNOW WHAT YOU ARE DOING', $s) - $s)) . ' */';
			eval($data);
			
			if (!empty($DBHOST) && file_exists($SERVER_DATA_ROOT . 'include/theme/default/db.inc')) {
				$data = file_get_contents($SERVER_DATA_ROOT . 'include/GLOBALS.php');
				if (strpos($data, "define('__dbtype__', 'mysql')") !== FALSE) {
					$dbtype = 'mysql';
				} else {
					$dbtype = 'pgsql';
				}
				$remove_db = 1;
			}
		}

		/* remove files on disk */
		fud_rmdir($SERVER_DATA_ROOT);
		if ($SERVER_ROOT != $SERVER_DATA_ROOT && $SERVER_ROOT) {
			fud_rmdir($SERVER_ROOT);
		}

		/* remove database stuff if needed */
		if (isset($remove_db)) {
			if ($dbtype == 'mysql') {
				if (@mysql_connect($DBHOST, $DBHOST_USER, $DBHOST_PASSWORD)) {
					if (@mysql_select_db($DBHOST_DBNAME) && $DBHOST_TBL_PREFIX) {
						$c = mysql_query("SHOW TABLES LIKE '".$DBHOST_TBL_PREFIX."%'");
						while ($r = mysql_fetch_row($c)) {
							mysql_query('DROP TABLE '.$r[0]);
						}
					}
				}
			} else {
				$connect_str = '';
				if (!empty($DBHOST)) {
					$connect_str .= 'host='.$DBHOST;
				}
				if (!empty($DBHOST_USER)) {
					$connect_str .= ' user='.$DBHOST_USER;
				}
				if (!empty($DBHOST_PASSWORD)) {
					$connect_str .= ' password='.$DBHOST_PASSWORD;
				}
				if (!empty($DBHOST_DBNAME)) {
					$connect_str .= ' dbname='.$DBHOST_DBNAME;
				}
				if (($conn = @pg_connect($connect_str))) {
					while ($r = mysql_fetch_row($c)) {
						pg_query($conn, 'DROP TABLE '.$r[0]);
					}
				}
			}
		}
		print_error('FUDforum installed in "'.$SERVER_DATA_ROOT.'" has been successfully uninstalled');
	}
?>
<div align="center">
<form name="uninstall" action="uninstall.php", method="post">
<table bgcolor="#000000" align="center" border="0" cellspacing="0" cellpadding="1">
<tr><td><table bgcolor="#FFFFFF" border=0 cellspacing=1 cellpadding=4 align="center">
	<tr><td colspan=2 bgcolor="#e5ffe7"><font color="red"><b>This utility will uninstall FUDforum from specified directories. Make sure that this is what you want to do, because once it runs there is no going back.</b></font></td></tr>
	<tr bgcolor="#bff8ff"><td valign="top"><b>Forum Data Root</b><br><font size="-1">This is the directory where you've installed the non-browseable forum files</font></td><td><input type="text" name="SERVER_DATA_ROOT" value="" size=40></td></tr>
	<tr bgcolor="#bff8ff"><td valign="top"><b>Server Root</b><br><font size="-1">This is the directory where you've installed the browseable forum files. If it is the same as "Forum Data Root", you can leave this field blank.</font></td><td><input type="text" name="SERVER_ROOT" value="" size=40></td></tr>
	<tr><td colspan="2" align="center" bgcolor="white"><input type="submit" name="submit" value="uninstall"></td><tr>
</table>	
</form>
</div>
</body>
</html>