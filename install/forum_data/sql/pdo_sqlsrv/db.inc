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
# define('fud_query_stats', 1);

class db { public static $db, $affected_rows, $res; }

function db_close()
{
	db::$db = null;
}

function db_version()
{	
	if (!defined('__FUD_SQL_VERSION__')) {
		define('__FUD_SQL_VERSION__', db::$db->getAttribute(PDO::ATTR_SERVER_VERSION));
	}
	return __FUD_SQL_VERSION__;
}

function db_lock($tables)
{
	if (!empty($GLOBALS['__DB_INC_INTERNALS__']['db_locked'])) {
		fud_sql_error_handler('Recursive Lock', 'internal', 'internal', db_version());
	}

	// Need this because pgsql can't handle lock aliases.
	$tbl = array();
	foreach (explode(',', str_replace(' WRITE', '', $tables)) as $v) {
		if (!($v = trim($v))) continue;
		$tbl[] = strtok($v, ' ');
	}

	db::$db->beginTransaction();

	$GLOBALS['__DB_INC_INTERNALS__']['db_locked'] = 1;
}

function db_unlock()
{
	if (empty($GLOBALS['__DB_INC_INTERNALS__']['db_locked'])) {
		unset($GLOBALS['__DB_INC_INTERNALS__']['db_locked']);
		fud_sql_error_handler('DB_UNLOCK: no previous lock established', 'internal', 'internal', db_version());
	}

	if (--$GLOBALS['__DB_INC_INTERNALS__']['db_locked'] < 0) {
		unset($GLOBALS['__DB_INC_INTERNALS__']['db_locked']);
		fud_sql_error_handler('DB_UNLOCK: unlock overcalled', 'internal', 'internal', db_version());
	}

	db::$db->commit();

	unset($GLOBALS['__DB_INC_INTERNALS__']['db_locked']);
}

function db_locked()
{
	return isset($GLOBALS['__DB_INC_INTERNALS__']['db_locked']);
}

function db_affected()
{
	return db::$affected_rows;
}

function __enifo($a)
{
	return end($a);	// Return last element of error array.
}

function uq($query)
{
	return q($query);
}

if (!defined('fud_query_stats')) {
	function q($query)
	{
		if (!strncasecmp($query, 'SELECT', 6)) {
			db::$res = null;
			db::$res = db::$db->query(_mssql_syntax($query)) or fud_sql_error_handler($query, __enifo(db::$db->errorInfo()), db::$db->errorCode(), db_version());
			return db::$res;
		}

		db::$res = null;
		if ((db::$affected_rows = db::$db->exec($query)) === FALSE) {
			fud_sql_error_handler($query, __enifo(db::$db->errorInfo()), db::$db->errorCode(), db_version());
		}
		return db::$affected_rows;
	}
} else {
	function q($query)
	{
		if (!strncasecmp($query, 'SELECT', 6)) {
			if (!isset($GLOBALS['__DB_INC_INTERNALS__']['query_count'])) {
				$GLOBALS['__DB_INC_INTERNALS__']['query_count'] = 1;
			} else {
				++$GLOBALS['__DB_INC_INTERNALS__']['query_count'];
			}

			if (!isset($GLOBALS['__DB_INC_INTERNALS__']['total_sql_time'])) {
				$GLOBALS['__DB_INC_INTERNALS__']['total_sql_time'] = 0;
			}

			$s = microtime(true);
			db::$res = null;
			db::$res = db::$db->query(_mssql_syntax($query)) or fud_sql_error_handler($query, __enifo(db::$db->errorInfo()), db::$db->errorCode(), db_version());
			$e = gettimeofday(true);

			$GLOBALS['__DB_INC_INTERNALS__']['last_time'] = ($e - $s);
			$GLOBALS['__DB_INC_INTERNALS__']['total_sql_time'] += $GLOBALS['__DB_INC_INTERNALS__']['last_time'];

			echo '<hr><b>Query #'. $GLOBALS['__DB_INC_INTERNALS__']['query_count'] .'</b><small>';
			echo ': time taken:     <i>'. number_format($GLOBALS['__DB_INC_INTERNALS__']['last_time'], 4) .'</i>';
			echo ', affected rows:  <i>'. db_affected() .'</i>';
			echo ', total sql time: <i>'.  number_format($GLOBALS['__DB_INC_INTERNALS__']['total_sql_time'], 4) .'</i>';
			echo '<pre>'. preg_replace('!\s+!', ' ', htmlspecialchars($query)) .'</pre></small>';

			return db::$res;
		}

		db::$res = null;
		if ((db::$affected_rows = db::$db->exec($query)) === FALSE) {
			fud_sql_error_handler($query, __enifo(db::$db->errorInfo()), db::$db->errorCode(), db_version());
		}
		return db::$affected_rows;
	}
}

function db_rowobj($result)
{
	return $result->fetch(PDO::FETCH_OBJ);
}

function db_rowarr($result)
{
	return $result->fetch(PDO::FETCH_NUM);
}

