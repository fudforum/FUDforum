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

/*{PRE_HTML_PHP}*/

	$RSS = '{TEMPLATE: index_RSS}';
	ses_update_status($usr->sid, '{TEMPLATE: index_update}');

/*{POST_HTML_PHP}*/

	$TITLE_EXTRA = ': {TEMPLATE: index_title}';

// @TODO: Move to seperate file.
	/* Handling of announcements. */
	include $GLOBALS['FORUM_SETTINGS_PATH'] .'announce_cache';
	$announcements = '';
	foreach ($announce_cache as $a_id => $a) {
		if ($a['start'] <= __request_timestamp__ && $a['end'] >= __request_timestamp__) {
			$announce_subj = $a['subject'];
			$announce_body = $a['text'];
			$announcements .= '{TEMPLATE: announce_entry}';
		}
	}

/*{POST_PAGE_PHP_CODE}*/
?>
{TEMPLATE: INDEX_PAGE}
