<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: post_opt.inc.t,v 1.6 2003/10/01 21:48:34 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

function tmpl_post_options($arg, $perms=0)
{
	$post_opt_html		= '{TEMPLATE: post_opt_html_off}';
	$post_opt_fud		= '{TEMPLATE: post_opt_fud_off}';
	$post_opt_images 	= '{TEMPLATE: post_opt_images_off}';
	$post_opt_smilies	= '{TEMPLATE: post_opt_smilies_off}';
	$edit_time_limit	= '';

	if (is_int($arg)) {
		if ($arg & 16) {
			$post_opt_fud = '{TEMPLATE: post_opt_fud_on}';
		} else if (!($arg & 8)) {
			$post_opt_html = '{TEMPLATE: post_opt_html_on}';
		}
		if ($perms & 16384) {
			$post_opt_smilies = '{TEMPLATE: post_opt_smilies_on}';
		}
		if ($perms & 32768) {
			$post_opt_images = '{TEMPLATE: post_opt_images_on}';
		}
		$edit_time_limit = $GLOBALS['EDIT_TIME_LIMIT'] ? '{TEMPLATE: edit_time_limit}' : '{TEMPLATE: no_edit_time_limit}';
	} else if ($arg == 'private') {
		$o =& $GLOBALS['FUD_OPT_1'];

		if ($o & 4096) {
			$post_opt_fud = '{TEMPLATE: post_opt_fud_on}';
		} else if (!($o & 2048)) {
			$post_opt_html = '{TEMPLATE: post_opt_html_on}';
		}
		if ($o & 16384) {
			$post_opt_images = '{TEMPLATE: post_opt_images_on}';
		}
		if ($o & 8192) {
			$post_opt_smilies = '{TEMPLATE: post_opt_smilies_on}';
		}
	} else if ($arg == 'sig') {
		$o =& $GLOBALS['FUD_OPT_1'];

		if ($o & 131072) {
			$post_opt_fud = '{TEMPLATE: post_opt_fud_on}';
		} else if (!($o & 65536)) {
			$post_opt_html = '{TEMPLATE: post_opt_html_on}';
		}
		if ($o & 524288) {
			$post_opt_images = '{TEMPLATE: post_opt_images_on}';
		}
		if ($o & 262144) {
			$post_opt_smilies = '{TEMPLATE: post_opt_smilies_on}';
		}
	}

	return '{TEMPLATE: posting_options}';
}
?>