<?php
/**
* copyright            : (C) 2001-2009 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: admsql.php,v 1.1 2009/04/01 17:50:20 frank Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; version 2 of the License.
**/

	require('./GLOBALS.php');
	fud_use('db.inc');	
	fud_use('adm.inc', true);

	require($WWW_ROOT_DISK . 'adm/admpanel.php');
?>
<div class="alert">
NOTE: this feature IS DANGEROUS and you could destroy all your data. Please only use it to run SELECT and DESCRIBE statements. Anything else may result in data loss.
</div>

<h2>SQL Manager</h2>

<form name="admsql" method="post" action="admsql.php">
<?php echo _hs; ?>
<table class="datatable">
<tr class="field"><td>
	Enter SQL statement(s):<br />
</td></tr>
<tr class="field"><td>
	<div style="float:right; font-size:xx-small;">
	Database: <?php echo $GLOBALS['DBHOST_USER'].'@'.$GLOBALS['DBHOST_DBNAME'] ?> :: 
	[ <a href="#" onclick="document.admsql.sql.value+='<?php echo $GLOBALS['DBHOST_TBL_PREFIX'];?>';">Insert table prefix</a> ]
	</div>
	<textarea id="sql" name="sql" rows="7" cols="72" style="width:99%;"><?php print $_POST['sql']; ?></textarea>
</td></tr>
<tr><td>
	<input type="submit" class="submit" value="Run It" />
</td></tr>
 </table>
</form>

<?php
if ($_POST['sql'] != '') {
	$sqlfile = str_replace("\r","",$_POST['sql']);
	$sqlfile = str_replace('{SQL_TABLE_PREFIX}', $GLOBALS['DBHOST_TBL_PREFIX'], $sqlfile);
	$sqlfile = explode(";\n", $sqlfile);

	foreach ($sqlfile as $sql) {
		if (preg_match("/[a-zA-Z]/", $sql) and !preg_match("/^(#|--)/", $sql)) {
			$q = q($sql);

			echo '<h2>SQL Results</h2>';
			echo '<table class="resulttable">';
			
			$i = 1;
			while ($result = db_fetch_array($q)) {
				echo '<tr class="resulttopic">';
				if ($i == 1) {
					foreach ($result as $key => $value) {
						if (!is_numeric($key)) {
							echo '<th>'.$key.'</th>';
						}
					}
				}
				echo '</tr>';
			
				echo '<tr class="field">';
				foreach ($result as $key => $value) {
					if (!is_numeric($key)) {
						echo '<td>'.$value.'</td>';
					}
				}
				echo '</tr>';
		
				$i++;
			}
	
			echo '</table>';
		}

		$num_rows = db_count($q);
		if (!$num_rows) $num_rows = 0;
		echo '<br /><i>'. $num_rows .' row(s) returned.</i>';
	}
}

require($WWW_ROOT_DISK . 'adm/admclose.html');
?>
