<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: get_cur_ppage.inc.t,v 1.1.1.1 2002/06/17 23:00:09 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

function tmpl_cur_ppage($folder_id, $msg_subject='')
{
	global $folders;

	if( empty($folder_id) || (empty($msg_subject) && strstr("ppost.php", $GLOBALS["PHP_SELF"])) )
		$user_action = '{TEMPLATE: writing_message}';
	else {	
		$folder = $folders[$folder_id];
	
		if( !empty($msg_subject) ) 
			$user_action = '{TEMPLATE: viewing_message}';
		else
			$user_action = '{TEMPLATE: viewing_folder}';
	}

	return '{TEMPLATE: cur_ppage}';
}
?>