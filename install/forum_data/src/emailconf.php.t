<?php
/***************************************************************************
* copyright            : (C) 2001-2004 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: emailconf.php.t,v 1.19 2004/01/29 22:58:32 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; either version 2 of the License, or
* (at your option) any later version.
***************************************************************************/

/*{PRE_HTML_PHP}*/
/*{POST_HTML_PHP}*/

	if (isset($_GET['conf_key'])) {
		/* it is possible that a user may access the email confirmation URL twice, for such a 'rare' case,
		 * we have this check to prevent a confusing error message being thrown at the hapeless user
		 */
		if (_uid && $usr->users_opt & 131072) {
			check_return($usr->returnto);
		}

		$uid = q_singleval("SELECT id FROM {SQL_TABLE_PREFIX}users WHERE conf_key='".addslashes($_GET['conf_key'])."'");
		if (!$uid || (__fud_real_user__ && __fud_real_user__ != $uid)) {
			error_dialog('{TEMPLATE: emailconf_err_invkey_title}', '{TEMPLATE: emailconf_err_invkey_msg}');
		}
		q("UPDATE {SQL_TABLE_PREFIX}users SET users_opt=users_opt|131072, conf_key='0' WHERE id=".$uid);
		if ($usr->users_opt & 2097152) {
			header('Location: {FULL_ROOT}{ROOT}' . ($FUD_OPT_2 & 32768 ? '/rc/' : '?t=reg_conf&') . _rsidl);
			return;
		} else if (!__fud_real_user__) {
			$usr->ses_id = user_login($uid, $usr->ses_id, true);
		}
		check_return($usr->returnto);
	} else {
		error_dialog('{TEMPLATE: emailconf_err_invkey_title}', '{TEMPLATE: emailconf_err_invkey_msg}');
	}
?>