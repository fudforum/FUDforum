<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: announcement.inc.t,v 1.1.1.1 2002/06/17 23:00:09 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

	
	$today = gmdate("Y-m-d", __request_timestamp__);
	$res = Q("SELECT {SQL_TABLE_PREFIX}announce.subject, {SQL_TABLE_PREFIX}announce.text FROM {SQL_TABLE_PREFIX}announce INNER JOIN {SQL_TABLE_PREFIX}ann_forums ON {SQL_TABLE_PREFIX}announce.id={SQL_TABLE_PREFIX}ann_forums.ann_id AND {SQL_TABLE_PREFIX}ann_forums.forum_id=".$frm->id." WHERE {SQL_TABLE_PREFIX}announce.date_started<='".$today."' AND {SQL_TABLE_PREFIX}announce.date_ended>='".$today."'");
	if ( DB_COUNT($res) ) {
		$announcements_data='';
		while ( $data = DB_ROWOBJ($res) ) $announcements_data .= '{TEMPLATE: announce_entry}';
		
		$announcements = '{TEMPLATE: announcements}';
	}
	QF($res);
?>