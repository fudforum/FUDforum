<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: tabs.inc.t,v 1.1.1.1 2002/06/17 23:00:09 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

if( isset($usr) ) {
	$pg = substr(strrchr($GLOBALS["HTTP_SERVER_VARS"]["PATH_TRANSLATED"], '/'),1);
	if( $pg == 'ppost.php' || $pg == 'pmsg_view.php' ) $pg = 'pmsg.php';
	

	$tablist = array(
'{TEMPLATE: tabs_register}'=>'{ROOT}?t=register&',
'{TEMPLATE: tabs_subscriptions}'=>'{ROOT}?t=subscribed&',
'{TEMPLATE: tabs_referrals}'=>'{ROOT}?t=referals&id='.$usr->id.'&',
'{TEMPLATE: tabs_buddy_list}'=>'{ROOT}?t=buddy_list&',
'{TEMPLATE: tabs_ignore_list}'=>'{ROOT}?t=ignore_list&'
);
	
	if( $GLOBALS['PM_ENABLED']=='Y' ) $tablist['{TEMPLATE: tabs_private_messaging}'] = '{ROOT}?t=pmsg&'._rsid;
	
	$tabs='';
	
	reset($tablist);
	while( list($tab_name, $tab_url) = each($tablist) ) {
		$path = substr($tab_url, 0, strpos($tab_url, '?'));
		$tab_url .= _rsid;
		$tabs .= ($pg == $path) ? '{TEMPLATE: active_tab}' : '{TEMPLATE: inactive_tab}';
	}
	
	$tabs = '{TEMPLATE: tablist}';
}	
?>