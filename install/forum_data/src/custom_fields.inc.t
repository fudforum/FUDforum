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

/* Read custom field definitions from the DB. */
function get_custom_field_defs()
{
	require $GLOBALS['FORUM_SETTINGS_PATH'] .'custom_field_cache';
	return $custom_field_cache;
}

/* Validate custom field values entered by users. */
function validate_custom_fields()
{
	foreach (get_custom_field_defs() as $k => $r) {
		// Call CUSTOM_FIELD_VALIDATE plugins.
		if (defined('plugins')) {
			$err = null;
			list($err) = plugin_call_hook('CUSTOM_FIELD_VALIDATE', array($err, $k, $r['name'], $_POST['custom_field_'. $k]));
			if ($err) {
				set_err('custom_field_'. $k, $err);
			}
		}

		/* Check if all required custom fields have values. */
		if (($r['field_opt'] & 1) && empty($_POST['custom_field_'. $k])) {	// 1==required.
				set_err('custom_field_'. $k, '{TEMPLATE: custom_field_required}');
		}
	}
}

/* Serialize custom field values for storage. */
function serialize_custom_fields()
{
	$custom_field_vals = null;
	foreach (get_custom_field_defs() as $k => $r) {
		if (!empty($_POST['custom_field_'. $k])) {
			$custom_field_vals[ $k ] = $_POST['custom_field_'. $k];
		}
	}
	return serialize($custom_field_vals);
}

/* main */
	// Unserialize custom fields to set display values.
	$custom_field_vals = unserialize($uent->custom_fields);

	// Setup custom fields for display.
	$required_custom_fields = $optional_custom_fields = '';
	foreach (get_custom_field_defs() as $k => $r) {
		$r['choice'] = preg_replace("/\r\n/", "\n", $r['choice']);	// Strip Windows newlines.
		$custom_field_vals[$k] = empty($custom_field_vals[$k]) ? '' : $custom_field_vals[$k];

		// Can field be edited.
		$disabled = ((($r['field_opt'] & 8) && !$is_a) || $r['field_opt'] & 16) ? 'disabled="disabled"' : '';

		if ($r['type_opt'] & 1) {	// # 1 == Textarea.
			$val = empty($custom_field_vals[$k]) ? $r['choice'] : $custom_field_vals[$k];
			$custom_field = '{TEMPLATE: custom_field_text}';
		} else if ($r['type_opt'] & 2) {	// # 2 == Select drop down.
			$custom_field_select = tmpl_draw_select_opt($r['choice'], $r['choice'], $custom_field_vals[$k]);
			$custom_field = '{TEMPLATE: custom_field_select}';
		} else if ($r['type_opt'] & 4) {	// # 4 == Radio buttons.
			$custom_field_radio = tmpl_draw_radio_opt('custom_field_'. $k, $r['choice'], $r['choice'], $custom_field_vals[$k], '{TEMPLATE: custom_field_radio_separator}');
			$custom_field = '{TEMPLATE: custom_field_radio}';
		} else {	// # 0 == Single line.
			$val = empty($custom_field_vals[$k]) ? $r['choice'] : $custom_field_vals[$k];
			$custom_field = '{TEMPLATE: custom_field_single_line}';
		}

		if ($r['field_opt'] & 1) {
			$required_custom_fields .= $custom_field;
		} else {
			$optional_custom_fields .= $custom_field;
		}
	}

?>
