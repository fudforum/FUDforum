<?php
/**
* copyright            : (C) 2001-2004 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: announcement.inc.t,v 1.12 2004/11/24 19:53:34 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; either version 2 of the License, or
* (at your option) any later version.
**/

	$announcements = '';
	if ($frm->is_ann) {
		$today = gmdate('Ymd', __request_timestamp__);
		$res = uq('SELECT a.subject, a.text FROM {SQL_TABLE_PREFIX}announce a INNER JOIN {SQL_TABLE_PREFIX}ann_forums af ON a.id=af.ann_id AND af.forum_id='.$frm->id.' WHERE a.date_started<='.$today.' AND a.date_ended>='.$today);
		while ($r = db_rowarr($res)) {
			$announcements .= '{TEMPLATE: announce_entry}';
		}
	}
?>
