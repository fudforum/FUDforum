<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: admipfilter.php,v 1.2 2002/06/26 19:41:21 hackie Exp $
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
	
	fud_use('ipfilter.inc');
	fud_use('ipfilter_adm.inc', TRUE);
	fud_use('widgets.inc', TRUE);	
	fud_use('util.inc');
	fud_use('adm.inc', TRUE);
	
	list($ses, $usr) = initadm();

	if ( !empty($btn_cancel) ) {
		header("Location: admipfilter.php?"._rsid);
		exit();
	}
	
	/* Remove trailing . from an ip if avaliable */
	$ipaddr = trim($ipaddr);
	if( substr($ipaddr,-1) == '.' ) $ipaddr = substr($ipaddr, 0, -1);
	
	/* Handle double .. */
	$ipaddr = str_replace('..', '.255.', $ipaddr);
	
	if ( !empty($edit) && !empty($btn_update) ) {
		$flt = new fud_ip_filter_adm;
		$flt->get($edit);
		$flt->sync($ipaddr);
		header("Location: admipfilter.php?"._rsid);
		exit();
	}
	
	if ( !empty($btn_submit) ) {
		$flt = new fud_ip_filter_adm;
		$flt->add($ipaddr);
		header("Location: admipfilter.php?"._rsid);
		exit();
	}
	
	if ( !empty($edit) ) {
		$flt = new fud_ip_filter_adm;
		$flt->get($edit);
		$ipaddr = $flt->ipaddr();
	}
	
	if ( !empty($del) ) {
		$flt = new fud_ip_filter_adm;
		$flt->get($del);
		$flt->delete();
	}
	
	cache_buster();
	
include('admpanel.php'); 
?>
<h2>IP Filter System</h2>
<form method="post">  
<?php echo _hs; ?>
<table border=0 cellspacing=1 cellpadding=3>
	<tr bgcolor="#bff8ff">
		<td>IP Address</td>
		<td><input type="text" name="ipaddr" value="<?php echo (empty($ipaddr)?'':htmlspecialchars($ipaddr)); ?>" size=15 maxLength=15></td> 
	</tr>
	<tr bgcolor="#bff8ff">
		<td colspan=2 align=right>
		<?php
			if ( !empty($edit) ) {
				echo '<input type="submit" name="btn_cancel" value="Cancel"> ';
				echo '<input type="submit" name="btn_update" value="Update">';
			}
			else {
				echo '<input type="submit" name="btn_submit" value="Add mask">';
			}
		?>
		</td>
	</tr>
	
</table>
<input type="hidden" name="edit" value="<?php echo $edit; ?>">
</form>

<table border=0 cellspacing=3 cellpadding=2>
<tr bgcolor="#e5ffe7">
	<td>IP Mask</td>
	<td>Action</td>
</tr>
<?php
	$iplist = new fud_ip_filter_adm;
	$iplist->getall();
	
	$i=1;
	while ( $obj = $iplist->eachip() ) {
		$bgcolor = ($i++%2)?' bgcolor="#fffee5"':'';
		if ( !empty($edit) && $edit==$obj->id ) $bgcolor =' bgcolor="#ffb5b5"';
		$ctl = "<td>[<a href=\"admipfilter.php?edit=$obj->id&"._rsid."\">Edit</a>] [<a href=\"admipfilter.php?del=$obj->id&"._rsid."\">Delete</a>]</td>";
		echo "<tr$bgcolor><td><table border=0 cellspacing=0 cellpadding=0><tr><td>".$obj->ip[0].".</td><td>".$obj->ip[1].".</td><td>".$obj->ip[2].".</td><td>".$obj->ip[3]."</td></tr></table></td>$ctl</tr>";
	}
	
?>
</table>
<?php require('admclose.html'); ?>