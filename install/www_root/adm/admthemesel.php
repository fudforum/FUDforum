<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: admthemesel.php,v 1.12 2003/05/12 16:49:55 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

	@set_time_limit(6000);

	require('GLOBALS.php');
	fud_use('adm.inc', true);

	if (isset($_POST['tname'], $_POST['tlang'], $_POST['ret'])) {
		header('Location: '.$_POST['ret'].'.php?tname='.$_POST['tname'].'&tlang='.$_POST['tlang'].'&'._rsidl);
		exit;
	}

	$ret = isset($_GET['ret']) ? $_GET['ret'] : 'tmpllist';

	require($WWW_ROOT_DISK . 'adm/admpanel.php'); 	

	list($def_thm, $def_tmpl) = db_saq('SELECT name, lang FROM '.$GLOBALS['DBHOST_TBL_PREFIX'].'themes WHERE t_default=\'Y\'');
?>
<h3>Template Set Selection</h3>
<form method="post" action="admthemesel.php">
<input type="hidden" name="ret" value="<?php echo $ret; ?>">
<table border=0 cellspacing=1 cellpadding=3>
<tr bgcolor="#bff8ff">
<?php
	echo _hs;
	$path = $GLOBALS['DATA_DIR'].'/thm';
	
	$dp = opendir($path);
	readdir($dp); readdir($dp);
	echo '<td>Template Set:</td><td><select name="tname">';
	while ($de = readdir($dp)) {
		if ($de == 'CVS' || !@is_dir($path . '/' . $de)) {
			continue;
		}
		echo '<option value="'.$de.'"'.($de == $def_thm ? ' selected' : '').'>'.$de.'</option>';
	}
	echo '</select></td>';
?>
</tr>
<tr bgcolor="#bff8ff">
<?php	
	$path .= '/default/i18n';
	
	$dp = opendir($path);
	readdir($dp); readdir($dp);
	echo '<td>Language:</td><td><select name="tlang">';
	while ($de = readdir($dp)) {
		if ($de == 'CVS' || !@is_dir($path . '/' . $de)) {
			continue;
		}
		echo '<option value="'.$de.'"'.($de == $def_tmpl ? ' selected' : '').'>'.$de.'</option>';
	}
	echo '</select></td>';
?>
</tr>
<?php
	echo '<tr bgcolor="#bff8ff" align=right><td colspan=2><input type="submit" name="btn_submit" value="Edit"></td></td>';
?>
</tr>
</table>
</form>
<?php require($WWW_ROOT_DISK . 'adm/admclose.html'); ?>