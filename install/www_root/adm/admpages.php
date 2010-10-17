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
	fud_use('page_adm.inc', true);
	fud_use('widgets.inc', true);

	$tbl = $GLOBALS['DBHOST_TBL_PREFIX'];

	require($WWW_ROOT_DISK .'adm/header.php');
	if (!empty($_POST['btn_cancel'])) {
		unset($_POST);
	}

	$edit = isset($_GET['edit']) ? (int)$_GET['edit'] : (isset($_POST['edit']) ? (int)$_POST['edit'] : '');

	// Add or edit a static page.
	if (isset($_POST['frm_submit']) && !empty($_POST['page_slug'])) {
		$error = 0;

		if ($edit && !$error) {
			$page = new fud_page;
			$page->sync($edit);
			$edit = '';	
			echo successify('Page was successfully updated.');
		} else if (!$error) {
			$page = new fud_page;
			$page->add();
			echo successify('Page was successfully added.');
		}
	}

	/* Remove a static page. */
	if (isset($_GET['del'])) {
		$page = new fud_page();
		$page->delete($_GET['del']);
		echo successify('Page was successfully deleted.');
	}

	/* Set defaults. */
	if ($edit && ($c = db_arr_assoc('SELECT * FROM '. $tbl .'pages WHERE id='. $edit))) {
		foreach ($c as $k => $v) {
			${'page_'.$k} = $v;
		}
	} else {
		$c = get_class_vars('fud_page');
		foreach ($c as $k => $v) {
			${'page_'. $k} = '';
		}
	}
?>
<h2>Static pages</h2>

<?php
echo '<h3>'. ($edit ? '<a name="edit">Edit Page:</a>' : 'Add New Page:') .'</h3>';
?>
<form method="post" id="frm_page" action="admpages.php">
<?php echo _hs; ?>
<table class="datatable">
	<tr class="field">
		<td>Page slug:<br /><font size="-2">The page slug is used in the URL of the page.</font></td>
		<td><input type="text" name="page_slug" value="<?php echo $page_slug; ?>" /></td>
	</tr>

	<tr class="field">
		<td>Page title:<br /><font size="-2">Title of the page.</font></td>
		<td><input type="text" name="page_title" value="<?php echo $page_title; ?>" /></td>
	</tr>

	<tr class="field">
		<td>Page body:<br /><font size="-2">Text to display on the page.</font></td>
		<td><textarea name="page_body" cols="60" rows="7"><?php echo $page_body; ?></textarea></td>
	</tr>

	<tr class="field">
		<td>Options:<br /><font size="-2">Page options.</font></td>
		<td><?php draw_select('page_page_opt', "Optional\nMandatory", "0\n1", ($page_page_opt & (1))); ?></td>
	</tr>

	<tr class="fieldaction">
		<td colspan="2" align="right">
<?php
	if ($edit) {
		echo '<input type="hidden" name="edit" value="'. $edit .'" />';
		echo '<input type="submit" value="Cancel" name="btn_cancel" /> ';
	}
?>
			<input type="submit" value="<?php echo ($edit ? 'Update Page' : 'Add Page'); ?>" name="frm_submit" />
		</td>
	</tr>
</table>
</form>

<h3>Defined pages:</h3>
<table class="resulttable fulltable">
<thead><tr class="resulttopic">
	<th>Slug</th><th>Title</th><th>Body</th><th>Action</th>
</tr></thead>
<?php
	$i = 0;
	$c = uq('SELECT id, slug, title, body FROM '. $tbl .'pages LIMIT 100');
	while ($r = db_rowobj($c)) {
		$i++;
		$bgcolor = ($edit == $r->id) ? ' class="resultrow3"' : (($i%2) ? ' class="resultrow1"' : ' class="resultrow2"');

		echo '<tr'. $bgcolor .'><td>'. $r->slug .'</td><td>'. $r->title .'</td><td><font size="-1">'. htmlspecialchars(substr($r->body, 0, 100)) .'...</font></td>';
		echo '<td><a href="admpages.php?edit='. $r->id .'&amp;'. __adm_rsid .'#edit">Edit</a> | <a href="admpages.php?del='. $r->id .'&amp;'. __adm_rsid .'">Delete</a></td></tr>';
	}
	unset($c);
	if (!$i) {
		echo '<tr class="field"><td colspan="4"><center>No pages found. Define some above.</center></td></tr>';
	}
?>
</table>

<?php require($WWW_ROOT_DISK .'adm/footer.php'); ?>
