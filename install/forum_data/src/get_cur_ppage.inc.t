<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: get_cur_ppage.inc.t,v 1.2 2003/04/17 09:37:33 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

$folders = array('INBOX'=>'{TEMPLATE: inbox}', 'SAVED'=>'{TEMPLATE: saved}', 'DRAFT'=>'{TEMPLATE: draft}', 'SENT'=>'{TEMPLATE: sent}', 'TRASH'=>'{TEMPLATE: trash}');

function tmpl_cur_ppage($folder_id, $folders, $msg_subject='')
{
	if (!$folder_id || (!$msg_subject && $_GET['t'] == 'ppost')) {
		$user_action = '{TEMPLATE: writing_message}';
	} else {	
		$user_action = $msg_subject ? '{TEMPLATE: viewing_message}' : '{TEMPLATE: viewing_folder}';
	}

	return '{TEMPLATE: cur_ppage}';
}
?>