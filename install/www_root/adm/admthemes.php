<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: admthemes.php,v 1.20 2002/10/26 23:32:12 hackie Exp $
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
	
	fud_use('widgets.inc', true);
	fud_use('util.inc');
	fud_use('objutil.inc');
	fud_use('adm.inc', true);
	@fud_use('theme.inc');
	
	if ( !function_exists('default_theme') ) {
		echo "<html>Can't locate theme header, compiling default theme<br>";
		fud_use('compiler.inc', true);
		compile_all('default', 'english', 'default');
		echo('<a href="admthemes.php?'._rsid.'&rand='.get_random_value().'">Try again</a></html>');
		exit();
	}

function cleandir($dir)
{
	if( !@is_dir($dir) ) {
		echo "Couldn't delete $dir, directory does not exist<br>\n";
		return;
	}

	$od = getcwd();
	chdir($dir);
	
	$dp = opendir('.');
	readdir($dp); readdir($dp);
	 
	while( $file = readdir($dp) ) {
		if( $file == 'GLOBALS.php' || $file == 'oldfrm_upgrade.php' || @is_link($file) ) continue;
	
		if( @is_dir($file) ) 
			cleandir($file);
		else 
			if( !unlink($file) ) echo "Couldn't remove (<b>".realpath($file)." -> ".$file."</b>)<br>\n";
	}
	
	closedir($dp);
	chdir($od);
	rmdir($dir);
}


	list($ses, $usr) = initadm();
	
	$thm = new fud_theme;
	
	if ( $btn_cancel ) {
		header("Location: admthemes.php?"._rsidl.'&rand='.get_random_value());
		exit();
	}
	
	if ( $nn=$HTTP_POST_VARS['newname'] ) {
		if ( !bq("SELECT * FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."themes WHERE name='$nn'") ) 
		{
			fud_use('compiler.inc', true);
			$root = $GLOBALS['DATA_DIR'].'thm/';
			$root_nn = $root.$nn;
			$u=umask(0);
			if ( !@is_dir($root_nn) && !@mkdir($root_nn, 0777) ) exit("can't create ($root_nn)<br>\n"); 
			fudcopy($root.'default/', $root_nn, '!.*!', true);
			umask($u);
		}
		header("Location: admthemes.php?"._rsidl.'&rand='.get_random_value());
		exit();
	}
	
	if ( !$btn_cancel && $HTTP_POST_VARS['thm_theme'] && !$edit ) {
		fetch_vars('thm_', $thm, $HTTP_POST_VARS);
		$thm->add();
		fud_use('compiler.inc', true);
		compile_all($thm->theme, $thm->lang, $thm->name);
		header("Location: admthemes.php?"._rsidl.'&rand='.get_random_value());
		exit();
	}
	else if ( $edit && $HTTP_POST_VARS['thm_theme'] ) {
		$thm->get($edit);
		$thm->enabled = '';
		$thm->t_default = '';
		fetch_vars('thm_', $thm, $HTTP_POST_VARS);
		if ( $thm->id == 1 ) $thm->name = 'default';
		$thm->sync();
		fud_use('compiler.inc', true);
		compile_all($thm->theme, $thm->lang, $thm->name);
		header("Location: admthemes.php?"._rsidl.'&rand='.get_random_value());
		exit();
	}

	if ( $rebuild ) {
		$thm->get($rebuild);
		fud_use('compiler.inc', true);
		compile_all($thm->theme, $thm->lang, $thm->name);
		header("Location: admthemes.php?"._rsidl.'&rand='.get_random_value());
		exit();
	}

	if ( $edit && !$prevloaded ) {
		$thm->get($edit);
		export_vars('thm_', $thm);
	}
	
	if ( is_numeric($del) && $del>1 ) {
		$thm->get($del);
		$thm->delete();

		cleandir($GLOBALS['WWW_ROOT_DISK'].'theme/'.$thm->name);

		header("Location: admthemes.php?"._rsidl.'&rand='.get_random_value());
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
	<?php if ( isset($edit) && $edit == 1 ) { ?>
	<td><?php echo htmlspecialchars($thm_name); ?></td>
	<?php } else { ?>
	<td><input type="text" name="thm_name" value="<?php echo htmlspecialchars($thm_name); ?>"></td>
	<?php } ?>
</tr>

<tr bgcolor="#bff8ff">
	<td valign=top>Template Set:</td>
	<td>
	<select name="thm_theme">
	<?php
		$dp = opendir($DATA_DIR.'/thm');
		readdir($dp); readdir($dp);
		while ( $de = readdir($dp) ) {
			$dr = $DATA_DIR.'/thm/'.$de;
			if ( $de == 'CVS' || !is_dir($dr) || !is_dir($dr.'/tmpl') ) continue;
			$sel = $thm_theme == $de ? ' selected' : '';
			echo '<option'.$sel.'>'.$de.'</option>';
		}
		closedir($dp);
	?></select>
	</td>
</tr>
<tr bgcolor="#bff8ff">
	<td>Language</td>
	<td>
	<?php
		$dp = opendir($DATA_DIR.'/thm/default/i18n');
		readdir($dp); readdir($dp);
		$selopt = '';
		if ( !$thm_lang ) $thm_lang = 'english';
		while ( $de = readdir($dp) ) {
			$dr = $DATA_DIR.'/thm/default/i18n/'.$de;
			if ( $de == 'CVS' || !is_dir($dr) ) continue;
			$sel = $thm_lang == $de ? ' selected' : '';
			$selopt .= '<option'.$sel.'>'.$de.'</option>';
			$locales[$de]['locale'] = trim(filetomem($dr.'/locale'));
			$pspell_file = $dr.'/pspell_lang';
			if ( file_exists($pspell_file) )
				$locales[$de]['pspell_lang'] = trim(filetomem($pspell_file));
			else
				$locales[$de]['pspell_lang'] = 'en';
		}
		closedir($dp);
		
		$cases = '';
		foreach($locales as $k => $v) {
			$cases .= "case '$k': document.admthm.thm_locale.value = '".$v['locale']."'; ";
			$cases .= "document.admthm.thm_pspell_lang.value='".$v['pspell_lang']."'; ";
			$cases .= "break;\n";
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
	<?php echo $selopt; ?>
	</select>
	</td>
</tr>

<tr bgcolor="#bff8ff">
	<td>Locale:</td>
	<td><input type="text" name="thm_locale" value="<?php echo htmlspecialchars($thm_locale?$thm_locale:'english'); ?>" size=7></td>
</tr>

<tr bgcolor="#bff8ff">
	<td>pSpell Language:</td>
	<td>
		<input type="text" name="thm_pspell_lang" value="<?php echo htmlspecialchars(!$thm_pspell_lang&&!$edit?'en':$thm_pspell_lang); ?>" size=4>
		[<a href="javascript://" onClick="javascript: document.admthm.thm_pspell_lang.value=''">disable</a>]
	</td>
</tr>

<tr bgcolor="#bff8ff">
	<td colspan=2>
	<?php draw_checkbox('thm_t_default', 'Y', $thm_t_default);?> Default <?php draw_checkbox('thm_enabled', 'Y', $thm_enabled); ?> Enabled
	</td>
</tr>
<tr bgcolor="#bff8ff">
<?php if ( !$edit ) { ?>
		<td colspan=2 align=right><input type="submit" name="btn_submit" value="Add"></td>
<?php } else { ?>
	<td colspan=2 align=right>
		<input type="submit" name="btn_cancel" value="Cancel">
		<input type="submit" name="btn_update" value="Update">
	</td>
<?php } ?>
</tr>
</table>
<input type="hidden" name="prevloaded" value="1">
<input type="hidden" name="edit" value="<?php echo $edit; ?>">
</form>

<form method="post">
<table border=0 cellspacing=1 cellpadding=3>
<tr bgcolor="#bff8ff"><td colspan=2>Create New Template Set</td></tr>
<tr bgcolor="#bff8ff">
	<td>Name</td>
	<td><input type="text" name="newname"></td>
</tr>
<tr bgcolor="#bff8ff">
	<td colspan=2 align=right><input type="submit" name="btn_submit" value="Create"></td>
</tr>
</table>
<?php echo _hs; ?>
</form>

<table border=0 cellspacing=0 cellpadding=3>
<tr bgcolor="#e5ffe7">
	<td>Name</td>
	<td>Theme</td>
	<td>Language</td>
	<td>Locale</td>
	<td>pSpell Lang</td>
	<td>Enabled</td>
	<td>Default</td>
	<td>Action</td>
</tr>
	
<?php
	$r = q("SELECT * FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."themes ORDER BY id");
	while ( $obj = db_rowobj($r) ) {
		$bgcolor = ($i++%2)?' bgcolor="#fffee5"':'';
		if ( !empty($edit) && $edit==$obj->id ) $bgcolor =' bgcolor="#ffb5b5"';
		
		$act = '[<a href="admthemes.php?'._rsid.'&edit='.$obj->id.'&rand='.get_random_value().'">Edit</a>]';
		$act .= '[<a href="admthemes.php?'._rsid.'&rebuild='.$obj->id.'">Rebuild Theme</a>]';
		$act .= '[<a href="admoptimizer.php?'._rsid.'&tname='.$obj->name.'">Optimize Theme</a>]';
		
		if ( $obj->id != 1 ) $act .= '[<a href="admthemes.php?'._rsid.'&del='.$obj->id.'&rand='.get_random_value().'">Delete</a>]';
		
		
		
	
		echo "<tr$bgcolor>
			<td>".htmlspecialchars($obj->name)."</td>
			<td>".htmlspecialchars($obj->theme)."</td>
			<td>".htmlspecialchars($obj->lang)."</td>
			<td>".htmlspecialchars($obj->locale)."</td>
			<td>".((!$obj->pspell_lang)?'<font color="green">disabled</font>':htmlspecialchars($obj->pspell_lang))."</td>
			<td>".($obj->enabled=='Y'?'Yes':'<font color="green">No</font>')."</td>
			<td>".$obj->t_default."</td>
			<td nowrap>$act</td>
		</tr>\n";
	}
	qf($r);

?>
</table>
<?php readfile('admclose.html'); ?>