<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: rebuildforum.php,v 1.2 2002/06/26 19:41:21 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

	@set_time_limit(6000);
	define('admin_form', 1);
	
	include_once "GLOBALS.php";
	
	fud_use('adm.inc', TRUE);
	fud_use('compiler.inc', TRUE);

	if( !empty($HTTP_POST_VARS['cancel']) ) {
		header("Location: admglobal.php?"._rsid);
		exit;
	}

	include('admpanel.php');
	if( empty($HTTP_POST_VARS['conf']) ) {
?>		
<form method="post" action="rebuildforum.php">
<div align="center">
Rebuilding process will rebuild every single file from templates. This operation will take several minutes to perform.<br><br>
<h2>Do you wish to proceed?</h2>
<input type="submit" name="cancel" value="No">&nbsp;&nbsp;&nbsp;<input type="submit" name="conf" value="Yes">
</div>
<?php echo _hs; ?>
</form>	
<?php	
		readfile('admclose.html');
		exit;	
	}
	
	$st = __request_timestamp__;
	compile_all();
	$end = __request_timestamp__;
	
	echo "Recompiling of the forum took: ".($end-$st)." seconds<br>\n";
	
	readfile('admclose.html');
?>