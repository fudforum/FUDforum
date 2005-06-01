<?php
/**
* copyright            : (C) 2001-2004 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: admthemes.php,v 1.56 2005/06/01 19:39:52 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; either version 2 of the License, or
* (at your option) any later version.
**/

	$meml = (($meml = ini_get("memory_limit")) && (int)$meml < 10);

	require('./GLOBALS.php');
	fud_use('widgets.inc', true);
	fud_use('adm.inc', true);
	fud_use('compiler.inc', true);
	fud_use('theme.inc', true);

	$edit = isset($_GET['edit']) ? (int)$_GET['edit'] : (isset($_POST['edit']) ? (int)$_POST['edit'] : '');

	/* Limit theme names to sane characters */
	if (isset($_POST['newname'])) {
		$_POST['newname'] = preg_replace('![^A-Za-z0-9_]!', '_', $_POST['newname']);
	}

	if (isset($_POST['newname']) && !q_singleval("SELECT id FROM ".$DBHOST_TBL_PREFIX."themes WHERE name='".addslashes($_POST['newname'])."'")) {
		$root = $DATA_DIR . 'thm/';
		$root_nn = $root . preg_replace('![^A-Za-z0-9_]!', '_', $_POST['newname']);
		$u = umask(0);
		if (!@is_dir($root_nn) && !@mkdir($root_nn, 0777)) {
			exit('can\'t create ('.$root_nn.')<br>');
		}

		fudcopy($root . 'default/', $root_nn, '*', true);
		if ($_POST['base_template_set'] == 'path_info') {
			fudcopy($root . 'path_info/', $root_nn, '*', true);
		}
		umask($u);
	}

	if (isset($_GET['rebuild_all'])) {
		$r = q('SELECT theme, lang, name FROM '.$DBHOST_TBL_PREFIX.'themes');
		while (($data = db_rowarr($r))) {
			compile_all($data[0], $data[1], $data[2]);
		}
		unset($r);
	}

	if (isset($_POST['thm_theme']) && !$edit) {
		$thm = new fud_theme;
		$thm->add();
		compile_all($thm->theme, $thm->lang, $thm->name);
	} else if (isset($_POST['edit'])) {
		$thm = new fud_theme;
		if ($edit == 1) {
			$thm->name = 'default';
		}
		$thm->sync((int)$_POST['edit']);
		compile_all($thm->theme, $thm->lang, $thm->name);
		$edit = '';
	} else if (isset($_GET['rebuild']) && ($data = db_saq('SELECT theme, lang, name FROM '.$DBHOST_TBL_PREFIX.'themes WHERE id='.(int)$_GET['rebuild']))) {
		compile_all($data[0], $data[1], $data[2]);
	} else if (isset($_GET['edit']) && ($c = db_arr_assoc('SELECT * FROM '.$DBHOST_TBL_PREFIX.'themes WHERE id='.$edit))) {
		foreach ($c as $k => $v) {
			${'thm_'.$k} = $v;
		}
		$thm_t_default = $c['theme_opt'] & 2;
		$thm_enabled = $c['theme_opt'] & 1;
	} else if (isset($_GET['del']) && (int)$_GET['del'] > 1) {
		fud_theme::delete((int)$_GET['del']);
	}

	if (!$edit) {
		$c = get_class_vars('fud_theme');
		foreach ($c as $k => $v) {
			${'thm_'.$k} = '';
		}
		$thm_locale = 'english';
		$thm_pspell_lang = 'en';
		$thm_t_default = $thm_enabled = 0;
	}

	require($WWW_ROOT_DISK . 'adm/admpanel.php');
?>
<h2>Theme Management [ <a href="admthemes.php?rebuild_all=1&<?php echo __adm_rsidl; ?>">Rebuild all Themes</a> ]</h2>

<form name="admthm" action="admthemes.php" method="post">
<?php echo _hs; ?>
<table class="datatable solidtable">
<tr class="field">
	<td>Name:</td>
	<td>
<?php
	if ($edit && $edit == 1) {
		echo htmlspecialchars($thm_name);
	} else {
		echo '<input type="text" name="thm_name" value="'.htmlspecialchars($thm_name).'">';
	}
?>
	</td>
</tr>

<tr class="field">
	<td valign=top>Template Set:</td>
	<td>
	<select name="thm_theme">
	<?php
		if (!defined('GLOB_ONLYDIR')) { /* pre PHP 4.3.3 hack for FreeBSD and Windows */
			define('GLOB_ONLYDIR', 0);
		}

		$files = glob($DATA_DIR.'/thm/*', GLOB_ONLYDIR|GLOB_NOSORT);
		foreach ($files as $file) {
			if (!file_exists($file . '/tmpl')) {
				continue;
			}
			$n = basename($file);
			echo '<option value="'.$n.'"'.($n == $thm_theme ? ' selected' : '').'>'.$n.'</option>';
		}
	?></select>
	</td>
