<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: show.inc.t,v 1.3 2003/03/30 12:44:52 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

if (_uid) {
	if (!isset($th)) { $th = ''; }
	$unread_posts = '{TEMPLATE: unread_posts}';
} else {
	$unread_posts = '';
}
if (empty($th)) {
	$unanswered_posts = '{TEMPLATE: unanswered_posts}';
} else {
	$unanswered_posts = '';
}
?>