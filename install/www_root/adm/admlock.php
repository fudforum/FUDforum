<?php
/***************************************************************************
* copyright            : (C) 2001-2003 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: admlock.php,v 1.23 2003/11/12 13:45:18 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it 
* under the terms of the GNU General Public License as published by the 
* Free Software Foundation; either version 2 of the License, or 
* (at your option) any later version.
***************************************************************************/

	require ('./GLOBALS.php');
	fud_use('adm.inc', true);
	fud_use('glob.inc', true);

function chmoddir($dirn, $dirp, $filep, $rec=false)
{
	@chmod($dirn, $dirp);
	if (!($d = opendir($dirn))) {
		echo 'ERROR: Unable to open "'.$dirn.'" directory<br>';
		return;
	}
	readdir($d); readdir($d);
	while ($f = readdir($d)) {
		$path = $dirn . '/' . $f;
		if (@is_file($path) && !@chmod($path, $filep)) {
			echo 'ERROR: couldn\'t chmod "'.$path.'"<br>';
		} else if (@is_dir($path) && $rec === true) {
			chmoddir($path, $dirp, $filep, true);
		}
	}
	closedir($d);
}

	if (isset($_POST['usr_passwd'], $_POST['usr_login']) && q_singleval("SELECT id FROM ".$DBHOST_TBL_PREFIX."users WHERE login='".addslashes($_POST['usr_login'])."' AND passwd='".md5($_POST['usr_passwd'])."' AND (users_opt & 1048576) > 0")) {
		$FUD_OPT_2 |= 8388608;
		if (isset($_POST['btn_unlock'])) {
			$dirperms = 0777;
			$fileperms = 0666;
			@unlink($ERROR_PATH.'FILE_LOCK');
			$FUD_OPT_2 ^= 8388608;
		} else {
			if (!strncmp(PHP_SAPI, 'apache', 6)) {
				$dirperms = 0700;
				$fileperms = 0600;
			} else {
				$dirperms = 0711;
				$fileperms = 0644;
			}
		}

		chmoddir(realpath($WWW_ROOT_DISK), $dirperms, $fileperms, true);
		chmoddir(realpath($DATA_DIR), $dirperms, $fileperms, true);

		change_global_settings(array('FUD_OPT_2' => $FUD_OPT_2));
	}

	$status = ($FUD_OPT_2 & 8388608 ? 'LOCKED' : 'UNLOCKED');

	require($WWW_ROOT_DISK . 'adm/admpanel.php');
?>
<div align="center" style="font-size: xx-large; color: #ff0000;">
	The forum's files appear to be: <b><?php echo $status; ?></b>.<br>
	<font size="-1">If this test claims that the forum is unlocked, but you still cannot modify your files click on the "Unlock Files" button.</font><br>
	For security reasons remember to lock your forum's files after you are done editing them.
</div>
<form method="post">
<table border=0 cellspacing=0 cellpadding=3>
<tr><td>Login:</td><td><input type="text" name="usr_login" value="<?php echo $usr->alias; ?>"></td></tr>
<tr><td>Password:</td><td><input type="password" name="usr_passwd"></td></tr>
<tr><td colspan=2 algin=middle>
	<input type="submit" name="btn_lock" value="Lock Files">
	<input type="submit" name="btn_unlock" value="Unlock Files">
</td></tr>
</table>
<?php echo _hs; ?>
</form>
<?php require($WWW_ROOT_DISK . 'adm/admclose.html'); ?>