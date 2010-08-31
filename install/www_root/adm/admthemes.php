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

	// Run from command line.
	if (php_sapi_name() == 'cli') {
		if (empty($_SERVER['argv'][1]) || $_SERVER['argv'][1] != 'compileall') {
			echo "Usage: php admthemes.php compileall\n";
			echo " - specify 'compileall' to confirm execution.\n";
			die();
		}

		fud_use('adm_cli.inc', 1);
		$_GET['rebuild_all'] = 1;
	}

	fud_use('widgets.inc', true);
	fud_use('adm.inc', true);
	fud_use('compiler.inc', true);
	fud_use('theme.inc', true);

	require($WWW_ROOT_DISK .'adm/header.php');

	if (!empty($_POST['btn_cancel'])) {
		unset($_POST);
	}
	$edit = isset($_GET['edit']) ? (int)$_GET['edit'] : (isset($_POST['edit']) ? (int)$_POST['edit'] : '');

	if (isset($_GET['rebuild_all'])) {
		$r = q('SELECT theme, lang, name FROM '. $DBHOST_TBL_PREFIX .'themes');
		while (($data = db_rowarr($r))) {
			try {
				compile_all($data[0], $data[1], $data[2]);
				pf(successify('Theme '. $data[2] .' ('. $data[1] .') was successfully rebuilt.'));
			} catch (Exception $e) {
				pf(errorify('Please fix theme: '. $e->getMessage()));
			}
		}
		unset($r);
		if (defined('shell_script')) {
			return;
		}
	}

	if (isset($_POST['thm_theme']) && @file_exists($DATA_DIR .'thm/'. $_POST['thm_theme'] .'/.path_info') && !($FUD_OPT_2 & 32768)) {
		echo '<h3 class="alert">You need to enable PATH_INFO support in the <a href="admglobal.php?'. __adm_rsid .'#2">Global Settings Manager</a> before using a path_info theme.</h3>';
		// Change POST to GET request to reload editor window.
		$_GET['edit'] = $_POST['edit'];
		unset($_POST['edit'], $_POST['thm_theme']);
	}

	if (isset($_POST['thm_theme']) && !$edit) {
		$thm = new fud_theme;
		if ($thm->name) {
			if (q_singleval('SELECT id FROM '. $DBHOST_TBL_PREFIX .'themes WHERE name='. _esc($_POST['thm_name']))) {
				pf(errorify('There is already a theme with this name.'));
			} elseif (setlocale(LC_ALL, $_POST['thm_locale']) === FALSE) {
				pf(errorify('The specified locale ('. $_POST['thm_locale'] .') does not exist on your system.'));
			} else {
				$thm->add();
				try {
					compile_all($thm->theme, $thm->lang, $thm->name);
					pf(successify('Theme '. $thm->name .' was successfully created.'));
				} catch (Exception $e) {
					pf(errorify('Please fix theme: '. $e->getMessage()));
				}
			}
		}
	} else if (isset($_POST['edit'])) {
		$thm = new fud_theme;
		if ($edit == 1) {
			$thm->name = 'default';
		}
		if ($thm->name) {
			$thm->sync((int)$_POST['edit']);
			try {
				compile_all($thm->theme, $thm->lang, $thm->name);
				pf(successify('Theme saved and successfully rebuilt.'));
			} catch (Exception $e) {
				pf(errorify('Please fix theme: '. $e->getMessage()));
			}
		}
		$edit = '';
	} else if (isset($_GET['rebuild']) && ($data = db_saq('SELECT theme, lang, name FROM '. $DBHOST_TBL_PREFIX .'themes WHERE id='. (int)$_GET['rebuild']))) {
		try {
			compile_all($data[0], $data[1], $data[2]);
			pf(successify('Theme '. $data[2] .' ('. $data[1] .') was successfully rebuilt.'));
		} catch (Exception $e) {
			pf(errorify('Please fix theme: '. $e->getMessage()));
		}
	} else if (isset($_GET['edit']) && ($c = db_arr_assoc('SELECT * FROM '. $DBHOST_TBL_PREFIX .'themes WHERE id='. $edit))) {
		foreach ($c as $k => $v) {
			${'thm_'. $k} = $v;
		}
		$thm_t_default = $c['theme_opt'] & 2;
		$thm_enabled = $c['theme_opt'] & 1;
	} else if (isset($_GET['del']) && (int)$_GET['del'] > 1) {
		fud_theme::delete((int)$_GET['del']);
		pf(successify('Theme was successfully deleted.'));
	}

	if (!$edit) {
		// Set default values.
		foreach (get_class_vars('fud_theme') as $k => $v) {
			${'thm_'. $k} = '';
		}

		if (!isset($thm_theme) || empty($thm_theme)) {
			$thm_theme = 'default';
		}
		if (!isset($thm_lang) || empty($thm_lang)) {
			// Get default language from browser.
			$thm_lang = isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2) : 'en';
		}
		if (!isset($thm_pspell_lang) || empty($thm_pspell_lang)) {
			$thm_pspell_lang = $thm_lang;
		}
		$thm_t_default = 0; $thm_enabled = 1;
	}
