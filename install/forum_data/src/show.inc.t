<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: show.inc.t,v 1.5 2003/04/14 18:49:51 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

if (!isset($th)) {
	$th = '';
}

if (_uid) {
	if (!isset($frm->id)) {
		$frm->id = '';
	}
	$unread_posts = '{TEMPLATE: unread_posts}';
} else {
	$unread_posts = '';
}
if ($th) {
	$unanswered_posts = '{TEMPLATE: unanswered_posts}';
} else {
	$unanswered_posts = '';
}
?>