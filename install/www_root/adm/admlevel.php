<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: admlevel.php,v 1.1.1.1 2002/06/17 23:00:09 hackie Exp $
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
	fud_use('static/level_adm.inc');	
	fud_use('util.inc');
	fud_use('static/adm.inc');
	fud_use('objutil.inc');
	
	list($ses, $adm) = initadm();
	
	cache_buster();
	
	$lev = new fud_level;
	
	if ( !empty($lev_cancel) ) {
		header("Location: admlevel.php?"._rsid);
		exit();
	}

	if ( @count($HTTP_POST_VARS) > 1 ) {
		if ( !$edit ) {	
			fetch_vars('lev_', $lev, $HTTP_POST_VARS);
			$lev->add();
		}
		else if ( $edit ) {
			$lev->get_by_id($edit);
			fetch_vars('lev_', $lev, $HTTP_POST_VARS);
			$lev->sync();
		}
		/* update levels */
		
		header("Location: admlevel.php?"._rsid);
	}
	
	if ( !empty($act) && $act=='del' && !empty($del_id) ) {
		$lev->get_by_id($del_id);
		$lev->delete();
		/* update levels */

		header("Location: admlevel.php?"._rsid);
	}

	if ( !empty($edit) && !(@count($HTTP_POST_VARS)>1) ) {
		$lev->get_by_id($edit);
		export_vars('lev_', $lev);
	}
	
	if( !empty($rebuild_levels) ) {
		DB_LOCK($GLOBALS['MYSQL_TBL_PREFIX'].'users+, '.$GLOBALS['MYSQL_TBL_PREFIX'].'level+');

		$pl = 2000000000;
		$r = Q("SELECT id,post_count,name FROM ".$GLOBALS['MYSQL_TBL_PREFIX']."level ORDER BY post_count DESC");
		while( $obj = DB_ROWOBJ($r) ) {
			Q("UPDATE ".$GLOBALS['MYSQL_TBL_PREFIX']."users SET level_id=".$obj->id." WHERE posted_msg_count<".$pl." AND posted_msg_count>=".$obj->post_count);
			$pl = $obj->post_count;
		}
		QF($r);
		DB_UNLOCK();
	}

	$img_path = '../images/';
	
	include('admpanel.php'); 
?>
<h2>Rank Manager</h2>
<div align="center"><font size="+1" color="#ff0000">If you've made any modification to the user ranks<br>YOU MUST RUN CACHE REBUILDER by &gt;&gt; <a href="admlevel.php?rebuild_levels=1&<?php echo _rsid; ?>">clicking here</a> &lt;&lt;</font></div>
<form method="post" name="lev_form">
<?php echo _hs; ?>
<table border=0 cellspacing=1 cellpadding=2>
	<tr bgcolor="#bff8ff">
		<td>Rank Name</td>
		<td><input type="text" name="lev_name" value="<?php echo (!isset($lev_name)?'':htmlspecialchars($lev_name)); ?>"></td>
	</tr>
	<tr bgcolor="#bff8ff">
		<td>Rank Image</td>
		<td><input type="text" name="lev_img" value="<?php echo (!isset($lev_img)?'':htmlspecialchars($lev_img)); ?>"><br>
		<font size=-1>Your image path is: <a href="#" onClick="javascript: document.lev_form.lev_img.value='<?php echo $img_path; ?>'+document.lev_form.lev_img.value; "><?php echo $img_path; ?></a></font></td>
	</tr>
	
	<tr bgcolor="#bff8ff">
		<td>Which Image to Show:</td>
		<td><?php draw_select("lev_pri", "Avatar & Rank Image\nAvatar Only\nRank Image Only", "B\nA\nL", $lev_pri); ?></td>
	</tr>
	
	<tr bgcolor="#bff8ff">
		<td>Post Count</td>
		<td><input type="text" name="lev_post_count" value="<?php echo (!isset($lev_post_count)?'':$lev_post_count); ?>" size=11 maxLength=10></td>
	</tr>
	
	<tr>
		<td colspan=2 bgcolor="#bff8ff" align=right>
		<?php
			if ( empty($edit) ) {
				echo '<input type="submit" name="lev_submit" value="Add Level">';
			}
			else {
				echo '<input type="submit" name="lev_cancel" value="Cancel"> ';
				echo '<input type="submit" name="lev_update" value="Update">';
			}
		?>
		</td>
	</tr>
</table>
</form>

<?php $lev->get_all_levels(); ?>
<table border=0 cellspacing=1 cellpadding=1>
<tr bgcolor="#e5ffe7">
	<td>Name</td>
	<td>Post Count</td>
	<td>Action</td>
</tr>
<?php

	$lev->resetlev();
	$i=1;
	while ( $obj=$lev->nextlev() ) {
		$bgcolor = (($i++)%2) ? ' bgcolor="#fffee5"':'';
		if ( !empty($edit) && $edit == $obj->id ) $bgcolor=' bgcolor="#ffb5b5"';
		echo "<tr$bgcolor><td>$obj->name</td><td align=center>$obj->post_count</td><td><a href=\"admlevel.php?edit=$obj->id&"._rsid."\">Edit</a> | <a href=\"admlevel.php?act=del&del_id=$obj->id&"._rsid."\">Delete</a></td></tr>\n";
	}
?>
</table>
<?php require('admclose.html'); ?>