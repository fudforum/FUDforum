<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: list_referers.php.t,v 1.12 2003/09/30 03:27:52 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

/*{PRE_HTML_PHP}*/

	ses_update_status($usr->sid, '{TEMPLATE: list_referers_update}');

/*{POST_HTML_PHP}*/

	if (!isset($_GET['start']) || !($start = (int)$_GET['start'])) {
		$start = 0;
	}

	if (($ttl = q_singleval('select count(distinct(referer_id)) from {SQL_TABLE_PREFIX}users WHERE referer_id>0'))) {
		if ($start > $ttl) {
			$start = 0;
		}
	
		$c = q('SELECT u2.alias, u2.id, count(*) AS cnt FROM {SQL_TABLE_PREFIX}users u LEFT JOIN {SQL_TABLE_PREFIX}users u2 ON u2.id=u.referer_id WHERE u.referer_id > 0 AND u2.id IS NOT NULL GROUP BY u2.id ORDER BY cnt, u.alias DESC LIMIT '.qry_limit($MEMBERS_PER_PAGE, $start));
		$referer_entry_data = '';
		while ($r = db_rowarr($c)) {
			$r_list = '';		
			$c2 = uq('SELECT alias, id FROM {SQL_TABLE_PREFIX}users WHERE referer_id='.$r[1]);
			$refered_entry_data = '';
			while ($r2 = db_rowarr($c2)) {
				$refered_entry_data .= '{TEMPLATE: refered_entry}';
			}
			qf($c2);
			$referer_entry_data .= '{TEMPLATE: referer_entry}';
		}
		qf($c);

		if ($ttl > $MEMBERS_PER_PAGE) {
			if ($FUD_OPT_2 & 32768) {
				$page_pager = tmpl_create_pager($start, $MEMBERS_PER_PAGE, $ttl, '{ROOT}/lt/', '/' . _rsid);
			} else {
				$page_pager = tmpl_create_pager($start, $MEMBERS_PER_PAGE, $ttl, '{ROOT}?t=list_referers&amp;'._rsid);	
			}
		} else {
			$page_pager = '';
		}
	} else {
		$page_pager = $referer_entry_data = '';
	}
	

/*{POST_PAGE_PHP_CODE}*/
?>
{TEMPLATE: REFERALS_PAGE}