<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: admsmiley.php,v 1.1.1.1 2002/06/17 23:00:09 hackie Exp $
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
	
	fud_use('static/widgets.inc');
	fud_use('util.inc');
	fud_use('smiley.inc');
	fud_use('static/adm.inc');
	
	list($ses, $usr) = initadm();
	
	$smiley_dir = '../images/smiley_icons/';

	cache_buster();
	
	if ( !empty($btn_cancel) ) {
		header("Location: admsmiley.php?"._rsid);
	}
	
	if ( !empty($del) ) {
		$sml_d = new fud_smiley;
		$sml_d->get($del);
		$sml_d->delete();
		header("Location: admsmiley.php?"._rsid);
		exit();
	}
	
	if ( !empty($btn_update) && !empty($edit) ) {
		$sml_u = new fud_smiley;
		$sml_u->get($edit);
		$sml_u->fetch_vars($HTTP_POST_VARS, 'sml_');
		$sml_u->sync();
		header("Location: admsmiley.php?"._rsid);
		exit();
	}
	
	if ( !empty($edit) && empty($prl) ) {
		$sml_r = new fud_smiley;
		$sml_r->get($edit);
		$sml_r->export_vars('sml_');
	}
	
	if ( !empty($btn_submit) ) {
		$sml = new fud_smiley;
		$sml->fetch_vars($HTTP_POST_VARS, 'sml_');
		$sml->add();
		header("Location: admsmiley.php?"._rsid);
		exit();
	}
	
	if ( !empty($icoul_size) ) {
		/* check extention */
		if ( preg_match('/.*(\.jpg|\.jpeg|\.gif|\.png)$/i', $icoul_name) ) {
			$err = 0;
			if ( !empty($ico_lfname) && !preg_match('/.*(\.jpg|\.jpeg|\.gif|\.png)$/i', $ico_lfname) ) {
				$err = 1;
				$err2 = 1;
			}
			
			if ( !$err ) {
				$dst_name = $smiley_dir.(( isset($ico_lfname) ) ? $ico_lfname : $icoul_name);
				umask(0177);
				move_uploaded_file($icoul, $dst_name);
				$sml_img = $icoul_name;
			}
		}
		else $err = 1;
	}
	
	if ( $chpos && $chdest ) {
		$sml = new fud_smiley;
		$sml->get_by_vieworder($chpos);
		$sml->chpos($chdest);
		header("Location: admsmiley.php?"._rsid);
		exit();
	}
	
	include('admpanel.php'); 
	
	
	
	if ( empty($chpos) ) {
?>

<h2>Smiley Management System</h2>

<form name="frm_sml" method="post" enctype="multipart/form-data">
<table border=0 cellspacing=1 cellpadding=3>
<?php 
	echo _hs; 
	if ( @is_writeable($smiley_dir) ) { ?>	
		<tr bgcolor="#bff8ff">
			<td colspan=2><b>Smilies Upload (upload smiley into the system)</td>
		</tr>
		<tr bgcolor="#bff8ff">
			<td>Smilies Upload:</td>
			<td>
				<input type="file" name="icoul"> <input type="submit" name="btn_upload" value="Upload">
				<?php if ( !empty($err) ) { ?>
					<br><font size=-1 color="#ff0000">Only (*.gif, *.jpg, *.png) files are supported</font>
				<?php } ?>
			</td>
		</tr>
	<?php } else { ?>
		<tr bgcolor="#bff8ff"> 
			<td colspan=2><font color="#ff0000">Web server doesn't have write permissions to <b>'<?php echo realpath($smiley_dir); ?>'</b>, smiley upload disabled</font></td>
		</tr>
	<?php } ?>
	
	<tr><td colspan=2>&nbsp;</td></tr>

	<tr bgcolor="#bff8ff">
		<td colspan=2><a name="img"><b>Smilies Mangement</b></a></td>
	</tr>

	<tr bgcolor="#bff8ff">
		<td>Smiley Description:</td>
		<td><input type="text" name="sml_descr" value="<?php echo (empty($sml_descr)?'':htmlspecialchars($sml_descr)); ?>"></td>
	</tr>
	
	<tr bgcolor="#bff8ff"> 
		<td>Smiley Text:<br><font size=-1>Will be replaced with smiley,<br>use <b>~</b> to seperate multiple allowed codes</font></td>
		<td><input type="text" name="sml_code" value="<?php echo (empty($sml_code)?'':htmlspecialchars($sml_code)); ?>"></td>
	</tr>
		
	<tr bgcolor="#bff8ff">
		<td valign=top><a name="sml_sel">Smiley Image:</a></td>
		<td>
			<input type="text" name="sml_img" value="<?php echo (empty($sml_img)?'':htmlspecialchars($sml_img)); ?>" onChange="javascript: if ( document.frm_sml.sml_img.value.length ) document.prev_icon.src='<?php echo $smiley_dir; ?>' + document.frm_sml.sml_img.value; else document.prev_icon.src='../blank.gif';">
			[<a href="javascript://" onClick="javascript:window.open('admsmileysel.php?<?php echo _rsid; ?>', 'admsmileysel', 'menubar=false,scrollbars=yes,resizable=yes,height=300,width=500,screenX=100,screenY=100');">SELECT ICON</a>]
		</td>
	</tr>
	
	<tr bgcolor="#bff8ff">
		<td>Preview Image:</td>
		<td>
			<table border=1 cellspacing=1 cellpadding=2 bgcolor="#ffffff">
				<tr><td align=center valign=center>
					<img src="<?php echo ( strlen($sml_img) )?$smiley_dir.$sml_img:'../blank.gif'; ?>" name="prev_icon" border=0>
				</td></tr>
			</table>
				
		
		</td>
			
	</tr>
	
	<tr bgcolor="#bff8ff">
		<?php
			if ( empty($edit) ) {
				echo '<td colspan=2 align=right><input type="submit" name="btn_submit" value="Add Smiley"></td>';
			}
			else {
				echo '<td colspan=2 align=right><input type="submit" name="btn_cancel" value="Cancel">
				   <input type="submit" name="btn_update" value="Update"></td>';
			}
		?>
	</tr>
	
</table>
<input type="hidden" name="edit" value="<?php echo $edit; ?>">
<input type="hidden" name="prl" value="1">
</form>
<? } /*if empty($chpos) */ ?>
<table border=0 cellspacing=3 cellpadding=2>
<tr bgcolor="#e5ffe7">
	<td>Smiley</td>
	<td>Text</td>
	<td>Description</td>
	<td>Action</td>
