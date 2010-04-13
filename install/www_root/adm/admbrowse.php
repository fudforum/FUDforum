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

	if (isset($_POST['btn_mini_cancel']) || isset($_GET['btn_mini_cancel'])) {
		exit('<html><script type="text/javascript">window.close();</script></html>');
	}

	require('./GLOBALS.php');
	fud_use('adm.inc', true);
	fud_use('widgets.inc', true);
	fud_use('tar.inc', true);
	
function bit_test($val, $mask)
{
	return (($val & $mask) == $mask) ? $mask : 0;
}

function mode_string($mode, $de)
{
	/* Determine string mode 01234567890. */
	$mode_str = 'drwxrwxrwxt';

	if (!is_dir($de)) {	/* directory */
		$mode_str[0] = '-';
	}
	if (!bit_test($mode, 00400)) {	/* owner read */
		$mode_str[1] = '-';
	}
	if (!bit_test($mode, 00200)) {	/* owner write */
		$mode_str[2] = '-';
	}
	if (!bit_test($mode, 00100)) {	/* owner exec */
		$mode_str[3] = '-';
	}
	if (bit_test($mode, 0004000)) {	/* setuid */
		$mode_str[3] = 's';
	}
	if (!bit_test($mode, 00040)) {	/* group read */
		$mode_str[4] = '-';
	}
	if (!bit_test($mode, 00020)) {	/* group write */
		$mode_str[5] = '-';
	}
	if (!bit_test($mode, 00010)) {	/* group exec */
		$mode_str[6] = '-';
	}
	if (bit_test($mode, 0002000)) {	/* setgid */
		$mode_str[6] = 's';
	}
	if (!bit_test($mode, 00004)) {	/* world read */
		$mode_str[7] = '-';
	}
	if (!bit_test($mode, 00002)) {	/* world write */
		$mode_str[8] = '-';
	}
	if (!bit_test($mode, 00001)) {	/* world exec */
		$mode_str[9] = '-';
	}
	if (!bit_test($mode, 0001000)) {	/* sticky (warning: NOT POSIX) */
		$mode_str[10] = '-';
	}

	return $mode_str;
}

function fud_rmdir($dir)
{
	$dirs = array(realpath($dir));

	while (list(,$v) = each($dirs)) {
		if (!($files = glob($v.'/*', GLOB_NOSORT))) {
			continue;
		}
		foreach ($files as $file) {
			if (is_dir($file) && !is_link($file)) {
				$dirs[] = $file;
			} else if (!unlink($file)) {
				return false;
			}
		}
	}
	
	$dirs = array_reverse($dirs);
	
	foreach ($dirs as $dir) {
		if (!rmdir($dir)) {
			return false;
		}
	}
	
	return true;
}

if (!extension_loaded('posix')) {
	function posix_getpwuid($id)
	{
		return array('name' => $id);
	}

	function posix_getgrgid($id)
	{
		return array('name' => $id);
	}
}

