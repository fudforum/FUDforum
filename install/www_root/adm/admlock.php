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

	require ('./GLOBALS.php');

	// Run from command line.
	if (php_sapi_name() == 'cli') {
		if (strcasecmp($_SERVER['argv'][1], 'lock') != 0 && strcasecmp($_SERVER['argv'][1], 'unlock') != 0 || empty($_SERVER['argv'][2]) || empty($_SERVER['argv'][3])) {
			echo "Usage: php admlock.php [lock|unlock] userid password\n";
			die();
		}

		fud_use('adm_cli.inc', 1);
		$_POST['btn_'. $_SERVER['argv'][1] ] = 'Lock/Unlock files';
		$_POST['usr_login'] = $_SERVER['argv'][2];
		$_POST['usr_passwd'] = $_SERVER['argv'][3];
	}

	fud_use('adm.inc', true);
	fud_use('glob.inc', true);
	require($WWW_ROOT_DISK .'adm/header.php');

	// Authenticate login details.
	$authenticated = 0;
	if (isset($_POST['usr_login'], $_POST['usr_passwd'])) {
		$r = db_sab('SELECT id, passwd, salt FROM '. $DBHOST_TBL_PREFIX .'users WHERE login='. _esc($_POST['usr_login']) .' AND users_opt>=1048576 AND '. q_bitand('users_opt', 1048576) .' > 0');
		if ($r && (empty($r->salt) && $r->passwd == md5($_POST['usr_passwd']) || $r->passwd == sha1($r->salt . sha1($_POST['usr_passwd'])))) {
			$authenticated = 1;
		} else {
			pf(errorify('Unable to authenticate.'));
		}
	}

	// Perform lock or unlock operation.
	if ($authenticated) {
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

		$dirs = array(realpath($DATA_DIR));
		if ($WWW_ROOT_DISK != $DATA_DIR) {
			$dirs[] = realpath($WWW_ROOT_DISK);
		}

		$u = umask(0);

		while (list(,$v) = each($dirs)) {
			@chmod($v, $dirperms);
			if (!is_readable($v)) {
				pf(errorify('ERROR: Unable to open directory '. $v .'!'));
				continue;
			}
			if (!($files = glob($v .'/{.b*,.h*,.p*,.n*,.m*,*}', GLOB_BRACE|GLOB_NOSORT))) {
				continue;
			}
			foreach ($files as $path) {
				if (@is_file($path) && !@chmod($path, $fileperms)) {
					pf(errorify('ERROR: couldn\'t chmod "'. $path));
				} else if (@is_dir($path) && !is_link($path)) {
					$dirs[] = $path;
				}
			}
		}
		umask($u);

		change_global_settings(array('FUD_OPT_2' => $FUD_OPT_2));
	}

	$status = ($FUD_OPT_2 & 8388608 ? 'LOCKED' : 'UNLOCKED');

	if (defined('shell_script')) {
		pf('Forum files appear to be '. $status);
		exit;
	}
?>
<h2>Lock/Unlock Forum Files</h2>
<?php if ($status == 'UNLOCKED' ) echo '<div class="alert">For security reasons, please remember to lock your forum\'s files when you are done editing them.</div>'; ?>
<form method="post" action="">
<p>The forum's files appear to be: 
<?php echo '<font size="+2" color="'. ($status=='LOCKED' ? 'green' : 'red') .'">'. $status .'</font>.<br />'; ?>
If this test claims that the forum is unlocked, but you still cannot modify your files click on the "Unlock Files" button.
</p>
<table border="0" cellspacing="0" cellpadding="3">
<tr><td>Login:</td><td><input type="text" name="usr_login" value="<?php echo $usr->alias; ?>" /></td></tr>
<tr><td>Password:</td><td><input type="password" name="usr_passwd" /></td></tr>
<tr><td colspan="2" align="center">
	<input type="submit" name="btn_lock" value="Lock Files" />
	<input type="submit" name="btn_unlock" value="Unlock Files" />
</td></tr>
</table>
<?php echo _hs; ?>
</form>
<?php require($WWW_ROOT_DISK .'adm/footer.php'); ?>
