<?php
/**
* copyright            : (C) 2001-2009 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: admplugins.php,v 1.15 2009/11/02 20:35:18 frank Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; version 2 of the License.
**/

	// Enable error reporting before GLOBALS.php to show plugin errors.
	@ini_set('display_errors', 1);
	error_reporting(E_ALL);

	require('./GLOBALS.php');
	fud_use('adm.inc', true);
	fud_use('glob.inc', true);
	fud_use('widgets.inc', true);
	fud_use('plugins.inc', true);
	fud_use('draw_select_opt.inc');

	require($WWW_ROOT_DISK . 'adm/header.php');

	$help_ar = read_help();

	if (isset($_POST['form_posted'])) {
		foreach ($_POST as $k => $v) {
			if (!strncmp($k, 'FUD_OPT_3', 9)) {
				$NEW_FUD_OPT_3 = (int) $v;
			}
		}

		if (($NEW_FUD_OPT_3 ^ $FUD_OPT_3) & (4194304)) {
			if (!($NEW_FUD_OPT_3 & 4194304)) {
				$FUD_OPT_3 &= ~4194304;
			} else {
				$FUD_OPT_3 |= 4194304;
			}
			change_global_settings(array('FUD_OPT_3' => $FUD_OPT_3));
			$GLOBALS['FUD_OPT_3'] = $FUD_OPT_3;
		}
	}

	$prev_plugins = plugin_load_from_cache();
	if (isset($_POST['plugin_state'])) {
		if (isset($_POST['plugins'])) {
			plugin_rebuild_cache($_POST['plugins']);
		} else {
			plugin_rebuild_cache(NULL);
		}
	}
	$plugins = plugin_load_from_cache();

	// Install new plugins.
	while (list($key, $val) = @each($plugins)) {
		if (! in_array($val, $prev_plugins)) {
			echo "Install/activate plugin: ". $val ."...<br />\n";
			$enable_func = substr($val, 0, strrpos($val, '.')) .'_enable';
			if ((include_once($PLUGIN_PATH.'/'.$val)) && function_exists($enable_func)) {
				$enable_func(); 
			}
		}
	}
	// Deinstall plugins.
	while (list($key, $val) = @each($prev_plugins)) {
		if (! in_array($val, $plugins)) {
			echo "Deinstall/deactivate plugin: ". $val ."...<br />\n";
			$disable_func = substr($val, 0, strrpos($val, '.')) .'_disable';
			if ((include_once($PLUGIN_PATH.'/'.$val)) && function_exists($disable_func)) {
				$disable_func();
			}
		}
	}

	// Configure a plugin.
	if (isset($_GET['config']) || isset($_POST['config'])) {
		$plugin = isset($_GET['config']) ? $_GET['config'] : $_POST['config'];
		$func_base = substr($plugin, 0, strrpos($plugin, '.'));
		if ( strpos($func_base, '/') ) {
			$func_base = substr($func_base, strpos($func_base, '/')+1);
		}
		include_once($PLUGIN_PATH.'/'.$plugin);

		$info_func = $func_base . '_info';
		if (function_exists($info_func)) {
			$info = $info_func();
		}
		if (isset($info['name'])) {
			echo '<h2>Plugin: '.$info['name'].'</h2>';
		} else {
			echo '<h2>Plugin: '.$plugin.'</h2>';
		}
		if (isset($info['desc'])) {
			echo '<div class="tutor">'.$info['desc'].'</div><br />';
		}

		echo '<fieldset><legend>Meta-information:</legend>';
		echo '<b>Plugin file:</b> '.$plugin.'<br />';
		echo '<b>Last modified:</b> '.date("d F Y H:i:s", filemtime($PLUGIN_PATH.'/'.$plugin)).'<br />';
		if (isset($info['author'])) {
			echo '<b>Author:</b> '.$info['author'].'<br />';
		}
		if (isset($info['version'])) {
			echo '<b>Version:</b> '.$info['version'].'<br />';
		}
		echo '<b>Status:</b> '. (in_array($plugin, $plugins) ? 'Enabled' : 'Disabled') .'<br />';
		if (isset($info['help'])) {
			echo '<div style="font-size: small; float:right;">[ <a href="'.$info['help'].'">Plugin documentation</a> ]</div>';
		} else {
			echo '<div style="font-size: small; float:right;">[ <a href="http://cvs.prohost.org/index.php/'.$func_base.'.plugin">Documentation on Wiki</a> ]</div>';
		}
		echo '</fieldset>';

		$config_func = $func_base . '_config';
		if (function_exists($config_func)) {
			echo '<form method="post" action="admplugins.php" autocomplete="off">';
			echo '<fieldset><legend>Configuration:</legend>';
			echo _hs;
			echo '<input type="hidden" name="config" value="'.$plugin.'" />';
			$config_func();
			echo '<input type="submit" name="Set" value="Change settings" />';
			echo '</fieldset></form>';
		}

		echo '<div style="float:right;">[ <a href="admplugins.php?'.__adm_rsid.'">Return to Plugin Manager &raquo;</a> ]</div>';

		echo '</td></tr></table></body></html>';	// Standard footer not applicable here.
		exit;
	}

