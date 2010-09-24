<?php
/***************************************************************************
* copyright            : (C) 2001-2010 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id$
*
* This program is free software; you can redistribute it and/or modify it 
* under the terms of the GNU General Public License as published by the 
* Free Software Foundation; version 2 of the License. 
***************************************************************************/

function fud_ini_get($opt)
{
	return (ini_get($opt) == '1' ? 1 : 0);
}

/* main */
	if (fud_ini_get('safe_mode') && basename(__FILE__) != 'safe_unprotect.php') {
		$c = getcwd();
		copy($c .'/unprotect.php', $c .'/safe_unprotect.php');
		header('Location: safe_unprotect.php');
		exit;
	}

	require 'GLOBALS.php';
	fud_use('file_adm.inc', true);
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" xml:lang="en">
<head>
	<title>FUDforum Unlock Files Script</title>
	<link rel="styleSheet" href="<?php echo $WWW_ROOT; ?>adm/style/adm.css" type="text/css" />
	<style>html, body { height: 95%; }</style>
</head>
<body>
<table class="headtable"><tr>
  <td><img src="<?php echo $WWW_ROOT; ?>images/fudlogo.gif" alt="" style="float:left;" border="0" /></td>
  <td><span class="linkhead">Quick Unlock Files</span></td>
  <td> &nbsp; </td>
</tr></table>
<table class="maintable" style="height:100%;">
<tr>
<td class="maindata">
<?php
	umask(0);
	fud_unlock(substr($DATA_DIR, 0, -1));
	if ($DATA_DIR != $WWW_ROOT_DISK) {
		fud_unlock(substr($WWW_ROOT_DISK, 0, -1));
	}
	@unlink($GLOBALS['ERROR_PATH'] .'FILE_LOCK');
	if (basename(__FILE__) == 'safe_unprotect.php') {
		@unlink('safe_unprotect.php');
	}
?>
<div align="center">
	<h1>Your forum is now unlocked.</h1>
	<p>Remember to lock your forum when you are done editing it by navigating to <i>Admin Control Panel</i> -&gt; <i>Lock/Unlock Forum Files</i>.</p>
	<p>Also, please remember to delete this script to ensure hackers do not exploit it.</p>
</div>
</tr>
</td>
</table>
</body>
</html>
