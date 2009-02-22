<?php
/**
* copyright            : (C) 2001-2009 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: admmysql.php,v 1.14 2009/02/22 17:31:36 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; version 2 of the License.
**/
	require('./GLOBALS.php');
	fud_use('adm.inc', true);

	if (__dbtype__ != 'mysql') {
		exit("This control panel is intended for MySQL users only!");
	}

	// get a list of supported charsets
	$chars = db_all('SHOW CHARACTER SET');
	sort($chars);

	if (!empty($_POST['charset']) && in_array($_POST['charset'], $chars)) {
		foreach (get_fud_table_list() as $v) {
			$res = db_saq("SHOW CREATE TABLE " . $v);
			if (preg_match('!CHARSET\s*=\s*([A-Za-z0-9-]+)!', $res[1], $m)) {
				if (!strcasecmp($m[1], $_POST['charset'])) {
					echo "Table " . $v . " was already converted.<br />\n";
					continue;
				}
			}
			q('ALTER IGNORE TABLE '.$v.' CONVERT TO CHARACTER SET '.$_POST['charset']);
			echo "Table " . $v . " was successfully converted.<br />\n";
		}
	}

	require($WWW_ROOT_DISK . 'adm/admpanel.php');
?>
<h2>MySQL Table Character Set Adjuster</h2>
<form method="post" id="a_frm" action="">
<?php echo _hs; ?>
<table class="datatable solidtable">
<tr class="field">
        <td>Available Character Sets</td>
	<td><select name="charset">
<?php foreach ($chars as $v) 
	if ( $v == 'utf8' ) {
		echo '<option value="'.$v.'" selected="selected">'.$v.'</option>'; 
	} else {
		echo '<option value="'.$v.'">'.$v.'</option>';  
	}
?></select></td>
</tr>
<tr class="field">
	<td colspan="2" align="right"><input tabindex="3" type="submit" value="Change Charset" name="btn_submit" /></td>
</tr>
</table>
</form>

<br />
<table class="tutor" width="99%"><tr><td>
All forums should seriously consider converting their databases to the UTF-8 character set. Note that the conversion will take a long time to run, escpecially on large databases.
</td></tr></table>

<?php require($WWW_ROOT_DISK . 'adm/admclose.html'); ?>
