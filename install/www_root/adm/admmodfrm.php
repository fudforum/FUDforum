<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: admmodfrm.php,v 1.8 2002/09/18 20:52:08 hackie Exp $
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
	
	fud_use('cat.inc');
	fud_use('forum.inc');
	fud_use('util.inc');
	fud_use('adm.inc', true);
	fud_use('users.inc');
	fud_use('rev_fmt.inc');
	
	list($ses, $usr_adm) = initadm();
	
	$usr = new fud_user_adm;
	$cat = new fud_cat_adm;
	$frm = new fud_forum_adm;
	
	$usr->get_user_by_id($usr_id);

	if ( !empty($mod_submit) ) {
		$frm->get_all_forums();
		$frm->resetfrm();
		
		$mod = NULL;
		
		$usr->start_mod();
		$usr->de_moderate();		
		while ( $frm->nextfrm() ) {
			if ( isset($HTTP_POST_VARS['mod_allow_'.$frm->id]) && $HTTP_POST_VARS['mod_allow_'.$frm->id] == 'Y' ) {
				$usr->mk_moderator($frm->id);
				$mod = 1;
			}
		}
		$usr->end_mod();
		
		if( $usr->is_mod != 'A' ) q("UPDATE ".$GLOBALS['DBHOST_TBL_PREFIX']."users SET is_mod='".(($mod)?'Y':'N')."' WHERE id=".$usr->id);
		
		/* mod rebuild */	
		rebuildmodlist();

		reverse_FMT($usr->alias);
		exit("<html><script language=\"JavaScript\">\nwindow.opener.location='admuser.php?usr_login=".urlencode($usr->alias)."&"._rsid."'; window.close();\n</script></html>");
	}
	
?>
<html>
<body bgcolor="#ffffff">
<h3>Allowing <?php echo $usr->alias; ?> to moderate</h3>
<form name="frm_mod" method="post">
<?php echo _hs; ?>
<table border=0 cellspacing=1 cellpadding=2>
<?php
	$cat->get_all_cat();
	$cat->resetcat();
	
	while ( $cat->nextcat() ) {
		echo "<tr bgcolor=\"#e5ffe7\"><td colspan=2>$cat->name</td></tr>\n";
		
		$frm->get_cat_forums($cat->id);
		$frm->resetfrm();
		while ( $frm->nextfrm() ) {
			echo "<tr><td><input type=\"checkbox\" name=\"mod_allow_$frm->id\" value=\"Y\"".(($frm->is_moderator($usr->id))?' checked':'')."></td><td>$frm->name</td></tr>\n"; 
		}
	}


	/* deleted forums */
	$frm->get_cat_forums(0);
	if ( $frm->countfrm() ) {
		echo "<tr bgcolor=\"#e5ffe7\"><td colspan=2>DELETED FORUMS</td></tr>\n";
		$frm->resetfrm();
		while ( $frm->nextfrm() ) {
			echo "<tr><td><input type=\"checkbox\" name=\"mod_allow_$frm->id\" value=\"Y\"".(($frm->is_moderator($usr->id))?' checked':'')."></td><td>$frm->name</td></tr>\n"; 
		}
	}
?>
<tr>
	<td colspan=2 align=right><input type="submit" name="mod_submit" value="Apply"></td>
</tr>
</table>
<input type="hidden" name="usr_id" value="<?php echo $usr_id; ?>">
</form>
</html>