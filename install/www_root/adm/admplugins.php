<?php
/**
* copyright            : (C) 2001-2011 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id$
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; version 2 of the License.
**/

/** Compile themes to apply COMPILER_* hooks. */
function compile_themes()
{
	$r = q('SELECT theme, lang, name FROM '. $GLOBALS['DBHOST_TBL_PREFIX'] .'themes WHERE '. q_bitand('theme_opt', 1) .' = 1');
	while (($data = db_rowarr($r))) {
		try {
			compile_all($data[0], $data[1], $data[2]);
		} catch (Exception $e) {
			pf(errorify('Unable to rebuild theme '. $data[2] .': '. $e->getMessage()));
		}
	}
	pf(successify('Themes were rebuilt.'));
}

/* main */
	// Enable error reporting before GLOBALS.php to show plugin errors.
	@ini_set('display_errors', 1);
	error_reporting(E_ALL);

	require('./GLOBALS.php');
	fud_use('adm.inc', true);
	fud_use('glob.inc', true);
	fud_use('widgets.inc', true);
	fud_use('draw_select_opt.inc');
	fud_use('plugins.inc', true);	// Run-time component.
	fud_use('plugin_adm.inc', true);	// Admin class.
	fud_use('compiler.inc', true);

	$tbl = $GLOBALS['DBHOST_TBL_PREFIX'];
	$help_ar = read_help();

	// AJAX call to reorder plugins.
	if (!empty($_POST['ajax']) && $_POST['ajax'] == 'reorder') {
		$new_order = 1;
		foreach ($_POST['order'] as $id) {
			q('UPDATE '. $tbl .'plugins SET priority = '. $new_order++ .' WHERE id = '. $id);
		}
		fud_plugin::rebuild_cache();
		exit('Plugins successfully reordered.');	// End AJAX call.
	}

	require($WWW_ROOT_DISK .'adm/header.php');

	// Enable or disable plugin system (PLUGINS_ENABLED setting).
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

	// Toggle a plugin's state (from plugin config screen).
	if (isset($_GET['state'])) {
		$plugin = $_GET['config'];
		if ($plugin_id = fud_plugin::is_active($plugin)) {
			$_GET['deact'] = $plugin;
		} else {
			$_GET['act'] = $plugin;
		}
	}

	// Activate a single plugin.
	if (isset($_GET['act'])) {
		$plugin = $_GET['act'];
		list($ok, $msg) = fud_plugin::activate($plugin);
		if ($ok) {
			echo $msg ? successify($msg) : successify('Plugin '. $plugin .' was successfully installed and activated.');
		} else {
			echo errorify('Cannot activate '. $plugin .': '. $msg);
		}
	}

	// Deactivate a single plugin.
	if (isset($_GET['deact'])) {
		$plugin = $_GET['deact'];
		list($ok, $msg) = fud_plugin::deactivate($plugin);
		if ($ok) {
			echo $msg ? successify($msg) : successify('Plugin '. $plugin .' was successfully deinstalled and deactivated.');
		} else {
			echo errorify('Cannot deactivate '. $plugin .': '. $msg);
		}
	}
	
	// Activate a group of plugins.
	if (isset($_POST['activate_plugins'], $_POST['plugins'])) {
		while (list($key, $plugin) = @each($_POST['plugins'])) {
			list($ok, $msg) = fud_plugin::activate($plugin);
			if ($ok) {
				echo $msg ? successify($msg) : successify('Plugin '. $plugin .' was successfully installed and activated.');
			} else {
				echo errorify('Cannot activate '. $plugin .': '. $msg);
			}
		}
	}

	// Show plugin info and configuration options.
	if (isset($_GET['config']) || isset($_POST['config'])) {
		$plugin = isset($_GET['config']) ? $_GET['config'] : $_POST['config'];
		$func_base = substr($plugin, 0, strrpos($plugin, '.'));
		if ( strpos($func_base, '/') ) {
			$func_base = substr($func_base, strpos($func_base, '/')+1);
		}
		include_once($PLUGIN_PATH .'/'. $plugin);

		// Process info hook.
		$info_func = $func_base .'_info';
		if (function_exists($info_func)) {
			$info = $info_func();
		}
		// Note H3 instead of H2 to disable context sensitive help.
		if (isset($info['name'])) {
			echo '<h3>Plugin: '. $info['name'] .'</h3>';
		} else {
			echo '<h3>Plugin: '. $plugin .'</h3>';
		}

		echo '<fieldset><legend>Meta-information:</legend>';
		if (isset($info['desc'])) {
			echo '<p>'. $info['desc'] .'</p>';
		}
		echo '<table>';
		echo '<tr><td><b>Plugin file:</b></td><td>'. $plugin .'</td></tr>';
		echo '<tr><td><b>Last modified:</b></td><td>'. date('d F Y H:i:s', filemtime($PLUGIN_PATH .'/'. $plugin)) .'</td></tr>';
		if (isset($info['author'])) {
			echo '<tr><td><b>Author:</b></td><td>'. $info['author'] .'</td></tr>';
		}
		if (isset($info['version'])) {
			echo '<tr><td><b>Version:</b></td><td>'. $info['version'] .'</td></tr>';
		}
		echo '<tr><td><b>Status:</b></td><td>';
		if ($FUD_OPT_3 & 4194304) {
			$enabled = fud_plugin::is_active($plugin);
			echo $enabled ? '<font color="green" />Enabled</font>' : '<font color="red" />Disabled</font>';
			echo '&nbsp;<span style="font-size:small;">[ <a href="admplugins.php?state='. $enabled .'&amp;config='. urlencode($plugin) .'&amp;'. __adm_rsid .'" />Toggle</a> ]</span>';
		} else {
			echo '<font color="red">Plugin system is disabled!</font>';
		}
		echo '</td></tr>';
		echo '</table>';
		echo '<span style="font-size:small; float:right;">';
		echo '[ <a href="admbrowse.php?view=1&amp;dest='. $func_base .'.plugin&amp;cur='. urlencode(dirname($PLUGIN_PATH.$plugin)) .'&amp;'. __adm_rsid .'">View code</a> ]';
		if (isset($info['help'])) {
			echo ' [ <a href="'. $info['help'] .'">Plugin documentation</a> ]';
		} else {
			echo ' [ <a href="http://cvs.prohost.org/index.php/'. $func_base .'.plugin">Documentation on Wiki</a> ]';
		}
		echo '</span><br />';
		echo '</fieldset>';

		// Process config hook.
		$config_func = $func_base .'_config';
		if (function_exists($config_func)) {
			echo '<form method="post" action="admplugins.php" autocomplete="off">';
			echo '<fieldset><legend>Configuration:</legend>';
			echo _hs;
			echo '<input type="hidden" name="config" value="'.$plugin.'" />';
			$config_func();

			// Recompile themes if the plugin requested it.
			if (defined('REBUILD_THEMES')) {
				compile_themes();
			}
			echo '<input type="submit" name="Set" value="Configure" />';
			echo '</fieldset></form>';
		}

		echo '<br /><div style="float:right;">[ <a href="admplugins.php?'. __adm_rsid .'">Return to Plugin Manager &raquo;</a> ]</div>';
		require($WWW_ROOT_DISK .'adm/footer.php');
		exit;
	}

	// Recompile themes if a plugin requested it.
	if (defined('REBUILD_THEMES')) {
		compile_themes();
	}
