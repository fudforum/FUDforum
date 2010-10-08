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

/* Return list of indexes and columns for a given table as defined in FUDforum's SQL files. */
function get_stbl_from_file($file)
{
	$data = str_replace('{SQL_TABLE_PREFIX}', $GLOBALS['DBHOST_TBL_PREFIX'], file_get_contents($file));
	$tbl = array('name'=>'', 'index'=>array(), 'flds'=>array());

	/* Fetch table name. */
	if (!preg_match('!CREATE TABLE '. $GLOBALS['DBHOST_TBL_PREFIX'] .'([a-z_]+)!', $data, $m)) {
		return;
	}
	$tbl['name'] = $GLOBALS['DBHOST_TBL_PREFIX'] . rtrim($m[1]);

	/* Match fields. */
	if (!preg_match('!\(([^;]+)\);!', $data, $m)) {
		return;
	}

	foreach (explode("\n", $m[1]) as $v) {
		if (!($v = trim($v))) {
			continue;
		}
		if (preg_match("!([a-z_]+)\s([^\n,]+)!", $v, $r)) {
			$r[2] = str_replace(' BINARY', '', $r[2]);	// Remove MySQL BINARY before comparing.

			if (strpos($r[2], ' NOT NULL') !== false) {
				$r[2] = str_replace(' NOT NULL', '', $r[2]);
				$not_null = 1;
			} else {
				$not_null = 0;
			}

			if (strpos($r[2], ' AUTO_INCREMENT') !== false) {
				$r[2] = str_replace(' AUTO_INCREMENT', '', $r[2]);
				$auto = 1;
			} else {
				$auto = 0;
			}

			if (preg_match('! DEFAULT (.*)$!', $r[2], $d)) {
				$default = str_replace('\'', '', $d[1]);
				$r[2] = str_replace(' DEFAULT '. $d[1], '', $r[2]);
			} else {
				$default = null;
			}

			if (strpos($r[2], ' PRIMARY KEY') !== false) {
				$r[2] = str_replace(' PRIMARY KEY', '', $r[2]);
				$key = 1;
			} else {
				$key = 0;
			}

			$tbl['flds'][$r[1]] = array('type'=>trim($r[2]), 'not_null'=>$not_null, 'primary'=>$key, 'default'=>$default, 'auto'=>$auto);
		}

		/* Look for out of line primary keys. */
		if (preg_match('!PRIMARY KEY \((.+)\)!', $v, $d)) {
			$pkcols = explode(',', $d[1]);
			foreach ($pkcols as $pkcol) {
				$tbl['flds'][ trim($pkcol) ]['primary'] = 1;
			}
		}
	}

	/* Indexes. */
	if (preg_match_all('!CREATE ?(UNIQUE|) INDEX ([^\s]+) ON '. $tbl['name'] .' \(([^;]+)\);!', $data, $m)) {
		$c = count($m[0]);
		for ($i = 0; $i < $c; $i++) {
			$tbl['index'][$m[2][$i]] = array('unique'=>(empty($m[1][$i]) ? 0 : 1), 'cols'=>str_replace(' ', '', $m[3][$i]));
		}
	}

	return $tbl;
}

