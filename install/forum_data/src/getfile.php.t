<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: getfile.php.t,v 1.3 2002/06/25 01:40:22 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

	include_once "GLOBALS.php";
	{PRE_HTML_PHP}{POST_HTML_PHP}
	$file = new fud_attach;

	if( !is_numeric($id) ) invl_inp_err();

	if( empty($private) )
		$file->get($id);
	else
		$file->get($id,'Y');
	
	if( empty($file->id) ) invl_inp_err();	
	
	if( $usr->is_mod != 'A' ) {
		if ( ($file->private == 'Y' && $usr->id != $file->owner) ) {
			std_error('access');
			exit;
		}
		else if( $file->private != 'Y') {
			$forum_id = q_singleval("SELECT forum_id FROM {SQL_TABLE_PREFIX}msg INNER JOIN {SQL_TABLE_PREFIX}thread ON {SQL_TABLE_PREFIX}msg.thread_id={SQL_TABLE_PREFIX}thread.id WHERE {SQL_TABLE_PREFIX}msg.id=".$file->message_id);
			if( !is_perms(_uid, $forum_id, 'READ') ) {
				std_error('access');
				exit;
			}			
		}
	}	

	reverse_FMT($file->original_name);

	$header = q_singleval("SELECT mime_hdr FROM {SQL_TABLE_PREFIX}mime WHERE id=".$file->mime_type);
	if( empty($header) ) $header = 'application/ocet-stream';
	
	if( preg_match('!^(audio|video|image)/!i', $header) && !strstr($HTTP_SERVER_VARS['HTTP_USER_AGENT'], 'MSIE') )
		$append = 'inline; ';
	else
		$append = 'attachment; ';		
	
	header('Content-type: '.$header);
	header("Content-Disposition: ".$append."filename=".$file->original_name);	
		
	if( !@file_exists($file->location) ) exit;
	
	$file->inc_dl_count();
	fpassthru(fopen($file->location, 'rb'));
?>