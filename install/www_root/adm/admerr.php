<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: admerr.php,v 1.2 2002/08/29 14:36:26 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/
	
	define('admin_form', 1);
	
	include_once "GLOBALS.php";
	
	fud_use('db.inc');
	fud_use('adm.inc', TRUE);
	
	list($ses, $usr) = initadm();
	
	fud_use('th.inc');
	fud_use('imsg.inc');
	fud_use('fileio.inc');
	fud_use('err.inc');

	if( !empty($clear_sql_log) ) {
		unlink($GLOBALS['ERROR_PATH'].'sql_errors');
	}
	
	if( !empty($clear_fud_log) ) {
		unlink($GLOBALS['ERROR_PATH'].'fud_errors');
	}
	
	include('admpanel.php'); 
?>
<h2>Error Log Browser</h2>

<?php
	if( @file_exists($GLOBALS['ERROR_PATH'].'fud_errors') && filesize($GLOBALS['ERROR_PATH'].'fud_errors') ) {
		echo '<h4>FUDforum Error Log [<a href="admerr.php?clear_fud_log=1&<? echo _rsid; ?>">clear log</a>]</h4>';
		echo '<table border=1 cellspacing=1 cellpadding=3><tr bgcolor="#bff8ff"><td>Time</td><td>Error Description</td></tr>';
		
		$errors = file($GLOBALS['ERROR_PATH'].'fud_errors');
		foreach( $errors as $error ) {
			list($time,$msg) = explode('] ', substr($error, 1));
			echo '<tr><td nowrap valign="top">'.$time.'</td><td>'.base64_decode($msg).'</td></tr>';
		}
		echo '</table><br /><br />';
	}

	if( @file_exists($GLOBALS['ERROR_PATH'].'sql_errors') && filesize($GLOBALS['ERROR_PATH'].'sql_errors') ) {
		echo '<h4>SQL Error Log [<a href="admerr.php?clear_sql_log=1&<? echo _rsid; ?>">clear log</a>]</h4>';
		echo '<table border=1 cellspacing=1 cellpadding=3><tr bgcolor="#bff8ff"><td>Time</td><td>Error Description</td></tr>';
		
		$errors = file($GLOBALS['ERROR_PATH'].'sql_errors');
		foreach( $errors as $error ) {
			list($time,$msg) = explode('] ', substr($error, 1));
			echo '<tr><td nowrap valign="top">'.$time.'</td><td>'.base64_decode($msg).'</td></tr>';
		}
		echo '</table><br /><br />';
	}

	if( !isset($errors) ) 
		echo "<h4>Error logs are currently empty</h4><br />";

	require('admclose.html'); 
?>