?>
<h2>Plugin Manager</h2>
<form method="post" action="admplugins.php" autocomplete="off">
<?php echo _hs ?>
<table class="datatable solidtable">
<?php
	print_bit_field('Plugins Enabled', 'PLUGINS_ENABLED');
?>
<tr class="fieldaction"><td colspan="2" align="left"><input type="submit" name="btn_submit" value="Set" /></td></tr>
</table>
<input type="hidden" name="form_posted" value="1" />
</form>

<h3>Available plugins:</h3>
<p>Click on any of the below plugin names for more info and configuration options:</p>
<form method="post" action="admplugins.php" autocomplete="off">
<?php echo _hs ?>
<table class="datatable solidtable">
<tr class="fieldtopic"><td><b>Plugin name</b></td><td><b>Plugin activated?</b></td></tr>
<?php
foreach (glob("$PLUGIN_PATH/*") as $file) {
	if (is_dir($file)) {	// Check for plugins in subdirectories
		$dir = basename($file);
		foreach (glob("$PLUGIN_PATH/$dir/*") as $dirfile) {
			if (!preg_match("/\.plugin$/", $dirfile)) continue;	// Not a plugin file
			$plugin_files[] = $dir.'/'.basename($dirfile);
		}
	}
	if (!preg_match("/\.plugin$/", $file)) continue;	// Not a plugin file
	$plugin_files[] = basename($file);
}

$disabled = ($GLOBALS['FUD_OPT_3'] & 4194304) ? '' : 'disabled="disabled"';
foreach ($plugin_files as $plugin) {	
	$checked = in_array($plugin, $plugins) ? 'checked="checked"' : '';
?>
<tr class="field">
  <td><a href="admplugins.php?config=<?php echo urlencode($plugin).'&amp;'.__adm_rsid.'">'.$plugin; ?></a></td>
  <td class="center"><input type="checkbox" name="plugins[]" value="<?php echo $plugin; ?>" <?php echo $checked.' '.$disabled; ?> /></td>
</tr>
<?php } ?> 
<tr class="fieldtopic center">
  <td>&nbsp;</td>
  <td><input type="submit" name="plugin_state" value="Change state" <?php echo $disabled; ?> /></td>
</tr>
</table>
</form>

<br />
<table class="tutor" width="99%"><tr><td>
Plugins are stored in: <?php echo realpath($PLUGIN_PATH); ?><br />
To add new plugins, <b><a href="admbrowse.php?down=1&amp;cur=<?php echo urlencode($PLUGIN_PATH); ?>&amp;<?php echo __adm_rsid; ?>">upload</a></b> them to this directory and activate them on this page. Plugins may also be placed into subdirectories.
</td></tr></table>

<?php require($WWW_ROOT_DISK . 'adm/footer.php'); ?>
