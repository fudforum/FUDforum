<?php
/**
* copyright            : (C) 2001-2012 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id$
*
* This program is free software; you can redistribute it and/or modify it 
* under the terms of the GNU General Public License as published by the 
* Free Software Foundation; version 2 of the License. 
**/

/** Check DB version compatibility. */
function validate_db_version()
{
	if (!function_exists('db_version')) {
		// get_version() was renamed to db_version() in FUDforum v3.0.2.
		function db_version() { return get_version(); }
	}

	$dbver = db_version();	// Defined in db.inc.

	if (__dbtype__ == 'mysql' && version_compare($dbver, '5.0.0', '<')) {
		return 'MySQL version '. $dbver .' is not supported. Please upgrade to MySQL Version 5.0.0 or higher.';
	} else if (__dbtype__ == 'pgsql' && version_compare($dbver, '8.1.0', '<')) {
		return 'PostgreSQL version '. $dbver .' is not supported. Please upgrade to PgSql Version 8.1.0 or higher.';
	} else if (__dbtype__ == 'oracle' && version_compare($dbver, '10.2.0', '<')) {
		return 'Oracle version '. $dbver .' is not supported. Please upgrade to Oracle Version 10.2.0 or higher.';
	} else if (__dbtype__ == 'sqlsrv' && version_compare($dbver, '10.00.00', '<')) {
		return 'SQL Server version '. $dbver .' is not supported. Please upgrade to SQL Server Version 11.00.0000 or higher.';
	}
}

/** Return list of indexes and columns for a given table as defined in FUDforum's SQL files. */
function get_stbl_from_file($file)
{
	$data = str_replace('{SQL_TABLE_PREFIX}', $GLOBALS['DBHOST_TBL_PREFIX'], file_get_contents($file));
	$tbl  = array('name'=>'', 'index'=>array(), 'flds'=>array());

	/* Extract table name. */
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
	if (preg_match_all('!CREATE\s+?(UNIQUE |)INDEX\s+([^\s]+)\s+ON\s+'. $tbl['name'] .' \(([^;]+)\);!', $data, $m)) {
		$c = count($m[0]);
		for ($i = 0; $i < $c; $i++) {
			$tbl['index'][$m[2][$i]] = array('unique'=>(empty($m[1][$i]) ? 0 : 1), 'cols'=>str_replace(' ', '', $m[3][$i]));
		}
	}

	return $tbl;
}

