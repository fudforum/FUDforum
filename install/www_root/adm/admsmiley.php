<?php
/***************************************************************************
* copyright            : (C) 2001-2003 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: admsmiley.php,v 1.14 2003/10/16 21:59:05 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it 
* under the terms of the GNU General Public License as published by the 
* Free Software Foundation; either version 2 of the License, or 
* (at your option) any later version.
***************************************************************************/

	require('./GLOBALS.php');
	fud_use('adm.inc', true);
	fud_use('widgets.inc', true);

	$tbl = $GLOBALS['DBHOST_TBL_PREFIX'];

	$smiley_dir = '../images/smiley_icons/';

	if (isset($_GET['del'])) {
		db_lock($tbl.'smiley WRITE');
		if (($im = q_singleval('SELECT img FROM '.$tbl.'smiley WHERE id='.(int)$_GET['del']))) {
			q('DELETE FROM '.$tbl.'smiley WHERE id='.(int)$_GET['del']);
			if (db_affected()) {
				q('UPDATE '.$tbl.'smiley SET vieworder=vieworder-1 WHERE id>'.(int)$_GET['del']);
			}
			@unlink($GLOBALS['WWW_ROOT_DISK'] . 'images/smiley_icons/'.$im);
		}
		db_unlock();
	}

	if (isset($_GET['edit'])) {
		list($sml_code, $sml_img, $sml_descr) = db_saq('SELECT code, img, descr FROM '.$tbl.'smiley WHERE id='.(int)$_GET['edit']);
		$edit = (int)$_GET['edit'];
	} else {
		$edit = $sml_code = $sml_img = $sml_descr = '';
	}

	if (isset($_FILES['icoul']) && $_FILES['icoul']['size'] && preg_match('!\.(jpg|jpeg|gif|png)$!i', $_FILES['icoul']['name'])) {
		move_uploaded_file($_FILES['icoul']['tmp_name'], $GLOBALS['WWW_ROOT_DISK'] . 'images/smiley_icons/' . $_FILES['icoul']['name']);
		if (empty($_POST['avt_img'])) {
			$_POST['avt_img'] = $_FILES['icoul']['name'];
		}
		$sml_img = $_FILES['icoul']['name'];
	}

	if (isset($_POST['btn_update'], $_POST['edit']) && !empty($_POST['sml_img']) && !empty($_POST['sml_code'])) {
		q('UPDATE '.$tbl.'smiley SET code='.strnull(addslashes($_POST['sml_code'])).', img='.strnull(addslashes($_POST['sml_img'])).', descr='.strnull(addslashes($_POST['sml_descr'])).' WHERE id='.(int)$_POST['edit']);
	} else if (isset($_POST['btn_submit']) && !empty($_POST['sml_img']) && !empty($_POST['sml_code'])) {
		$view_order = q_singleval('SELECT MAX(vieworder) FROM '.$tbl.'smiley') + 1;
		q('INSERT INTO '.$tbl.'smiley (code, img, descr, vieworder) VALUES('.strnull(addslashes($_POST['sml_code'])).', '.strnull(addslashes($_POST['sml_img'])).', '.strnull(addslashes($_POST['sml_descr'])).', '.$view_order.')');
	}

	if (isset($_GET['chpos'], $_GET['chdest'])) {
		$oldp = (int)$_GET['chpos'];
		$newp = (int)$_GET['chdest'];
		if ($oldp != $newp && $newp) {
			db_lock($GLOBALS['DBHOST_TBL_PREFIX'].'smiley WRITE');
			q('UPDATE '.$GLOBALS['DBHOST_TBL_PREFIX'].'smiley SET vieworder=2147483647 WHERE vieworder='.$oldp);
			if ($oldp < $newp) {
				q('UPDATE '.$GLOBALS['DBHOST_TBL_PREFIX'].'smiley SET vieworder=vieworder-1 WHERE vieworder<='.$newp.' AND vieworder>'.$oldp);
				$maxp = q_singleval('SELECT MAX(vieworder) FROM '.$GLOBALS['DBHOST_TBL_PREFIX'].'smiley WHERE  vieworder!=2147483647');
				if ($newp > $maxp) {
					$newp = $maxp + 1;
				}
			} else {
				q('UPDATE '.$GLOBALS['DBHOST_TBL_PREFIX'].'smiley SET vieworder=vieworder+1 WHERE vieworder<'.$oldp.' AND vieworder>='.$newp);
			}
			q('UPDATE '.$GLOBALS['DBHOST_TBL_PREFIX'].'smiley SET vieworder='.$newp.' WHERE vieworder=2147483647');
			db_unlock();
			$_GET['chpos'] = null;
		}
	}

	require($WWW_ROOT_DISK . 'adm/admpanel.php');

	if (!isset($_GET['chpos'])) {
?>

<h2>Smiley Management System</h2>

<form name="frm_sml" method="post" enctype="multipart/form-data" action="admsmiley.php">
<table border=0 cellspacing=1 cellpadding=3>
<?php
	echo _hs;
	if (@is_writeable($GLOBALS['WWW_ROOT_DISK'] . 'images/smiley_icons')) { ?>
		<tr bgcolor="#bff8ff">
			<td colspan=2><b>Smilies Upload (upload smiley into the system)</td>
		</tr>
		<tr bgcolor="#bff8ff">
			<td>Smilies Upload:<br><font size="-1">Only (*.gif, *.jpg, *.png) files are supported</font></td>
			<td><input type="file" name="icoul"> <input type="submit" name="btn_upload" value="Upload"></td>
		</tr>
	<?php } else { ?>
		<tr bgcolor="#bff8ff">
			<td colspan=2><font color="#ff0000">Web server doesn't have write permissions to <b>'<?php echo $GLOBALS['WWW_ROOT_DISK'] . 'images/smiley_icons'; ?>'</b>, smiley upload disabled</font></td>
		</tr>
	<?php } ?>

	<tr><td colspan=2>&nbsp;</td></tr>

	<tr bgcolor="#bff8ff">
		<td colspan=2><a name="img"><b>Smilies Mangement</b></a></td>
	</tr>

	<tr bgcolor="#bff8ff">
		<td>Smiley Description:</td>
		<td><input type="text" name="sml_descr" value="<?php echo htmlspecialchars($sml_descr); ?>"></td>
	</tr>

	<tr bgcolor="#bff8ff">
		<td>Smiley Text:<br><font size=-1>Will be replaced with smiley,<br>use <b>~</b> to seperate multiple allowed codes</font></td>
		<td><input type="text" name="sml_code" value="<?php echo htmlspecialchars($sml_code); ?>"></td>
	</tr>

	<tr bgcolor="#bff8ff">
		<td valign=top><a name="sml_sel">Smiley Image:</a></td>
		<td>
			<input type="text" name="sml_img" value="<?php echo htmlspecialchars($sml_img); ?>"
				onChange="javascript:
						if (document.frm_sml.sml_img.value.length) {
							document.prev_icon.src='<?php echo $GLOBALS['WWW_ROOT']; ?>images/smiley_icons/' + document.frm_sml.sml_img.value;
						} else {
							document.prev_icon.src='../blank.gif';
						}">
			[<a href="javascript://" onClick="javascript:window.open('admsmileysel.php?<?php echo _rsidl; ?>', 'admsmileysel', 'menubar=false,scrollbars=yes,resizable=yes,height=300,width=500,screenX=100,screenY=100');">SELECT ICON</a>]
		</td>
	</tr>

	<tr bgcolor="#bff8ff">
		<td>Preview Image:</td>
		<td>
			<table border=1 cellspacing=1 cellpadding=2 bgcolor="#ffffff">
				<tr><td align=center valign=center>
					<img src="<?php echo ($sml_img ? $GLOBALS['WWW_ROOT'] . 'images/smiley_icons/' . $sml_img : '../blank.gif'); ?>" name="prev_icon" border=0>
				</td></tr>
			</table>
		</td>
	</tr>

	<tr bgcolor="#bff8ff">
		<?php
			if (!$edit) {
				echo '<td colspan=2 align=right><input type="submit" name="btn_submit" value="Add Smiley"></td>';
			} else {
				echo '<td colspan=2 align=right><input type="submit" name="btn_cancel" value="Cancel"><input type="submit" name="btn_update" value="Update"></td>';
			}
		?>
	</tr>

</table>
<input type="hidden" name="edit" value="<?php echo $edit; ?>">
</form>
<?php } /* if (!isset($_GET['chpos'])) { */ ?>
<table border=0 cellspacing=3 cellpadding=2>
<tr bgcolor="#e5ffe7">
	<td>Smiley</td>
	<td>Code</td>
	<td>Description</td>
	<td>Action</td>
