<?php
/**
* copyright            : (C) 2001-2009 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: admhelp.php,v 1.1 2009/09/07 15:49:52 frank Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; version 2 of the License.
**/

	require('./GLOBALS.php');
	fud_use('adm.inc', true);

	if (isset($_POST['tname'], $_POST['tlang'], $_POST['btn_edit'])) {
		header('Location: hlplist.php?tname='.$_POST['tname'].'&tlang='.$_POST['tlang'].'&'.__adm_rsidl);
		exit;
	}

	require($WWW_ROOT_DISK . 'adm/admpanel.php');

	list($def_thm, $def_tmpl) = db_saq('SELECT name, lang FROM '.$GLOBALS['DBHOST_TBL_PREFIX'].'themes WHERE theme_opt=3');
?>
<h2>Help File Editor</h2>
<div class="tutor">Please document all changes you make to FUDforum's default help files, as future upgrades may overwrite your changes. Create a <a href="admtemplates.php?<?php echo __adm_rsid; ?>">custom template set</a> to prevent this from happening.</div>
<p>Select a template set and language to edit:</p>
<form method="post" action="admhelp.php">
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

<?php require($WWW_ROOT_DISK . 'adm/admclose.html'); ?>
