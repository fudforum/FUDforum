<?
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: init_errors.inc.t,v 1.1 2002/06/26 22:00:25 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/
 /* inital checks and funcs */	

if ( $GLOBALS['FORUM_ENABLED'] != 'Y' && !defined('admin_form') ) {
	fud_use('cfg.inc', TRUE);
	exit(cfg_dec($GLOBALS['DISABLED_REASON']).'{TEMPLATE: core_adm_login_msg}');
}	

if( @file_exists($GLOBALS['WWW_ROOT_DISK'].'install.php') ) exit('{TEMPLATE: install_script_present_error}');
/* end init checks and funcs */

?>