/* Return a list of FUDforum tables from the database. */
function get_fud_table_list($tbl=null)
{
	if (__dbtype__ == 'firebird') {
		if ($tbl) {
			return db_all('SELECT rdb$relation_name FROM rdb$relations WHERE rdb$view_blr is null AND(rdb$system_flag is null or rdb$system_flag = 0) AND rdb$relation_name LIKE \''. $tbl .'\'');
		}
		return db_all('SELECT rdb$relation_name FROM rdb$relations WHERE rdb$view_blr is null AND(rdb$system_flag is null or rdb$system_flag = 0) AND rdb$relation_name LIKE \''. $GLOBALS['DBHOST_TBL_PREFIX'] .'%\'');
	} else if (__dbtype__ == 'mssql') {
		if ($tbl) {
			return db_all('EXEC sp_tables @table_name = \''. $tbl .'\'');
		}
		return db_all('EXEC sp_tables @table_name = \''. $GLOBALS['DBHOST_TBL_PREFIX'] .'%\'');
	} else if (__dbtype__ == 'mysql') {
		if ($tbl) {
			return db_all('SHOW TABLES LIKE \''. $tbl .'\'');
		}
		return db_all('SHOW TABLES LIKE \''. $GLOBALS['DBHOST_TBL_PREFIX'] .'%\'');
	} else if (__dbtype__ == 'oracle') {
		if ($tbl) {
			return db_all('SELECT lower(table_name) FROM user_tables WHERE table_name LIKE \''. strtoupper($tbl) .'\'');
		}
		return db_all('SELECT lower(table_name) FROM user_tables WHERE table_name LIKE \''. strtoupper($GLOBALS['DBHOST_TBL_PREFIX'] .'%\''));
	} else if (__dbtype__ == 'pgsql') {
		if ($tbl) {
			return db_all('SELECT relname FROM pg_class WHERE relkind=\'r\' AND relname LIKE \''. $tbl .'\'');
		}
		return db_all('SELECT relname FROM pg_class WHERE relkind=\'r\' AND relname LIKE \''. addcslashes($GLOBALS['DBHOST_TBL_PREFIX'],'_') .'%\'');
	} else if (__dbtype__ == 'sqlite') {
		if ($tbl) {
			return db_all('SELECT tbl_name FROM sqlite_master WHERE type=\'table\' AND name LIKE \''. $tbl .'\'');
		}
		return db_all('SELECT tbl_name FROM sqlite_master WHERE type=\'table\' AND name LIKE \''. $GLOBALS['DBHOST_TBL_PREFIX'] .'%\'');
	} else {
		die('Unsupported database.');
	}
}