function q_singleval($query)
{
	return q($query)->fetchColumn();
}

function q_limit($query, $limit, $off=0)
{
	if ($off) {
		// For SQL Server 11 (denali), simply:
		// return $query . ' OFFSET '. $off .' ROWS FETCH NEXT '. $limit .' ROWS ONLY';

		$query = preg_replace('/SELECT(\s*DISTINCT)?/Dsi', 'SELECT$1 TOP(10000000) ', $query);
		$query = 'SELECT *
				FROM (SELECT sub2.*, ROW_NUMBER() OVER(ORDER BY sub2.line2) AS line3
				FROM (SELECT 1 AS line2, sub1.* FROM ('. $query .') AS sub1) as sub2) AS sub3';
		if ($limit > 0)	{
			$query .= ' WHERE line3 BETWEEN '. ($off+1) .' AND '. ($off + $limit);
		} else {
			$query .= ' WHERE line3 > '. $off;
		}
		return $query;
	} else {
		return 'SELECT TOP '. $limit . substr($query, 6);
	}
}

function q_concat($arg)
{
	// Breaks the standard by using the '+' operator instead of '||'. 
	$tmp = func_get_args();
	$val = null;

	foreach($tmp as $str) {
		$val .= empty($val) ? 'CAST('. $str .' AS VARCHAR)' : ' + CAST('. $str .' AS VARCHAR)';
	}

	return $val;
}

function q_rownum() {
    return 'ROW_NUMBER() OVER(ORDER BY (SELECT 1))';
}

function q_bitand($fieldLeft, $fieldRight) {
	return $fieldLeft .' & '. $fieldRight;
}

function q_bitor($fieldLeft, $fieldRight) {
	return '('. $fieldLeft .' | '. $fieldRight .')';
}

function q_bitnot($bitField) {
	return '~'. $bitField;
}

function db_saq($q)
{
	return q($q)->fetch(PDO::FETCH_NUM);
}

function db_sab($q)
{
	return q($q)->fetch(PDO::FETCH_OBJ);
}

function db_qid($q)
{
	q($q);
	return db::$db->lastInsertId();
}

function db_arr_assoc($q)
{
	return q($q)->fetch(PDO::FETCH_ASSOC);
}

function db_fetch_array($q)
{
		return is_object($q) ? $q->fetch(PDO::FETCH_ASSOC) : null;
}

function db_li($q, &$ef, $li=0)
{
	$r = db::$db->exec($q);

	if ($r !== false) {
		if (!$li) {
			return $r;
		}
		return db::$db->lastInsertId();
	}

	/* Duplicate key. */
	if (($c = db::$db->errorCode()) == '23000' || $c == '23505') {
		$ef = ltrim(strrchr(__enifo(db::$db->errorInfo()), ' '));
		return null;
	} else {
		fud_sql_error_handler($q, __enifo(db::$db->errorInfo()), db::$db->errorCode(), db_version());
	}
}

function ins_m($tbl, $flds, $types, $vals)
{
	// MS-SQL 2008 and higher supports multiple value pairs.
	$r = db::$db->exec('INSERT INTO '. $tbl .' ('. $flds .') VALUES ('. implode('),(', $vals). ')');

	/* Ignore error 23000: Cannot insert duplicate key row in object. */
	if ($err = sqlsrv_errors()) {
		if ($err[0]['code'] == 23000) {
			return null;
		} else {
			fud_sql_error_handler($q, __enifo(db::$db->errorInfo()), db::$db->errorCode(), db_version());
		}
	}
}

function db_all($q)
{
	return uq($q)->fetchAll(PDO::FETCH_COLUMN);
}

function _esc($s)
{
	return db::$db->quote($s);
}

function _mssql_syntax($q)
{
	// Workaround for error: 'LENGTH' is not a recognized built-in function name.
	$q = preg_replace('/LENGTH\(/i', 'DataLength(', $q);

	return $q;
}

/* Connect to DB. */
if (empty(db::$db)) {
	if (!extension_loaded('sqlsrv')) {
		die('ERROR: sqlsrv extension not loaded!');
	}

	$dsn = 'sqlsrv:server='. $GLOBALS['DBHOST'] .'; Database='. $GLOBALS['DBHOST_DBNAME'];
	$opts = array(PDO::SQLSRV_ATTR_ENCODING => PDO::SQLSRV_ENCODING_UTF8);
	if (!($GLOBALS['FUD_OPT_1'] & 256)) {
		// Connection pooling is enabled by default.
		$opts['ConnectionPooling'] = 0;
	}

	try {
		db::$db = new PDO($dsn, $GLOBALS['DBHOST_USER'], $GLOBALS['DBHOST_PASSWORD'], $opts);
	} catch (PDOException $e) {
		fud_sql_error_handler('Failed to establish database connection', 'PDO says: '. $e->getMessage(), '', '');
	}

	define('__dbtype__', 'mssql');
}

?>
