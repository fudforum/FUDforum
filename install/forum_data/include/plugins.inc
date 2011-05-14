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

if ($GLOBALS['FUD_OPT_3'] & 4194304) {	// Plugins enabled?
	// Something to test against.
	define('plugins', 1);

	// Include all the activated plugins!
	include $GLOBALS['FORUM_SETTINGS_PATH'] .'plugin_cache';
}

/** Plugins will call this function to register itself when it is included. */
function plugin_add_hook($type, $func) {
	global $plugin_hooks;
	$plugin_hooks[$type][] = $func;
}

/** Execute all registered plugin functions of a particular hook. */
function plugin_call_hook($type, $data=array()) {
	global $plugin_hooks;
	if (isset($plugin_hooks[$type])) {
		foreach ($plugin_hooks[$type] as $func) {
			$data = call_user_func($func, $data);
		}
	}
	return $data;
}

?>
