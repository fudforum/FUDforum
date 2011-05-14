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

class fud_plugin
{
	/** Activate a plugin and return error or activation message. */
	static function activate($name)
	{
		$ok = $err = null;

		// Call the plugin's enable function.
		$plugin_file = basename($name);
		$enable_func = substr($plugin_file, 0, strrpos($plugin_file, '.')) .'_enable';
		if ((include_once($GLOBALS['PLUGIN_PATH'] .'/'. $name)) && function_exists($enable_func)) {
			list($ok, $err) = $enable_func();
			if ($err) return array(0, $err);
		}

		// Get max priority.
		$priority = q_singleval('SELECT MAX(priority)+1 FROM '. $GLOBALS['DBHOST_TBL_PREFIX'] .'plugins');

		// Load into db.
		$id = db_qid('INSERT INTO '. $GLOBALS['DBHOST_TBL_PREFIX'] .'plugins (
			name,
			priority
		) VALUES (
			'. ssn($name) .',
			'. (int)$priority .'
		)');

		fud_plugin::rebuild_cache();
		return array(1, $ok);
	}

	/** Deactivate a plugin and return error or deactivation message. */
	static function deactivate($name)
	{
		$ok = $err = null;

		// Call the plugin's disable function.
		$plugin_file = basename($name);
		$disable_func = substr($plugin_file, 0, strrpos($plugin_file, '.')) .'_enable';
		if ((include_once($GLOBALS['PLUGIN_PATH'] .'/'. $name)) && function_exists($disable_func)) {
				list($ok, $err) = $disable_func();
				if ($err) return array(0, $err);
		}

		// Load into db.
		q('DELETE FROM '. $GLOBALS['DBHOST_TBL_PREFIX'] .'plugins WHERE name = '. _esc($name));

		fud_plugin::rebuild_cache();
		return array(1, $ok);
	}

	/** Check if a plugin is active or not. */
	static function is_active($name)
	{
		// If it's in the DB, it is active.
		$id = q_singleval('SELECT id FROM '. $GLOBALS['DBHOST_TBL_PREFIX'] .'plugins WHERE name = \''. $name .'\'');
		return $id ? 1 : 0;
	}

	/** Build or rebuild the plugin cache. */
	static function rebuild_cache() {
		$plugins = '';

		$c = uq('SELECT * FROM '. $GLOBALS['DBHOST_TBL_PREFIX'] .'plugins ORDER BY priority');	// In firing order!
		while ($r = db_rowobj($c)) {
			$plugins .= 'include_once \''. $GLOBALS['PLUGIN_PATH'] .$r->name ."';\n";
		}
		unset($c);

		$fp = fopen($GLOBALS['TMP'] .'plugin_cache', 'w');
		fwrite($fp, '<?php '. $plugins .' ?>');
		fclose($fp);

		if (!strncasecmp('win', PHP_OS, 3)) {
			@unlink($GLOBALS['FORUM_SETTINGS_PATH'] .'plugin_cache');
		}
		rename($GLOBALS['TMP'] .'plugin_cache', $GLOBALS['FORUM_SETTINGS_PATH'] .'plugin_cache');
	}

}

?>
