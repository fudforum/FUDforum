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

if (!defined('_hs'))   define('_hs', '');
if (!defined('_rsid')) define('_rsid', '');

function exit_forum_disabled($format='html')
{
	$TITLE_EXTRA = $RSS = null;

	header('HTTP/1.1 503 Service Temporarily Unavailable');
	header('Status: 503 Service Temporarily Unavailable');
	header('Retry-After: 1800');	// 30 minutes.
	header('Connection: Close');
	header('Content-type: text/'. $format .'; charset={TEMPLATE: errmsg_CHARSET}');

	if ($format == 'xml') {
		exit('{TEMPLATE: forum_disabled_xml}');
	} else {
		exit('{TEMPLATE: forum_disabled_html}');
	}
}

function exit_user_banned()
{
	$TITLE_EXTRA = $RSS = null;

	header('Content-type: text/html; charset={TEMPLATE: errmsg_CHARSET}');
	exit('{TEMPLATE: forum_banned_user}');
}

?>
