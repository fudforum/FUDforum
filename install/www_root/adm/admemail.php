<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: admemail.php,v 1.2 2002/06/26 19:41:21 hackie Exp $
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
	
	fud_use('widgets.inc', TRUE);
	fud_use('iemail.inc');
	fud_use('util.inc');
	fud_use('adm.inc', TRUE);

	list($ses, $usr) = initadm();

	if ( !empty($btn_submit) ) {
		$eml = new fud_email_block;
		$eml->add($e_type, $e_string);
		header("Location: admemail.php?"._rsid);
		exit();
	}
	
	if ( !empty($edit) && empty($p_l) ) {
		$eml = new fud_email_block;
		$eml->get($edit);
		$e_string = addslashes($eml->string);
		$e_type = $eml->type;
	}
	
	if ( !empty($btn_cancel) ) {
		header("Location: admemail.php?"._rsid);
		exit(); 
	}
	
	if ( !empty($edit) && !empty($btn_update) ) {
		$eml = new fud_email_block;
		$eml->get($edit);
		$eml->sync($e_type, $e_string);
		header("Location: admemail.php?"._rsid);
		exit();
	}
	
	if ( !empty($del) ) {
		$eml = new fud_email_block;
		$eml->get($del);
		$eml->delete();
		header("Location: admemail.php?"._rsid);
		exit();
	}
	
	cache_buster();
 include('admpanel.php'); 
?>
<h2>Email Filter</h2>
<form method="post">  
<?php echo _hs; ?>
<table border=0 cellspacing=1 cellpadding=3>
	<tr bgcolor="#bff8ff">
		<td>Type:</td>
		<td><?php draw_select("e_type", "Simple\nRegexp", "SIMPLE\nREGEX", empty($e_type)?'':$e_type); ?></td>
	</tr>
	
	<tr bgcolor="#bff8ff">
		<td>String:</td>
		<td><input type="text" name="e_string" value="<?php echo htmlspecialchars(stripslashes($e_string)); ?>"></td>
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
<input type="hidden" name="p_l" value="1">
<input type="hidden" name="edit" value="<?php echo $edit; ?>">
</form>

<table border=0 cellspacing=3 cellpadding=2>
<tr bgcolor="#e5ffe7">
	<td>Address/Regex</td>
	<td>Type</td>
	<td>Action</td>
</tr>
<?php
	$el = new fud_email_block;
	$el->getall();
	$el->resete();
	
	$i=1;
	while ( $obj = $el->eache() ) {
		$bgcolor = ($i++%2)?' bgcolor="#fffee5"':'';
		if ( !empty($edit) && $edit==$obj->id ) $bgcolor =' bgcolor="#ffb5b5"';
		$ctl = "[<a href=\"admemail.php?edit=$obj->id&"._rsid."\">Edit</a>] [<a href=\"admemail.php?del=$obj->id&"._rsid."\">Delete</a>]";
		echo "<tr$bgcolor><td>$obj->string</td><td>$obj->type</td><td>$ctl</td></tr>\n";
	}
?>
</table>
<?php require('admclose.html'); ?>