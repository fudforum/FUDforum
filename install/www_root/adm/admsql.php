<?php
/**
* copyright            : (C) 2001-2010 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id$
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; version 2 of the License.
**/

	require('./GLOBALS.php');
	fud_use('adm.inc', true);
	fud_use('dbadmin.inc', true);	// For get_fud_table_list(), get_fud_col_list(), etc.

	require($WWW_ROOT_DISK .'adm/header.php');
?>
<h2>SQL Manager</h2>
<form name="admsql" method="post" action="admsql.php">
<?php echo _hs; ?>
<table class="datatable">
<tr><td class="alert">
	NOTE: Please only use this feature to run <em>SELECT</em> and <em>DESCRIBE</em> statements.
	Anything else may result in data loss.
</td></tr>
<tr><td>&nbsp;</td></tr>
<tr class="field"><td>
	Enter SQL statements (terminate them with semicolons):<br />
</td></tr>
<tr class="field"><td>
	<div style="float:right; font-size:xx-small;">
	<b>Database:</b> <?php echo __dbtype__; ?> ::
	<b>Connection:</b> <?php echo $GLOBALS['DBHOST_USER'] .'@'. $GLOBALS['DBHOST_DBNAME'] ?> :: 
	<select onchange="if(this.selectedIndex!=0) document.admsql.sql.value+=this.options[this.selectedIndex].value;">
	<option>Insert table name:</option>
	<?php
		$tables = get_fud_table_list();
		sort($tables);
		foreach($tables as $tbl) {
			echo '<option value="'. $tbl .'">'. $tbl .'</option>';
		}
	?>
	</select>
	</div>
	<textarea id="sql" name="sql" rows="7" cols="72" style="width:99%;"><?php if (isset($_POST['sql'])) { print $_POST['sql']; } else { print 'SELECT * FROM '; } ?></textarea>
</td></tr>
<tr><td>
	<input type="submit" class="submit" value="Run It" />
</td></tr>
 </table>
</form>
<script type="text/javascript">
/* <![CDATA[ */
$(document).ready(function() {
	$('#sql').focus();
});
/* ]]> */
</script>

<?php
if (isset($_POST['sql']) && $_POST['sql'] != '') {
	$sqlfile = str_replace("\r", '', $_POST['sql']);
	$sqlfile = str_replace('{SQL_TABLE_PREFIX}', $GLOBALS['DBHOST_TBL_PREFIX'], $sqlfile);
	$sqlfile = explode(";", $sqlfile);

	foreach ($sqlfile as $sql) {
		if (preg_match('/[a-zA-Z]/', $sql) and !preg_match('/^(#|--)/', $sql)) {
			if (preg_match('/^\s*use\s+\w+\s*;?$/i', $sql)) {
				echo '<div class="tutor">For security reasons you may not switch to another database</div>';
				break;
			}

			// Database neutral describe.
			if (preg_match('/^\s*desc(ribe)?\s+(\w+)\s*;?$/i', $sql, $m)) {
				echo '<h2>Columns for '. $m[2] .'</h2>';
				echo '<table class="resulttable">';
				echo '<thead><tr class="resulttopic"><th>Column Name</th><th>Type</th><th>Null</th><th>Primary</th><th>Default</th><th>Auto incrementing</th></tr></thead>';
				foreach (get_fud_col_list($m[2]) as $col => $props) {
					echo '<tr><td>'. $col .'</td><td>'. $props['type'] .'</td><td>'. ($props['not_null'] ? 'NOT NULL' : '') .'</td><td>'. ($props['primary'] ? 'Yes' : 'No') .'</td><td>'. $props['default'] .'</td><td>'. ($props['auto'] ? 'Yes' : 'No') .'</td></tr>';
				}
				echo '</table>';

				echo '<h2>Indexes</h2>';
				echo '<table class="resulttable">';
				echo '<thead><tr class="resulttopic"><th>Index Name</th><th>Unique</th><th>Columns</th></tr></thead>';
				foreach (get_fud_index_list($m[2]) as $idx => $props) {
					echo '<tr><td>'. $idx .'</td><td>'. ($props['unique'] ? 'Yes' : 'No') .'</td><td>'. $props['cols'] .'</td><td></tr>';
				}
				echo '</table>';

				continue;
			}

			// Execute query.
			try {
				$q = uq($sql);

				echo '<h2>SQL Results</h2>';
				echo '<table class="resulttable">';

				$i = 1;
				while ($result = db_fetch_array($q)) {
					if ($i == 1) {	// Column name headings.
						echo '<thead><tr class="resulttopic">';
						foreach ($result as $key => $value) {
							if (!is_numeric($key)) {
								echo '<th>'. $key .'</th>';
							}
						}
						echo '</tr></thead>';
					}

					echo '<tr class="field">';
					foreach ($result as $key => $value) {
						if (!is_numeric($key)) {
							echo '<td>'. htmlspecialchars($value) .'</td>';
						}
					}
					echo '</tr>';

					$i++;
				}

				echo '</table>';

				if ($i > 2) {
					echo '<br /><i>'. ($i-1) .' rows returned.</i>';
				} else if ($i > 1) {
					echo '<br /><i>1 row returned.</i>';
				} else {
					echo '<i>Statement executed. No rows returned.</i>';
				}

			} catch(Exception $e) {
				echo '<h2>SQL Error</h2>';
				echo errorify($e->getMessage());
			}
		}
	}
}

require($WWW_ROOT_DISK .'adm/footer.php');
?>
