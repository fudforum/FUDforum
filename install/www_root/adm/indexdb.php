<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: indexdb.php,v 1.4 2002/09/18 20:52:08 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

	@set_time_limit(2000);
	
	define('admin_form', 1);
	
	include_once "GLOBALS.php";
	
	fud_use('isearch.inc');
	fud_use('fileio.inc');
	fud_use('rev_fmt.inc');
	fud_use('adm.inc', true);
	fud_use('glob.inc', true);
	
	list($ses, $usr) = initadm();
        
	if( !empty($HTTP_POST_VARS['cancel']) ) {
		header("Location: admglobal.php?"._rsidl);
		exit;
	}
	include('admpanel.php');

	if( empty($HTTP_POST_VARS['conf']) ) {
?>		
<form method="post" action="indexdb.php">
<div align="center">
This script will attempt to rebuild the search indices for the entire forum. This is a VERY CPU-intensive process 
and can take a VERY LONG time, especially on large forums. You should ONLY run this if you absolutely must.<br><br>
<h2>Do you wish to proceed?</h2>
<input type="submit" name="cancel" value="No">&nbsp;&nbsp;&nbsp;<input type="submit" name="conf" value="Yes">
</div>
<?php echo _hs; ?>
</form>
<?php
		readfile('admclose.html');
		exit;	
	}
	
	if( $GLOBALS['FORUM_ENABLED'] == 'Y' ) {
		echo '<br>Disabling the forum for the duration of maintenance run<br>';
		maintenance_status('Undergoing maintenance, please come back later.', 'N');
	}
	
	echo "<br>Please wait while index is being rebuilt.<br>This may take a while depending on the size of your forum.<br>\n";	
	flush();
	re_build_index();
	
	echo "Done<br>\n";
	
	if( $GLOBALS['FORUM_ENABLED'] == 'Y' ) {
		echo '<br>Re-enabling the forum.<br>';
		maintenance_status($GLOBALS['DISABLED_REASON'], 'Y');
	}
	else
		echo '<br><font size=+1 color="red">Your forum is currently disabled, to re-enable it go to the <a href="admglobal.php?'._rsid.'">Global Settings Manager</a> and re-enable it.</font>';
	
	readfile('admclose.html');
?>
