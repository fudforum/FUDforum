<?php
/**
* copyright            : (C) 2001-2006 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: th.inc.t,v 1.50 2006/09/19 14:37:56 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; version 2 of the License.
**/

function th_lock($id, $lck)
{
	q("UPDATE {SQL_TABLE_PREFIX}thread SET thread_opt=(thread_opt|1)".(!$lck ? '& ~ 1' : '')." WHERE id=".$id);
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
?>