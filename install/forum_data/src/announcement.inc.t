<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: announcement.inc.t,v 1.5 2003/04/06 15:52:04 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

	$announcements = '';	
	if ($frm->is_ann) {
		$today = gmdate('Ymd', __request_timestamp__);
		$res = uq("SELECT {SQL_TABLE_PREFIX}announce.subject, {SQL_TABLE_PREFIX}announce.text FROM {SQL_TABLE_PREFIX}announce INNER JOIN {SQL_TABLE_PREFIX}ann_forums ON {SQL_TABLE_PREFIX}announce.id={SQL_TABLE_PREFIX}ann_forums.ann_id AND {SQL_TABLE_PREFIX}ann_forums.forum_id=".$frm->id." WHERE {SQL_TABLE_PREFIX}announce.date_started<='".$today."' AND {SQL_TABLE_PREFIX}announce.date_ended>='".$today."'");
		while ($r = db_rowarr($res)) {
			$announcements .= '{TEMPLATE: announce_entry}';
		}
		qf($res);
	}
?>
