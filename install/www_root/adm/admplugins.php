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

	// Enable error reporting before GLOBALS.php to show plugin errors.
	@ini_set('display_errors', 1);
	error_reporting(E_ALL);

	require('./GLOBALS.php');
	fud_use('adm.inc', true);
	fud_use('glob.inc', true);
	fud_use('widgets.inc', true);
	fud_use('plugins.inc', true);
	fud_use('draw_select_opt.inc');

	require($WWW_ROOT_DISK .'adm/header.php');

	$help_ar = read_help();

	// Enable or disable PLUGINS_ENABLED.
	if (isset($_POST['form_posted'])) {
		if (isset($_POST['FUD_OPT_3_PLUGINS_ENABLED'])) {
			if ($_POST['FUD_OPT_3_PLUGINS_ENABLED'] & 4194304) {
				$FUD_OPT_3 |= 4194304;
				echo successify('Plugin support was successfully enabled.<br />Note that you can now activate plugins below.');
			} else {
				$FUD_OPT_3 &= ~4194304;
				echo successify('Plugin support was successfully disabled.');
			}
			change_global_settings(array('FUD_OPT_3' => $FUD_OPT_3));
		}
	}

	// Install new plugins.
	$prev_plugins = plugin_load_from_cache();
	if (isset($_POST['plugin_state'], $_POST['plugins'])) {
		while (list($key, $val) = @each($_POST['plugins'])) {
			if (! in_array($val, $prev_plugins)) {
				$enable_func = substr($val, 0, strrpos($val, '.')) .'_enable';
				if ( strpos($enable_func, '/') ) {
					$enable_func = substr($enable_func, strpos($enable_func, '/')+1);
				}
				if ((include_once($PLUGIN_PATH .'/'. $val)) && function_exists($enable_func)) {
					$err = $enable_func();
					if ($err) {
						unset($_POST['plugins'][$key]);
						echo errorify('Plugin '. $val .' cannot activate: '. $err);
						continue;
					}
				}
				echo successify('Plugin '. $val .' was successfully installed and activated.');
			}
		}
		plugin_rebuild_cache($_POST['plugins']);
	}

	// Clear the plugin cache.
	if (isset($_POST['plugin_state']) && !isset($_POST['plugins'])) {
			plugin_rebuild_cache(NULL);
	}

	// Deinstall plugins.
	$plugins = plugin_load_from_cache();
	while (list($key, $val) = @each($prev_plugins)) {
		if (! in_array($val, $plugins)) {
			$disable_func = substr($val, 0, strrpos($val, '.')) .'_disable';
			if ( strpos($disable_func, '/') ) {
				$disable_func = substr($disable_func, strpos($disable_func, '/')+1);
			}
			if ((include_once($PLUGIN_PATH.'/'.$val)) && function_exists($disable_func)) {
				$err = $disable_func();
				if ($err) {
					echo errorify('Plugin '. $val .' uninstall error: '. $err);
				}
			}
			echo successify('Plugin '. $val .' was successfully deinstalled and deactivated.');
		}
	}

	// Show plugin info and configuration options.
	if (isset($_GET['config']) || isset($_POST['config'])) {
		$plugin = isset($_GET['config']) ? $_GET['config'] : $_POST['config'];
		$func_base = substr($plugin, 0, strrpos($plugin, '.'));
		if ( strpos($func_base, '/') ) {
			$func_base = substr($func_base, strpos($func_base, '/')+1);
		}
		include_once($PLUGIN_PATH.'/'.$plugin);

		// Process info hook.
		$info_func = $func_base . '_info';
		if (function_exists($info_func)) {
			$info = $info_func();
		}
		if (isset($info['name'])) {
			echo '<h2>Plugin: '.$info['name'].'</h2>';
		} else {
			echo '<h2>Plugin: '.$plugin.'</h2>';
		}

		echo '<fieldset class="tutor"><legend>Meta-information:</legend>';
		echo '<table>';
		echo '<tr><td><b>Plugin file:</b></td><td>'. $plugin .'</td></tr>';
		echo '<tr><td><b>Last modified:</b></td><td>'.date('d F Y H:i:s', filemtime($PLUGIN_PATH.'/'.$plugin)).'</td></tr>';
		if (isset($info['author'])) {
			echo '<tr><td><b>Author:</b></td><td>'.$info['author'].'</td></tr>';
		}
		if (isset($info['version'])) {
			echo '<tr><td><b>Version:</b></td><td>'.$info['version'].'</td></tr>';
		}
		echo '<tr><td><b>Status:</b></td><td>'. (in_array($plugin, $plugins) ? 'Enabled' : 'Disabled') .
		                        (($FUD_OPT_3 & 4194304) ? '' : ' (plugin system is disabled)') .'</td></tr>';
		if (isset($info['desc'])) {
			echo '<tr><td valign="top"><b>Description:</b></td><td>'. $info['desc'] .'</td></tr>';
		}
		echo '<tr><td colspan="2">';
		if (isset($info['help'])) {
			echo '<div style="font-size:small; float:right;">[ <a href="'.$info['help'].'">Plugin documentation</a> ]</div>';
		} else {
			echo '<div style="font-size:small; float:right;">[ <a href="http://cvs.prohost.org/index.php/'.$func_base.'.plugin">Documentation on Wiki</a> ]</div>';
		}
		echo '</td></tr></table>';
		echo '</fieldset>';
		echo '<br />';

		// Process config hook.
		$config_func = $func_base . '_config';
		if (function_exists($config_func)) {
			echo '<form method="post" action="admplugins.php" autocomplete="off">';
			echo '<fieldset class="tutor"><legend>Configuration:</legend>';
			echo _hs;
			echo '<input type="hidden" name="config" value="'.$plugin.'" />';
			$config_func();
			echo '<input type="submit" name="Set" value="Change settings" />';
			echo '</fieldset></form>';
		}

		echo '<br /><div style="float:right;">[ <a href="admplugins.php?'.__adm_rsid.'">Return to Plugin Manager &raquo;</a> ]</div>';

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
<tr class="fieldaction"><td colspan="2" align="right"><input type="submit" name="btn_submit" value="Set" /></td></tr>
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
	if (is_dir($file)) {	// Check for plugins in subdirectories.
		$dir = basename($file);
		foreach (glob("$PLUGIN_PATH/$dir/*") as $dirfile) {
			if (!preg_match('/\.plugin$/', $dirfile)) continue;	// Not a plugin file.
			$plugin_files[] = $dir.'/'.basename($dirfile);
		}
	}
	if (!preg_match('/\.plugin$/', $file)) continue;	// Not a plugin file.
	$plugin_files[] = basename($file);
}

$disabled = ($FUD_OPT_3 & 4194304) ? '' : 'disabled="disabled"';
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

<?php require($WWW_ROOT_DISK .'adm/footer.php'); ?>