?>
<h2>Plugin Manager</h2>
<div class="tutor">
To add new plugins, <b><a href="admbrowse.php?down=1&amp;cur=<?php echo urlencode($PLUGIN_PATH); ?>&amp;<?php echo __adm_rsid; ?>">upload</a></b> them to this directory and activate them on this page. Plugins may also be placed into subdirectories.
Plugins are stored in: <?php echo realpath($PLUGIN_PATH); ?><br />
</div>
<br />

<fieldset><legend>Settings:</legend>
<form method="post" action="admplugins.php" autocomplete="off">
<?php echo _hs ?>
<table class="datatable solidtable">
<?php
	print_bit_field('Plugins Enabled', 'PLUGINS_ENABLED');
?>
<tr class="fieldaction"><td colspan="2" align="right"><input type="submit" name="btn_submit" value="Set" /></td></tr>
</table>
</fieldset>
<input type="hidden" name="form_posted" value="1" />
</form>

<?php if ($FUD_OPT_3 & 4194304) { /* Hide if plugin system is disabled. */ ?>
<h3>Enabled plugins:</h3>
<p>The below plugins are active and listed in firing order. Drag and drop them to change the order.</p>
<table class="resulttable fulltable">
<thead><tr class="resulttopic">
	<th>Plugin</th><th>Action</th>
</tr></thead>
<tbody id="sortable">
<?php
	$i = 0;
	$c = uq('SELECT id, name, priority FROM '. $tbl .'plugins ORDER BY priority');
	while ($r = db_rowobj($c)) {
		$i++;
		$bgcolor = ($i%2) ? ' class="resultrow1"' : ' class="resultrow2"';
		echo '<tr id="order_'. $r->id .'"'. $bgcolor .' title="'. htmlspecialchars($r->name) .'">
		      <td><span class="ui-icon ui-icon-arrowthick-2-n-s"></span><a href="admplugins.php?config='. urlencode($r->name) .'&amp;'. __adm_rsid .'" title="Configure plugin">'. $r->name .'</a></td><td><a href="admplugins.php?deact='. $r->name .'&amp;'. __adm_rsid .'">Deactivate</a></td></tr>';
	}
	unset($c);
	if (!$i) {
		echo '<tr class="field"><td colspan="3"><center>Activate the required plugins below.</center></td></tr>';
	}
?>
</table>
<?php } ?>

