<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: emailconf.php.t,v 1.6 2003/04/10 18:33:43 hackie Exp $
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
/*{POST_HTML_PHP}*/

	if (isset($_GET['conf_key'])) {
		$uid = q_singleval("SELECT id FROM {SQL_TABLE_PREFIX}users WHERE conf_key='".addslashes($_GET['conf_key'])."'");
		if (__fud_real_user__ && __fud_real_user__ != $uid) {
			error_dialog('{TEMPLATE: emailconf_err_invkey_title}', '{TEMPLATE: emailconf_err_invkey_msg}');
		}
		
		usr_email_confirm($uid);
		check_return($usr->returnto);
	} else {
		error_dialog('{TEMPLATE: emailconf_err_invkey_title}', '{TEMPLATE: emailconf_err_invkey_msg}');
	}
?>