/* main */
	/* Figure out the ROOT paths based on the location of web browseable dir & data dir. */
	$ROOT_PATH[0] = realpath($GLOBALS['WWW_ROOT_DISK']);
	$ROOT_PATH[1] = realpath($GLOBALS['DATA_DIR']);

	$cur_dir = realpath(isset($_POST['cur']) ? $_POST['cur'] : (isset($_GET['cur']) ? $_GET['cur'] : $ROOT_PATH[0]));
	$dest = isset($_POST['dest']) ? basename($_POST['dest']) : (isset($_GET['dest']) ? basename($_GET['dest']) : '');

	/* Ensure the specified path is within the forum directories. */
	if (strpos($cur_dir, $ROOT_PATH[1]) !== 0 && strpos($cur_dir, $ROOT_PATH[0]) !== 0) {
		$cur = $cur_dir = $ROOT_PATH[0];
		$dest = '';
	}

	/* Download file code. */
	if (isset($_GET['down']) && $dest && @file_exists($cur_dir . '/' . $dest)) {
		if (is_file($cur_dir . '/' . $dest)) {
			header('Content-type: application/octet-stream');
			header('Content-Disposition: attachment; filename='.$dest);
			readfile($cur_dir . '/' . $dest);
		} else {
			header('Content-type: application/x-tar');
			header('Content-Disposition: attachment; filename='.$dest.'.tar');
			echo make_tar($cur_dir . '/' . $dest);
		}
		exit;
	}
	
	/* Delete file/directory code. */
	if (isset($_GET['del']) && $dest && @file_exists($cur_dir . '/' . $dest)) {
		if ($dest == '.' || $dest == '..') {
			define('popup', 1);
			require($WWW_ROOT_DISK .'adm/header.php');
			echo '<h2>File/Directory Deletion</h2>';
			echo errorify('ERROR: You cannot delete . or ..');
			require($WWW_ROOT_DISK .'adm/footer.php');
			exit;
		}
		if (isset($_GET['del_confirmed'])) {
			if (@is_dir($cur_dir .'/'. $dest) && !fud_rmdir($cur_dir .'/'. $dest)) {
				define('popup', 1);
				echo '<h2>File/Directory Deletion</h2>';
				require($WWW_ROOT_DISK .'adm/header.php');
				echo errorify('ERROR: failed to remove directory '. $cur_dir .'/'. $dest);
				require($WWW_ROOT_DISK .'adm/footer.php');
				exit;
			} else if (@is_file($cur_dir .'/'. $dest) && !unlink($cur_dir .'/'. $dest)) {
				define('popup', 1);
				require($WWW_ROOT_DISK .'adm/header.php');
				echo '<h2>File/Directory Deletion</h2>';
				echo errorify('ERROR: failed to remove file '. $cur_dir .'/'. $dest);
				require($WWW_ROOT_DISK .'adm/footer.php');
				exit;
			} else {
				exit('<html><script type="text/javascript"> window.opener.location = \'admbrowse.php?'.__adm_rsidl.'&cur='.urlencode($cur_dir).'\'; window.close();</script></html>');
			}
		} else {
			$file = $cur_dir .'/'. $dest;
			$type = @is_dir($file) ? 'directory' : 'file';

			define('popup', 1);
			require($WWW_ROOT_DISK .'adm/header.php');
		?>
			<h2>File/Directory Deletion</h2>
			<p>Are you sure you want to delete <?php echo $type.' <span style="color:red"><b>'.$file.'</b></span>'; ?></p>
			<form method="get" action="admbrowse.php">
			<input type="hidden" name="cur" value="<?php echo $cur_dir; ?>" />
			<input type="hidden" name="dest" value="<?php echo $dest; ?>" />
			<input type="hidden" name="del" value="1" />
			<?php echo _hs; ?>
			<div align="center"><input type="submit" name="btn_mini_cancel" value="No" /> <input type="submit" name="del_confirmed" value="Yes" /></div>
			</form>
		<?php
			require($WWW_ROOT_DISK .'adm/footer.php');
			exit;
		}
	}

	/* Change file/directory mode. */
	if (isset($_GET['chmod'])) {
		$file = $cur_dir.'/'.$dest;
		$st = stat($file);
		if (!isset($st[2])) {
			$st[2] = $st['mode'];
		}
		$mode_o = sprintf('%o', 0x0FFF & $st[2]);

		define('popup', 1);
		require($WWW_ROOT_DISK .'adm/header.php');
?>
		<h2>Change File Permissions</h2>
		<?php echo $file .' is currenly <b>'. mode_string($st[2], $file) .' ('. $mode_o .')</b>'; ?><br />
		change it to:<br />
		<form method="post" action="admbrowse.php">
		<?php echo _hs; ?>
		<input type="hidden" name="chmod" value="1" />
		<input type="hidden" name="cur" value="<?php echo $cur_dir; ?>" />
		<input type="hidden" name="dest" value="<?php echo $dest; ?>" />
		<table border="0">
		<tr><td>Group:</td><td>Read</td><td>Write</td><td>Execute</td></tr>
		<tr><td>Owner:</td>
			<td><?php draw_checkbox('oread', 0400, bit_test($st[2], 0400)); ?></td>
			<td><?php draw_checkbox('owrite', 0200, bit_test($st[2], 0200)); ?></td>
			<td><?php draw_checkbox('oexec', 0100, bit_test($st[2], 0100)); ?></td></tr>
		<tr><td>Group:</td>
			<td><?php draw_checkbox('gread', 0040, bit_test($st[2], 0040)); ?></td>
			<td><?php draw_checkbox('gwrite', 0020, bit_test($st[2], 0020)); ?></td>
			<td><?php draw_checkbox('gexec', 0010, bit_test($st[2], 0010)); ?></td></tr>
		<tr><td>World:</td>
			<td><?php draw_checkbox('wread', 0004, bit_test($st[2], 0004)); ?></td>
			<td><?php draw_checkbox('wwrite', 0002, bit_test($st[2], 0002)); ?></td>
			<td><?php draw_checkbox('wexec', 0001, bit_test($st[2], 0001)); ?></td></tr>
		<tr><td colspan="4"><?php draw_checkbox('setuid', 0004000, bit_test($st[2], 0004000)); ?> setuid</td></tr>
		<tr><td colspan="4"><?php draw_checkbox('setgid', 0002000, bit_test($st[2], 0002000)); ?> setgid</td></tr>
		<tr><td colspan="4"><?php draw_checkbox('sticky', 0001000, bit_test($st[2], 0001000)); ?> sticky</td></tr>
		<tr><td colspan="4" align="right"><input type="submit" name="btn_submit" value="Apply" /> <input type="submit" name="btn_mini_cancel" value="Cancel" /></td></tr>
		</table>
		</form>
<?php
		require($WWW_ROOT_DISK .'adm/footer.php');
		exit;
	}

	/* Change file/directory permissions. */
	if (isset($_POST['chmod']) && $dest && @file_exists($cur_dir . '/' . $dest)) {
		$file = $cur_dir.'/'.$dest;
		$perm_bits = array('oread', 'owrite', 'oexec', 'gread', 'gwrite', 'gexec', 'wread', 'wwrite', 'wexec', 'setuid', 'setgid', 'sticky');
		$new_mode = 0;
		foreach ($perm_bits as $v) {
			if (isset($_POST[$v])) {
				$new_mode |= $_POST[$v] + 0;
			}
		}
		if (!@chmod($file, $new_mode)) {
			exit('<html>Unable to chmod <b>'.$file.'</b><br /><a href="#" onclick="window.close();">close</a></html>');
		} else {
			exit('<html><script type="text/javascript"> window.opener.location = \'admbrowse.php?'.__adm_rsidl.'&cur='.urlencode($cur_dir).'\'; window.close();</script></html>');
		}
	}

	/* Print header & menu. No code for popup windows beyond this point. */
	require($WWW_ROOT_DISK . 'adm/header.php');
		
	/* Directory creation code. */
	if (isset($_GET['btn_mkdir']) && !empty($_GET['mkdir'])) {
		$u = umask(0);
		if (!@mkdir($cur_dir . '/' . basename($_GET['mkdir']), ($FUD_OPT_2 & 8388608 ? 0700 : 0777))) {
			echo errorify('ERROR: failed to create '. $cur_dir .'/'. basename($_GET['mkdir']) .'.');
		} else {
			echo successify('Directory '. $cur_dir .'/'. basename($_GET['mkdir']) .' successfully created.');
		}
		umask($u);
	}

	/* File upload code. */
	if (isset($_FILES['fname']) && $_FILES['fname']['size']) {
		$fdest = !empty($_POST['d_name']) ? $_POST['d_name'] : $_FILES['fname']['name'];
		$fdest = $cur_dir . '/' . basename($fdest);
		if (move_uploaded_file($_FILES['fname']['tmp_name'], $fdest)) {
			@chmod($fdest, ($FUD_OPT_2 & 8388608 ? 0600 : 0666));
			echo successify('File <i>'. basename($fdest) .'</i> was successfully uploaded.'.
			                ((preg_match('/src|thm/', $fdest)) ? '<br />You need to rebuild your themes to see the changes.' : ''));
		} else {
			switch ($_FILES['fname']['error']) {
			case UPLOAD_ERR_INI_SIZE:
				errorify('The uploaded file exceeds the upload_max_filesize directive in php.ini.');
				return;
			case UPLOAD_ERR_FORM_SIZE:
				errorify('The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.');
				return;
			case UPLOAD_ERR_PARTIAL:
				errorify('The uploaded file was only partially uploaded.');
				return;
			case UPLOAD_ERR_NO_FILE:
				errorify('No file was uploaded.');
				return;
			case UPLOAD_ERR_NO_TMP_DIR:
				errorify('Missing a temporary folder.');
				return;
			case UPLOAD_ERR_CANT_WRITE:
				errorify('Failed to write file to disk.');
				return;
			case UPLOAD_ERR_EXTENSION:
				errorify('File upload stopped by extension.');
				return;
			default:
				errorify('Unknown upload error. Please try again.');
			}
		}
	}

	/* View file code. */
	if (isset($_GET['view']) && $dest && @file_exists($cur_dir . '/' . $dest)) {
		$file = $cur_dir.'/'.$dest;
		$ext = pathinfo($file, PATHINFO_EXTENSION);
		if (in_array($ext, array('gz', 'zip', 'db', 'gif', 'jpg', 'png'))) {
			echo errorify('Cannot view binary file.');
		} elseif (is_file($file) && is_readable($file)) {
			echo '<h2>View file: '. $dest .'</h2>';
			echo '<code><pre>'. htmlentities(file_get_contents($file)) .'</pre></code>';
			echo '<p><a href="admbrowse.php?'.__adm_rsid.'&amp;cur='.urlencode($cur_dir).'">&laquo; Back to file manager</a></p>';
			exit;
		} else {
			echo errorify('File not found: '. $file);
		}
	}
