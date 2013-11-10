<?php
/**
* copyright            : (C) 2001-2013 Advanced Internet Designs Inc.
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
	fud_use('logaction.inc');	// Log dangerous actions.

	require($WWW_ROOT_DISK .'adm/header.php');

	$sql_dir  = $GLOBALS['DATA_DIR'] .'sql/scripts/';
	$sql_file = '';

	if (isset($_POST['save']) && !empty($_POST['sql_file']) &&!empty($_POST['txtb'])) {
		// Save SQL script.
		$sql_file = preg_replace('/\.sql/i','', $_POST['sql_file']);
		$sql_file = preg_replace('/[^a-zA-Z0-9-_ ]/','', $sql_file) .'.sql';
		file_put_contents($sql_dir . $sql_file, $_POST['txtb']);
		pf(successify('SQL script '. $sql_file .' saved.'));
		
		$sql_to_show = $_POST['txtb'];
		unset($_POST['txtb']);
	} else if (isset($_POST['delete']) && file_exists($sql_dir . $_POST['sql_file'])) {
		// Delete SQL script.
		$sql_file = $_POST['sql_file'];
		unlink($sql_dir . $sql_file);
		pf(successify('SQL script '. $sql_file .' deleted.'));
		
		$sql_to_show = '';
		unset($_POST['txtb']);
	} else if (isset($_POST['execute'])) {
		// Execute SQL (see below).
	} else if (isset($_POST['script']) && file_exists($sql_dir . $_POST['script'])) {
		// Load script into textarea for execution.
		$sql_file      = $_POST['script'];
		$sql_to_show = file_get_contents($sql_dir . $_POST['script']);
		pf(successify('SQL script '. $sql_file .' loaded.'));
		
		unset($_POST['txtb']);
	}
	
	// Change POST to GET request to reload editor window.
	if (!isset($sql_to_show) && !isset($_POST['txtb']) && !isset($_GET['sql'])) {
		$_POST['txtb'] = 'SHOW TABLES;';
	}
	if (isset($_GET['sql'])) {
		$_POST['txtb'] = $_GET['sql'];
	}
?>
<h2>SQL Manager</h2>
<form id="admsql" enctype="multipart/form-data" method="post" action="admsql.php">
<?php echo _hs; ?>
<table class="datatable">
<tr><td class="alert">
	Only use <em>SELECT</em>, <em>SHOW</em> and <em>DESCRIBE</em> statements below.
	Anything else may result in corruption or data loss!
</td></tr>
<tr><td>&nbsp;</td></tr>
<tr class="field"><td>
	Enter SQL statements (terminate them with semicolons):<br />
</td></tr>
<tr class="field"><td>
	<span style="font-size:xx-small; float:right;">
	<b>Database:</b> <?php echo __dbtype__ .' '. db_version(); ?> ::
	<b>Connection:</b> <?php echo $GLOBALS['DBHOST_USER'] .'@'. $GLOBALS['DBHOST_DBNAME'] ?> :: 

	<select id="tables" style="font-family: monospace; font-size: 8pt;" onchange="
var txtb = jQuery('#txtb');
var caretPos = document.getElementById('txtb').selectionStart; // This won't work in IE 8.
txtb.val( txtb.val().substring(0, caretPos) + tables.value + txtb.val().substring(caretPos) );">
	<option value="">Insert</option>
	<optgroup label="SQL clauses:">
	<option value="SELECT * FROM ">SELECT * FROM </option>
	<option value="DESCRIBE ">DESCRIBE</option>
	<option value="SHOW TABLES">SHOW TABLES</option>
	</optgroup>
	<optgroup label="Table names:">
	<?php
		$tables = get_fud_table_list();
		sort($tables);
		foreach($tables as $tbl) {
			echo '<option value="'. $tbl .'">'. $tbl .'</option>';
		}
	?>
	</optgroup>
	</select>

	<?php
		$scripts = glob($sql_dir .'*.sql');
		if (count($scripts) > 0) {
	?>
	<select id="script" name="script" style="font-family: monospace; font-size: 8pt;" onchange="this.form.submit();">
	<option value="">Load SQL script</option>
	<?php
		foreach ($scripts as $script) {
			echo '<option value="'. basename($script) .'">'. basename($script) .'</option>';
		}
	?>
	</select>
	<?php } ?>
	
	</span>

	<textarea id="txtb" name="txtb" placeholder="Enter SQL here..." autofocus="autofocus" rows="5" cols="72" style="width:99%; box-sizing: border-box;"><?php
		if (isset($_POST['txtb'])) print $_POST['txtb'];
		else if (isset($sql_to_show)) print $sql_to_show; 
	?></textarea>
</td></tr>
<tr><td>
	<span style="float:right; font-size:x-small">
		Save script as:
		<input name="sql_file" type="text"   value="<?php echo $sql_file; ?>" size="15" style="font-size: xx-small;" />
		<input name="save"     type="submit" value="Save" style="font-size: xx-small;" />
		<?php if (!empty($sql_file)) { ?>
			<input name="delete"   type="submit" value="Delete" style="font-size: xx-small;" />
		<?php } ?>
	</span>
	<input name="execute" type="submit" value="Run It" style="font-weight:bold;" />
</td></tr>
 </table>
</form>
<script>
jQuery(document).ready(function() {
	setTimeout(function(){
		jQuery('#txtb').focus();
        }, 1);
});
</script>

<?php
if (isset($_POST['txtb']) && $_POST['txtb'] != '') {
	$sqlfile = str_replace("\r", '', $_POST['txtb']);
	$sqlfile = str_replace('{SQL_TABLE_PREFIX}', $GLOBALS['DBHOST_TBL_PREFIX'], $sqlfile);
	$sqlfile = explode(";", $sqlfile);

	foreach ($sqlfile as $sql) {
		$sql = trim($sql);

		if (preg_match('/[a-zA-Z]/', $sql) and !preg_match('/^(#|--)/', $sql)) {
			if (preg_match('/^\s*use\s+\w+\s*;?$/i', $sql)) {
				echo '<div class="tutor">For security reasons you may not switch to another database!</div>';
				break;
			}

			// Database neutral SHOW TABLES.
			if (preg_match('/^\s*show tables\s*;?$/i', $sql)) {
				echo '<h2>FUDforum tables</h2>';
				echo '<table class="resulttable">';
				echo '<thead><tr class="resulttopic"><th>Table name</th><th>Rows</th><th>Actions</th></tr></thead>';
				$tables = get_fud_table_list();
				$i      = 0;
				foreach($tables as $tbl) {
					if (preg_match('/_fl_|_tv_/i', $tbl)) {
						// Skip caching tables.
						continue;
					}
					$i++;
					$rows = q_singleval('SELECT count(*) FROM '. $tbl);
					echo '<tr><td>'. $tbl .'</td>';
					echo '<td>'. $rows .'</td>';
					echo '<td><a href="admsql.php?sql=DESCRIBE '. $tbl .'&amp;'. __adm_rsid .'">Describe</a> | <a href="admsql.php?sql='. q_limit('SELECT * FROM '. $tbl, 10) .';&amp;'. __adm_rsid .'">Select</a></td></tr>';
				}
				echo '</table>';
				
				echo '<p><i>'. $i .' tables listed, ocupying '. number_format(get_sql_disk_usage()/1024/1024, 2) .' MB.</i></p>';
				continue;
			}
			
			// Database neutral DESCRIBE.
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
				
				echo '<p style="font-size: small">';
				echo '[ <a href="admsql.php?sql=SHOW TABLES;&amp;'. __adm_rsid .'">Show tables</a> ] ';
				echo '[ <a href="admsql.php?sql='. q_limit('SELECT * FROM '. $m[2], 10) .';&amp;'. __adm_rsid .'">Select data</a> ]';
				echo '</p>';

				continue;
			}

			// Log potentially dangerous SQL statements.
			if (!preg_match('/^\s*(desc|select|show).*/i', $sql)) {
				logaction(_uid, 'Executed SQL', 0, $sql);
			}

			// Execute query.
			try {

				$s = microtime(true);
				$q = uq($sql);
				$t = number_format(microtime(true) - $s, 4);

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

				if (preg_match('/^\s*(desc|select|show).*/i', $sql)) {
					echo '<br /><i>'. ($i-1) .' rows returned in '. $t .' secs.</i>';
				} else {
					echo '<i>Statement executed. Affected rows: '. db_affected() .'. Time taken: '. $t .' secs.</i>';
				}
				
				echo '<p style="font-size: small">';
				echo '[ <a href="admsql.php?sql=SHOW TABLES;&amp;'. __adm_rsid .'">Show tables</a> ] ';
				echo '</p>';

			} catch(Exception $e) {
				echo '<h2>SQL Error</h2>';
				echo errorify($e->getMessage());
			}
		}
	}
}

require($WWW_ROOT_DISK .'adm/footer.php');
?>
