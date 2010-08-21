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

function clean_rgx()
{
	if (!$_POST['rpl_replace_opt']) {
		$_POST['rpl_replace_str'] = '/'. $_POST['rpl_replace_str'] .'/'. $_POST['rpl_preg_opt'];
		$_POST['rpl_from_post'] = '/'. $_POST['rpl_from_post'] .'/'. $_POST['rpl_from_post_opt'];
	} else {
		$_POST['rpl_replace_str'] = '/'. addcslashes($_POST['rpl_replace_str'], '/') .'/';
	}
}

	require($WWW_ROOT_DISK .'adm/header.php');

	if (!empty($_POST['btn_cancel'])) {
		unset($_POST);
	}
	
	if (isset($_POST['btn_submit']) && !empty($_POST['rpl_replace_str']) && !empty($_POST['rpl_with_str'])) {
		clean_rgx();
		if (!$_POST['rpl_replace_opt']) {
			q('INSERT INTO '. $DBHOST_TBL_PREFIX .'replace (replace_opt, replace_str, with_str, from_post, to_msg) VALUES(0, '. _esc($_POST['rpl_replace_str']) .', '. _esc($_POST['rpl_with_str']) .', '. _esc($_POST['rpl_from_post']) .', '. _esc($_POST['rpl_to_msg']) .')');
		} else {
			q('INSERT INTO '. $DBHOST_TBL_PREFIX .'replace (replace_opt, replace_str, with_str) VALUES(1, '. _esc($_POST['rpl_replace_str']) .', '. _esc($_POST['rpl_with_str']) .')');
		}
		echo successify('Replacement rule successfully added.');
	} else if (isset($_POST['btn_update'], $_POST['edit'])) {
		clean_rgx();
		if ($_POST['rpl_replace_opt']) {
			$_POST['rpl_from_post'] = $_POST['rpl_to_msg'] = '';
		}
		q('UPDATE '. $DBHOST_TBL_PREFIX .'replace SET
			replace_opt='. (int)$_POST['rpl_replace_opt'] .',
			replace_str='. _esc($_POST['rpl_replace_str']) .',
			with_str='. _esc($_POST['rpl_with_str']) .',
			from_post='. _esc($_POST['rpl_from_post']) .',
			to_msg='. _esc($_POST['rpl_to_msg']) .'
		WHERE id='. (int)$_POST['edit']);
		echo successify('Replacement rule successfully updated.');
	}

	if (isset($_GET['del'])) {
		q('DELETE FROM '. $DBHOST_TBL_PREFIX .'replace WHERE id='. (int)$_GET['del']);
		echo successify('Replacement rule successfully deleted.');
	}
	if (isset($_GET['edit']) || isset($_POST['edit'])) {
		$edit = isset($_GET['edit']) ? (int)$_GET['edit'] : (int)$_POST['edit'];
		list($rpl_replace_opt, $rpl_replace_str, $rpl_with_str, $rpl_from_post, $rpl_to_msg) = db_saq('SELECT replace_opt,replace_str,with_str,from_post,to_msg FROM '. $DBHOST_TBL_PREFIX .'replace WHERE id='. $edit);
		if ($rpl_replace_opt) {
			$rpl_replace_str = str_replace('\\/', '/', substr($rpl_replace_str, 1, -1));
		} else {
			$p = strrpos($rpl_replace_str, '/');
			$rpl_preg_opt = substr($rpl_replace_str, ($p + 1));
			$rpl_replace_str = substr($rpl_replace_str, 1, ($p - 1));

			$p = strrpos($rpl_from_post, '/');
			$rpl_from_post_opt = substr($rpl_from_post, ($p + 1));
			$rpl_from_post = substr($rpl_from_post, 1, ($p - 1));
		}
	} else {
		$edit = $rpl_replace_str = $rpl_with_str = $rpl_from_post = $rpl_to_msg = $rpl_from_post_opt = $rpl_preg_opt = '';
		$rpl_replace_opt = isset($_POST['rpl_replace_opt']) ? (int) $_POST['rpl_replace_opt'] : 1;
	}
?>
<h2>Replacement and Censorship System</h2>
<p>Add, edit, and remove words and phrases to censor on your forums.</p>

<h3><?php echo $edit ? '<a name="edit">Edit Replacement:</a>' : 'Add Replacement:'; ?></h3>
<form id="frm_rpl" method="post" action="admreplace.php">
<?php echo _hs; ?>
<table class="datatable solidtable">
	<tr class="field">
		<td>Replacement Type:</td>
		<td><?php echo create_select('rpl_replace_opt', "Simple Replace\nPerl Regex (preg_replace)", "1\n0", ($rpl_replace_opt & 1), 'onchange="document.forms[\'frm_rpl\'].submit();"'); ?></td>
	</tr>

	<tr class="field">
		<td>Replace mask:</td>
		<?php if (!$rpl_replace_opt) { ?>
			<td>/<input tabindex="1" type="text" name="rpl_replace_str" value="<?php echo htmlspecialchars($rpl_replace_str); ?>" />/<input type="text" name="rpl_preg_opt" size="3" value="<?php echo htmlspecialchars($rpl_preg_opt); ?>" /></td>
		<?php } else { ?>
			<td> <input tabindex="1" type="text" name="rpl_replace_str" value="<?php echo htmlspecialchars($rpl_replace_str); ?>" /></td>
		<?php } ?>
	</tr>

	<tr class="field">
		<td>Replace with:</td>
		<td><input tabindex="2" type="text" name="rpl_with_str" value="<?php echo htmlspecialchars($rpl_with_str); ?>" /></td>
	</tr>

<?php
	if (!$rpl_replace_opt) {
?>
	<tr>
		<td colspan="2"><br /></td>
	</tr>

	<tr class="fieldtopic">
		<td colspan="2"><font size="-2"><b>Reverse replacement logic, e.g upon editing a post:</b><br />(Optional with the Perl Regex)</font></td>
	</tr>

	<tr class="field">
		<td>Replace mask:</td>
		<td>/<input type="text" name="rpl_from_post" value="<?php echo htmlspecialchars($rpl_from_post); ?>" />/<input type="text" name="rpl_from_post_opt" size="3" value="<?php echo htmlspecialchars($rpl_from_post_opt); ?>" /></td>
	</tr>

	<tr class="field">
		<td>Replace with:<br /></td>
		<td><input type="text" name="rpl_to_msg" value="<?php echo htmlspecialchars($rpl_to_msg); ?>" /></td>
	</tr>

<?php
	} /* !$rpl_replace_opt */
?>
	<tr class="fieldaction" align="right">
		<td colspan="2">
<?php
			if ($edit) {
				echo '<input type="submit" value="Cancel" name="btn_cancel" /> <input tabindex="3" type="submit" value="Update" name="btn_update" />';
			} else {
				echo '<input tabindex="3" type="submit" value="Add" name="btn_submit" />';
			}
?>
		</td>
	</tr>

<?php
	if (!$rpl_replace_opt) {
		if (!isset($_POST['btn_regex'])) {
			$regex_str = $rpl_replace_str;
			$regex_src = 'Some '. $rpl_replace_str .' text';
			$regex_with = $rpl_with_str;
			$regex_str_opt = $rpl_preg_opt;
		} else {
			$regex_str = $_POST['regex_str'];
			$regex_src = $_POST['regex_src'];
			$regex_with = $_POST['regex_with'];
			$regex_str_opt = $_POST['regex_str_opt'];
		}
?>
	<tr>
		<td colspan="2"><br /></td>
	</tr>

	<tr class="fieldtopic">
		<td colspan="2"><b><font size="-2">Test area, tryout your regex here:</font></b></td>
	</tr>

	<tr class="field">
		<td>Replace mask:</td>
		<td>/<input type="text" name="regex_str" value="<?php echo htmlspecialchars($regex_str); ?>" />/<input type="text" name="regex_str_opt" size="3" value="<?php echo htmlspecialchars($regex_str_opt); ?>" /></td>
	</tr>

	<tr class="field">
		<td>Replace with:</td>
		<td><input type="text" name="regex_with" value="<?php echo htmlspecialchars($regex_with); ?>" /></td>
	</tr>

	<tr class="field">
		<td valign="top">Test text:</td>
		<td><textarea name="regex_src" rows="2" cols="25"><?php echo htmlspecialchars($regex_src); ?></textarea></td>
	</tr>
<?php
	if (isset($_POST['btn_regex'])) {
		$str = preg_replace('/'. $regex_str .'/'. $regex_str_opt, $regex_with, $regex_src);
?>
	<tr class="fieldresult">
		<td valign="top">Test result:</td>
		<td>
			<font size="-1">'<?php echo htmlspecialchars($regex_str); ?>' applied to:</font><br />
			<table border="1" cellspacing="0" cellpadding="3" bgcolor="yellow">
			<tr><td><font size="-1"><?php echo htmlspecialchars($regex_src); ?></font></td></tr>
			</table>
			<br />
			<font size="-1">produces:</font><br />
			<table border="1" cellspacing="0" cellpadding="3" bgcolor="yellow">
			<tr><td><font size="-1"><?php echo htmlspecialchars($str); ?></font></td></tr>
			</table>
		</td>
	</tr>
<?php
	} /* isset($_POST['btn_regex']) */
?>

	<tr class="fieldaction" align="right">
		<td colspan="2"><input type="submit" name="btn_regex" value="Test it!" /></td>
	</tr>
<?php } /* !$rpl_replace_opt */ ?>

</table>
<input type="hidden" name="edit" value="<?php echo $edit; ?>" />
</form>
<h3>Defined replacements:</h3>
<table class="resulttable fulltable">
<thead><tr class="resulttopic">
	<th>Replace Type</th>
	<th>Replace</th>
	<th>With</th>
	<th valign="middle"><font size="-3">(only if regexp:</font> From</th>
	<th valign="middle">To<font size="-3">)</font></th>
	<th>Action</th>
</tr></thead>
<?php
	$c = uq('SELECT * FROM '. $DBHOST_TBL_PREFIX .'replace ORDER BY replace_opt');
	$i = 0;
	while ($r = db_rowobj($c)) {
		$i++;
		$bgcolor = ($edit == $r->id) ? ' class="resultrow3"' : (($i%2) ? ' class="resultrow1"' : ' class="resultrow2"');

		if ($r->replace_opt) {
			$rtype = 'Simple';
			$r->replace_str = substr($r->replace_str, 1, -1);
		} else {
			$rtype = 'Regular Expression';
		}

		echo '<tr'. $bgcolor .'><td>'. $rtype .'</td><td>'. htmlspecialchars($r->replace_str) .'</td><td>'. htmlspecialchars($r->with_str) .'</td>';
		if ($r->replace_opt) {
			echo '<td colspan="2" align="center">n/a</td>';
		} else {
			echo '<td>'. htmlspecialchars($r->from_post) .'</td><td>'. htmlspecialchars($r->to_msg) .'</td>';
		}
		echo '<td>[<a href="admreplace.php?edit='. $r->id .'&amp;'. __adm_rsid .'#edit">Edit</a>] [<a href="admreplace.php?del='. $r->id .'&amp;'. __adm_rsid .'">Delete</a>]</td></tr>';
	}
	unset($c);
	if (!$i) {
		echo '<tr class="field"><td colspan="6"><center>No replacments found. Define some above.</center></td></tr>';
	}
?>
</table>
<?php require($WWW_ROOT_DISK .'adm/footer.php'); ?>
