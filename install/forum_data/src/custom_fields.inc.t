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

/* Read custom field definitions from the DB. */
function get_custom_field_defs()
{
// TODO: Setup a cache so that we don't have read from DB every time we need them.
	$custom_field_defs = null;
	$c = uq('SELECT * FROM {SQL_TABLE_PREFIX}custom_fields ORDER BY vieworder');
	while ($r = db_rowobj($c)) {
		$custom_field_defs[ $r->id ] = $r;
	}
	return $custom_field_defs;
}

/* Check if all required custom fields have values. */
function validate_custom_fields()
{
	foreach (get_custom_field_defs() as $k => $r) {
		if (($r->field_opt & 1) && empty($_POST['custom_field_'. $r->id])) {	// 1==required.
				set_err('custom_field_'. $r->id, '{TEMPLATE: custom_field_required}');
		}
	}
}

/* Serialize custom field values for storage. */
function serialize_custom_fields()
{
	$custom_field_vals = null;
	foreach (get_custom_field_defs() as $k => $r) {
		if (!empty($_POST['custom_field_'. $r->id])) {
			$custom_field_vals[ $r->id ] = $_POST['custom_field_'. $r->id];
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
		$r->choice = preg_replace("/\r\n/", "\n", $r->choice);	// Strip Windows newlines.
		$custom_field_vals[$r->id] = empty($custom_field_vals[$r->id]) ? '' : $custom_field_vals[$r->id];

		if ($r->type_opt & 1) {	// # 1 == Textarea.
			$val = empty($custom_field_vals[$r->id]) ? $r->choice : $custom_field_vals[$r->id];
			$custom_field = '{TEMPLATE: custom_field_text}';
		} else if ($r->type_opt & 2) {	// # 2 == Select drop down.
			$custom_field_select = tmpl_draw_select_opt($r->choice, $r->choice, $custom_field_vals[$r->id]);
			$custom_field = '{TEMPLATE: custom_field_select}';
		} else if ($r->type_opt & 4) {	// # 4 == Radio buttons.
			$custom_field_radio = tmpl_draw_radio_opt('custom_field_'. $r->id, $r->choice, $r->choice, $custom_field_vals[$r->id], '{TEMPLATE: custom_field_radio_separator}');
			$custom_field = '{TEMPLATE: custom_field_radio}';
		} else {	// # 0 == Single line.
			$val = empty($custom_field_vals[$r->id]) ? $r->choice : $custom_field_vals[$r->id];
			$custom_field = '{TEMPLATE: custom_field_single_line}';
		}

		if ($r->field_opt & 1) {
			$required_custom_fields .= $custom_field;
		} else {
			$optional_custom_fields .= $custom_field;
		}
	}

?>
