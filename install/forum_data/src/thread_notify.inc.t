<?php
/**
* copyright            : (C) 2001-2009 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id$
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; version 2 of the License.
**/

function is_notified($user_id, $thread_id)
{
	return q_singleval('SELECT * FROM {SQL_TABLE_PREFIX}thread_notify WHERE thread_id='.(int)$thread_id.' AND user_id='.$user_id);
}

function thread_notify_add($user_id, $thread_id)
{
	db_li('INSERT INTO {SQL_TABLE_PREFIX}thread_notify (user_id, thread_id) VALUES ('.$user_id.', '.(int)$thread_id.')', $ret);
}

function thread_notify_del($user_id, $thread_id)
{
	q('DELETE FROM {SQL_TABLE_PREFIX}thread_notify WHERE thread_id='.(int)$thread_id.' AND user_id='.$user_id);
}

function thread_bookmark_add($user_id, $thread_id)
{
	db_li('INSERT INTO {SQL_TABLE_PREFIX}bookmarks (user_id, thread_id) VALUES ('.$user_id.', '.(int)$thread_id.')', $ret);
}

function thread_bookmark_del($user_id, $thread_id)
{
	q('DELETE FROM {SQL_TABLE_PREFIX}bookmarks WHERE thread_id='.(int)$thread_id.' AND user_id='.$user_id);
}
?>
