<?php
/***************************************************************************
* copyright            : (C) 2001-2004 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: show.inc.t,v 1.13 2004/01/04 16:38:27 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; either version 2 of the License, or
* (at your option) any later version.
***************************************************************************/

if (!isset($th)) {
	$th = 0;
}
if (!isset($frm->id)) {
	$frm->id = 0;
}

$unread_posts = _uid ? '{TEMPLATE: unread_posts}' : '';
$unanswered_posts = !$th ? '{TEMPLATE: unanswered_posts}' : '';
?>