<h3>Available plugins:</h3>
<p>Click on any of the below plugin names for more info and configuration options:</p>
<form method="post" action="admplugins.php" autocomplete="off">
<?php echo _hs ?>
<table class="datatable solidtable">
<thead><tr class="fieldtopic"><td><b>Plugin name</b></td><td><b>Activate?</b></td></tr></thead>
<?php
foreach (glob("$PLUGIN_PATH/*") as $file) {
	if (is_dir($file)) {	// Check for plugins in subdirectories.
		$dir = basename($file);
		foreach (glob("$PLUGIN_PATH/$dir/*") as $dirfile) {
			if (!preg_match('/\.plugin$/', $dirfile)) continue;	// Not a plugin file.
			$plugin_files[] = $dir .'/'. basename($dirfile);
		}
	}
	if (!preg_match('/\.plugin$/', $file)) continue;	// Not a plugin file.
	$plugin_files[] = basename($file);
}

$disabled = ($FUD_OPT_3 & 4194304) ? '' : 'disabled="disabled"';
foreach ($plugin_files as $plugin) {
	if (fud_plugin::is_active($plugin)) continue;	// Skip, already active.
?>
<tr class="field">
  <td><a href="admplugins.php?config=<?php echo urlencode($plugin) .'&amp;'. __adm_rsid .'" title="Configure plugin">'. $plugin; ?></a></td>
  <td class="center"><input type="checkbox" name="plugins[]" value="<?php echo $plugin; ?>" <?php echo $disabled; ?> /></td>
</tr>
<?php } ?> 
<tr class="fieldtopic center">
  <td>&nbsp;</td>
  <td><input type="submit" name="activate_plugins" value="Activate" <?php echo $disabled; ?> /></td>
</tr>
</table>
</form>

<?php require($WWW_ROOT_DISK .'adm/footer.php'); ?>
