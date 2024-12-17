<?php
/**
* copyright            : (C) 2001-2011 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id$
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; version 2 of the License.
**/
	require('./GLOBALS.php');
	fud_use('adm.inc', true);
	fud_use('widgets.inc', true);
	fud_use('dbadmin.inc', true);	// For get_fud_table_list().

	require($WWW_ROOT_DISK .'adm/header.php');

	if (__dbtype__ != 'mysql') {
		exit('This control panel is intended for MySQL users only!');
	}

	// Get a list of supported charsets.
	$charsets = db_all('SHOW CHARACTER SET');
	sort($charsets);

	if (!empty($_POST['charset']) && in_array($_POST['charset'], $charsets)) {
	
		pf('<h3>Charset Converter Output</h3>');

	
		foreach (get_fud_table_list() as $v) {
			$res = db_saq('SHOW CREATE TABLE '. $v);
			$charset = $collate = '';
			if (preg_match('!CHARSET\s*=\s*([A-Za-z0-9-]+)!', $res[1], $m)) {
				$charset = $m[1];
			}
			if (preg_match('!COLLATE\s*=\s*([A-Za-z0-9-_]+)!', $res[1], $m)) {
				$collate = $m[1];
			}
			
			if ($_POST['charset'] == 'utf8mb4' && $charset != 'utf8mb4') {
				q('ALTER IGNORE TABLE '. $v .' CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
			} else if ($_POST['charset'] == 'utf8mb4' && $charset == 'utf8mb4' && $collate != 'utf8mb4_unicode_ci' ) {
				q('ALTER IGNORE TABLE '. $v .' DEFAULT COLLATE utf8mb4_unicode_ci');
			} else if (!strcasecmp($charset, $_POST['charset'])) {
				echo 'Table '. $v ." was already converted<br />\n";
				continue;
			} else {
				q('ALTER IGNORE TABLE '. $v .' CONVERT TO CHARACTER SET '. $_POST['charset']);
			}
			echo 'Table '. $v ." was successfully converted.<br />\n";
		}
		pf('<br /><div class="tutor">Charset conversion done.</div>');
	} else {
?>
<h2>MySQL Character Set Adjuster</h2>

<div class="tutor">
All forums should convert their tables to the <b>UTF8MB4</b> character set.
If unsure, it is safe to just re-run it.
Note that the conversion will take a long time to run, especially on large databases. 
After converting your database, remember to also convert your forum's messages by running the <b><a href="compact.php?<?php echo __adm_rsid; ?>">compactor</a></b>.
</div>

<br />
<form method="post" id="a_frm" action="">
<?php echo _hs; ?>
<table class="datatable solidtable">
<tr class="field">
	<td>Available Character Sets</td>
	<td><?php draw_select('charset', implode("\n", $charsets), implode("\n", $charsets), 'utf8mb4'); ?></td>
</tr>
<tr class="fieldaction">
	<td colspan="2" align="right"><input tabindex="3" type="submit" value="Change Charset" name="btn_submit" /></td>
</tr>
</table>
</form>

<?php } require($WWW_ROOT_DISK .'adm/footer.php'); ?>