?>
<h2>Theme Manager</h2>
<div class="tutor">
	Themes combine the forum's source code (logic) with <a href="admtemplates.php?<?php echo __adm_rsid; ?>">templates</a> (for layout) 
	and <a href="admmessages.php?<?php echo __adm_rsid; ?>">message files</a> of a particular language.
	The resulting files are deployed to the forum's web accessable <a href="admbrowse.php?cur=<?php echo urlencode($GLOBALS['WWW_ROOT_DISK'].'/theme'); ?>&amp;<?php echo __adm_rsid; ?>">'theme' directory</a>.
	You can define multiple themes to support different languages and/or layouts.
</div>

<h3><?php echo ($edit && $edit >= 1) ? '<a name="edit">Edit Theme:</a>' : 'Add New Theme:'; ?></h3>
<form id="admthm" action="admthemes.php" method="post">
<?php echo _hs; ?>
<table class="datatable solidtable">
<tr class="field">
	<td>Theme Name:</td>
	<td>
	<?php
		if ($edit && $edit == 1) {
			echo htmlspecialchars($thm_name);
		} else {
			echo '<input type="text" name="thm_name" value="'. htmlspecialchars($thm_name) .'" />';
		}
	?>
	</td>
</tr>

<tr class="field">
	<td valign="top">Template Set:</td>
	<td>
	<select name="thm_theme">
	<?php
		foreach (glob($DATA_DIR .'/thm/*', GLOB_ONLYDIR) as $file) {
			if (!file_exists($file .'/tmpl')) {
				continue;
			}
			$n = basename($file);
			echo '<option value="'. $n .'"'. ($n == $thm_theme ? ' selected="selected"' : '') .'>'. $n .'</option>';
		}
	?></select>
	</td>
</tr>
<tr class="field">
	<td>Language:</td>
	<td>
	<?php
		$selopt = '';
		foreach (glob($DATA_DIR .'/thm/default/i18n/*', GLOB_ONLYDIR) as $file) {
			if (!file_exists($file .'/msg')) {
				continue;
			}
			$langcode = $langname = basename($file);
			if (file_exists($file .'/name')) {
				$langname = trim(file_get_contents($file .'/name'));
			}
			$selopt .= '<option value="'. $langcode .'"'.($thm_lang == $langcode ? ' selected="selected"' : '').'>'. $langname .'</option>';

			$tryloc = file($file .'/locale', FILE_IGNORE_NEW_LINES);
			$tryloc[] = '';	// Also consider the system's default locale.
			$loc = setlocale(LC_ALL, $tryloc);
			$loc = preg_match('/WIN/', PHP_OS) ? utf8_encode($loc) : $loc;	// Windows silliness.

			$locales[$langcode]['locale'] = $loc;
			$locales[$langcode]['pspell_lang'] = $langcode;
		}
		if (!isset($thm_locale) || empty($thm_locale)) {
		    $thm_locale = $locales[$thm_lang]['locale'];
		}

		$cases = '';
		foreach($locales as $k => $v) {
			$cases .= "case '$k': document.forms['admthm'].thm_locale.value = '". $v['locale'] ."'; ";
			$cases .= "document.forms['admthm'].thm_pspell_lang.value='". $v['pspell_lang'] ."'; ";
			$cases .= "break;\n";
		}
	?>
