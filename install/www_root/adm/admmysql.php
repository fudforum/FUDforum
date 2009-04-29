<?php
/**
* copyright            : (C) 2001-2009 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: admmysql.php,v 1.16 2009/04/29 20:06:35 frank Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; version 2 of the License.
**/
	require('./GLOBALS.php');
	fud_use('adm.inc', true);

	require($WWW_ROOT_DISK . 'adm/admpanel.php');
	
	if (__dbtype__ != 'mysql') {
		exit("This control panel is intended for MySQL users only!");
	}

	// get a list of supported charsets
	$charsets = db_all('SHOW CHARACTER SET');
	sort($charsets);

	if (!empty($_POST['charset']) && in_array($_POST['charset'], $charsets)) {
		foreach (get_fud_table_list() as $v) {
			$res = db_saq("SHOW CREATE TABLE " . $v);
			$charset = $collate = '';
			if (preg_match('!CHARSET\s*=\s*([A-Za-z0-9-]+)!', $res[1], $m)) {
				$charset = $m[1];
			}
			if (preg_match('!COLLATE\s*=\s*([A-Za-z0-9-_]+)!', $res[1], $m)) {
				$collate = $m[1];
			}
			
			if ($_POST['charset'] == 'utf8' && $charset != 'utf8') {
				q('ALTER IGNORE TABLE '.$v.' CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci');
			} else if ($_POST['charset'] == 'utf8' && $charset == 'utf8' && $collate != 'utf8_unicode_ci' ) {
				q('ALTER IGNORE TABLE '.$v.' DEFAULT COLLATE utf8_unicode_ci');
			} else if (!strcasecmp($charset, $_POST['charset'])) {
				echo "Table " . $v . " was already converted<br />\n";
				continue;
			} else {
				q('ALTER IGNORE TABLE '.$v.' CONVERT TO CHARACTER SET '.$_POST['charset']);
			}
			echo "Table " . $v . " was successfully converted.<br />\n";
		}
	}
?>
<h2>MySQL Character Set Adjuster</h2>
<form method="post" id="a_frm" action="">
<?php echo _hs; ?>
<table class="datatable solidtable">
<tr class="field">
        <td>Available Character Sets</td>
	<td><select name="charset">
<?php foreach ($charsets as $charset) 
	if ( $charset == 'utf8' ) {
		echo '<option value="'.$charset.'" selected="selected">'.$charset.'</option>'; 
	} else {
		echo '<option value="'.$charset.'">'.$charset.'</option>';  
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
All forums should convert their tables to the <b>UTF-8</b> character set. Note that the conversion will take a long time to run, especially on large databases. Alter converting your database, remember to also convert your forum's messages by running the <b><a href="compact.php?<?php echo __adm_rsid; ?>">compactor</a></b>.
</td></tr></table><br />

<?php require($WWW_ROOT_DISK . 'adm/admclose.html'); ?>