/* Return a list of colums and column properties from the database. */
function get_fud_col_list($table)
{
	if (__dbtype__ == 'mysql') {
		$c = q('SHOW FIELDS FROM '. $table);
		while ($r = db_rowobj($c)) {
			$type = strtoupper(preg_replace('!(int|bigint)\(([0-9]+)\)!', '\1', $r->Type));
			$not_null = $r->Null == 'YES' ? 0 : 1;
			$key = $r->Key == 'PRI' ? 1 : 0;
			$default = (!is_null($r->Default) && $r->Default != 'NULL') ? $r->Default : '';
			$auto = $r->Extra ? 1 : 0;

			$ret[$r->Field] = array('type'=>$type, 'not_null'=>$not_null, 'primary'=>$key, 'default'=>$default, 'auto'=>$auto);
		}
		unset($c);

		$tmp = db_rowarr(q('show create table '. $table));
		if (strpos($tmp[1], 'utf8') === false) {
			q('ALTER IGNORE TABLE '. $table .' CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci');
		}
	} else if (__dbtype__ == 'oracle') {
		$c = q('SELECT tc.column_name,  data_type||NVL2(char_col_decl_length, \'(\'||char_col_decl_length||\')\', NULL), nullable, c.constraint_type, data_default
				FROM all_tab_columns tc
					LEFT OUTER JOIN (
                        all_cons_columns cc
                        JOIN all_constraints c ON (
                                c.owner=cc.owner
                                AND c.constraint_name = cc.constraint_name
                                AND c.constraint_type=\'P\'
                        )
					) ON (
                        tc.owner = cc.owner
                        AND tc.table_name = cc.table_name
                        AND tc.column_name = cc.column_name
					)
				WHERE tc.table_name = UPPER(\''. $table .'\')
				ORDER BY tc.column_id');
		while ($r = db_rowarr($c)) {
			$colname = strtolower( $r[0] );
			$type = preg_replace(array('!NUMBER!', '!VARCHAR2!', '!CLOB\(.+?\)!', '!FLOAT!'), array('INT', 'VARCHAR', 'TEXT', 'BIGINT'), $r[1]);
			$not_null = $r[2] == 'N' ? 1 : 0;
			$key = $r[3] == 'P' ? 1 : 0;
			$default = is_null($r[4]) ? null : trim(str_replace("'", '', $r[4]));

			// Check if PK is AUTO GENERATED (i.e. has a sequence).
			$auto = 0;
			if ($type == 'INT' && $key) {
				$auto = (int)q_singleval('SELECT 1 FROM user_sequences WHERE sequence_name LIKE UPPER(\''. $table .'%_SEQ\')');
			}

			$ret[$colname] = array('type'=>$type, 'not_null'=>$not_null, 'primary'=>$key, 'default'=>$default, 'auto'=>$auto);
		}
		unset($r);
	} else if (__dbtype__ == 'pgsql') {
		$c = q('SELECT col.column_name, data_type, is_nullable, column_default, constraint_type, character_maximum_length
			FROM information_schema.columns col
			LEFT OUTER JOIN information_schema.key_column_usage cu
				ON (col.table_name = cu.table_name
					AND col.column_name = cu.column_name)
			LEFT JOIN information_schema.table_constraints cons
				ON  (cu.table_name = cons.table_name
					AND cu.constraint_name = cons.constraint_name 
					AND cons.constraint_type = \'PRIMARY KEY\')
			WHERE col.table_name = \''. $table .'\'
			ORDER BY col.ordinal_position');
		while ($r = db_rowarr($c)) {
			$key = empty($r[4]) ? 0 : 1;
			$not_null = $r[2] == 'NO' ? 1 : 0;
			$type = strtoupper(preg_replace(array('!character varying!', '!character!', '!integer!'), array('VARCHAR', 'CHAR', 'INT'), $r[1]));
			$type = (empty($r[5])) ? $type : $type .'('. $r[5] .')';	// Append type length.
			$auto = !strncmp($r[3], 'nextval', 7) ? 1 : 0;
			$default_val = preg_replace('/::.*/', '', $r[3]);	// Remove anything after ::. I.e. '0'::character varying.
			$default = (!$auto) ? trim(str_replace("'", '', $default_val)) : null;

			$ret[$r[0]] = array('type'=>$type, 'not_null'=>$not_null, 'primary'=>$key, 'default'=>$default, 'auto'=>$auto);
		}
		unset($r);
	} else if (__dbtype__ == 'sqlite') {
		$c = q('PRAGMA table_info(\''. $table .'\')');
		while ($r = db_rowobj($c)) {
			$key = $r->pk;
			$not_null = ($r->notnull || $r->pk) ? 1 : 0;
			$type = ($r->type == 'INTEGER') ? 'INT' : $r->type;
			$default = is_null($r->dflt_value) ? null : trim(str_replace("'", '', $r->dflt_value));
			$auto = ($r->type == 'INTEGER' && $r->pk) ? 1 : 0;

			$ret[$r->name] = array('type'=>$type, 'not_null'=>$not_null, 'primary'=>$key, 'default'=>$default, 'auto'=>$auto);
		}
		unset($r);
	}

	return $ret;
}

/* Add a composite primary key to a table. */
function create_primary_key($table, $col_list)
{
	if (__dbtype__ != 'sqlite') {
		q('ALTER TABLE '. $table .' ADD PRIMARY KEY ('. $col_list .')');
	} else {
		// SQLite cannot add PK's, we need to recreate the table and copy the data over.

		// Construct new CREATE TABLE statement.
		$new_tab_def = 'CREATE TABLE '. $table .' (';
		$tmp_db_cols = get_fud_col_list($table);
		foreach ($tmp_db_cols as $tmp_name => $tmp_pr) {
			$new_tab_def .= make_table_row($tmp_name, $tmp_pr) .",\n";	// Add all col's.
		}
		$new_tab_def .= ' PRIMARY KEY ('. $col_list .'))';	// Append PK clause.
		q('BEGIN TRANSACTION');
		drop_table('tmp', true);
		q('CREATE TABLE tmp AS SELECT * FROM '. $table);
		drop_table($table);
		q($new_tab_def);
		q('INSERT INTO '. $table .' SELECT * FROM tmp');
		drop_table('tmp');
		q('COMMIT');
	}
}

/* Vacuum/Optimize/Analyze all FUDfiorum's database tables. */
function optimize_fud_tables($tbl_list=null)
{
	if (__dbtype__ == 'sqlite') {	// DB level.
		uq('VACUUM');
	} else if (__dbtype__ == 'firebird') {	// Not implemented.
		die('Not yet implemented');
	}

	if (!$tbl_list) {
		$tbl_list = get_fud_table_list();
	}

	foreach ($tbl_list as $t)
		if (__dbtype__ == 'mssql') {
			uq('UPDATE STATISTICS '. $t);
		if (__dbtype__ == 'mysql') {
			uq('OPTIMIZE TABLE '. $t);
		} else if (__dbtype__ == 'oracle') {
			uq('ANALYZE TABLE '. $t .' COMPUTE STATISTICS;');
		} else if (__dbtype__ == 'pgsql') {
			uq('VACUUM ANALYZE '. $t);
		} else if (__dbtype__ == 'sqlite') {
			uq('ANALYZE '. $t);
		}
	}
}

/* Return the size of FUDforum's database tables in bytes. */
function get_sql_disk_usage()
{
	if (__dbtype__ == 'mysql') {
		return q_singleval('SELECT sum(data_length + index_length) FROM information_schema.TABLES WHERE table_name LIKE \''. $GLOBALS['DBHOST_TBL_PREFIX'] .'%\'');
	} else if (__dbtype__ == 'oracle') {
		return q_singleval('SELECT sum(bytes) FROM user_segments WHERE segment_name LIKE UPPER(\''. $GLOBALS['DBHOST_TBL_PREFIX'] .'%\')');
	} else if (__dbtype__ == 'pgsql') {
		return q_singleval('SELECT pg_database_size(\''. $GLOBALS['DBHOST_DBNAME'] .'\')');
	}

	return 0;	// Not implemented.
}

/* Create a table. */
function create_table($stmt)
{
	$stmt = str_replace('{SQL_TABLE_PREFIX}', $GLOBALS['DBHOST_TBL_PREFIX'], $stmt);

	if (__dbtype__ == 'mysql') {
		if (preg_match('/^\s*CREATE\s*TABLE\s*/i', $stmt)) {
			/* Because mysql with InnoDB table refuses to resolve indexes correctly, we need to force MyISAM table type. */
			$stmt .= ' ENGINE=MyISAM ';
			/* We need to specify a default charset for MySQL 4.1.2+. */
			$stmt .= ' DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci';
		}
	}

	// Queue statements to create Oracle sequence and trigger.
	$extra_stmts = array();
	if (__dbtype__ == 'oracle') {
		if (preg_match('/^\s*CREATE\s*TABLE\s*([\{\}\w]*)/i', $stmt, $m)) {
			$tbl = $m[1];				
			preg_match_all('/\s*(\w*)\s*INT NOT NULL AUTO_INCREMENT/i', $stmt, $m, PREG_PATTERN_ORDER);
			foreach ($m[1] as $c) {
				array_push($extra_stmts, 'CREATE SEQUENCE '. $tbl .'_'. $c .'_seq');
				array_push($extra_stmts, 'CREATE OR REPLACE TRIGGER  '. $tbl .'_'. $c .'_trg BEFORE INSERT ON '. $tbl .' FOR EACH ROW BEGIN SELECT '. $tbl .'_'. $c .'_seq.nextval INTO :new.'. $c .' FROM dual; END;');
			}
		}
	}

	// Data type conversions.
	$src = array("!#.*\n!", '!UNIX_TIMESTAMP!');
	$dst = array('', time());
	if (__dbtype__ == 'mssql') {
		array_push($src, '!BINARY!', '!INT NOT NULL AUTO_INCREMENT!');
		array_push($dst, '',          'INT IDENTITY');
	} else if (__dbtype__ == 'oracle') {
		// Use FLOAT (cannot use NUMBER) for BIGINT in Oracle. We need a seperate type to back map it to BIGINT later.
		array_push($src, '/TEXT/', '/BIGINT/', '/BINARY/', '/INT NOT NULL AUTO_INCREMENT/', '/NOT NULL DEFAULT (.+?)([,\s$])/');
		array_push($dst, 'CLOB',   'FLOAT',    '',         'NUMBER',                        'DEFAULT \\1 NOT NULL\\2');
	} else if (__dbtype__ == 'pgsql') {
		array_push($src, '!BINARY!', '!INT NOT NULL AUTO_INCREMENT!');
		array_push($dst, '',         'SERIAL');
	} else if (__dbtype__ == 'sqlite') {
		array_push($src, '!BINARY!', '!INT NOT NULL AUTO_INCREMENT!');
		array_push($dst, '',         'INTEGER');
	}
	$stmt = trim(preg_replace($src, $dst, $stmt));

	// Do it!
	$rc = q($stmt);
	if ($rc === FALSE) return $rc;

	/* Run extra statements. For example, crreate Oracle sequences and triggers. */
	foreach($extra_stmts as $q) {
		// echo "DEBUG: Run $q<hr>";
		$rc = q($q);
	}

	return $rc;
}

/* Drop a table. */
function drop_table($tbl, $ifexists=false) {
	$tbl = str_replace('{SQL_TABLE_PREFIX}', $GLOBALS['DBHOST_TBL_PREFIX'], $tbl);
	
	// Drop Orace sequences.
	if (__dbtype__ == 'oracle') {
		$c = q('SELECT sequence_name FROM user_sequences WHERE sequence_name LIKE \''. strtoupper($tbl .'%\''));
		while ($r = db_rowarr($c)) {
			// echo 'DEBUG: Drop oracle seq '. $r[0] .'<hr>';
			q('DROP SEQUENCE '. $r[0]);
		}
	}

	if ($ifexists) {
		if (__dbtype__ == 'oracle') {
			q('BEGIN execute immediate \'DROP TABLE '. $tbl .'\'; EXCEPTION when others then null; END;');
		} else {
			q('DROP TABLE IF EXISTS '. $tbl);
		}
	} else {
		q('DROP TABLE '. $tbl);
	}
}

/* Create an index on a table. */
function create_index($tbl, $name, $unique, $flds, $del_dups=true)
{
	$tbl = str_replace('{SQL_TABLE_PREFIX}', $GLOBALS['DBHOST_TBL_PREFIX'], $tbl);
	$name = str_replace('{SQL_TABLE_PREFIX}', $GLOBALS['DBHOST_TBL_PREFIX'], $name);

	/* Before adding a unique index, we need to check & remove any duplicates. */
	if ($unique && $del_dups) {
		$f = explode(',', $flds);
		$n = count($f);
		$c = q('SELECT '. $flds .', count(*) AS cnt FROM '. $tbl .' GROUP BY '. $flds .' HAVING count(*) > 1');
		while ($r = db_rowarr($c)) {
			$cond = '';
			foreach ($f as $k => $v) {
				if (empty($cond)) {
					$cond .= $v .'=\''. addslashes($r[$k]) .'\'';
				} else {
					$cond .= ' AND '. $v .'=\''. addslashes($r[$k]) .'\'';
				}
			}
			q('DELETE FROM '. $tbl .' WHERE '. $cond .' LIMIT '. ($r[$n] - 1));
		}
		unset($c);
	}

	if (__dbtype__ == 'mysql') {
		// Prevent the famous 'duplicate entry' errors on MySQL.
		q('ALTER IGNORE TABLE '. $tbl .' ADD '. ($unique ? 'UNIQUE ' : 'INDEX ') . $name .' ('. $flds .')');
	} else {
		$unique = $unique ? 'UNIQUE' : '';
		q('CREATE '. $unique .' INDEX '. $name .' ON '. $tbl .' ('. $flds .')');
	}
}

/* Drop a database index. */
function drop_index($table, $index)
{
	if (__dbtype__ == 'mysql') {
		q('ALTER TABLE '. $table .' DROP INDEX '. $index);
	} else if (__dbtype__ == 'pgsql') {
		// Leave PostgreSQL's Primary Keys alone.
		if ($index == $table .'_pkey') {
			return;
		}
		// Drop cascase to also drop possible PK constraints.
		q('DROP INDEX '. $index .' CASCADE');
	} else {
		// Skip SQLite's auto indexes.
		if (__dbtype__ == 'sqlite' && strpos($index, 'sqlite_autoindex') !== FALSE) {
			return;
		}
		q('DROP INDEX '. $index);
	}
}

/* Return a list of FUDforum's indexes from the database. */
function get_fud_index_list($table)
{
	$tbl = array();

	if (__dbtype__ == 'mysql') {
		$c = q('SHOW INDEX FROM '. $table);
		while ($r = db_rowobj($c)) {
			if ($r->Key_name == 'PRIMARY') {
				continue;
			}
			if (!isset($tbl[$r->Key_name])) {
				$tbl[$r->Key_name] = array('unique'=>!$r->Non_unique, 'cols'=>array($r->Column_name));
			} else {
				$tbl[$r->Key_name]['cols'][] = $r->Column_name;
			}
		}
		unset($c);

		foreach ($tbl as $k => $v) {
			$tbl[$k]['cols'] = implode(',', $v['cols']);
		}
	} else if (__dbtype__ == 'oracle') {
		$c = q('SELECT LOWER(index_name) AS index_name, DECODE(uniqueness, \'UNIQUE\', 1, 0) AS uniq 
				FROM user_indexes
				WHERE table_name =UPPER(\''. $table .'\')
				AND index_type = \'NORMAL\' AND generated = \'N\'');
		while ($r = db_rowobj($c)) {
			$tbl[$r->index_name] = array('unique' => $r->uniq, 'cols' => array());
		
			$c2 = q('SELECT LOWER(column_name) as column_name FROM user_ind_columns WHERE index_name = UPPER(\''. $r->index_name .'\') ORDER BY column_position');
			while ($r2 = db_rowobj($c2)) {
				$tbl[$r->index_name]['cols'][] = $r2->column_name;
			}
		}
		unset($c);

		foreach ($tbl as $k => $v) {
			$tbl[$k]['cols'] = implode(',', $v['cols']);
		}
	} else if (__dbtype__ == 'pgsql') {
		$c = q('SELECT pg_catalog.pg_get_indexdef(i.indexrelid) 
				FROM pg_catalog.pg_class c, pg_catalog.pg_class c2, pg_catalog.pg_index i 
				WHERE c.relname = \''. $table .'\' 
				AND c.oid= i.indrelid 
				AND i.indexrelid = c2.oid');
		while ($r = db_rowarr($c)) {
			$tmp = explode(' ', $r[0], 5);
			if ($tmp[1] != 'UNIQUE') {
				$tbl[$tmp[2]] = array('unique' => 0, 'cols' => substr(strrchr(array_pop($tmp), '('), 1, -1));
			} else {
				if (preg_match('/_pkey$/', $tmp[3])) {
					continue;	// Skip autogenerated PK indexes.
				}
				$tbl[$tmp[3]] = array('unique' => 1, 'cols' => substr(strrchr(array_pop($tmp), '('), 1, -1));
			}
		}
		unset($c);
	} else if (__dbtype__ == 'sqlite') {
		$c = q('PRAGMA index_list(\''. $table .'\')');
		while ($r = db_rowobj($c)) {
			$tbl[$r->name] = array('unique' => $r->unique, 'cols' => array());

			$c2 = q('PRAGMA index_info(\''. $r->name .'\')');
			while ($r2 = db_rowobj($c2)) {
				$tbl[$r->name]['cols'][] = $r2->name;
			}
		}
		unset($c);

		foreach ($tbl as $k => $v) {
			$tbl[$k]['cols'] = implode(',', $v['cols']);
		}
	}

	return $tbl;
}

/* Add a column to a database table. */
function add_column($tbl, $col, $prop)
{
	if (__dbtype__ != 'sqlite') {
		q('ALTER TABLE '. $tbl .' ADD '. make_table_row($col, $prop));
	} else {
		// SQLite cannot add PK cols. Add them without PK clause and hope for the best.
		$col_def = make_table_row($col, $prop);
		$col_def = str_replace('PRIMARY KEY', '', $col_def);
		q('ALTER TABLE '. $tbl .' ADD '. $col_def);
	}
}

/* Change the properties of a column on a database table. */
function alter_column($tbl, $col, $prop)
{
	if (__dbtype__ == 'pgsql') {
var_dump($prop);
		q('ALTER TABLE '. $tbl .' ALTER COLUMN '. $col .' TYPE '. $prop['type']);
		if (!is_null($prop['default'])) {
			if (strpos($prop['type'], 'INT') !== false || $prop['default'] == 'NULL') {
				$def = $prop['default'];	// Don't quote NULL or numbers.
			} else {
				$def = '\''. $prop['default'] .'\'';
			}
			q('ALTER TABLE '. $tbl .' ALTER COLUMN '. $col .' SET DEFAULT '. $def);
			q('UPDATE '. $tbl .' SET '. $col .'='. $def .' WHERE '. $col .' IS NULL');
		}
		if ($prop['not_null']) {
			q('ALTER TABLE '. $tbl .' ALTER COLUMN '. $col .' SET NOT NULL');
		}
	} else if (__dbtype__ == 'sqlite') {
		// SQLite cannot change columns, we need to recreate the table.

		// Construct new CREATE TABLE statement.
		$new_tab_def = 'CREATE TABLE '. $tbl .' (';
		$tmp_db_cols = get_fud_col_list($tbl);
		foreach ($tmp_db_cols as $tmp_name => $tmp_pr) {
			if ($col == $tmp_name) {
				$new_tab_def .= make_table_row($col, $prop) .",\n";
			} else {
				$new_tab_def .= make_table_row($tmp_name, $tmp_pr) .",\n";
			}
		}
		$new_tab_def = preg_replace('/,$/', ')', $new_tab_def);
		q('BEGIN TRANSACTION');
		drop_table('tmp', true);
		q('CREATE TABLE tmp AS SELECT * FROM '. $tbl);
		drop_table($tbl);
		q($new_tab_def);
		q('INSERT INTO '. $tbl .' SELECT * FROM tmp');
		drop_table('tmp');
		q('COMMIT');
	} else {
		q('ALTER TABLE '. $tbl .' CHANGE '. $col .' '. make_table_row($col, $prop));
	}
}

function make_table_row($col, $prop)
{
	$data = ' '. $col .' '. $prop['type'];
	if ($prop['not_null']) {
		$data .= ' NOT NULL';
	}
	if (!is_null($prop['default'])) {
		if (strpos($prop['type'], 'INT') !== false || $prop['default'] == 'NULL') {
			$data .= ' DEFAULT '. $prop['default'];	// Don't quote NULL or numbers.
		} else {
			$data .= ' DEFAULT \''. $prop['default'] .'\'';
		}
	}
	if ($prop['auto']) {
		$data .= ' AUTO_INCREMENT';
	}
	if ($prop['primary']) {
		$data .= ' PRIMARY KEY';
	}

	// Because I'm lazy I've copied this from create_table(). Separate func maybe?
	// Data type conversions.
	$src = $dst = array();
	if (__dbtype__ == 'mssql') {
		array_push($src, '!BINARY!', '!INT NOT NULL AUTO_INCREMENT!');
		array_push($dst, '',          'INT IDENTITY');
	} else if (__dbtype__ == 'oracle') {
		// Use FLOAT (cannot use NUMBER) for BIGINT in Oracle. We need a seperate type to back map it to BIGINT later.
		array_push($src, '/TEXT/', '/BIGINT/', '/BINARY/', '/INT NOT NULL AUTO_INCREMENT/', '/NOT NULL DEFAULT (.+?)([,\s$])/');
		array_push($dst, 'CLOB',   'FLOAT',    '',         'NUMBER',                        'DEFAULT \\1 NOT NULL\\2');
	} else if (__dbtype__ == 'pgsql') {
		array_push($src, '!BINARY!', '!INT NOT NULL AUTO_INCREMENT!');
		array_push($dst, '',         'SERIAL');
	} else if (__dbtype__ == 'sqlite') {
		array_push($src, '!BINARY!', '!INT NOT NULL AUTO_INCREMENT!');
		array_push($dst, '',         'INTEGER');
	}
	$data = trim(preg_replace($src, $dst, $data));

	return $data;
}

/* Drop a column from a database table. */
function drop_column($tbl, $col)
{
	if (__dbtype__ == 'pgsql') {
		// CASCADE required to also drop possible PK constraints.
		q('ALTER TABLE '. $tbl .' DROP '. $col .' CASCADE');
	} else if (__dbtype__ == 'sqlite') {
		// SQLite cannot drop columns. We need to recreate the table.
		q('BEGIN TRANSACTION');
		drop_table('tmp', true);
		q('CREATE TABLE tmp AS SELECT * FROM '. $tbl);
		drop_table($tbl);
		$sql_file = $GLOBALS['DATA_DIR'] .'/sql/fud_'. substr($tbl, strlen($GLOBALS['DBHOST_TBL_PREFIX'])) .'.tbl';
		create_table(file_get_contents($sql_file));
		$col_list = implode(',', array_keys(get_fud_col_list($tbl)));
		q('INSERT INTO '. $tbl .' SELECT '. $col_list .' FROM tmp');
		drop_table('tmp');
		q('COMMIT');
	} else {
		q('ALTER TABLE '. $tbl .' DROP '. $col);
	}
}

/* Reset database sequences to the highest count based on data values. */
function reset_fud_sequences()
{
	/* We may need to restore sequence numbers for databases like Oracle and PostgreSQL. */
	if (__dbtype__ == 'oracle') {
		foreach(db_all('SELECT sequence_name FROM user_sequences WHERE sequence_name LIKE upper(\''. addcslashes($GLOBALS['DBHOST_TBL_PREFIX'], '_') .'%\_id\_seq\') ESCAPE \'\\\'') as $v) {
			if (!($m = q_singleval('SELECT MAX(id)+1 FROM '. basename($v, '_ID_SEQ')))) {
				$m = 1;
			}
			// echo 'Reset Oracle sequence '. $v .' to '. $m .'.<br />';
			q('DROP SEQUENCE '. $v);
			q('CREATE SEQUENCE '. $v .' START WITH '. $m);
		}
	} else if (__dbtype__ == 'pgsql') {
		foreach(db_all('SELECT relname FROM pg_class WHERE relkind=\'S\' AND relname LIKE \''. addcslashes($GLOBALS['DBHOST_TBL_PREFIX'], '_') .'%\_id\_seq\'') as $v) {
			if (!($m = q_singleval('SELECT MAX(id) FROM '. basename($v, '_id_seq')))) {
				$m = 1;
			}
			// echo 'Reset PgSQL sequence '. $v .' to '. $m .'.<br />';
			q('SELECT setval(\''. $v .'\', '. $m .')');
		}
	}
}

?>