<script type="text/javascript">
/* <![CDATA[ */
function update_locale()
{
	switch (document.forms['admthm'].thm_lang.value) {
		<?php echo $cases; ?>
	}
}
/* ]]> */
</script>

	<select name="thm_lang" onchange="update_locale();">
	<?php echo $selopt; ?>
	</select>
	</td>
</tr>

<tr class="field">
	<td>Locale:</td>
	<td><input type="text" name="thm_locale" value="<?php echo htmlspecialchars($thm_locale); ?>" size="12" /></td>
</tr>

<tr class="field">
	<td>Spell check language:</td>
	<td>
		<input type="text" name="thm_pspell_lang" value="<?php echo htmlspecialchars($thm_pspell_lang); ?>" size="2" />
		[<a href="javascript://" onclick="document.forms['admthm'].thm_pspell_lang.value=''">disable</a>]
	</td>
</tr>

<tr class="field">
	<td colspan="2">
	<label><?php draw_checkbox('thm_t_default', '2', $thm_t_default);?> Default</label>
	<label><?php draw_checkbox('thm_enabled', '1', $thm_enabled); ?> Enabled</label>
	</td>
</tr>
<tr class="fieldaction">
<?php if (!$edit) { ?>
		<td colspan="2" align="right"><input type="submit" name="btn_submit" value="Add" /></td>
<?php } else { ?>
	<td colspan="2" align="right">
		<input type="submit" name="btn_cancel" value="Cancel" />
		<input type="submit" name="btn_update" value="Update" />
	</td>
<?php } ?>
</tr>
</table>
<input type="hidden" name="prevloaded" value="1" />
<input type="hidden" name="edit" value="<?php echo $edit; ?>" />
</form>

<h3>Available Themes:</h3>
<table class="resulttable fulltable">
<thead><tr class="resulttopic">
	<th>Name</th>
	<th>Template Set</th>
	<th>Language</th>
	<th>Locale</th>
	<th>Spell Lang</th>
	<th>Enabled</th>
	<th>Default</th>
	<th>Action</th>
</tr></thead>
<?php
	$i = 0;
	$c = uq('SELECT * FROM '. $DBHOST_TBL_PREFIX .'themes ORDER BY name');
	while ($r = db_rowobj($c)) {
		$i++;
		$bgcolor = ($edit == $r->id) ? ' class="resultrow3"' : (($i%2) ? ' class="resultrow1"' : ' class="resultrow2"');

		echo '<tr'. $bgcolor .'>
			<td>'. htmlspecialchars($r->name) .'</td>
			<td>'. htmlspecialchars($r->theme) .'</td>
			<td>'. htmlspecialchars($r->lang) .'</td>
			<td>'. htmlspecialchars($r->locale) .'</td>
			<td>'. (!$r->pspell_lang ? '<font color="green">disabled</font> ' : htmlspecialchars($r->pspell_lang)) .'</td>
			<td>'. ($r->theme_opt & 1 ? 'Yes' : '<font color="green">No</font>') .'</td>
			<td>'. ($r->theme_opt & 2 ? 'Yes' : '<font color="green">No</font>') .'</td>
			<td nowrap="nowrap"><a href="admthemes.php?'.__adm_rsid.'&amp;edit=' .$r->id .'#edit">Edit</a> | <a href="admthemes.php?'. __adm_rsid .'&amp;rebuild='. $r->id .'">Rebuild Theme</a>
			'. ($r->id != 1 ? ' | <a href="admthemes.php?'. __adm_rsid .'&amp;del=' .$r->id .'">Delete</a>' : '') .'
			</td>
		</tr>';
	}
	unset($c);
?>
</table>
[ <a href="admthemes.php?rebuild_all=1&amp;<?php echo __adm_rsid; ?>">Rebuild all Themes</a> ]

<?php require($WWW_ROOT_DISK .'adm/footer.php'); ?>
