<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: emailconf.php.t,v 1.3 2002/07/30 14:34:37 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

	
	{PRE_HTML_PHP}
	$usr = fud_user_to_reg($usr);
	{POST_HTML_PHP}

	if ( !empty($conf_key) ) {
		$r = q("SELECT * FROM {SQL_TABLE_PREFIX}users WHERE conf_key='".$conf_key."'");
		if ( !is_result($r) ) {
			error_dialog('{TEMPLATE: emailconf_err_invkey_title}', '{TEMPLATE: emailconf_err_invkey_msg}', NULL, 'FATAL');
			exit();
		}
		
		$conf_usr = db_singleobj($r);
		$usr = new fud_user_reg;
		$ses = new fud_session;
		$ses->cookie_get_session();
		
		$ses->save_session($conf_usr->id);		
		$usr->get_user_by_id($conf_usr->id);

		if ( $usr->conf_key == $conf_key ) $usr->email_confirm();
		
		check_return();
	}
	else {
		error_dialog('{TEMPLATE: emailconf_err_invkey_title}', '{TEMPLATE: emailconf_err_invkey_msg}', NULL, 'FATAL');
		exit();
	}
?>