</tr>
<?php
	$sml_draw = new fud_smiley;
	$sml_draw->getall();
	$sml_draw->resets();
	
	$i=1;
	while ( $obj = $sml_draw->eachs() ) {
		$bgcolor = ($i++%2)?' bgcolor="#fffee5"':'';
		if ( !empty($edit) && $edit==$obj->id ) $bgcolor =' bgcolor="#ffb5b5"';
		if ( $chpos == $obj->vieworder ) $bgcolor = ' bgcolor="#ffb5b5"';
		
		if ( $chpos && $chpos != $obj->vieworder && ($chpos+1) != $obj->vieworder ) {
			if ( $obj->vieworder > $chpos ) 
				$newvieworder = $obj->vieworder-1;
			else
				$newvieworder = $obj->vieworder;
			
			echo "<tr bgcolor=\"#efefef\"><td colspan=4 align=middle><a href=\"admsmiley.php?"._rsid."&chpos=$chpos&chdest=$newvieworder\">Place Here</a></td></tr>";
		}

		$ctl = ( $chpos ) ? "<td></td>" : "<td nowrap>[<a href=\"admsmiley.php?edit=$obj->id&"._rsid."#img\">Edit</a>] [<a href=\"admsmiley.php?del=$obj->id&"._rsid."\">Delete</a>] [<a href=\"admsmiley.php?chpos=$obj->vieworder&"._rsid."\">Change Position</a>]</td>";
		
		echo "<tr$bgcolor><td>$obj->vieworder -> <img src=\"$smiley_dir$obj->img\" border=0></td><td>$obj->code</td><td>$obj->descr</td>$ctl</tr>\n";
		$pobj = $obj;
	}
	
	if ( $chpos && $chpos!=$pobj->vieworder ) 
		echo "<tr bgcolor=\"#efefef\"><td colspan=4 align=middle><a href=\"admsmiley.php?"._rsid."&chpos=$chpos&chdest=".($pobj->vieworder)."\">Place Here</a></td></tr>";
?>
</table>
<?php require('admclose.html'); ?>