?>
<h2>File Administration System</h2>
<?php
	if (!@is_dir($cur_dir)) {
		$cur_dir = $ROOT_PATH[0];
	}

	echo 'Currently browsing: <b>'.htmlspecialchars($cur_dir)."</b><br />\n";
	echo 'Go to directory: ';
	echo '[ <a href="admbrowse.php?'.__adm_rsid.'&amp;cur='.urlencode($ROOT_PATH[0]).'" title="'.htmlentities($ROOT_PATH[0]).'">WWW_SERVER_ROOT</a> ] ';
	echo '[ <a href="admbrowse.php?'.__adm_rsid.'&amp;cur='.urlencode($ROOT_PATH[1]).'" title="'.htmlentities($ROOT_PATH[1]).'">DATA_ROOT</a> ]<br />';

	clearstatcache();
	if (!is_readable($cur_dir)) {
		echo errorify('PERMISSION DENINED ACCSESING '. $cur_dir);
		$cur_dir = $ROOT_PATH[0];
	}

	// Print README file.
	$readme_file = $cur_dir.'/README';
	if (is_file($readme_file)) {
		echo '<code><pre><b>README:</b> '. htmlentities(file_get_contents($readme_file)) .'</pre></code>';
	}
?>
<br />
<form method="get" action="admbrowse.php"><input type="hidden" name="cur" value="<?php echo $cur_dir; ?>" /><?php echo _hs; ?>
<fieldset class="field">
        <legend><b>Create directory</b></legend>