</tr>
<?php
	$c = uq('SELECT id, img, code, descr, vieworder FROM '.$tbl.'smiley ORDER BY vieworder');
	$i = 1;
	$chpos = isset($_GET['chpos']) ? (int)$_GET['chpos'] : '';
	while ($r = db_rowobj($c)) {
		if ($edit == $r->id) {
			$bgcolor = ' bgcolor="#ffb5b5"';
		} else {
			$bgcolor = ($i++%2) ? ' bgcolor="#fffee5"' : '';
		}
		if (isset($_GET['chpos'])) {
			if ($_GET['chpos'] == $r->vieworder) {
				$bgcolor = ' bgcolor="#ffb5b5"';
			} else if ($_GET['chpos'] != ($r->vieworder - 1)) {
				echo '<tr bgcolor="#efefef"><td align=center colspan=9><a href="admsmiley.php?chpos='.$_GET['chpos'].'&chdest='.($r->vieworder - ($_GET['chpos'] < $r->vieworder ? 1 : 0)).'&'._rsidl.'">Place Here</a></td></tr>';
			}
			$lp = $r->vieworder;
		}
		echo '<tr '.$bgcolor.'><td><img src="'.$GLOBALS['WWW_ROOT'].'images/smiley_icons/'.$r->img.'" border=0></td><td>'.htmlspecialchars($r->code).'</td><td>'.$r->descr.'</td>
			<td nowrap>[<a href="admsmiley.php?edit='.$r->id.'&'._rsidl.'#img">Edit</a>] [<a href="admsmiley.php?del='.$r->id.'&'._rsidl.'">Delete</a>] [<a href="admsmiley.php?chpos='.$r->vieworder.'&'._rsidl.'">Change Position</a>]</td>
			</tr>';
	}

	if (isset($lp)) {
		echo '<tr bgcolor="#efefef"><td align=center colspan=9><a href="admsmiley.php?chpos='.$_GET['chpos'].'&chdest='.($lp + 1).'&'._rsidl.'">Place Here</a></td></tr>';
	}
?>
</table>
<?php require($WWW_ROOT_DISK . 'adm/admclose.html'); ?>