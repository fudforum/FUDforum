<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: allowed_user_lnk.inc.t,v 1.1.1.1 2002/06/17 23:00:09 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/


function is_allowed_user()
{
	global $usr;
	
	if ( strtoupper($GLOBALS['COPPA']) == 'Y' && strtoupper($usr->coppa) == 'Y' )
		{ error_dialog('{TEMPLATE: err_coppa_title}', '{TEMPLATE: err_coppa_msg}', $returnto_d); exit(); }

	if ( $GLOBALS['EMAIL_CONFIRMATION'] == 'Y' && $usr->email_conf == 'N' ) 
		{ std_error('emailconf'); exit(); }	
			
	if ( $usr->blocked == 'Y' || is_email_blocked($usr->email) ) 
		{ error_dialog('{TEMPLATE: err_blockedaccnt_title}', '{TEMPLATE: err_blockedaccnt_msg}', $returnto_d, 'FATAL'); exit(); }
}
?>