<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: msgreport.inc.t,v 1.2 2002/06/18 18:26:09 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

	
function submit_msg_report($user_id, $msg_id, $reason)
{
	q("INSERT INTO {SQL_TABLE_PREFIX}msg_report(user_id, msg_id, reason, stamp)
		VALUES(".$user_id.", ".$msg_id.", '".$reason."', ".__request_timestamp__.")");
}

function delete_msg_report($id, $usr_id)
{
	if ( !bq("SELECT count(*) FROM {SQL_TABLE_PREFIX}msg_report
		LEFT JOIN {SQL_TABLE_PREFIX}msg
			ON {SQL_TABLE_PREFIX}msg_report.msg_id={SQL_TABLE_PREFIX}msg.id
		LEFT JOIN {SQL_TABLE_PREFIX}thread
			ON {SQL_TABLE_PREFIX}msg.thread_id={SQL_TABLE_PREFIX}thread.id
		LEFT JOIN {SQL_TABLE_PREFIX}forum
			ON {SQL_TABLE_PREFIX}thread.forum_id={SQL_TABLE_PREFIX}forum.id
		LEFT JOIN {SQL_TABLE_PREFIX}mod 
			ON {SQL_TABLE_PREFIX}forum.id={SQL_TABLE_PREFIX}mod.forum_id AND {SQL_TABLE_PREFIX}mod.user_id=".$usr_id."
	WHERE
		{SQL_TABLE_PREFIX}msg_report.id=".$id) ) 
	{
		return 0;
	}
	
	q("DELETE FROM {SQL_TABLE_PREFIX}msg_report WHERE id=$id");
	
	return 1;
}

function get_report($id, $usr_id)
{
	if ( !($obj=db_singleobj(q("SELECT {SQL_TABLE_PREFIX}msg.subject, {SQL_TABLE_PREFIX}msg.id FROM {SQL_TABLE_PREFIX}msg_report
		LEFT JOIN {SQL_TABLE_PREFIX}msg
			ON {SQL_TABLE_PREFIX}msg_report.msg_id={SQL_TABLE_PREFIX}msg.id
		LEFT JOIN {SQL_TABLE_PREFIX}thread
			ON {SQL_TABLE_PREFIX}msg.thread_id={SQL_TABLE_PREFIX}thread.id
		LEFT JOIN {SQL_TABLE_PREFIX}forum
			ON {SQL_TABLE_PREFIX}thread.forum_id={SQL_TABLE_PREFIX}forum.id
		LEFT JOIN {SQL_TABLE_PREFIX}mod 
			ON {SQL_TABLE_PREFIX}forum.id={SQL_TABLE_PREFIX}mod.forum_id AND {SQL_TABLE_PREFIX}mod.user_id=".$usr_id."
	WHERE
		{SQL_TABLE_PREFIX}msg_report.id=".$id))) ) 
	{
		return NULL;
	}
	
	return $obj;
}

?>