<?php
/***************************************************************************
* copyright            : (C) 2001-2003 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: admthemes.php,v 1.39 2003/11/08 00:37:37 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it 
* under the terms of the GNU General Public License as published by the 
* Free Software Foundation; either version 2 of the License, or 
* (at your option) any later version.
***************************************************************************/

function get_func_usage(&$toks)
{
	foreach ($toks as $k => $tok) {
		if (is_array($tok) && $tok[0] == T_FUNCTION) {
			$fc = is_array($toks[$k+2]) ? $toks[$k+2][1] : $toks[$k+3][1];
			$func[$fc] = -1;
			$func_pos[$fc] = $k;
		}
	}

	if (!isset($func)) {
		return 0;
	}

	foreach ($toks as $tok) {
		if (is_array($tok) && $tok[0] == T_STRING && isset($func[$tok[1]])) {
			$func[$tok[1]]++;
		}
	}

	krsort($func);

	$job = 0;
	foreach ($func as $k => $v) {
		if ($v) {
			break;
		}
		$job = 1;
		$i = 0;
		$j = $func_pos[$k];
		$n = count($toks);
		for ($j; $j < $n; $j++) {
			if ($toks[$j] === '{') {
				++$i;
			} else if ($toks[$j] === '}') {
				--$i;
				if ($i < 1) {
					break;
				}
			}
			unset($toks[$j]);
		}
		unset($toks[$j]);
	}

	return $job;
}

