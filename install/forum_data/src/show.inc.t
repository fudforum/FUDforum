<?php
/***************************************************************************
* copyright            : (C) 2001-2003 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: show.inc.t,v 1.10 2003/10/09 14:34:27 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it 
* under the terms of the GNU General Public License as published by the 
* Free Software Foundation; either version 2 of the License, or 
* (at your option) any later version.
***************************************************************************/

if (!isset($th)) {
	$th = '';
}
if (!isset($frm->id)) {
	$frm->id = '';
}

$unread_posts = _uid ? '{TEMPLATE: unread_posts}' : '';
$unanswered_posts = !$th ? '{TEMPLATE: unanswered_posts}' : '';
?>