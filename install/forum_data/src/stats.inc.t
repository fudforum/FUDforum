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

if ($FUD_OPT_2 & 2 || $is_a) {	// PUBLIC_STATS is enabled or Admin user.
	$page_gen_time = number_format(microtime(true) - __request_timestamp__, 5);
	$page_stats = $FUD_OPT_2 & 2 ? '{TEMPLATE: public_page_stats}' : '{TEMPLATE: admin_page_stats}';
} else {
	$page_stats = '';
}
?>
