<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: admapprove_avatar.php,v 1.2 2002/06/18 18:26:09 hackie Exp $
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
	define('no_inline', 1);

	include_once "GLOBALS.php";
	
	fud_use('db.inc');
	fud_use('time.inc');
	fud_use('cookies.inc');
	fud_use('users.inc');
	fud_use('ssu.inc');
	fud_use('static/adm.inc');
	fud_use('util.inc');
	fud_use('static/util_adm.inc');
	
	list($ses, $usr) = initadm();
	
	$avatar_dir = '../images/custom_avatars/';
	
	/* check for convert */
	if ( !empty($fixit) && $MOGRIFY_BIN ) {
		exec($MOGRIFY_BIN.' -geometry '.$GLOBALS['CUSTOM_AVATAR_MAX_DIM'].' +profile iptc +profile icm +comment '.$avatar_dir.$fixit);
		header("Location: admapprove_avatar.php?"._rsid."&rand=".get_random_value());
		exit();
	}
	
	if ( !empty($usr_id) ) {
		$usr_a = new fud_user_adm;
		$usr_a->get_user_by_id($usr_id);
		$usr_a->approve_avatar();
		header("Location: admapprove_avatar.php?"._rsid."&rand=".get_random_value());
		exit();
	}
	
	if ( !empty($del) ) {
		$usr_a = new fud_user_adm;
		$usr_a->get_user_by_id($del);
		if ( !strlen($usr_a->avatar_loc) ) 
			@unlink($avatar_dir.$del);

		$usr_a->unapprove_avatar();
		header("Location: admapprove_avatar.php?"._rsid."&rand=".get_random_value());
		exit();
	}
	cache_buster();
	include('admpanel.php'); 
?>
<h2>Avatar Approval System</h2>	
<table border=0 cellspacing=0 cellpadding=3>
<?php
	$a=0;
	
	$r = q("SELECT * FROM ".$GLOBALS['MYSQL_TBL_PREFIX']."users WHERE avatar_loc IS NULL AND avatar_approved='N' ORDER BY id");
	while ( $obj = db_rowobj($r) ) {
		if ( $MOGRIFY_BIN ) {
			$fix_it = ' [<a href="admapprove_avatar.php?fixit='.$obj->id.'&'._rsid.'">Fix It</a>]';
		}
		
		$a=1;
		
		echo '<tr bgcolor="#bff8ff"><td>
		<table border=0 cellspacing=0 cellpadding=0 width="100%">
		<tr><td align=left>
		'.$obj->login.'
		</td>
		<td align=right>
			[<a href="admapprove_avatar.php?usr_id='.$obj->id.'&'._rsid.'">Approve</a>] [<a href="admapprove_avatar.php?del='.$obj->id.'&'._rsid.'">Delete</a>]'.$fix_it.'
		</td>
		</table>
		
		</td></tr>';
		echo '<tr bgcolor="#bff8ff"><td align=center>(local)<br><img src="'.$avatar_dir.$obj->id.'?rnd='.get_random_value().'" border=0></td></tr>';
		
	}
	qf($r);

	$r = q("SELECT * FROM ".$GLOBALS['MYSQL_TBL_PREFIX']."users WHERE avatar_loc IS NOT NULL AND avatar_approved='N' ORDER BY id");
	while ( $obj = db_rowobj($r) ) {
		$a=1;
		echo '<tr bgcolor="#bff8ff"><td>
		<table border=0 cellspacing=0 cellpadding=0 width="100%">
		<tr><td align=left>
		'.$obj->login.'
		</td>
		<td align=right>
			[<a href="admapprove_avatar.php?usr_id='.$obj->id.'&'._rsid.'">Approve</a>] [<a href="admapprove_avatar.php?del='.$obj->id.'&'._rsid.'">Delete</a>]
		</td>
		</table>
		
		</td></tr>';
		echo '<tr bgcolor="#bff8ff"><td align=middle>'.$obj->avatar_loc.'<br><img src="'.$obj->avatar_loc.'" border=0></td></tr>';
	}
	qf($r);
?>
</table>
<?php if( empty($a) ) echo 'There is nothing to approve.'; ?>
<?php readfile('admclose.html'); ?>