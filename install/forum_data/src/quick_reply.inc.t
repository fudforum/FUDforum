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

        /* Variables for quick_reply template */
	$quick_reply_enabled = _uid && ($GLOBALS['FUD_OPT_3'] & 8388608) && ((!($frm->thread_opt & 1) || $perms & 4096));
	$quick_reply_collapsed = $GLOBALS['FUD_OPT_3'] & 16777216;
	$quick_reply_subject = strncmp('{TEMPLATE: quick_reply_prefix}', $obj2->subject, strlen('{TEMPLATE: quick_reply_prefix}')) ? '{TEMPLATE: quick_reply_prefix}'.' '. $obj2->subject : $obj2->subject;
?>
