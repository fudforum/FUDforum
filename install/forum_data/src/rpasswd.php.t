<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: rpasswd.php.t,v 1.3 2002/11/03 19:36:21 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

	define('plain_form', 1);
	
	{PRE_HTML_PHP}
	$usr = fud_user_to_reg($usr);
	
	if ( !isset($usr) ) {
		std_error('login');
		exit();
	}
	
	if ( !empty($btn_submit) ) {
		$cpasswd = stripslashes($cpasswd);
		$passwd1 = stripslashes($passwd1);
		$passwd2 = stripslashes($passwd2);
		
		/* early php4 versions hack */
		$tmp = get_id_by_radius(addslashes($usr->login), $cpasswd);
	
		if ( $usr->id != $tmp ) {
			$rpasswd_error_msg = '{TEMPLATE: rpasswd_invalid_passwd}';
		}
		else if ( $passwd1 != $passwd2 ) {
			$rpasswd_error_msg = '{TEMPLATE: rpasswd_passwd_nomatch}';
		}
		else if ( strlen($passwd1) < 6 ) {
			$rpasswd_error_msg = '{TEMPLATE: rpasswd_passwd_length}';
		}
		else {
			$usr->ch_passwd($passwd1);
			exit('<html><script>window.close();</script></html>');
		}
		
		if ( $rpasswd_error_msg ) $rpasswd_error = '{TEMPLATE: rpasswd_error}';
	}
	
	$TITLE_EXTRA = ': {TEMPLATE: rpasswd_title}';
	{POST_HTML_PHP}

	$return_field = create_return();
	{POST_PAGE_PHP_CODE}
?>
{TEMPLATE: RPASSWD_PAGE}