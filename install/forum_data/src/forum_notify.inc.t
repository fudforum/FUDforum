<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: forum_notify.inc.t,v 1.1.1.1 2002/06/17 23:00:09 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/
	
class fud_forum_notify
{
	var $id;
	var $user_id;
	var $forum_id;
	
	function add($user_id='', $forum_id='')
	{	
		if ( strlen($user_id) ) $this->user_id = $user_id;
		if ( strlen($forum_id) ) $this->forum_id = $forum_id;
		
		if ( IS_RESULT($r=Q("SELECT id FROM {SQL_TABLE_PREFIX}forum_notify WHERE forum_id=".$this->forum_id." AND user_id=".$this->user_id)) ) {
			list($id) = DB_SINGLEARR($r);
			return $id;
		}
		
		Q("INSERT INTO {SQL_TABLE_PREFIX}forum_notify(user_id, forum_id)
			VALUES (
				".$this->user_id.",
				".$this->forum_id."
			)
		");
	}

	function delete($user_id, $forum_id)
	{
		if ( $id ) $this->id = $id;
		Q("DELETE FROM {SQL_TABLE_PREFIX}forum_notify WHERE forum_id=".$forum_id." AND user_id=".$user_id);
	}		
}

function is_forum_notified($user_id, $forum_id)
{
	return BQ("SELECT * FROM {SQL_TABLE_PREFIX}forum_notify WHERE forum_id=".$forum_id." AND user_id=".$user_id);
}
?>