<table class="datatable">
	<tr class="tiny">
		<td>New directory name:</td>
		<td><input type="text" name="mkdir" value="" /></td>
		<td align="right" colspan="2"><input type="submit" name="btn_mkdir" value="Create Directory" /></td>
	</tr>
</table>
</fieldset>
</form>
<br />

<form method="post" action="admbrowse.php" enctype="multipart/form-data"><input type="hidden" name="cur" value="<?php echo $cur_dir; ?>" /><?php echo _hs; ?>
<fieldset class="field">
        <legend><b>Upload a file</b></legend>
<table cellspacing="2" cellpadding="2" border="0">
	<tr class="tiny">
		<td>File to upload:</td>
		<td><input type="file" name="fname" /><input type="hidden" name="tmp_f_val" value="1" /></td>
	</tr>
	<tr class="tiny">
		<td>New file name:<br />(leave blank if want the uploaded filename to remain unchanged)</td>
		<td><input type="text" name="d_name" value="" /></td>
	</tr>
	<tr class="tiny">
		<td colspan="2" align="right"><input type="submit" name="file_upload" value="Upload File" /></td>
	</tr>
</table>
</fieldset>
</form>
<br />
<table class="resulttable fulltable">
<thead><tr class="resulttopic">
	<th>Mode</th><?php if (!preg_match('/WIN/', PHP_OS)) echo '<th>Owner</th><th>Group</th>'; ?><th>Size</th><th>Date</th><th>Time</th><th>Name</th><th align="center" colspan="3">Action</th>	
