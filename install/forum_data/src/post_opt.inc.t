<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: post_opt.inc.t,v 1.2 2003/04/02 01:46:35 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/
	
function tmpl_post_options($arg)
{
	$post_opt_html		= '{TEMPLATE: post_opt_html_off}';
	$post_opt_fud		= '{TEMPLATE: post_opt_fud_off}';
	$post_opt_images 	= '{TEMPLATE: post_opt_images_off}';
	$post_opt_smilies	= '{TEMPLATE: post_opt_smilies_off}';
	$edit_time_limit	= '';
	
	if (is_object($arg)) {
		if ($arg->tag_style == 'ML') {
			$post_opt_fud = '{TEMPLATE: post_opt_fud_on}';
		} else if ($arg->tag_style == 'HTML') {
			$post_opt_html = '{TEMPLATE: post_opt_html_on}';
		}
		if (is_perms(_uid, $arg->id, 'SML')) {
			$post_opt_smilies = '{TEMPLATE: post_opt_smilies_on}';
		}
		if (is_perms(_uid, $arg->id, 'IMG')) {
			$post_opt_images = '{TEMPLATE: post_opt_images_on}';
		}
		
		$edit_time_limit = $GLOBALS['EDIT_TIME_LIMIT'] ? '{TEMPLATE: edit_time_limit}' : '{TEMPLATE: no_edit_time_limit}';
	} else if ($arg == 'private') {
		if ($GLOBALS['PRIVATE_TAGS'] == 'ML') {
			$post_opt_fud = '{TEMPLATE: post_opt_fud_on}';
		} else if ($GLOBALS['PRIVATE_TAGS'] == 'HTML') {
			$post_opt_html = '{TEMPLATE: post_opt_html_on}';
		}
		
		if ($GLOBALS['PRIVATE_IMAGES'] == 'Y') {
			$post_opt_images = '{TEMPLATE: post_opt_images_on}';
		}
		if ($GLOBALS['PRIVATE_MSG_SMILEY'] == 'Y') {
			$post_opt_smilies = '{TEMPLATE: post_opt_smilies_on}';
		}
	} else if ($arg == 'sig') {
		if ($GLOBALS['FORUM_CODE_SIG'] == 'ML') {
			$post_opt_fud = '{TEMPLATE: post_opt_fud_on}';
		} else if ($GLOBALS['FORUM_CODE_SIG'] == 'HTML') {
			$post_opt_html = '{TEMPLATE: post_opt_html_on}';
		}
		
		if ($GLOBALS['FORUM_IMG_SIG'] == 'Y') {
			$post_opt_images = '{TEMPLATE: post_opt_images_on}';
		}
		if ($GLOBALS['FORUM_SML_SIG'] == 'Y') {
			$post_opt_smilies = '{TEMPLATE: post_opt_smilies_on}';
		}
	}	

	return '{TEMPLATE: posting_options}';
}
?>