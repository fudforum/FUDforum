<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: admlangsel.php,v 1.1.1.1 2002/06/17 23:00:09 hackie Exp $
****************************************************************************

****************************************************************************
*
*       This program is free software; you can redistribute it and/or modify
*       it under the terms of the GNU General Public License as published by
*       the Free Software Foundation; either version 2 of the License, or
*       (at your option) any later version.
*
***************************************************************************/

	define('admin_form', 1);

	include_once "GLOBALS.php";
	
	fud_use('static/adm.inc');
	fud_use('static/glob.inc');
	
	list($ses, $usr) = initadm();
	
	include('admpanel.php');
	
	if ( $lng ) {
		if( empty($locale) ) {
			switch( $lng ) 
			{
				case 'german':
					$locale = 'german';
					break;
				case 'swedish':
					$locale = 'swedish';
					break;
				case 'polish':
					$locale = 'polish';
					break;	
				default:
					$locale = 'english';
			}	
		}
		
		$global_config = read_global_config();
		change_global_val('LANGUAGE', $lng, $global_config);
		change_global_val('LOCALE', $locale, $global_config);
		write_global_config($global_config);
		
		$LANGUAGE = $lng;
		$LOCALE = $locale;
		
		fud_use('static/compiler.inc');
		fud_use('static/lang.inc');
		
		switch_lang();
		compile_all();

		echo "Language changed to '$LANGUAGE'<br>\n";
	}

	$curdir = getcwd();
	chdir($GLOBALS['TEMPLATE_DIR'].'i18n');
	$dp = opendir('.');
	readdir($dp); readdir($dp);
	while ( $de = readdir($dp) ) {
		if( !@file_exists($de.'/msg') ) continue;
		$checked = ($de==$LANGUAGE?' selected':'');
		$sel_opt .= "<option value=\"$de\"$checked>$de</option>\n";
		$charset[$de] = filetomem($de.'/charset');
	}
	closedir($dp);
	chdir($curdir);
	
	switch( $LANGUAGE ) 
	{
		case 'german':
			$locale = 'german';
			break;
		case 'swedish':
			$locale = 'swedish';
			break;
		case 'polish':
			$locale = 'polish';
			break;	
		default:
			$locale = 'english';
	}	
?>	
<script>
function update_locale()
{
	switch( document.admlan.lng.value )
	{
		case 'german':
			document.admlan.locale.value = 'german';
			break;
		case 'swedish':
			document.admlan.locale.value = 'swedish';
			break;
		case 'polish':
			document.admlan.locale.value = 'polish';
			break;	
		default:
			document.admlan.locale.value = 'english';
	}
}
</script>
<table cellspacing=2 cellpadding=2 border=0>
<form action="admlangsel.php" name="admlan" method="post"><?php echo _hs; ?>
<tr>
	<td><b>Language:</b></td>
	<td><select name="lng" onChange="javascript: update_locale();"><?php echo $sel_opt; ?></select></td>
</tr>
<tr>
	<td><b>Locale:</b><br><font size="-1" color="#ff0000">Do not change this option unless your 100% sure you know what it does.</font></td>
	<td><input type="text" name="locale" value="<?php echo $locale; ?>"></td>
</tr>
<tr>
	<td colspan=2 align="right"><input type="submit" name="submit" value="Set"></td>
</tr>
</form>
</table>

<?php readfile('admclose.html'); ?>