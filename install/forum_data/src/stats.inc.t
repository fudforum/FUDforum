<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: stats.inc.t,v 1.7 2003/09/26 21:35:21 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

if (_uid && $usr->users_opt & 1048576) {
	$page_gen_end = gettimeofday();
	$page_gen_time = sprintf('%.5f', ($page_gen_end['sec'] - $GLOBALS['PAGE_TIME']['sec'] + (($page_gen_end['usec'] - $GLOBALS['PAGE_TIME']['usec'])/1000000)));
	$page_stats = '{TEMPLATE: admin_page_stats}';
} else if ($GLOBALS['PUBLIC_STATS'] == 'Y') {
	$page_gen_end = gettimeofday();
	$page_gen_time = sprintf('%.5f', ($page_gen_end['sec'] - $GLOBALS['PAGE_TIME']['sec'] + (($page_gen_end['usec'] - $GLOBALS['PAGE_TIME']['usec'])/1000000)));
	$page_stats = '{TEMPLATE: public_page_stats}';
} else {
	$page_stats = '';
}
?>