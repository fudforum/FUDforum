<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: th.inc.t,v 1.40 2003/09/28 11:38:50 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/
	
function th_lock($id, $lck)
{
	q("UPDATE {SQL_TABLE_PREFIX}thread SET thread_opt=(thread_opt|1)".(!$lck ? '&~1' : '')." WHERE id=".$id);
}
	
function th_inc_view_count($id)
{
	q('UPDATE {SQL_TABLE_PREFIX}thread SET views=views+1 WHERE id='.$id);
}

function th_inc_post_count($id, $r, $lpi=0, $lpd=0)
{
	if ($lpi && $lpd) {
		q('UPDATE {SQL_TABLE_PREFIX}thread SET replies=replies+'.$r.', last_post_id='.$lpi.', last_post_date='.$lpd.' WHERE id='.$id);
	} else {
		q('UPDATE {SQL_TABLE_PREFIX}thread SET replies=replies+'.$r.' WHERE id='.$id);
	}
}

function th_frm_last_post_id($id, $th)
{
	return (int) q_singleval('SELECT {SQL_TABLE_PREFIX}thread.last_post_id FROM {SQL_TABLE_PREFIX}thread INNER JOIN {SQL_TABLE_PREFIX}msg ON {SQL_TABLE_PREFIX}thread.root_msg_id={SQL_TABLE_PREFIX}msg.id WHERE {SQL_TABLE_PREFIX}thread.forum_id='.$id.' AND {SQL_TABLE_PREFIX}thread.id!='.$th.' AND {SQL_TABLE_PREFIX}thread.moved_to=0 AND {SQL_TABLE_PREFIX}msg.apr=1 ORDER BY {SQL_TABLE_PREFIX}thread.last_post_date DESC LIMIT 1');
}
?>