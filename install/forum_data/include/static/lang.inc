<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: lang.inc,v 1.1.1.1 2002/06/17 23:00:09 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

function rcopy($from, $to)
{
	umask(0);
	clearstatcache();
	if( @!is_dir($to) ) {
		$perms = fileperms($from);
		if( !@mkdir($to, $perms) ) 
			exit("FATAL ERROR: cannot create $to with ".sprintf("%o", $perms&07777)."<br>\n");
	}

	if( $dir = @opendir($from) ) {
		umask(0177);
		while( ($file = readdir($dir)) ) {
		  	if( $file == "." || $file == ".." ) continue;

		  	clearstatcache();
		  	if( @is_dir($from.$file.$GLOBALS['SLASH']) ) rcopy($from.$file.$GLOBALS['SLASH'], $to.$file.$GLOBALS['SLASH']);
		  	else if( @is_file($from.$file) ) {
		  		if( @!copy($from.$file, $to.$file) ) {
		  			if( !@is_file($to.$file) ) {
			  			echo "can't copy: $from$file -> $to$file<br>\n";
		  				exit;
		  			}	
		  		}
		  	}	
		}
		closedir($dir);
	}
}

function switch_lang()
{
	rcopy($GLOBALS['TEMPLATE_DIR'].'i18n/'.$GLOBALS['LANGUAGE'].'/img/', $GLOBALS['WWW_ROOT_DISK'].'styles/default/');
	rcopy($GLOBALS['TEMPLATE_DIR'].'i18n/'.$GLOBALS['LANGUAGE'].'/help/', $GLOBALS['WWW_ROOT_DISK'].'help/');
}	
?>