</tr>
<tr class="field">
	<td>Language</td>
	<td>
	<?php
		if (!$thm_lang) {
			$thm_lang = 'english';
		}
		$selopt = '';
		$files = glob($DATA_DIR.'/thm/default/i18n/*', GLOB_ONLYDIR|GLOB_NOSORT);
		foreach ($files as $file) {
			if (!file_exists($file . '/msg')) {
				continue;
			}
			$n = basename($file);
			$selopt .= '<option'.($thm_lang == $n ? ' selected' : '').'>'.$n.'</option>';
			$locales[$n]['locale'] = trim(file_get_contents($file . '/locale'));
			$pspell_file = $file . '/pspell_lang';
			$locales[$n]['pspell_lang'] = file_exists($pspell_file) ? trim(file_get_contents($pspell_file)) : 'en';
		}

		$cases = '';
		foreach($locales as $k => $v) {
			$cases .= "case '$k': document.admthm.thm_locale.value = '".$v['locale']."'; ";
			$cases .= "document.admthm.thm_pspell_lang.value='".$v['pspell_lang']."'; ";
			$cases .= "break;\n";
		}
	?>
<script>
function update_locale()
{
	switch (document.admthm.thm_lang.value) {
		<?php echo $cases; ?>
	}
}
</script>

	<select name="thm_lang" onChange="javascript: update_locale();">
	<?php echo $selopt; ?>
	</select>
	</td>
</tr>

<tr class="field">
	<td>Locale:</td>
	<td><input type="text" name="thm_locale" value="<?php echo htmlspecialchars($thm_locale); ?>" size=7></td>
</tr>

<tr class="field">
	<td>pSpell Language:</td>
	<td>
		<input type="text" name="thm_pspell_lang" value="<?php echo htmlspecialchars($thm_pspell_lang); ?>" size=4>
		[<a href="javascript://" onClick="javascript: document.admthm.thm_pspell_lang.value=''">disable</a>]
	</td>
</tr>

<tr class="field">
	<td colspan=2>
	<?php draw_checkbox('thm_t_default', '2', $thm_t_default);?> Default <?php draw_checkbox('thm_enabled', '1', $thm_enabled); ?> Enabled
	</td>
</tr>
<tr class="fieldaction">
<?php if (!$edit) { ?>
		<td colspan=2 align=right><input type="submit" name="btn_submit" value="Add"></td>
<?php } else { ?>
	<td colspan=2 align=right>
		<input type="submit" name="btn_cancel" value="Cancel">
		<input type="submit" name="btn_update" value="Update">
	</td>
<?php } ?>
</tr>
</table>
<input type="hidden" name="prevloaded" value="1">
<input type="hidden" name="edit" value="<?php echo $edit; ?>">
</form>

<form method="post">
<table class="datatable solidtable">
<tr class="field"><td colspan=2>Create New Template Set</td></tr>
<tr class="field">
	<td>Base Template Set:</td>
	<td>
	<select name="base_template_set">
	<option value="default">Default</option>
	<option value="path_info">Path Info</option>
	</select></td>
</tr>
<tr class="field">
	<td>Name</td>
	<td><input type="text" name="newname"></td>
</tr>
<tr class="fieldaction">
	<td colspan=2 align=right><input type="submit" name="btn_submit" value="Create"></td>
</tr>
</table>
<?php echo _hs; ?>
</form>

<table class="resulttable fulltable">
<tr class="resulttopic">
	<td>Name</td>
	<td>Template Set</td>
	<td>Language</td>
	<td>Locale</td>
	<td>pSpell Lang</td>
	<td>Enabled</td>
	<td>Default</td>
	<td>Action</td>
</tr>
<?php
	$i = 1;
	$c = uq('SELECT * FROM '.$DBHOST_TBL_PREFIX.'themes ORDER BY id');
	while ($r = db_rowobj($c)) {
		if ($edit == $r->id) {
			$bgcolor = ' class="resultrow1"';
		} else {
			$bgcolor = ($i++%2) ? ' class="resultrow2"' : ' class="resultrow1"';
		}

		echo '<tr '.$bgcolor.'>
			<td>'.htmlspecialchars($r->name).'</td>
			<td>'.htmlspecialchars($r->theme).'</td>
			<td>'.htmlspecialchars($r->lang).'</td>
			<td>'.htmlspecialchars($r->locale).'</td>
			<td>'.(!$r->pspell_lang ? '<font color="green">disabled</font> ' : htmlspecialchars($r->pspell_lang)).'</td>
			<td>'.($r->theme_opt & 1 ? 'Yes' : '<font color="green">No</font>').'</td>
			<td>'.($r->theme_opt & 2 ? 'Yes' : '<font color="green">No</font>').'</td>
			<td nowrap>[<a href="admthemes.php?'.__adm_rsidl.'&edit='.$r->id.'">Edit</a>] [<a href="admthemes.php?'.__adm_rsidl.'&rebuild='.$r->id.'">Rebuild Theme</a>]
			'.($r->id != 1 ? '[<a href="admthemes.php?'.__adm_rsidl.'&del='.$r->id.'">Delete</a>]' : '').'
			</td>
		</tr>';
	}
?>
</table>
<?php require($WWW_ROOT_DISK . 'adm/admclose.html'); ?>
