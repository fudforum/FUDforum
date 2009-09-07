<?php
/**
* copyright            : (C) 2001-2009 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: admthemes.php,v 1.82 2009/09/07 15:49:52 frank Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; version 2 of the License.
**/

	require('./GLOBALS.php');
	fud_use('widgets.inc', true);
	fud_use('adm.inc', true);
	fud_use('compiler.inc', true);
	fud_use('theme.inc', true);

	require($WWW_ROOT_DISK . 'adm/admpanel.php');

	$edit = isset($_GET['edit']) ? (int)$_GET['edit'] : (isset($_POST['edit']) ? (int)$_POST['edit'] : '');

	if (isset($_GET['rebuild_all'])) {
		$r = q('SELECT theme, lang, name FROM '.$DBHOST_TBL_PREFIX.'themes');
		while (($data = db_rowarr($r))) {
			echo '<font color="green">Rebuilding theme '. $data[2] . ' ('. $data[1] .')...</font><br />';
			compile_all($data[0], $data[1], $data[2]);
		}
		unset($r);
	}

	if (isset($_POST['thm_theme']) && @file_exists($DATA_DIR.'thm/'.$_POST['thm_theme'].'/.path_info') && !($FUD_OPT_2 & 32768)) {
		unset($_POST['edit'], $_POST['thm_theme']);
		echo '<h3 class="alert">You need to enable PATH_INFO support in the <a href="admglobal.php?'.__adm_rsid.'">Global Settings Manager</a> before using a path_info theme.</h3>';
	}

	if (isset($_POST['thm_theme']) && !$edit) {
		$thm = new fud_theme;
		if ($thm->name) {
			$thm->add();
			compile_all($thm->theme, $thm->lang, $thm->name);
			echo '<font color="green">Theme '.$thm->name.' was successfully created.</font>';
		}
	} else if (isset($_POST['edit'])) {
		$thm = new fud_theme;
		if ($edit == 1) {
			$thm->name = 'default';
		}
		if ($thm->name) {
			$thm->sync((int)$_POST['edit']);
			compile_all($thm->theme, $thm->lang, $thm->name);
		}
		$edit = '';
		echo '<font color="green">Theme saved and successfully rebuilt.</font>';
	} else if (isset($_GET['rebuild']) && ($data = db_saq('SELECT theme, lang, name FROM '.$DBHOST_TBL_PREFIX.'themes WHERE id='.(int)$_GET['rebuild']))) {
		echo '<font color="green">Rebuilding theme '. $data[2] . ' ('. $data[1] .')...</font>';
		compile_all($data[0], $data[1], $data[2]);
	} else if (isset($_GET['edit']) && ($c = db_arr_assoc('SELECT * FROM '.$DBHOST_TBL_PREFIX.'themes WHERE id='.$edit))) {
		foreach ($c as $k => $v) {
			${'thm_'.$k} = $v;
		}
		$thm_t_default = $c['theme_opt'] & 2;
		$thm_enabled = $c['theme_opt'] & 1;
	} else if (isset($_GET['del']) && (int)$_GET['del'] > 1) {
		fud_theme::delete((int)$_GET['del']);
		echo '<font color="green">Theme successfully deleted</font>';
	}

	if (!$edit) {
		foreach (get_class_vars('fud_theme') as $k => $v) {
			${'thm_'.$k} = '';
		}
		if (strncasecmp('win', PHP_OS, 3)) {	// Not Windows
			$thm_locale = 'en_US.UTF-8';
		} else {
			$thm_locale = 'english';			// No UTF-8 locales on Windows
		}
		$thm_pspell_lang = isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2) : 'en';
		$thm_t_default = 0; $thm_enabled = 1;
	}
?>
<h2>Theme Manager</h2>
<div class="tutor">
	Themes combine a <a href="admtemplates.php?<?php echo __adm_rsid; ?>">template set</a> (layout) 
	with <a href="admmessages.php?<?php echo __adm_rsid; ?>">message files</a> of a particular language.
	The resulting files are deployed to the forum's web accessable <a href="admbrowse.php?cur=<?php echo urlencode($GLOBALS['WWW_ROOT_DISK'].'/theme'); ?>&<?php echo __adm_rsid; ?>">'theme' directory</a>.
	You can define multiple themes to support different languages and/or layouts.
</div>

<?php
    if ($edit && $edit == 1) {
		echo '<h3>Edit Theme:</h3>';
	} else {
		echo '<h3>Create New Theme:</h3>';
	}
?>
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
		echo '<input type="text" name="thm_name" value="'.htmlspecialchars($thm_name).'" />';
	}
?>
	</td>
</tr>

<tr class="field">
	<td valign="top">Template Set:</td>
	<td>
	<select name="thm_theme">
	<?php
		if (!$thm_theme) {
			$thm_theme = 'default';
		}
		foreach (glob($DATA_DIR.'/thm/*', GLOB_ONLYDIR) as $file) {
			if (!file_exists($file . '/tmpl')) {
				continue;
			}
			$n = basename($file);
			echo '<option value="'.$n.'"'.($n == $thm_theme ? ' selected="selected"' : '').'>'.$n.'</option>';
		}
	?></select>
	</td>
</tr>
<tr class="field">
	<td>Language:</td>
	<td>
	<?php
		if (!$thm_lang) {
			$thm_lang = 'english';
		}
		$selopt = '';
		foreach (glob($DATA_DIR.'/thm/default/i18n/*', GLOB_ONLYDIR) as $file) {
			if (!file_exists($file . '/msg')) {
				continue;
			}
			$n = basename($file);
			$selopt .= '<option'.($thm_lang == $n ? ' selected="selected"' : '').'>'.$n.'</option>';
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
<script type="text/javascript">
/* <![CDATA[ */
function update_locale()
{
	switch (document.admthm.thm_lang.value) {
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
	<td>pSpell Language:</td>
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
<br />

<h3>Available Themes:</h3>
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
	$c = uq('SELECT * FROM '.$DBHOST_TBL_PREFIX.'themes ORDER BY name');
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
			<td nowrap="nowrap">[<a href="admthemes.php?'.__adm_rsid.'&amp;edit='.$r->id.'">Edit</a>] [<a href="admthemes.php?'.__adm_rsid.'&amp;rebuild='.$r->id.'">Rebuild Theme</a>]
			'.($r->id != 1 ? '[<a href="admthemes.php?'.__adm_rsid.'&amp;del='.$r->id.'">Delete</a>]' : '').'
			</td>
		</tr>';
	}
	unset($c);
?>
</table>
[ <a href="admthemes.php?rebuild_all=1&amp;<?php echo __adm_rsid; ?>">Rebuild all Themes</a> ]

<?php require($WWW_ROOT_DISK . 'adm/admclose.html'); ?>
