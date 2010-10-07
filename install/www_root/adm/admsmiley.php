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

	require('./GLOBALS.php');
	fud_use('adm.inc', true);
	fud_use('widgets.inc', true);
	fud_use('sml_rcache.inc', true);

	$tbl = $GLOBALS['DBHOST_TBL_PREFIX'];
	require($WWW_ROOT_DISK .'adm/header.php');

	$smiley_dir = '../images/smiley_icons/';

	if (isset($_GET['del'])) {
		db_lock($tbl.'smiley WRITE');
		if (($im = q_singleval('SELECT img FROM '. $tbl .'smiley WHERE id='. (int)$_GET['del']))) {
			q('DELETE FROM '. $tbl .'smiley WHERE id='. (int)$_GET['del']);
			if (db_affected()) {
				q('UPDATE '. $tbl .'smiley SET vieworder=vieworder-1 WHERE id>'. (int)$_GET['del']);
			}
			@unlink($GLOBALS['WWW_ROOT_DISK'] .'images/smiley_icons/'. $im);
		}
		smiley_rebuild_cache();
		db_unlock();
		echo successify('Smiley succesfully deleted.');
	}

	if (isset($_GET['edit'])) {
		list($sml_code, $sml_img, $sml_descr) = db_saq('SELECT code, img, descr FROM '. $tbl .'smiley WHERE id='. (int)$_GET['edit']);
		$edit = (int)$_GET['edit'];
	} else {
		$edit = $sml_code = $sml_img = $sml_descr = '';
	}

	if (isset($_FILES['icoul']) && $_FILES['icoul']['size'] && preg_match('!\.(jpg|jpeg|gif|png)$!i', $_FILES['icoul']['name'])) {
		move_uploaded_file($_FILES['icoul']['tmp_name'], $GLOBALS['WWW_ROOT_DISK'] .'images/smiley_icons/'. $_FILES['icoul']['name']);
		if (empty($_POST['avt_img'])) {
			$_POST['avt_img'] = $_FILES['icoul']['name'];
		}
		$sml_img = $_FILES['icoul']['name'];
	}

	if (isset($_POST['btn_update'], $_POST['edit']) && !empty($_POST['sml_img']) && !empty($_POST['sml_code']) && $_POST['sml_code']{strlen($_POST['sml_code']) - 1} != '~') {
		q('UPDATE '. $tbl .'smiley SET code='. ssn($_POST['sml_code']) .', img='. ssn($_POST['sml_img']) .', descr='. ssn($_POST['sml_descr']) .' WHERE id='. (int)$_POST['edit']);
		smiley_rebuild_cache();
		echo successify('Smiley succesfully updated.');
	} else if (isset($_POST['btn_submit']) && !empty($_POST['sml_img']) && !empty($_POST['sml_code']) && $_POST['sml_code']{strlen($_POST['sml_code']) - 1} != '~') {
		$view_order = q_singleval('SELECT MAX(vieworder) FROM '. $tbl .'smiley') + 1;
		q('INSERT INTO '.$tbl.'smiley (code, img, descr, vieworder) VALUES('. ssn($_POST['sml_code']) .', '. ssn($_POST['sml_img']) .', '. ssn($_POST['sml_descr']) .', '. $view_order .')');
		smiley_rebuild_cache();
		echo successify('Smiley succesfully added.');
	}

	if (isset($_GET['chpos'], $_GET['chdest'])) {
		$oldp = (int)$_GET['chpos'];
		$newp = (int)$_GET['chdest'];
		if ($oldp != $newp && $newp) {
			db_lock($GLOBALS['DBHOST_TBL_PREFIX'] .'smiley WRITE');
			q('UPDATE '. $GLOBALS['DBHOST_TBL_PREFIX'] .'smiley SET vieworder=2147483647 WHERE vieworder='. $oldp);
			if ($oldp < $newp) {
				q('UPDATE '. $GLOBALS['DBHOST_TBL_PREFIX'] .'smiley SET vieworder=vieworder-1 WHERE vieworder<='. $newp .' AND vieworder>'. $oldp);
				$maxp = q_singleval('SELECT MAX(vieworder) FROM '. $GLOBALS['DBHOST_TBL_PREFIX'] .'smiley WHERE  vieworder!=2147483647');
				if ($newp > $maxp) {
					$newp = $maxp + 1;
				}
			} else {
				q('UPDATE '. $GLOBALS['DBHOST_TBL_PREFIX'] .'smiley SET vieworder=vieworder+1 WHERE vieworder<'. $oldp .' AND vieworder>='. $newp);
			}
			q('UPDATE '. $GLOBALS['DBHOST_TBL_PREFIX'] .'smiley SET vieworder='. $newp .' WHERE vieworder=2147483647');
			db_unlock();
			$_GET['chpos'] = null;
			echo successify('Smiley\'s position was succesfully changed.');
		}
	}

	if (!isset($_GET['chpos'])) {
?>
<script type="text/javascript">
/* <![CDATA[ */
function sml_form_check() 
{
	var a = document.frm_sml.sml_code.value;
	if (a[a.length - 1] == '~') {
		alert('FUDforum separates emoticons with a tilde (~) therefor the last char of the smiley/emoticon code cannot be a ~.');
		return false;	
	}
	return true;
}
/* ]]> */
</script>

<h2>Smiley Management System</h2>

<h3>Upload smiley icon:</h3>
<form id="frm_sml" method="post" enctype="multipart/form-data" action="admsmiley.php" onsubmit="return sml_form_check();">
<?php
	echo _hs;
	echo '<table class="datatable solidtable">';
	if (@is_writeable($GLOBALS['WWW_ROOT_DISK'] .'images/smiley_icons')) { ?>
		<tr class="field">
			<td>Icon to upload:<br /><font size="-1">Only (*.gif, *.jpg, *.jpeg, *.png) files are allowed.</font></td>
			<td><input type="file" name="icoul" /> <input type="submit" name="btn_upload" value="Upload" /></td>
			<td><input type="hidden" name="tmp_f_val" value="1" /></td>
		</tr>
	<?php } else { ?>
		<tr class="field">
			<td colspan="2"><span style="color:red;">Web server doesn't have write permissions to <b>'<?php echo $GLOBALS['WWW_ROOT_DISK'] .'images/smiley_icons'; ?>'</b>, smiley upload disabled.</span></td>
		</tr>
	<?php } ?>
</table>

<h3><?php echo $edit ? '<a name="edit">Edit Smiley:</a>' : 'Add New Smiley:'; ?></h3>
<table class="datatable solidtable">
	<tr class="field">
		<td>Smiley Description:</td>
		<td><input type="text" name="sml_descr" value="<?php echo htmlspecialchars($sml_descr); ?>" /></td>
	</tr>

	<tr class="field">
		<td>Smiley Text:<br /><font size="-1">Will be replaced with smiley,<br />use <b>~</b> to separate multiple allowed codes.</font></td>
		<td><input type="text" name="sml_code" value="<?php echo htmlspecialchars($sml_code); ?>" /></td>
	</tr>

	<tr class="field">
		<td valign="top"><a name="sml_sel">Smiley Image:</a></td>
		<td>
			<input type="text" name="sml_img" value="<?php echo htmlspecialchars($sml_img); ?>"
				onchange="
						if (this.value.length) {
							document.prev_icon.src='<?php echo $GLOBALS['WWW_ROOT']; ?>images/smiley_icons/' + this.value;
						} else {
							document.prev_icon.src='../blank.gif';
						}" />
			[<a href="javascript://" onclick="window.open('admiconsel.php?type=3&amp;<?php echo __adm_rsid; ?>', 'admsmileysel', 'menubar=false,scrollbars=yes,resizable=yes,height=300,width=500,screenX=100,screenY=100');">SELECT ICON</a>]
		</td>
	</tr>

	<tr class="field">
		<td>Preview Image:</td>
		<td>
			<table border="1" cellspacing="1" cellpadding="2" bgcolor="#ffffff">
				<tr><td align="center" valign="middle">
					<img src="<?php echo ($sml_img ? $GLOBALS['WWW_ROOT'] .'images/smiley_icons/'. $sml_img : '../blank.gif'); ?>" name="prev_icon" border="0" alt="blank" />
				</td></tr>
			</table>
		</td>
	</tr>

	<tr class="fieldaction">
		<?php
			if (!$edit) {
				echo '<td colspan="2" align="right"><input type="submit" name="btn_submit" value="Add Smiley" /></td>';
			} else {
				echo '<td colspan="2" align="right"><input type="submit" name="btn_cancel" value="Cancel" /><input type="submit" name="btn_update" value="Update" /></td>';
			}
		?>
	</tr>

</table>
<input type="hidden" name="edit" value="<?php echo $edit; ?>" />
</form>
<?php } /* if (!isset($_GET['chpos'])) { */ ?>

