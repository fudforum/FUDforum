<?php
/**
* copyright            : (C) 2001-2009 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: admtemplates.php,v 1.1 2009/09/07 15:49:52 frank Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; version 2 of the License.
**/

	require('./GLOBALS.php');
	fud_use('adm.inc', true);
	fud_use('compiler.inc', true);
	fud_use('theme.inc', true);

	require($WWW_ROOT_DISK . 'adm/admpanel.php');

	if (isset($_POST['tname'], $_POST['tlang'], $_POST['btn_edit'])) {
		header('Location: tmpllist.php?tname='.$_POST['tname'].'&tlang='.$_POST['tlang'].'&'.__adm_rsidl);
		exit;
	}

	/* Limit theme names to sane characters */
	if (isset($_POST['newname'], $_POST['btn_create'])) {
		$_POST['newname'] = preg_replace('![^A-Za-z0-9_]!', '_', trim($_POST['newname']));
	} else {
		$_POST['newname'] = '';
	}

	if ($_POST['newname'] && !q_singleval("SELECT id FROM ".$DBHOST_TBL_PREFIX."themes WHERE name="._esc($_POST['newname']))) {
		$root = $DATA_DIR . 'thm/';
		$root_nn = $root . preg_replace('![^A-Za-z0-9_]!', '_', $_POST['newname']);
		$u = umask(0);
		if (!@is_dir($root_nn) && !@mkdir($root_nn, 0777)) {
			exit('ERROR: Unable to create ['.$root_nn.']<br />');
		}

		if ($_POST['copy_mode'] == 'headfoot') {	// sparse theme - header & footer
			mkdir($root_nn.'/tmpl', 0777);
			if ($_POST['base_template_set'] == 'path_info') {
				fudcopy($root . 'path_info/tmpl/', $root_nn.'/tmpl', '{header.tmpl,footer.tmpl}', true);
		    } else {
				fudcopy($root . 'default/tmpl/', $root_nn.'/tmpl', '{header.tmpl,footer.tmpl}', true);
			}
		} else if ($_POST['copy_mode'] == 'headfootcss') {	// sparse theme - header, footer & css
			mkdir($root_nn.'/tmpl', 0777);
			fudcopy($root . 'default/tmpl/', $root_nn.'/tmpl', 'forum.css.tmpl', true);
			if ($_POST['base_template_set'] == 'path_info') {
				fudcopy($root . 'path_info/tmpl/', $root_nn.'/tmpl', '{header.tmpl,footer.tmpl}', true);
		    } else {
				fudcopy($root . 'default/tmpl/', $root_nn.'/tmpl', '{header.tmpl,footer.tmpl}', true);
			}
		} else if ($_POST['copy_mode'] == 'all') {	// full theme with all files - not recommended!
			fudcopy($root . 'default/', $root_nn, '*', true);
			if ($_POST['base_template_set'] == 'path_info') {
				fudcopy($root . 'path_info/', $root_nn, '*', true);
		    }
		}

		if ($_POST['base_template_set'] == 'path_info') {	// Copy the PATH_INFO pointer
			fudcopy($root . 'path_info/', $root_nn, '.path_info', true);
		}
		umask($u);
		echo '<font color="green">Template set '.$_POST['newname'].' was successfully created.</font>';
	}
	
	list($def_thm, $def_tmpl) = db_saq('SELECT name, lang FROM '.$GLOBALS['DBHOST_TBL_PREFIX'].'themes WHERE theme_opt=3');
?>
<h2>Template Editor</h2>
<div class="tutor">
	Please document all changes you make to FUDforum's default templates, as future upgrades may overwrite your changes. 
	Create a custom template set (below) to prevent this from happening.
</div>

<h3>Edit template files:</h3>
<p>Select a template set and language to edit:</p>
<form method="post" action="admtemplates.php">
<?php echo _hs; ?>
<table class="datatable solidtable">
<tr class="field">
<td>Template Set:</td><td><select name="tname">
<?php
	foreach (glob($GLOBALS['DATA_DIR'].'/thm/*', GLOB_ONLYDIR) as $file) {
		if (!file_exists($file . '/tmpl')) {
			continue;
		}
		$n = basename($file);
		echo '<option value="'.$n.'"'.($n == $def_thm ? ' selected="selected"' : '').'>'.$n.'</option>';
	}
?>
</select></td>
</tr>
<tr class="field">
<td>Language:</td><td><select name="tlang">
<?php
	foreach (glob($GLOBALS['DATA_DIR'] . 'thm/default/i18n/*', GLOB_ONLYDIR) as $file) {
		if (!file_exists($file . '/msg')) {
			continue;
		}
		$n = basename($file);
		echo '<option value="'.$n.'"'.($n == $def_tmpl ? ' selected="selected"' : '').'>'.$n.'</option>';
	}
?>
</select></td></tr>
<tr class="fieldaction" align="right"><td colspan="2"><input type="submit" name="btn_edit" value="Edit" /></td></tr></table></form>

<h3>Create custom template set:</h3>
<form method="post" action="admtemplates.php">
<table class="datatable solidtable">
<tr class="field">
	<td>Base Template Set:<br /><font size="-1">(inherit from here)</font></td>
	<td>
	<select name="base_template_set">
	<option value="default">Default</option>
	<option value="path_info">Path Info</option>
	</select></td>
</tr>
<tr class="field">
	<td>Name:</td>
	<td><input type="text" name="newname" /></td>
</tr>
<tr class="field">
	<td>What to copy:</td>
	<td>
	<select name="copy_mode">
	<option value="headfoot">Header and footer templates</option>	
	<option value="headfootcss">Header, footer and CSS templates</option>
	<option value="all">All template files (not recommended)</option>
	</select></td>
</tr>
<tr class="fieldaction">
	<td colspan="2" align="right"><input type="submit" name="btn_create" value="Create" /></td>
</tr>
</table>
<?php echo _hs; ?>
</form>

<?php require($WWW_ROOT_DISK . 'adm/admclose.html'); ?>
