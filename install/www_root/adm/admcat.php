<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: admcat.php,v 1.6 2002/09/18 20:52:08 hackie Exp $
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
	
	fud_use("cat.inc");
	fud_use("widgets.inc", true);
	fud_use('util.inc');
	fud_use('adm.inc', true);
	
	list($ses, $usr) = initadm();
	cache_buster();	
	
	if ( !empty($cat_cancel_edit) ) {
		header("Location: admcat.php?"._rsidl);
	}
	
	$cat = new fud_cat_adm;
	
	if ( !empty($edit) ) {
		$cat->get_cat($edit);
	}
	
	if ( !empty($cat_submit) ) {
		$cat->fetch_vars($HTTP_POST_VARS, 'cat_');
		if ( strlen($cat_description) ) $cat->description = ' - '.$cat->description;
		
		if ( !$edit ) 
			$cat->add_cat($HTTP_POST_VARS['cat_pos']);
		else 
			$cat->sync();
			
		header("Location: admcat.php?"._rsidl."&rnd=".get_random_value(64));
		exit();
	}
	else if ( !empty($edit) ) {
		if ( strlen($cat->description) ) $cat->description = preg_replace('!\s*-\s*!', '', $cat->description);
		$cat->export_vars('cat_');
	}
	
	if ( !empty($act) && !empty($ct) && $act=="del" ) { 
		$cat->delete($ct); 
		header("Location: admcat.php?"._rsidl."&rnd=".get_random_value(64));
		exit();
	}
	
	if ( isset($chpos) && isset($newpos) ) {
		$cat->change_pos($chpos, $newpos);
		header("Location: admcat.php?"._rsidl."&rnd=".get_random_value(64));
		exit();
	}

	$cat_name = ( isset($cat_name) ) ? htmlspecialchars($cat_name) : '';
	$cat_description = ( isset($cat_description) ) ? htmlspecialchars($cat_description) : '';

	include('admpanel.php');
?>
<h2>Category Management System</h2>

<?php if ( !isset($chpos) ) { ?>

<form method="post" action="admcat.php">
<?php echo _hs; ?>
<table border=0 cellspacing=1 cellpadding=3>
	<tr bgcolor="#bff8ff">
		<td>Category Name:</td>
		<td><input type="text" name="cat_name" value="<?php echo $cat_name; ?>" maxLength=50></td>
	</tr>
		
	<tr bgcolor="#bff8ff">
		<td>Description:</td>
		<td><input type="text" name="cat_description" value="<?php echo $cat_description; ?>" maxLength=255></td>
	</tr>
	
	<tr bgcolor="#bff8ff">
		<td>Collapsible</td>
		<td><?php draw_select('cat_allow_collapse', "Yes\nNo", "Y\nN", yn($cat_allow_collapse)); ?></td>
	</tr>
		
	<tr bgcolor="#bff8ff">
		<td>Default view: </td>
		<td><?php draw_select('cat_default_view', "Open\nCollapsed", "OPEN\nCOLLAPSED", empty($cat_default_view)?'':$cat_default_view); ?></td>
	</tr>
	
	<?php if ( empty($edit) ) { ?>
	
	<tr bgcolor="#bff8ff">
		<td>Insert position:</td>
		<td><?php draw_select('cat_pos', "Last\nFirst", "LAST\nFIRST", empty($cat_pos)?'':$cat_pos); ?></td>
	</tr>
	
	<?php } ?>
	
	<tr bgcolor="#bff8ff">
		<td colspan=2 align=right>
			<?php if ( !empty($edit) ) echo '<input type="submit" name="cat_cancel_edit" value="Cancel">&nbsp;'; ?>
			<input type="submit" value="<?php echo ( !empty($edit) ) ? 'Update Category' :'Add Category';?>" name="cat_submit">
		</td>
	</tr>
</table>
<?php
	if ( !empty($edit) ) echo '<input type="hidden" value="'.$edit.'" name="edit">';
?>
</form>

<?php } 
	if ( isset($chpos) ) {
		echo '<a href="admcat.php">Cancel</a><br>';
	}
?>

<br>
<table border=0 cellspacing=3 cellpadding=2>
<tr bgcolor="#e5ffe7">
	<td>Category Name</td>
	<td>Description</td>
	<td>Collapsible</td>
	<td>Default View</td>
	<td align="center">Action</td>
	<td>Position</td>
</tr>
<?php
	$cat->get_all_cat();
	$cat->resetcat();
	$i=1;
	
	while ( $cat->nextcat() ) {
		$bgcolor = (($i++)%2) ? ' bgcolor="#fffee5"':'';
		
		if ( !empty($edit) && $edit == $cat->id ) $bgcolor=' bgcolor="#ffb5b5"';
		
		if ( isset($chpos) ) {
			if ( $chpos == $cat->view_order ) $bgcolor=' bgcolor="#ffb5b5"';
			
			if ( $chpos != $cat->view_order && $chpos != $cat->view_order-1 ) {
				$sub = ( $chpos < $cat->view_order ) ? 1 : 0;
				echo '<tr bgcolor="#efefef"><td align=center colspan=7><font size=-1><a href="admcat.php?chpos='.$chpos.'&newpos='.($cat->view_order-$sub).'&'._rsid.'">Place Here</a></font></td></tr>';
			}
		}

		echo '<tr'.$bgcolor.'><td>'.$cat->name.'</td><td>'.((strlen($cat->description)>30)?substr($cat->description, 0, 30).'...':$cat->description.'&nbsp;').'</td><td>'.(($cat->allow_collapse=='Y')?'Yes':'No').'</td><td>'.$cat->default_view.'</td><td nowrap>[<a href="admforum.php?cat_id='.$cat->id.'&'._rsid.'">Edit Forums</a>] [<a href="admcat.php?edit='.$cat->id.'&'._rsid.'">Edit Category</a>] [<a href="admcat.php?ct='.$cat->id.'&act=del&'._rsid.'">Delete</a>]</td><td>[<a href="admcat.php?chpos='.$cat->view_order.'&'._rsid.'">Change</a>]</td></tr>';
	}
	
	if ( isset($chpos) && $chpos != $cat->view_order ) {
		echo '<tr bgcolor="#efefef"><td align=center colspan=7><font size=-1><a href="admcat.php?chpos='.$chpos.'&newpos='.$cat->view_order.'&'._rsid.'">Place Here</a></font></td></tr>';
	}
	
?>
</table>
<?php readfile('admclose.html'); ?>