<h3>Available smilies:</h3>
<table class="resulttable fulltable">
<thead><tr class="resulttopic">
	<th>Smiley</th>
	<th>Code</th>
	<th>Description</th>
	<th>Action</th>
</tr></thead>
<?php
	$c = uq('SELECT id, img, code, descr, vieworder FROM '. $tbl .'smiley ORDER BY vieworder');
	$i = 0;
	$chpos = isset($_GET['chpos']) ? (int)$_GET['chpos'] : '';
	while ($r = db_rowobj($c)) {
		$i++;
		$r->code = '<b>'. str_replace('~', '</b> or <b>', htmlspecialchars($r->code)) .'</b>';
		$bgcolor = ($edit == $r->id) ? ' class="resultrow3"' : (($i%2) ? ' class="resultrow1"' : ' class="resultrow2"');

		if (isset($_GET['chpos'])) {
			if ($_GET['chpos'] == $r->vieworder) {
				$bgcolor = ' class="resultrow2"';
			} else if ($_GET['chpos'] != ($r->vieworder - 1)) {
				echo '<tr class="field"><td align="center" colspan="9"><a href="admsmiley.php?chpos='. $_GET['chpos'] .'&amp;chdest='. ($r->vieworder - ($_GET['chpos'] < $r->vieworder ? 1 : 0)) .'&amp;'. __adm_rsid .'">Place Here</a></td></tr>';
			}
			$lp = $r->vieworder;
		}
		echo '<tr'. $bgcolor .'><td><img src="'. $GLOBALS['WWW_ROOT'] .'images/smiley_icons/'. $r->img .'" border="0" alt="'. $r->descr .'" /></td><td>'. $r->code .'</td><td>'. $r->descr .'</td>
			<td nowrap="nowrap">[<a href="admsmiley.php?edit='. $r->id .'&amp;'. __adm_rsid .'#edit">Edit</a>] [<a href="admsmiley.php?del='. $r->id .'&amp;'. __adm_rsid .'">Delete</a>] [<a href="admsmiley.php?chpos='. $r->vieworder .'&amp;'. __adm_rsid .'">Change Position</a>]</td>
			</tr>';
	}
	unset($c);
	if (isset($lp)) {
		echo '<tr class="field"><td align="center" colspan="4"><a href="admsmiley.php?chpos='. $_GET['chpos'] .'&amp;chdest='. ($lp + 1) .'&amp;'. __adm_rsid .'">Place Here</a></td></tr>';
	}
	if (!$i) {
		echo '<tr class="field"><td colspan="4"><center>No smileys found. Define some above.</center></td></tr>';
	}
?>
</table>
<?php require($WWW_ROOT_DISK .'adm/footer.php'); ?>