function clean_code($path, $toks)
{
	$old_size = filesize($path);
	$r = '';
	foreach ($toks as $k => $tok) {
		if (is_array($tok)) {
			switch ($tok[0]) {
				case T_COMMENT:
				case T_ML_COMMENT:
				case T_WHITESPACE:
					break;
				case T_FUNCTION:
				case T_CLASS:
				case T_NEW:
				case T_ECHO:
				case T_RETURN:
					$r .= $tok[1].' ';
					break;
				case T_AS:
				case T_LOGICAL_OR:
				case T_EXTENDS:
					$r .= ' '.$tok[1].' ';
					break;
				default:
					$r .= $tok[1];
			}
		} else {
			$r .= $tok;
		}
	}

	if (!($fp = fopen($path, 'w'))) {
		exit("unable to write to ".$path."<br>\n");
	}
	fwrite($fp, $r);
	fclose($fp);

	$saved = ($old_size - strlen($r));

	return $saved;
}
	$is_tok = extension_loaded('tokenizer');

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
		$ts = $_POST['base_template_set'] == 'path_info' ? 'path_info/' : 'default/';

		fudcopy($root . $ts, $root_nn, '!.*!', true);
		umask($u);
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
	} else if (isset($_GET['optimize']) && $is_tok && ($t_name = q_singleval('SELECT name FROM '.$DBHOST_TBL_PREFIX.'themes WHERE id='.(int)$_GET['optimize']))) {
		/* optimize *.php files */
		$path = $WWW_ROOT_DISK . 'theme/' . $t_name;
		$dir = opendir($path);
		$path .= '/';
		readdir($dir); readdir($dir);
		while ($f = readdir($dir)) {
			if (@is_file($path . $f) && substr($f, -4) == '.php') {
				$toks = token_get_all(file_get_contents($path . $f));
				while (get_func_usage($toks));
				clean_code($path . $f, $toks);
			}
		}
		closedir($dir);

		/* optimize *.inc files */
		$path = $WWW_ROOT_DISK . 'include/theme/' . $t_name;
		$dir = opendir($path);
		$path .= '/';
		readdir($dir); readdir($dir);
		while ($f = readdir($dir)) {
			if (@is_file($path . $f) && substr($f, -4) == '.inc') {
				clean_code($path . $f, token_get_all(file_get_contents($path . $f)));
			}
		}
		closedir($dir);
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
<h2>Theme Management</h2>

<form name="admthm" action="admthemes.php" method="post">
<?php echo _hs; ?>
<table border=0 cellspacing=1 cellpadding=3>
<tr bgcolor="#bff8ff">
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

<tr bgcolor="#bff8ff">
	<td valign=top>Template Set:</td>
	<td>
	<select name="thm_theme">
	<?php
		$dp = opendir($DATA_DIR . '/thm');
		readdir($dp); readdir($dp);
		while ($de = readdir($dp)) {
			$dr = $DATA_DIR . '/thm/' . $de;
			if ($de == 'CVS' || !@is_dir($dr) || !@is_dir($dr.'/tmpl')) {
				continue;
			}
			echo '<option'.($thm_theme == $de ? ' selected' : '').'>'.$de.'</option>';
		}
		closedir($dp);
	?></select>
	</td>
</tr>
<tr bgcolor="#bff8ff">
	<td>Language</td>
	<td>
	<?php
		$dp = opendir($DATA_DIR . '/thm/default/i18n');
		readdir($dp); readdir($dp);
		$selopt = '';
		if (!$thm_lang) {
			$thm_lang = 'english';
		}
		while ($de = readdir($dp)) {
			$dr = $DATA_DIR . '/thm/default/i18n/' . $de;
			if ($de == 'CVS' || !@is_dir($dr)) {
				continue;
			}
			$selopt .= '<option'.($thm_lang == $de ? ' selected' : '').'>'.$de.'</option>';
			$locales[$de]['locale'] = trim(file_get_contents($dr . '/locale'));
			$pspell_file = $dr . '/pspell_lang';
			$locales[$de]['pspell_lang'] = @file_exists($pspell_file) ? trim(file_get_contents($pspell_file)) : 'en';
		}
		closedir($dp);

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

<tr bgcolor="#bff8ff">
	<td>Locale:</td>
	<td><input type="text" name="thm_locale" value="<?php echo htmlspecialchars($thm_locale); ?>" size=7></td>
</tr>

<tr bgcolor="#bff8ff">
	<td>pSpell Language:</td>
	<td>
		<input type="text" name="thm_pspell_lang" value="<?php echo htmlspecialchars($thm_pspell_lang); ?>" size=4>
		[<a href="javascript://" onClick="javascript: document.admthm.thm_pspell_lang.value=''">disable</a>]
	</td>
</tr>

<tr bgcolor="#bff8ff">
	<td colspan=2>
	<?php draw_checkbox('thm_t_default', '2', $thm_t_default);?> Default <?php draw_checkbox('thm_enabled', '1', $thm_enabled); ?> Enabled
	</td>
</tr>
<tr bgcolor="#bff8ff">
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
<table border=0 cellspacing=1 cellpadding=3>
<tr bgcolor="#bff8ff"><td colspan=2>Create New Template Set</td></tr>
<tr bgcolor="#bff8ff">
	<td>Base Template Set:</td>
	<td>
	<select name="base_template_set">
	<option value="default">Default</option>
	<option value="path_info">Path Info</option>
	</select></td>
</tr>
<tr bgcolor="#bff8ff">
	<td>Name</td>
	<td><input type="text" name="newname"></td>
</tr>
<tr bgcolor="#bff8ff">
	<td colspan=2 align=right><input type="submit" name="btn_submit" value="Create"></td>
</tr>
</table>
<?php echo _hs; ?>
</form>

<table border=0 cellspacing=0 cellpadding=3>
<tr bgcolor="#e5ffe7">
	<td>Name</td>
	<td>Theme</td>
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
			$bgcolor = ' bgcolor="#ffb5b5"';
		} else {
			$bgcolor = ($i++%2) ? ' bgcolor="#fffee5"' : '';
		}

		echo '<tr '.$bgcolor.'>
			<td>'.htmlspecialchars($r->name).'</td>
			<td>'.htmlspecialchars($r->theme).'</td>
			<td>'.htmlspecialchars($r->lang).'</td>
			<td>'.htmlspecialchars($r->locale).'</td>
			<td>'.(!$r->pspell_lang ? '<font color="green">disabled</font> ' : htmlspecialchars($r->pspell_lang)).'</td>
			<td>'.($r->theme_opt & 1 ? 'Yes' : '<font color="green">No</font>').'</td>
			<td>'.($r->theme_opt & 2 ? 'Yes' : '<font color="green">No</font>').'</td>
			<td nowrap>[<a href="admthemes.php?'._rsidl.'&edit='.$r->id.'">Edit</a>] [<a href="admthemes.php?'._rsidl.'&rebuild='.$r->id.'">Rebuild Theme</a>]
			'.($is_tok ? '[<a href="admthemes.php?'._rsidl.'&optimize='.$r->id.'">Optimize Theme</a>]' : '').'
			'.($r->id != 1 ? '[<a href="admthemes.php?'._rsid.'&del='.$r->id.'">Delete</a>]' : '').'
			</td>
		</tr>';
	}
?>
</table>
<?php require($WWW_ROOT_DISK . 'adm/admclose.html'); ?>