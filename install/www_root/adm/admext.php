<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: admext.php,v 1.3 2002/08/07 12:18:43 hackie Exp $
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
	fud_use('util.inc');
	fud_use('adm.inc', TRUE);
	fud_use('fileio.inc');
	fud_use('ext.inc', TRUE);
	
	list($ses, $usr) = initadm();
	
	if( !empty($HTTP_POST_VARS['c_ext']) ) 
		$HTTP_POST_VARS['c_ext'] = ereg_replace(".*\.", "", $HTTP_POST_VARS['c_ext']);	
	
	if ( !empty($btn_submit) ) {
		$c = new fud_ext_block;
		$c->fetch_vars($HTTP_POST_VARS, 'c_');
		$c->add();
		header("Location: admext.php?"._rsidl);
		exit();
	}
	
	if ( !empty($edit) && empty($prev_l) ) {
		$c_r = new fud_ext_block;
		$c_r->get($edit);
		$c_r->export_vars('c_');
	}
	
	if ( !empty($btn_cancel) ) {
 		header("Location: admext.php?"._rsidl);
		exit();
	}
	
	if ( !empty($btn_update) && !empty($edit) ) {
		$c_s = new fud_ext_block;
		$c_s->get($edit);
		$c_s->fetch_vars($HTTP_POST_VARS, 'c_');
		$c_s->sync();
		header("Location: admext.php?"._rsidl);
		exit();
	}
	
	if ( !empty($del) ) {
		$c_d = new fud_ext_block;
		$c_d->get($del);
		$c_d->delete();
		header("Location: admext.php?"._rsidl);
		exit();
	}
	
	cache_buster();
include('admpanel.php'); ?>
<h2>Allowed Extensions</h2>
<form method="post" action="admext.php">  
<table border=0 cellspacing=1 cellpadding=3>
	<tr>
		<td colspan=2 bgcolor="#FFFFFF"><b>note:</b> if no file extension is entered, all files will be allowed</td>
	</tr>
	<tr bgcolor="#bff8ff">
		<td>Extension:</td>
		<td><input type="text" name="c_ext" value="<?php echo (empty($c_ext)?'':htmlspecialchars($c_ext)); ?>">
	</tr>
	
	<tr bgcolor="#bff8ff">
		<td colspan=2 align=right>
		<?php 
			if ( !empty($edit) ) {
				?>
				<input type="submit" name="btn_cancel" value="Cancel">&nbsp;
				<input type="submit" name="btn_update" value="Update">
				<?php
			}
			else {
				?><input type="submit" name="btn_submit" value="Add"><?php
			}
		?>
		</td>
	</tr>
</table>
<input type="hidden" name="edit" value="<?php echo $edit; ?>">
<input type="hidden" name="prev_l" value="1">
<? echo _hs; ?>
</form>

<table border=0 cellspacing=3 cellpadding=2>
<tr bgcolor="#e5ffe7">
	<td>Extension</td>
	<td>Action</td>
</tr>
<?php
	$c_l = new fud_ext_block;
	$c_l->getall();
	$c_l->resetc();
	
	$i=1;
	while ( $obj = $c_l->eachc() ) {
		$bgcolor = ($i++%2)?' bgcolor="#fffee5"':'';
		if ( !empty($edit) && $edit==$obj->id ) $bgcolor =' bgcolor="#ffb5b5"';
		$ctl = "<td>[<a href=\"admext.php?edit=$obj->id&"._rsid."\">Edit</a>] [<a href=\"admext.php?del=$obj->id&"._rsid."\">Delete</a>]</td>";
		
		echo "<tr$bgcolor><td>.$obj->ext</td>$ctl</tr>\n";
	}
?>
<?php require('admclose.html'); ?>