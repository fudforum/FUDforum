<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: admthemes.php,v 1.1.1.1 2002/06/17 23:00:09 hackie Exp $
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
	fud_use('objutil.inc');
	fud_use('static/adm.inc');
	@fud_use('theme.inc');
	
	if ( !function_exists('default_theme') ) {
		echo "<html>Can't locate theme header, compiling default theme<br>";
		fud_use('static/compiler.inc');
		compile_all('default', 'english', 'default');
		echo('<a href="admthemes.php?'._rsid.'&rand='.get_random_value().'">Try again</a></html>');
		exit();
	}

function cleandir($dir)
{
	$od = getcwd();
	chdir($dir);
	
	$dp = opendir('.');
	readdir($dp); readdir($dp);
	 
	while( $file = readdir($dp) ) {
		if( $file == 'GLOBALS.php' || $file == 'oldfrm_upgrade.php' || @is_link($file) ) continue;
	
		if( @is_dir($file) ) 
			cleandir($file);
		else
			unlink($file);		
	}
	
	closedir($dp);
	chdir($od);
}


	list($ses, $usr) = initadm();
	
	$thm = new fud_theme;
	
	if ( $btn_cancel ) {
		header("Location: admthemes.php?"._rsid.'&rand='.get_random_value());
		exit();
	}
	
	if ( $nn=$HTTP_POST_VARS['newname'] ) {
		if ( !BQ("SELECT * FROM ".$GLOBALS['MYSQL_TBL_PREFIX']."themes WHERE name='$nn'") ) 
		{
			fud_use('static/compiler.inc');
			$root = $GLOBALS['DATA_DIR'].'themes/';
			$root_nn = $root.$nn;
			$u=umask(0);
			if ( !@mkdir($root_nn, 0777) ) exit("can't create ($root_nn)<br>\n"); 
			fudcopy($root.'default/', $root_nn, '!.*!', TRUE);
			umask($u);
		}
		header("Location: admthemes.php?"._rsid.'&rand='.get_random_value());
		exit();
	}
	
	if ( !$btn_cancel && $HTTP_POST_VARS['thm_theme'] && !$edit ) {
		fetch_vars('thm_', $thm, $HTTP_POST_VARS);
		$thm->add();
		fud_use('static/compiler.inc');
		compile_all($thm->theme, $thm->lang, $thm->name);
		header("Location: admthemes.php?"._rsid.'&rand='.get_random_value());
		exit();
	}
	else if ( $edit && $HTTP_POST_VARS['thm_theme'] ) {
		$thm->get($edit);
		$thm->enabled = '';
		$thm->t_default = '';
		fetch_vars('thm_', $thm, $HTTP_POST_VARS);
		if ( $thm->id == 1 ) $thm->name = 'default';
		$thm->sync();
		fud_use('static/compiler.inc');
		compile_all($thm->theme, $thm->lang, $thm->name);
		header("Location: admthemes.php?"._rsid.'&rand='.get_random_value());
		exit();
	}

	if ( $rebuild ) {
		$thm->get($rebuild);
		fud_use('static/compiler.inc');
		compile_all($thm->theme, $thm->lang, $thm->name);
		header("Location: admthemes.php?"._rsid.'&rand='.get_random_value());
		exit();
	}

	if ( $edit && !$prevloaded ) {
		$thm->get($edit);
		export_vars('thm_', $thm);
	}
	
	if ( $del && $del != 1 ) {
		$thm->get($del);
		$thm->delete();
		cleandir($GLOBALS['WWW_ROOT_DISK'].'themes/'.$thm->name.'/images');
		rmdir($GLOBALS['WWW_ROOT_DISK'].'themes/'.$thm->name.'/images');
		cleandir($GLOBALS['WWW_ROOT_DISK'].'themes/'.$thm->name);
		rmdir($GLOBALS['WWW_ROOT_DISK'].'themes/'.$thm->name);
		$obj = default_theme();
		Q("UPDATE ".$GLOBALS['MYSQL_TBL_PREFIX']."users SET theme=$obj->id WHERE theme=$thm->id");
		header("Location: admthemes.php?"._rsid.'&rand='.get_random_value());
		exit();
	}
	
	include('admpanel.php');
?>
<h2>Theme Management</h2>

<form name="admthm" action="admthemes.php" method="post">
<?php echo _hs; ?>
<table border=0 cellspacing=1 cellpadding=3>
<tr bgcolor="#bff8ff">
	<td>Name:</td>
	<? if ( isset($edit) && $edit == 1 ) { ?>
	<td><?php echo htmlspecialchars($thm_name); ?></td>
	<? } else { ?>
	<td><input type="text" name="thm_name" value="<?php echo htmlspecialchars($thm_name); ?>"></td>
	<? } ?>
</tr>

