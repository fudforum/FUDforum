<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: stats.inc.t,v 1.3 2002/07/30 22:56:32 hackie Exp $
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

if( defined("fud_query_stats") ) {
	$q_time = query_count();
	$ttl_time = total_time();
	$fud_query_stats = '{TEMPLATE: fud_query_stats}';
}	

if ( isset($usr) && $usr->is_mod == 'A' )
	$page_stats = '{TEMPLATE: admin_page_stats}';
else if ( $GLOBALS['PUBLIC_STATS'] == 'Y' ) 
	$page_stats = '{TEMPLATE: public_page_stats}';
?>