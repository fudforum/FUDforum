<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: admlogin.php,v 1.1.1.1 2002/06/17 23:00:09 hackie Exp $
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
	fud_use('static/login.inc');
	fud_use('static/widgets.inc');
	fud_use('static/adm.inc');
	
	list($ses, $usr) = initadm();
	
	if ( !empty($btn_cancel) ) {
		header("Location: admlogin.php?"._rsid);
		exit();
	}
	
	$l = new fud_login_block;
	if ( !empty($btn_submit) ) {
		$l->add($l_login);
		$reload = 1;
	}
	
	if ( !empty($edit) && empty($p_l) ) {
		$l->get($edit);
		$l_login = $l->login;
	}
	
	if ( !empty($edit) && !empty($btn_update) ) {
		$l->get($edit);
		$l->sync($l_login);
		$reload = 1;
		
	}
	
	if ( !empty($del) ) {
		$l->get($del);
		$l->delete();
		$reload = 1;
	}
	
	if ( !empty($reload) ) {
		header("Location: admlogin.php?"._rsid);
		exit();
	}
	
	include('admpanel.php'); 
?>
<h2>Login Blocker</h2>
<form method="post">  
<?php echo _hs; ?>
<table border=0 cellspacing=1 cellpadding=3>
	<tr bgcolor="#bff8ff">
		<td>Regex:</td>
		<td><input type="text" name="l_login" value="<?php echo htmlspecialchars(stripslashes($l_login)); ?>"></td>
	</tr>
	
	<tr bgcolor="#bff8ff">
		<td colspan=2 align=right>
		<?php
		
			if ( !empty($edit) ) {
				echo '<input type="submit" name="btn_cancel" value="Cancel"> ';
				echo '<input type="submit" name="btn_update" value="Update">';
			}
			else
			echo '<input type="submit" name="btn_submit" value="Add">';
		?>
		</td>
	</tr>
</table>
<input type="hidden" name="edit" value="<?php echo $edit; ?>">
<input type="hidden" name="p_l" value="1">
</form>
<table border=0 cellspacing=3 cellpadding=2>
<tr bgcolor="#e5ffe7">
	<td>Regex</td>
	<td>Action</td>
</tr>
<?php
	$l = new fud_login_block;
	$l->getall();
	$l->resetl();
	
	$i=1;
	while ( $obj = $l->eachl() ) {
		$bgcolor = ($i++%2)?' bgcolor="#fffee5"':'';
		if ( !empty($edit) && $edit==$obj->id ) $bgcolor =' bgcolor="#ffb5b5"';
		$ctl = "[<a href=\"admlogin.php?edit=$obj->id&"._rsid."\">Edit</a>] [<a href=\"admlogin.php?del=$obj->id&"._rsid."\">Delete</a>]";
		
		echo "<tr$bgcolor><td>".htmlspecialchars($obj->login)."</td><td>$ctl</td></tr>\n";
	}
?>
<?php require('admclose.html'); ?>