<tr bgcolor="#bff8ff">
	<td valign=top>Theme:</td>
	<td>
	<select name="thm_theme">
	<?
		$oldpwd = getcwd();
		chdir($DATA_DIR.'/themes');
		$dp = opendir('.');
		readdir($dp); readdir($dp);
		while ( $de = readdir($dp) ) {
			if ( $de == 'CVS' || !is_dir($de) || !is_dir($de.'/tmpl') ) continue;
			$sel = $thm_theme == $de ? ' selected' : '';
			echo '<option'.$sel.'>'.$de.'</option>';
		}
		closedir($dp);
		chdir($oldpwd);
	?></select>
	</td>
</tr>
<tr bgcolor="#bff8ff">
	<td>Language</td>
	<td>
	<?
		$oldpwd = getcwd();
		chdir($DATA_DIR.'/themes/default/i18n');
		$dp = opendir('.');
		readdir($dp); readdir($dp);
		$selopt = '';
		while ( $de = readdir($dp) ) {
			if ( $de == 'CVS' || !is_dir($de) ) continue;
			$sel = $thm_lang == $de ? ' selected' : '';
			$selopt .= '<option'.$sel.'>'.$de.'</option>';
			$locales[$de] = trim(filetomem($de.'/locale'));
		}
		closedir($dp);
		chdir($oldpwd);
		
		reset($locales);
		$cases = '';
		while ( list($k, $v) = each($locales) ) {
			$cases .= "case '$k': document.admthm.thm_locale.value = '$v'; break;\n";
		}
	?>
<script>
function update_locale()
{
	switch( document.admthm.thm_lang.value )
	{
		<?echo $cases; ?>
	}
}
</script>

	<select name="thm_lang" onChange="javascript: update_locale();">
	<? echo $selopt; ?>
	</select>
	</td>
</tr>
<tr bgcolor="#bff8ff">
	<td>Locale:</td><td><input type="text" name="thm_locale" value="<? echo htmlspecialchars($thm_locale?$thm_locale:'english'); ?>" size=7></td>
</tr>
<tr bgcolor="#bff8ff">
	<td colspan=2>
	<?php draw_checkbox('thm_t_default', 'Y', $thm_t_default);?> Default <? draw_checkbox('thm_enabled', 'Y', $thm_enabled); ?> Enabled
	</td>
</tr>
<tr bgcolor="#bff8ff">
<? if ( !$edit ) { ?>
		<td colspan=2 align=right><input type="submit" name="btn_submit" value="Add"></td>
<? } else { ?>
	<td colspan=2 align=right>
		<input type="submit" name="btn_cancel" value="Cancel">
		<input type="submit" name="btn_update" value="Update">
	</td>
<? } ?>
</tr>
</table>
<input type="hidden" name="prevloaded" value="1">
<input type="hidden" name="edit" value="<?php echo $edit; ?>">
</form>

<form method="post">
<table border=0 cellspacing=1 cellpadding=3>
<tr bgcolor="#bff8ff"><td colspan=2>Create New Theme</td></tr>
<tr bgcolor="#bff8ff">
	<td>Name</td>
	<td><input type="text" name="newname"></td>
</tr>
<tr bgcolor="#bff8ff">
	<td colspan=2 align=right><input type="submit" name="btn_submit" value="Create"></td>
</tr>
</table>
<? echo _hs; ?>
</form>

<table border=0 cellspacing=0 cellpadding=3>
<tr bgcolor="#e5ffe7">
	<td>Name</td>
	<td>Theme</td>
	<td>Language</td>
	<td>Locale</td>
	<td>Enabled</td>
	<td>Default</td>
	<td>Action</td>
</tr>
	
<?
	$r = Q("SELECT * FROM ".$GLOBALS['MYSQL_TBL_PREFIX']."themes ORDER BY id");
	while ( $obj = DB_ROWOBJ($r) ) {
		$bgcolor = ($i++%2)?' bgcolor="#fffee5"':'';
		if ( !empty($edit) && $edit==$obj->id ) $bgcolor =' bgcolor="#ffb5b5"';
		
		$act = '[<a href="admthemes.php?'._rsid.'&edit='.$obj->id.'&rand='.get_random_value().'">Edit</a>]';
		$act .= '[<a href="admthemes.php?'._rsid.'&rebuild='.$obj->id.'">Rebuild Theme</a>]';
		
		if ( $obj->id != 1 ) $act .= '[<a href="admthemes.php?'._rsid.'&del='.$obj->id.'&rand='.get_random_value().'">Delete</a>]';
		
		
	
		echo "<tr$bgcolor>
			<td>".htmlspecialchars($obj->name)."</td>
			<td>".htmlspecialchars($obj->theme)."</td>
			<td>".htmlspecialchars($obj->lang)."</td>
			<td>".htmlspecialchars($obj->locale)."</td>
			<td>".$obj->enabled."</td>
			<td>".$obj->t_default."</td>
			<td nowrap>$act</td>
		</tr>\n";
	}
	QF($r);

?>
</table>
<?php readfile('admclose.html'); ?>