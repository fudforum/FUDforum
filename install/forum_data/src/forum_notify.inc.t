<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: forum_notify.inc.t,v 1.3 2003/04/02 17:10:58 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/
	
function is_forum_notified($user_id, $forum_id)
{
	return q_singleval('SELECT id FROM {SQL_TABLE_PREFIX}forum_notify WHERE forum_id='.$forum_id.' AND user_id='.$user_id);
}

function forum_notify_add($user_id, $forum_id)
{
	if (!is_forum_notified($user_id, $forum_id)) {
		q('INSERT INTO {SQL_TABLE_PREFIX}forum_notify(user_id, forum_id) VALUES ('.$user_id.', '.$forum_id.')');
	}
}

function forum_notify_del($user_id, $forum_id)
{
	q('DELETE FROM {SQL_TABLE_PREFIX}forum_notify WHERE forum_id='.$forum_id.' AND user_id='.$user_id);
}
?>