</tr></thead>
<?php
	$file_list = array();
	$dir_list = array('.', '..');

	if (($files = glob(realpath($cur_dir) . '/*', GLOB_NOSORT))) {
		foreach ($files as $file) {
			$n = basename($file);
			if (is_dir($file)) {
				 $dir_list[] = $n;
			} else {
				 $file_list[] = $n;
			}
		}
	}

	sort($dir_list);
	sort($file_list);

	$dir_data = array_merge($dir_list, $file_list);

	$cur_enc = urlencode($cur_dir);

	foreach($dir_data as $de) {
		$fpath = $cur_dir . '/' . $de;

		if (@is_file($fpath)) {
			$name = '<a href="admbrowse.php?view=1&dest='.htmlspecialchars($de).'&cur='.urlencode($cur_dir).'&amp;'.__adm_rsid.'" title="View file">'.htmlspecialchars($de).'</a>';
			$st = stat($fpath);
		} else if (@is_dir($fpath)) {
			$name = '<a href="admbrowse.php?cur='.urlencode($fpath).'&amp;'.__adm_rsid.'" title="Change directory">'.htmlspecialchars($de).'</a>';
			$st = stat($fpath);
		}

		$mode = isset($st[2]) ? $st[2] : $st['mode'];
		$mode_str = mode_string($mode, $de);
		$de_enc = urlencode($de);

		$passwdent = posix_getpwuid((isset($st[4])?$st[4]:$st['uid']));
		$owner = $passwdent['name'];
		$groupsent = posix_getgrgid((isset($st[5])?$st[5]:$st['gid']));
		$group = $groupsent['name'];

		$date_str = fdate('%d %b %Y', (isset($st[9])?$st[9]:$st['mtime']));
		$time_str = fdate('%H:%M:%S', (isset($st[9])?$st[9]:$st['mtime']));
		$mode_o = sprintf('%o', 0x0FFF&$mode);

		$size = round((isset($st[7])?$st[7]:$st['size'])/1024);

		if (preg_match('/install.php|upgrade.php|unprotect.php/i', $fpath)) {
			echo '<tr class="field admin_fixed" style="color:red;">';
			echo '<td nowrap="nowrap">';
			echo '<a name="flagged"></a>';
		} else {
			echo '<tr class="field admin_fixed">';
			echo '<td nowrap="nowrap">';
		}

		echo $mode_str.' ('. $mode_o .')</td>';
		if (!preg_match('/WIN/', PHP_OS)) {	// No onwer & group on Windows.
			echo '<td>'. $owner .'</td><td>'. $group .'</td>';
		}
		echo '<td nowrap="nowrap">'. $size .' KB</td><td nowrap="nowrap">'. $date_str .'</td><td>'. $time_str .'</td><td>'. $name .'</td>';

		if (@is_readable($fpath)) {
			if (@is_writeable($fpath) && !preg_match('/WIN/', PHP_OS)) {
				echo '<td style="border: #aebdc4; solid 1px 1px 1px 1px;"><a href="#" onclick="window.open(\'admbrowse.php?chmod=1&amp;cur='.$cur_enc.'&amp;dest='.$de_enc.'&amp;'.__adm_rsid.'\', \'chmod_window\', \'width=500,height=450,menubar=no\');">chmod</a></td>';
			}

			echo '<td style="border: #aebdc4; solid 1px 1px 1px 1px;"><a href="admbrowse.php?down=1&amp;cur='.$cur_enc.'&amp;dest='.$de_enc.'&amp;'.__adm_rsid.'">download</a></td>';

			if (@is_writeable($fpath)) {
				echo '<td style="border: #aebdc4; solid 1px 1px 1px 1px;"><a href="#" onclick="window.open(\'admbrowse.php?del=1&amp;cur='.$cur_enc.'&amp;dest='.$de_enc.'&amp;'.__adm_rsid.'\', \'delete_window\', \'width=500,height=350,menubar=no\');">delete</a></td>';
			} else {
				echo '<td style="border: #aebdc4; solid 1px 1px 1px 1px;" align="center">n/a</td>';
			}
		} else {
			echo '<td style="border: #aebdc4; solid 1px 1px 1px 1px;" colspan="3" align="center">n/a</td>';
		}
		echo '</tr>';
	}
echo '</table>';

require($WWW_ROOT_DISK . 'adm/footer.php');
?>
