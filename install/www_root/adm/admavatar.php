<?php
/**
* copyright            : (C) 2001-2007 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: admavatar.php,v 1.35 2007/01/03 23:31:36 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; version 2 of the License.
**/

	require('./GLOBALS.php');
	fud_use('adm.inc', true);
	fud_use('widgets.inc', true);

function clean_name($name)
{
	return trim(preg_replace(array('![^A-Za-z0-9 ]!', '!\s+!'), array(' ', ' '), $name));
}

function import_avatars($path)
{
	$list = array(realpath($path));
	$files = array();

	/* no proper directory detection in early 4.3.X releases */
	$ver = version_compare(PHP_VERSION, '4.3.3', '>=');

	while (list(,$v) = each($list)) {
		$files = array_merge($files, glob($v . "/{*.jpg,*.gif,*.png,*.jpeg}", GLOB_BRACE|GLOB_NOSORT));
	
		if (($dirs = glob($v . "/*", GLOB_BRACE|GLOB_NOSORT))) {
			foreach ($dirs as $dir) {
				if ($ver || is_dir($dir)) {
					$list[] = $dir;
				}
			}
		}
	}

	$base = basename($list[0]);
	$av_path = $GLOBALS['WWW_ROOT_DISK'] . 'images/avatars/';
	$i = 0;

	foreach ($files as $file) {
		$name_r = basename($file);
		$name = clean_name(substr($name_r, 0, strrpos($name_r, '.')));
		if (!$name) {
			continue;
		}
		$sect = basename(dirname($file));
		$sect = $sect == $base ? 'default' : clean_name($sect);
		if (!$sect) {
			$sect = 'default';
		}
		$name_r = str_replace(' ', '_', $sect) . '_' . $name_r;

		$id = db_li("INSERT INTO ".$GLOBALS['DBHOST_TBL_PREFIX']."avatar (img, descr, gallery) VALUES("._esc($name_r).", "._esc($name).", "._esc($sect).")", $em, 1);
		if ($id) {
			if (!copy($file, $av_path . $name_r)) {
				q("DELETE FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."avatar WHERE id=".$id);
				$i++;
			}
		}
	}

	return $i;
}

	$tbl = $GLOBALS['DBHOST_TBL_PREFIX'];


	if (!empty($_GET['del']) && ($im = q_singleval('SELECT img FROM '.$tbl.'avatar WHERE id='.(int)$_GET['del']))) {
		q('DELETE FROM '.$tbl.'avatar WHERE id='.(int)$_GET['del']);
		if (db_affected()) {
			q('UPDATE '.$tbl.'users SET avatar_loc=NULL, avatar=0, users_opt=(users_opt & ~ (8388608|16777216)) | 4194304 WHERE avatar='.(int)$_GET['del']);
		}
		@unlink($GLOBALS['WWW_ROOT_DISK'] . 'images/avatars/'.$im);
	}

	// gallery importing
	if (!empty($_POST['gallery_path'])) {
		$gallery_import = import_avatars($_POST['gallery_path']);
	} else {
		$gallery_import = 0;
	}

	// gallery removal
	if (!empty($_POST['gal_del'])) {
		$r = uq("SELECT img FROM ".$tbl."avatar WHERE gallery="._esc($_POST['gal_del']));
		while ($l = db_rowarr($r)) {
			@unlink($GLOBALS['WWW_ROOT_DISK'] . 'images/avatars/' . $l[0]);
		}
		unset($r);
		q("DELETE FROM ".$tbl."avatar WHERE gallery="._esc($_POST['gal_del']));
	}

	if (isset($_GET['edit'])) {
		list($avt_img, $avt_descr, $avt_gal) = db_saq('SELECT img, descr, gallery FROM '.$tbl.'avatar WHERE id='.(int)$_GET['edit']);
		$edit = (int)$_GET['edit'];
	} else {
		$edit = $avt_gal = $avt_img = $avt_descr = '';
	}

	if (isset($_FILES['icoul']) && $_FILES['icoul']['size'] && preg_match('!\.(jpg|jpeg|gif|png)$!i', $_FILES['icoul']['name'])) {
		move_uploaded_file($_FILES['icoul']['tmp_name'], $GLOBALS['WWW_ROOT_DISK'] . 'images/avatars/' . $_FILES['icoul']['name']);
		if (empty($_POST['avt_img'])) {
			$_POST['avt_img'] = $_FILES['icoul']['name'];
		}
	}

	if (!empty($_POST['avt_gal'])) {
		$avt_gal = $_POST['avt_gal'];
	} else if (!empty($_POST['avt_gal_m'])) {
		$avt_gal = $_POST['avt_gal_m'];
	} else {
		$avt_gal = 'default';
	}

	if (isset($_POST['btn_update'], $_POST['edit']) && !empty($_POST['avt_img'])) {
		$old_img = q_singleval('SELECT img FROM '.$tbl.'avatar WHERE id='.(int)$_POST['edit']);
		q('UPDATE '.$tbl.'avatar SET gallery='.ssn($avt_gal).', img='.ssn($_POST['avt_img']).', descr='._esc($_POST['avt_descr']).' WHERE id='.(int)$_POST['edit']);
		if (db_affected() && $old_img != $_POST['avt_img']) {
			$size = getimagesize($GLOBALS['WWW_ROOT_DISK'] . 'images/avatars/' . $_POST['avt_img']);
			$new_loc = '<img src="'.$GLOBALS['WWW_ROOT'].'images/avatars/'.$_POST['avt_img'].'" '.$size[3].' />';
			q('UPDATE '.$tbl.'users SET avatar_loc=\''.$new_loc.'\' WHERE avatar='.(int)$_POST['edit']);
		}
	} else if (isset($_POST['btn_submit']) && !empty($_POST['avt_img'])) {
		q('INSERT INTO '.$tbl.'avatar (img, descr, gallery) VALUES ('.ssn($_POST['avt_img']).', '._esc($_POST['avt_descr']).', '.ssn($avt_gal).')');
	}

	// fetch a list of avaliable galleries
	$galleries = db_all("SELECT DISTINCT(gallery) FROM ".$tbl."avatar");

	require($WWW_ROOT_DISK . 'adm/admpanel.php');
?>
<h2>Avatar Management System</h2>

<form name="frm_avt" method="post" action="admavatar.php" enctype="multipart/form-data">
<?php echo _hs; ?>
<table class="datatable solidtable">
	<?php if (@is_writeable($GLOBALS['WWW_ROOT_DISK'] . 'images/avatars')) { ?>
		<tr class="field">
			<td colspan="2"><b>Import Gallery</b><br /><font size="-1">Recursively process specified directory, creating avatars from all files with (*.gif, *.jpg, *.png, *.jpeg) extensions.<br>A new gallery will be created for every encountered sub-directory.</font></td>
		</tr>
		<tr class="field">
			<td>Gallery directory:</td>
			<td><input type="text" size="25" name="gallery_path"> <input type="submit" name="btn_gal_add" value="Import"></td>
		</tr>

<?php
	if (count($galleries) > 1) {
		echo '<tr class="field"><td>Remove Gallery:</td> <td><select name="gal_del"><option value=""></option>';
		foreach ($galleries as $gal) {
			$name = htmlspecialchars($gal);
			echo '<option value="'.$name.'">'.$name.'</option>';
		}
		echo '</select> <input type="submit" name="submit" value="Remove"></td></tr>';
	}
?>

		<tr><td colspan="2">&nbsp;</td></tr>

		<tr class="field">
			<td colspan=2><b>Avatar Upload</b> (upload avatars into the system)</td>
		</tr>
		<tr class="field">
			<td>Avatar Upload:<br /><font size="-1">Only (*.gif, *.jpg, *.png) files are supported</font></td>
			<td><input type="file" name="icoul"> <input type="submit" name="btn_upload" value="Upload"></td>
			<td><input type="hidden" name="tmp_f_val" value="1"></td>
		</tr>
	<?php } else { ?>
		<tr class="field">
			<td colspan="2"><font color="#ff0000">Web server doesn't have write permission to write to <b>'<?php echo $GLOBALS['WWW_ROOT_DISK'] . 'images/avatars'; ?>'</b>, avatar upload disabled</font></td>
		</tr>
	<?php } ?>

	<tr><td colspan="2">&nbsp;</td></tr>

	<tr class="field">
		<td colspan="2"><a name="img"><b>Avatar Management</b></a></td>
	</tr>

	<tr class="field">
		<td>Avatar Description:</td>
		<td><input type="text" name="avt_descr" value="<?php echo htmlspecialchars($avt_descr); ?>"></td>
	</tr>

	<tr class="field">
		<td>Gallery Name (optional):</td>
		<td><input type="text" name="avt_gal_m" value="">
<?php
	if (count($galleries) > 1) {
		echo ' <select name="avt_gal">';
		foreach ($galleries as $gal) {
			$name = htmlspecialchars($gal);
			echo '<option value="'.$name.'"'.($gal == $avt_gal ? ' selected' : '').'>'.$name.'</option>';
		}
		echo '</select>';
	}
?>
		</td>
	</tr>

	<tr class="field">
		<td valign=top><a name="avt_sel">Avatar Image:</a></td>
		<td>
			<input type="text" name="avt_img" value="<?php echo htmlspecialchars($avt_img); ?>"
				onChange="javascript:
					if (document.frm_avt.avt_img.value.length) {
						document.prev_icon.src='<?php echo $WWW_ROOT_DISK; ?>images/avatars/' + document.frm_avt.avt_img.value;
					} else {
						document.prev_icon.src='../blank.gif';
					}">
			[<a href="#avt_sel" onClick="javascript:window.open('admiconsel.php?type=4&amp;<?php echo __adm_rsid; ?>', 'admavatarsel', 'menubar=false,scrollbars=yes,resizable=yes,height=300,width=500,screenX=100,screenY=100');">SELECT AVATAR</a>]
		</td>
	</tr>

	<tr class="field">
		<td>Preview Image:</td>
		<td>
			<table border="1" cellspacing="1" cellpadding="2" bgcolor="#ffffff">
				<tr><td align="center" valign="middle">
					<img src="<?php echo ($avt_img ? $GLOBALS['WWW_ROOT'] . 'images/avatars/' . $avt_img : '../blank.gif'); ?>" name="prev_icon" border=0 alt="blank">
				</td></tr>
			</table>
		</td>
	</tr>

	<tr class="fieldaction">
		<?php
			if (!$edit) {
				echo '<td colspan="2" align="right"><input type="submit" name="btn_submit" value="Add Avatar"></td>';
			} else {
				echo '<td colspan="2" align="right"><input type="submit" name="btn_cancel" value="Cancel"><input type="submit" name="btn_update" value="Update"></td>';
			}
		?>
	</tr>
</table>
<input type="hidden" name="edit" value="<?php echo $edit; ?>">
</form>
<?php
	if (count($galleries) > 1) {
		// change gallery
		if (isset($_GET['avt_gal_sw'])) {
			$avt_gal = $_GET['avt_gal_sw'];
		}
		echo '<form name="frm_avt" method="get" action="admavatar.php">'._hs.'<div align="center">';
		echo '<select name="avt_gal_sw">';
		foreach ($galleries as $gal) {
			echo '<option value="'.htmlspecialchars($gal).'"'.($avt_gal == $gal ? ' selected' : '').'>'.htmlspecialchars($gal).'</option>';
		}
		echo '</select> <input type="submit" name="submit" value="View">';
		echo '</div></form>';
	}
?>
<table class="resulttable fulltable">
<tr class="resulttopic">
	<td>Avatar</td>
	<td>Description</td>
	<td align="center">Action</td>
</tr>
<?php
	$show_def = in_array($avt_gal, $galleries) ? $avt_gal : $galleries[0];

	$c = uq('SELECT id, img, descr FROM '.$tbl.'avatar WHERE gallery='._esc($show_def));
	$i = 0;
	while ($r = db_rowarr($c)) {
		if ($edit == $r[0]) {
			$bgcolor = ' class="resultrow1"';
		} else {
			$bgcolor = ($i++%2) ? ' class="resultrow2"' : ' class="resultrow1"';
		}
		echo '<tr '.$bgcolor.'>
				<td><img src="'.$GLOBALS['WWW_ROOT'].'images/avatars/'.$r[1].'" alt="'.$r[2].'" border=0 /></td>
				<td>'.$r[2].'</td>
				<td>[<a href="admavatar.php?edit='.$r[0].'&amp;'.__adm_rsid.'#img">Edit</a>] [<a href="admavatar.php?del='.$r[0].'&amp;'.__adm_rsid.'">Delete</a>]</td>
			</tr>';
	}
	unset($c);
?>
</table>
<?php require($WWW_ROOT_DISK . 'adm/admclose.html'); ?>
