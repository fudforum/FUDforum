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

	if (isset($_POST['form_posted'])) {
		// We must load admsqltest2.php before adm.inc as adm.inc will load the default DB driver.
		require_once('admsqltest2.php');
		exit;
	}

	require('./GLOBALS.php');
	fud_use('adm.inc', true);
	fud_use('glob.inc', true);
	fud_use('widgets.inc', true);

	require($WWW_ROOT_DISK .'adm/header.php');

	$help_ar = read_help();
	
	// Get list of available drivers.
	$drivers = '';
	foreach (glob($DATA_DIR .'sql/*', GLOB_ONLYDIR) as $f) {
		if (file_exists($f .'/db.inc')) {
			if ($drivers) {
				$drivers .= "\n". basename($f);
			} else {
				$drivers = basename($f);
			}
		}
	}

?>
<h2>Test SQL Driver</h2>
<div class="tutor">
	This control panel will take FUDforum's DB drivers through its paces. This will help to test existing drivers and to develop new drivers for yet unsupported databases.
</div>

<form name="admsqltest" method="post" action="admsqltest.php">
<?php echo _hs; ?>

<table class="datatable solidtable">
<tr class="fieldtopic"><td colspan="2"><a name="3" /><br /><b>Database Settings</b> </td></tr>
<input type="hidden" name="DATA_DIR" VALUE="<?php echo $DATA_DIR; ?>">
<tr class="field">
	<td><label for="CF_DBHOST">Database Driver: <br /><font size="-1">Select the driver that should be tested.</font></label></td><td valign="top">
<?php
	draw_select('CF_DBHOST_DBTYPE', $drivers, $drivers, $DBHOST_DBTYPE);
?>
</td></tr>
<?php
	print_reg_field('Database Server',            'DBHOST');
	print_reg_field('Database Name',              'DBHOST_DBNAME');
	print_reg_field('Database Login',             'DBHOST_USER');
	print_reg_field('Database Password',          'DBHOST_PASSWORD', 0, 1);
	print_reg_field('Database Table Prefix',      'DBHOST_TBL_PREFIX');
	print_bit_field('Use Persistent Connections', 'DBHOST_PERSIST');
?>
<tr class="fieldaction"><td colspan="2" align="right"><input type="submit" name="btn_submit" value="Run test" /></td></tr>

</table>
<input type="hidden" name="form_posted" value="1" />
</form>

<?php
require($WWW_ROOT_DISK .'adm/footer.php');
?>