/** Return a list of FUDforum tables from the database. */
function get_fud_table_list($tbl=null)
{
	if (__dbtype__ == 'db2') {
		if ($tbl) {
			return db_all('SELECT lower(tabname) FROM syscat.tables WHERE tabname LIKE \''. strtoupper($tbl) .'\'');
		}
		return db_all('SELECT lower(tabname) FROM syscat.tables WHERE tabname LIKE \''. strtoupper($GLOBALS['DBHOST_TBL_PREFIX'] .'%\''));
	} else if (__dbtype__ == 'firebird') {
		if ($tbl) {
			return db_all('SELECT lower(trim(rdb$relation_name)) FROM rdb$relations WHERE rdb$relation_name LIKE \''. strtoupper($tbl) .'%\'');
		}
		return db_all('SELECT lower(trim(rdb$relation_name)) FROM rdb$relations WHERE rdb$relation_name LIKE \''. strtoupper($GLOBALS['DBHOST_TBL_PREFIX']) .'%\'');
	} else if (__dbtype__ == 'mssql') {
		if ($tbl) {
			return db_all('SELECT table_name FROM information_schema.tables WHERE table_name LIKE \''. $tbl .'\'');
		}
		return db_all('SELECT table_name FROM information_schema.tables WHERE table_name LIKE \''. $GLOBALS['DBHOST_TBL_PREFIX'] .'%\'');
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

/** Return a list of colums and column properties from the database. */
function get_fud_col_list($table)
{
	$ret = array();

	if (__dbtype__ == 'db2') {
		$c = q('SELECT DISTINCT c.colname, c.typename, c.nulls, c.default, c.length, c.scale,
                c.identity, tc.type, k.colseq, c.colno
                FROM syscat.columns c
                LEFT JOIN (syscat.keycoluse k JOIN syscat.tabconst tc
                ON (k.tabschema = tc.tabschema
                    AND k.tabname = tc.tabname
                    AND tc.type = \'P\'))
                ON (c.tabschema = k.tabschema
                    AND c.tabname = k.tabname
                    AND c.colname = k.colname)
                WHERE UPPER(c.tabname) = UPPER(\''. $table .'\') 
				ORDER BY c.colno');
		while ($r = db_rowarr($c)) {
			$colname  = strtolower($r[0]);
			$type     = preg_replace(array('!INTEGER!', '!CHARACTER!', '!CLOB\(.+?\)!'), array('INT', 'CHAR', 'TEXT'), $r[1]);
			$type     = ($type=='CHAR' || $type=='VARCHAR') ? $type .'('. $r[4] .')' : $type;	// Append type length.
			$not_null = $r[2] == 'Y' ? 0 : 1;
			$key      = $r[7] == 'P' ? 1 : 0;
			$default  = trim(str_replace("'", '', $r[3]));
			$auto     = $r[6] == 'Y' ? 1 : 0;
			$ret[$colname] = array('type'=>$type, 'not_null'=>$not_null, 'primary'=>$key, 'default'=>$default, 'auto'=>$auto);
		}
	} else if (__dbtype__ == 'firebird') {
		$c = q('SELECT r.rdb$field_name,
					CASE f.rdb$field_type
						WHEN   8 THEN \'INT\'
						WHEN  14 THEN \'CHAR\'
						WHEN  16 THEN \'BIGINT\'
						WHEN  37 THEN \'VARCHAR\'
						WHEN 261 THEN \'TEXT\'
						ELSE \'UNKNOWN\'
					END AS field_type,
					r.rdb$null_flag,
					r.rdb$default_source,
					f.rdb$field_length
				FROM rdb$relation_fields r
				LEFT JOIN rdb$fields f ON r.rdb$field_source = f.rdb$field_name
				LEFT JOIN rdb$indices i ON i.rdb$relation_name = r.rdb$relation_name
				LEFT JOIN rdb$relation_constraints c ON c.rdb$index_name = i.rdb$index_name
				WHERE r.rdb$relation_name=UPPER(\''. $table .'\')
				ORDER BY r.rdb$field_position');
		while ($r = db_rowarr($c)) {
			$colname  = strtolower($r[0]);
			$type     = trim($r[1]);	// Get rid of spaces.
			$type     = ($type=='CHAR' || $type=='VARCHAR') ? $type .'('. $r[4] .')' : $type;	// Append type length.
			$not_null = $r[2] == '1' ? 1 : 0;
			$key      = (int)q_singleval('SELECT 1 FROM rdb$relation_constraints c, rdb$index_segments i WHERE i.rdb$index_name = c.rdb$index_name AND c.rdb$relation_name = UPPER(\''. $table .'\') AND i.rdb$field_name = \''. $r[0] .'\' AND c.rdb$constraint_type = \'PRIMARY KEY\'');
			$default  = trim(str_replace(array("'", 'DEFAULT '), '', $r[3]));

			// Check if PK is AUTO GENERATED (i.e. has a sequence).
			$auto = 0;
			if ($type == 'INT' && $key) {
				// The extra % at the end is not a bug. There is a space at the end of the col.
				$auto = (int)q_singleval('SELECT 1 FROM rdb$generators WHERE rdb$generator_name LIKE UPPER(\''. $table .'%_SEQ%\')');
			}

			$ret[$colname] = array('type'=>$type, 'not_null'=>$not_null, 'primary'=>$key, 'default'=>$default, 'auto'=>$auto);
		}
	} else if (__dbtype__ == 'mssql') {
		$c = q('SELECT column_name, data_type, is_nullable, column_default, character_maximum_length FROM INFORMATION_SCHEMA.Columns WHERE table_name = \''. $table .'\' ORDER BY ordinal_position');
		while ($r = db_rowarr($c)) {
			$colname = $r[0];
			$type = strtoupper($r[1]);
			$type = ($type=='CHAR' || $type=='VARCHAR') ? $type .'('. $r[4] .')' : $type;	// Append type length.
			$not_null = ($r[2] == 'NO') ? 1 : 0;
			$key = (int)q_singleval('SELECT 1 FROM INFORMATION_SCHEMA.Key_Column_Usage WHERE table_name = \''. $table .'\' AND column_name = \''. $r[0] .'\' AND constraint_name LIKE \'PK%\'');
			$default = str_replace(array('\'',')','('), '', $r[3]);

			$auto = 0;
			if ($type == 'INT' && $key) {
				$auto = (int)q_singleval('SELECT 1 FROM syscolumns WHERE COLUMNPROPERTY(id, name, \'IsIdentity\') = 1 AND OBJECT_NAME(id) =  \''. $table .'\' AND name = \''. $r[0] .'\'');
			}

			$ret[$colname] = array('type'=>$type, 'not_null'=>$not_null, 'primary'=>$key, 'default'=>$default, 'auto'=>$auto);
		}
	} else if (__dbtype__ == 'mysql') {
		// We can also use: SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, COLUMN_DEFAULT FROM INFORMATION_SCHEMA.COLUMNS...
		$c = q('SHOW FIELDS FROM '. $table);
		while ($r = db_rowobj($c)) {
			$type     = strtoupper(preg_replace('!(int|bigint)\(([0-9]+)\)!', '\1', $r->Type));
			$not_null = $r->Null == 'YES' ? 0 : 1;
			$key      = $r->Key == 'PRI' ? 1 : 0;
			$default  = (!is_null($r->Default) && $r->Default != 'NULL') ? $r->Default : '';
			$auto     = $r->Extra ? 1 : 0;

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
			$colname  = strtolower( $r[0] );
			$type     = preg_replace(array('!NUMBER!', '!VARCHAR2!', '!CLOB\(.+?\)!', '!FLOAT!'), array('INT', 'VARCHAR', 'TEXT', 'BIGINT'), $r[1]);
			$not_null = $r[2] == 'N' ? 1 : 0;
			$key      = $r[3] == 'P' ? 1 : 0;
			$default  = is_null($r[4]) ? null : trim(str_replace("'", '', $r[4]));

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
			$type        = strtoupper(preg_replace(array('!character varying!', '!character!', '!integer!'), array('VARCHAR', 'CHAR', 'INT'), $r[1]));
			$type        = (empty($r[5])) ? $type : $type .'('. $r[5] .')';	// Append type length.
			$auto        = !strncmp($r[3], 'nextval', 7) ? 1 : 0;
			$default_val = preg_replace('/::.*/', '', $r[3]);	// Remove anything after ::. I.e. '0'::character varying.
			$default     = (!$auto) ? trim(str_replace("'", '', $default_val)) : null;

			$ret[$r[0]] = array('type'=>$type, 'not_null'=>$not_null, 'primary'=>$key, 'default'=>$default, 'auto'=>$auto);
		}
		unset($r);
	} else if (__dbtype__ == 'sqlite') {
		$c = q('PRAGMA table_info(\''. $table .'\')');
		while ($r = db_rowobj($c)) {
			$key      = $r->pk;
			$not_null = ($r->notnull || $r->pk) ? 1 : 0;
			$type     = ($r->type == 'INTEGER') ? 'INT' : $r->type;
			$default  = is_null($r->dflt_value) ? null : trim(str_replace("'", '', $r->dflt_value));
			$auto     = ($r->type == 'INTEGER' && $r->pk) ? 1 : 0;

			$ret[$r->name] = array('type'=>$type, 'not_null'=>$not_null, 'primary'=>$key, 'default'=>$default, 'auto'=>$auto);
		}
		unset($r);
	}

	return $ret;
}

/** Add a composite primary key to a table. */
function create_primary_key($table, $col_list)
{
	if (__dbtype__ == 'mysql') {
		q('ALTER IGNORE TABLE '. $table .' ADD PRIMARY KEY ('. $col_list .')');
	} else if (__dbtype__ == 'sqlite') {
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
	} else {
		/* Before adding the primary key, we need to remove the duplicates. */
		delete_duplicate_rows($table, $col_list);
		q('ALTER TABLE '. $table .' ADD PRIMARY KEY ('. $col_list .')');
	}
}

/** Vacuum/Optimize/Analyze all FUDforum's database tables. */
function optimize_fud_tables($tbl_list=null)
{
	if (__dbtype__ == 'sqlite') {	// DB level.
		q('VACUUM');
	}

	if (!$tbl_list) {
		$tbl_list = get_fud_table_list();
	}

	foreach ($tbl_list as $t) {
		if (__dbtype__ == 'db2') {
			q('CALL SYSPROC.ADMIN_CMD(\'RUNSTATS ON TABLE '. $GLOBALS['DBHOST_USER'] .'.'. $t .' ON KEY COLUMNS and INDEXES ALL\');');
		} else if (__dbtype__ == 'firebird') {
			return;	// Automated garbage collection, no intervention required.
		} else if (__dbtype__ == 'mssql') {
			q('UPDATE STATISTICS '. $t);
		} else if (__dbtype__ == 'mysql') {
			q('OPTIMIZE TABLE '. $t);
		} else if (__dbtype__ == 'oracle') {
			q('ANALYZE TABLE '. $t .' COMPUTE STATISTICS;');
		} else if (__dbtype__ == 'pgsql') {
			q('VACUUM ANALYZE '. $t);
		} else if (__dbtype__ == 'sqlite') {
			q('ANALYZE '. $t);
		}
	}
}

/** Return the size of FUDforum's database tables in bytes. */
function get_sql_disk_usage()
{
	if (__dbtype__ == 'db2') {
		return q_singleval('SELECT SUM(t.npages*ts.pagesize) AS size FROM SYSCAT.TABLES t, SYSCAT.TABLESPACES ts WHERE t.tbspace = ts.tbspace AND T.tabname LIKE UPPER(\''. $GLOBALS['DBHOST_TBL_PREFIX'] .'%\')');
	} else if (__dbtype__ == 'firebird') {
		return filesize($GLOBALS['DBHOST_DBNAME']);
	} else if (__dbtype__ == 'mssql') {
		return q_singleval('SELECT SUM(a.used_pages)*8 FROM sys.objects o JOIN sys.partitions p ON p.object_id = o.object_id JOIN sys.allocation_units a ON p.partition_id = a.container_id WHERE o.type = \'U\' AND o.name LIKE \''. $GLOBALS['DBHOST_TBL_PREFIX'] .'%\'');
	} else if (__dbtype__ == 'mysql') {
		return q_singleval('SELECT sum(data_length + index_length) FROM information_schema.TABLES WHERE table_name LIKE \''. $GLOBALS['DBHOST_TBL_PREFIX'] .'%\'');
	} else if (__dbtype__ == 'oracle') {
		return q_singleval('SELECT sum(bytes) FROM user_segments WHERE segment_name LIKE UPPER(\''. $GLOBALS['DBHOST_TBL_PREFIX'] .'%\')');
	} else if (__dbtype__ == 'pgsql') {
		return q_singleval('SELECT pg_database_size(\''. $GLOBALS['DBHOST_DBNAME'] .'\')');
	} else if (__dbtype__ == 'sqlite') {
		// We can return filesize($DBHOST), but DB size is already accounted for in directory size.
		return 0;
	}

	return 0;	// Not implemented.
}

/** Create a new database table. */
function create_table($script)
{
	$script     = preg_replace('!#.*\n!', '', $script);	// Remove comments.
	$statements = explode(';', $script);	// Script may have several statement.

	foreach ($statements as $q) {
		$q = str_replace('{SQL_TABLE_PREFIX}', $GLOBALS['DBHOST_TBL_PREFIX'], $q);

		/* Because mysql with InnoDB table refuses to resolve indexes correctly, we need to force MyISAM table type. */
		if (__dbtype__ == 'mysql' && preg_match('/^\s*CREATE\s*TABLE\s*/i', $q)) {
			$q .= ' ENGINE=MyISAM ';
			/* We need to specify a default charset for MySQL 4.1.2+. */
			$q .= ' DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci';
		}		

		// Queue extra statements to create Firebird and Oracle sequences and triggers later.
		$extra_stmts = array();
		if (preg_match('/^\s*CREATE\s*TABLE\s*([\{\}\w]*)/i', $q, $m)) {
			$tbl = $m[1];				
			preg_match_all('/\s*(\w*)\s*INT NOT NULL AUTO_INCREMENT/i', $q, $m, PREG_PATTERN_ORDER);
			if (__dbtype__ == 'firebird') {
				foreach ($m[1] as $c) {
					array_push($extra_stmts, 'CREATE SEQUENCE '. $tbl .'_'. $c .'_seq');
					array_push($extra_stmts, 'CREATE OR ALTER TRIGGER '. $tbl .'_'. $c .'_trg FOR '. $tbl .' active before insert position 0 AS BEGIN if (new.'. $c .' is null) then begin new.'. $c .' = gen_id('. $tbl .'_'. $c .'_seq, 1); end end');
				}
			} else if (__dbtype__ == 'oracle') {
				foreach ($m[1] as $c) {
					array_push($extra_stmts, 'CREATE SEQUENCE '. $tbl .'_'. $c .'_seq');
					array_push($extra_stmts, 'CREATE OR REPLACE TRIGGER '. $tbl .'_'. $c .'_trg BEFORE INSERT ON '. $tbl .' FOR EACH ROW BEGIN SELECT '. $tbl .'_'. $c .'_seq.nextval INTO :new.'. $c .' FROM dual; END;');
				}
			}
		}

		// Data type conversions.
		$src = array('!UNIX_TIMESTAMP!');
		$dst = array(time());
		if (__dbtype__ == 'db2') { 
			array_push($src, '!TEXT!',      '!BINARY!', '!INT NOT NULL AUTO_INCREMENT!');
			array_push($dst, 'CLOB(65535)', '',         'INTEGER GENERATED BY DEFAULT AS IDENTITY');
		} else if (__dbtype__ == 'firebird') {
			// 'NOT NULL' for Firebird must be specified last.
			array_push($src, '!BINARY!', '!INT NOT NULL AUTO_INCREMENT!', '!TEXT!',             '/NOT NULL DEFAULT (.+?)([,\s$])/');
			array_push($dst, '',          'INT',                          'BLOB SUB_TYPE TEXT', 'DEFAULT \\1 NOT NULL\\2');
		} else if (__dbtype__ == 'mssql') {
			array_push($src, '!BINARY!', '!INT NOT NULL AUTO_INCREMENT!');
			array_push($dst, '',          'INT IDENTITY');
		} else if (__dbtype__ == 'oracle') {
			// Use FLOAT (cannot use NUMBER) for BIGINT in Oracle. We need a seperate type to back map it to BIGINT later.
			// 'NOT NULL' for Oracle must be specified last.
			array_push($src, '/TEXT/', '/BIGINT/', '/BINARY/', '/INT NOT NULL AUTO_INCREMENT/', '/NOT NULL DEFAULT (.+?)([,\s$])/');
			array_push($dst, 'CLOB',   'FLOAT',    '',         'NUMBER',                        'DEFAULT \\1 NOT NULL\\2');
		} else if (__dbtype__ == 'pgsql') {
			array_push($src, '!BINARY!', '!INT NOT NULL AUTO_INCREMENT!');
			array_push($dst, '',         'SERIAL');
		} else if (__dbtype__ == 'sqlite') {
			array_push($src, '!BINARY!', '!INT NOT NULL AUTO_INCREMENT!');
			array_push($dst, '',         'INTEGER');
		}
		$q = preg_replace($src, $dst, $q);

		// Do it!
		if (($q = trim($q))) {
			// echo "<pre>DEBUG: ". $q ."</pre>";
			$rc = q($q);
			if ($rc === FALSE) return $rc;
		}
	}

	/* Run extra statements. For example, crreate Oracle sequences and triggers. */
	foreach($extra_stmts as $q) {
		// echo "<pre>DEBUG: Run $q</pre>";
		$rc = q($q);
	}
	
	return $rc;
}

/** Drop a table. */
function drop_table($tbl, $ifexists=false)
{
	$tbl = str_replace('{SQL_TABLE_PREFIX}', $GLOBALS['DBHOST_TBL_PREFIX'], $tbl);

	// Drop Firebird and Oracle sequences.
	if (__dbtype__ == 'firebird') {
		q('COMMIT');	// Try to prevent "unsuccessful metadata update object... is in use".
		$c = q('SELECT rdb$generator_name FROM rdb$generators WHERE rdb$system_flag=0 AND rdb$generator_name LIKE \''. strtoupper($tbl .'%\''));
		while ($r = db_rowarr($c)) {
			// echo 'DEBUG: Drop firebird seq '. $r[0] .'<hr>';
			$trg_name = str_replace('_SEQ', '_TRG', $r[0]);
			q('DROP TRIGGER '. $trg_name);
			q('DROP SEQUENCE '. $r[0]);
		}
	} else if (__dbtype__ == 'oracle') {
		$c = q('SELECT sequence_name FROM user_sequences WHERE sequence_name LIKE \''. strtoupper($tbl .'%\''));
		while ($r = db_rowarr($c)) {
			// echo 'DEBUG: Drop oracle seq '. $r[0] .'<hr>';
			q('DROP SEQUENCE '. $r[0]);
		}
	}

	if ($ifexists) {
		if (__dbtype__ == 'db2') {
			q('BEGIN IF EXISTS (SELECT name FROM sysibm.systables WHERE name = \''. strtoupper($tbl) .'\') THEN EXECUTE IMMEDIATE \'DROP TABLE '. $tbl .'\'; END IF; END');
		} else if (__dbtype__ == 'firebird') {
			if (q_singleval('SELECT rdb$relation_name FROM rdb$relations WHERE rdb$relation_name = \''. strtoupper($tbl) .'\'')) {
				drop_table($tbl);
			}
		} else if (__dbtype__ == 'mssql') {
			q('IF EXISTS(SELECT table_name FROM information_schema.tables WHERE table_name = \''. $tbl .'\') DROP TABLE '. $tbl);
		} else if (__dbtype__ == 'oracle') {
			q('BEGIN execute immediate \'DROP TABLE '. $tbl .'\'; EXCEPTION when others then null; END;');
		} else {
			q('DROP TABLE IF EXISTS '. $tbl);
		}
	} else {
		q('DROP TABLE '. $tbl);
	}
}

/** Empty out a table. */
function truncate_table($tbl)
{
	if (__dbtype__ == 'sqlite') {
		// Guess what database doen't have a TRUNCATE command.
		q('DELETE FROM '. $tbl);
	} else {
		q('TRUNCATE TABLE '. $tbl);
	}
}

/** Delete duplicate rows from a table. */
function delete_duplicate_rows($tbl, $flds)
{
	$f = explode(',', $flds);
	$n = count($f);

	// Databases with an internal row identifier.
	if (__dbtype__ == 'firebird' || __dbtype__ == 'pgsql' || __dbtype__ == 'sqlite' || __dbtype__ == 'oracle') {
		$idcol = (__dbtype__ == 'firebird') ? 'rdb$db_key' : ((__dbtype__ == 'pgsql') ? 'oid' : 'rowid');
		$where = '';
		foreach ($f as $v) {
			$cond   = 'i.'. $v .' = '. $tbl .'.'. $v;
			$where .= (empty($where)) ? $cond : ' AND '. $cond;
		}
		q('DELETE FROM '. $tbl .' WHERE EXISTS (SELECT 1 FROM '. $tbl .' i WHERE '. $where .' AND i.'. $idcol .' < '. $tbl .'.'. $idcol .')');

		return;
	}

	// Other databases (at leaset MySQL & MS-SQL).
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
		if (__dbtype__ == 'mysql') {
			q('DELETE FROM '. $tbl .' WHERE '. $cond .' LIMIT '. ($r[$n] - 1));
		} else if (__dbtype__ == 'mssql') {
			q('DELETE TOP ('. ($r[$n] - 1) .') FROM '. $tbl .' WHERE '. $cond);
		} else {
			die('Cannot delete duplicate rows. Not implemented for your database!');
		}
	}
	unset($c);
}

/** Create an index on a table. */
function create_index($tbl, $name, $unique, $flds, $del_dups=true)
{
	$tbl  = str_replace('{SQL_TABLE_PREFIX}', $GLOBALS['DBHOST_TBL_PREFIX'], $tbl);
	$name = str_replace('{SQL_TABLE_PREFIX}', $GLOBALS['DBHOST_TBL_PREFIX'], $name);

	/* Before adding a unique index, we need to check & remove any duplicates. */
	if ($unique && $del_dups) {
		delete_duplicate_rows($tbl, $flds);
	}

	/* Check if index already exists and must be dropped first. */
	if (array_key_exists($name, get_fud_index_list($tbl))) {
		drop_index($tbl, $name);
	}

	if (__dbtype__ == 'mysql') {
		// Prevent the famous 'duplicate entry' errors on MySQL.
		q('ALTER IGNORE TABLE '. $tbl .' ADD '. ($unique ? 'UNIQUE ' : 'INDEX ') . $name .' ('. $flds .')');
	} else {
		$unique = $unique ? 'UNIQUE' : '';
		q('CREATE '. $unique .' INDEX '. $name .' ON '. $tbl .' ('. $flds .')');
	}
}

/** Drop a database index. */
function drop_index($table, $index)
{
	if (__dbtype__ == 'mssql') {
		q('DROP INDEX '. $index .' ON '. $table);
	} else if (__dbtype__ == 'mysql') {
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

/** Return a list of FUDforum's indexes from the database. */
function get_fud_index_list($table)
{
	$tbl = array();

	if (__dbtype__ == 'db2') {
		$c = q('SELECT LOWER(indname) AS index_name, DECODE(uniquerule, \'U\', 1, 0) AS uniq 
				FROM syscat.indexes
				WHERE tabname = UPPER(\''. $table .'\')
				AND user_defined = 1');
		while ($r = db_rowobj($c)) {
			$tbl[$r->index_name] = array('unique' => $r->uniq, 'cols' => array());
			$c2 = q('SELECT LOWER(colname) as column_name FROM syscat.indexcoluse WHERE indname = UPPER(\''. $r->index_name .'\') ORDER BY colorder');
			while ($r2 = db_rowobj($c2)) {
				$tbl[$r->index_name]['cols'][] = $r2->column_name;
			}
		}
		unset($c);
		foreach ($tbl as $k => $v) {
			$tbl[$k]['cols'] = implode(',', $v['cols']);
		}
	} else if (__dbtype__ == 'firebird') {
		$c = q('SELECT LOWER(rdb$index_name) AS index_name, COALESCE(rdb$unique_flag,0) AS uniq 
				FROM rdb$indices
				WHERE rdb$relation_name = UPPER(\''. $table .'\')
				AND   rdb$index_name    LIKE UPPER(\''. $table .'%\')
				AND   rdb$system_flag = 0');
		while ($r = db_rowobj($c)) {
			$tbl[$r->index_name] = array('unique' => $r->uniq, 'cols' => array());
			$c2 = q('SELECT LOWER(rdb$field_name) as column_name FROM rdb$index_segments WHERE rdb$index_name = UPPER(\''. $r->index_name .'\') ORDER BY rdb$field_position');
			while ($r2 = db_rowobj($c2)) {
				$tbl[$r->index_name]['cols'][] = $r2->column_name;
			}
		}
		unset($c);
		foreach ($tbl as $k => $v) {
			$tbl[$k]['cols'] = implode(',', $v['cols']);
		}
	} else if (__dbtype__ == 'mssql') {
		$c = q('SELECT i.name AS index_name, is_unique AS uniq 
				FROM   sys.indexes i
				INNER JOIN sys.tables t ON i.object_id = t.object_id
				WHERE t.name = \''. $table .'\'
				AND   i.name LIKE \''. $table .'%\'');
		while ($r = db_rowobj($c)) {
			$tbl[$r->index_name] = array('unique' => $r->uniq, 'cols' => array());
			$c2 = q('SELECT col.name AS column_name
						FROM sys.indexes ind
						INNER JOIN sys.index_columns ic ON ind.object_id = ic.object_id AND ind.index_id = ic.index_id
						INNER JOIN sys.columns col ON ic.object_id = col.object_id AND ic.column_id = col.column_id 
						INNER JOIN sys.tables t ON ind.object_id = t.object_id
						WHERE ind.name = \''. $r->index_name .'\'
						ORDER BY ic.index_column_id');
			while ($r2 = db_rowobj($c2)) {
				$tbl[$r->index_name]['cols'][] = $r2->column_name;
			}
		}
		unset($c);
		foreach ($tbl as $k => $v) {
			$tbl[$k]['cols'] = implode(',', $v['cols']);
		}
	} else if (__dbtype__ == 'mysql') {
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
				WHERE table_name = UPPER(\''. $table .'\')
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

/** Add a column to a database table. */
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

/** Change the properties of a column on a database table. */
function alter_column($tbl, $col, $prop)
{
	if (__dbtype__ == 'pgsql') {
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

/** Map column properties to DB syntax. */
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
	if (__dbtype__ == 'db2') { 
		array_push($src, '!TEXT!',      '!BINARY!', '!INT NOT NULL AUTO_INCREMENT!');
		array_push($dst, 'CLOB(65535)', '',         'INTEGER GENERATED BY DEFAULT AS IDENTITY');
	} else if (__dbtype__ == 'firebird') {
		array_push($src, '!BINARY!', '!INT NOT NULL AUTO_INCREMENT!', '!TEXT!');
		array_push($dst, '',          'INT',                          'BLOB SUB_TYPE TEXT');
	} else if (__dbtype__ == 'mssql') {
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

/** Drop a column from a database table. */
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

/** Reset database sequences to the highest count based on data values. */
function reset_fud_sequences()
{
	/* We may need to restore sequence numbers for databases like DB2, Oracle and PostgreSQL. */
	if (__dbtype__ == 'db2') {
		foreach(db_all('SELECT tabname FROM syscat.columns WHERE identity=\'Y\' AND tabname LIKE upper(\''. addcslashes($GLOBALS['DBHOST_TBL_PREFIX'], '_') .'%\')  ESCAPE \'\\\'') as $v) {
			if (!($m = q_singleval('SELECT MAX(id)+1 AS max_id FROM '. $v))) {
				$m = 1;
			}
			echo 'Reset DB2 sequence '. $v .'.id to '. $m .'.<br />';
			q('ALTER TABLE '. $v .' ALTER COLUMN id RESTART WITH '. $m);
		}
	} else if (__dbtype__ == 'firebird') {
		foreach(db_all('SELECT rdb$generator_name FROM rdb$generators WHERE rdb$system_flag=0 AND rdb$generator_name LIKE LIKE upper(\''. addcslashes($GLOBALS['DBHOST_TBL_PREFIX'], '_') .'%\') ESCAPE \'\\\'') as $v) {
			if (!($m = q_singleval('SELECT MAX(id)+1 FROM '. basename($v, '_ID_SEQ')))) {
				$m = 1;
			}
			q('ALTER SEQUENCE '. $v .' RESTART WITH '. $m);
		}
	} else if (__dbtype__ == 'oracle') {
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
