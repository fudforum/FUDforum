<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: stats.inc.t,v 1.1.1.1 2002/06/17 23:00:09 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/
$page_gen_time = (db_getmicrotime() - $GLOBALS['PAGE_TIME']);
$q_time = QUERY_COUNT();
$ttl_time = TOTAL_TIME();

if ( isset($usr) && $usr->is_mod == 'A' )
	$page_stats = '{TEMPLATE: admin_page_stats}';
else if ( $GLOBALS['PUBLIC_STATS'] == 'Y' ) 
	$page_stats = '{TEMPLATE: public_page_stats}';
?>