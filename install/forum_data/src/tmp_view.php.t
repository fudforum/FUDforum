<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: tmp_view.php.t,v 1.2 2002/07/30 14:34:37 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/
	
	
	if ( !empty($img) && strlen($img) ) 
		$file = $GLOBALS['TMP'].basename($img);
	else
		$file = 'blank.gif';
	
	if( !@file_exists($file) ) $file = 'blank.gif';
	
	header('Content-type: image');
	fpassthru(fopen($file, 'rb'));
?>