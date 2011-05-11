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

if (!defined('fud_sql_lnk')) {
	if (!extension_loaded('sqlsrv')) {
		die('ERROR: sqlsrv extension not loaded!');
	}

	$connInfo = array('UID'=>$GLOBALS['DBHOST_USER'], 'PWD'=>$GLOBALS['DBHOST_PASSWORD'],
                      'Database'=>$GLOBALS['DBHOST_DBNAME'], 'CharacterSet'=>'UTF-8');

	if (!($GLOBALS['FUD_OPT_1'] & 256)) {
		// Connection pooling is enabled by default.
		$connInfo['ConnectionPooling'] = 0;
	}
	$conn = sqlsrv_connect($GLOBALS['DBHOST'], $connInfo);
	if ($conn === false ) {
		$err = sqlsrv_errors();
		fud_sql_error_handler('Initiating SQLSRV driver', $err[0]['message'], $err[0]['code'], 'Unknown');
	}
	define('fud_sql_lnk', $conn);

	define('__dbtype__', 'mssql');
}

function db_close()
{
	 sqlsrv_close(fud_sql_lnk);
}

function db_version()
{	
	if (!defined('__FUD_SQL_VERSION__')) {
		$ver = sqlsrv_server_info(fud_sql_lnk);
		define('__FUD_SQL_VERSION__', $ver['SQLServerVersion']);
	}
	return __FUD_SQL_VERSION__;
}

function db_lock($tables)
{
	if (!empty($GLOBALS['__DB_INC_INTERNALS__']['db_locked'])) {
		fud_sql_error_handler('Recursive Lock', 'internal', 'internal', db_version());
	} else {
		// q('LOCK '. $tables .' in exclusive mode');
		$GLOBALS['__DB_INC_INTERNALS__']['db_locked'] = 1;
	}
	sqlsrv_begin_transaction(fud_sql_lnk);
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
	unset($GLOBALS['__DB_INC_INTERNALS__']['db_locked']);
	sqlsrv_commit(fud_sql_lnk);
}

function db_locked()
{
	return isset($GLOBALS['__DB_INC_INTERNALS__']['db_locked']);
}

function db_affected()
{
	// Takes a statement resource, and not a connection as with most other PHP drivers.
	return sqlsrv_rows_affected($GLOBALS['__DB_INC_INTERNALS__']['stmt']);
}

function uq($query)
{
	return q($query);
}

if (!defined('fud_query_stats')) {
	function q($query)
	{
		$r = sqlsrv_query(fud_sql_lnk, _mssql_syntax($query));
		if ($r === false) {
			$err = sqlsrv_errors();
			fud_sql_error_handler($query, $err[0]['message'], $err[0]['code'], db_version());
		}
		$GLOBALS['__DB_INC_INTERNALS__']['stmt'] = $r;

		return $r;
	}
} else {
	function q($query)
	{
		if (!isset($GLOBALS['__DB_INC_INTERNALS__']['query_count'])) {
			$GLOBALS['__DB_INC_INTERNALS__']['query_count'] = 1;
		} else {
			++$GLOBALS['__DB_INC_INTERNALS__']['query_count'];
		}
	
		if (!isset($GLOBALS['__DB_INC_INTERNALS__']['total_sql_time'])) {
			$GLOBALS['__DB_INC_INTERNALS__']['total_sql_time'] = 0;
		}

		$s = microtime(true);
		$r = sqlsrv_query(fud_sql_lnk, _mssql_syntax($query));
		$e = microtime(true);
		if ($r === false) {
			$err = sqlsrv_errors();
			fud_sql_error_handler($query, $err[0]['message'], $err[0]['code'], db_version());
		}
		$GLOBALS['__DB_INC_INTERNALS__']['stmt'] = $r;

		$GLOBALS['__DB_INC_INTERNALS__']['last_time'] = ($e - $s);
		$GLOBALS['__DB_INC_INTERNALS__']['total_sql_time'] += $GLOBALS['__DB_INC_INTERNALS__']['last_time'];

		echo '<pre>'. preg_replace('!\s+!', ' ', $query) .'</pre>';
		echo '<pre>query count: '. $GLOBALS['__DB_INC_INTERNALS__']['query_count'] .' time taken: '. $GLOBALS['__DB_INC_INTERNALS__']['last_time'] .'</pre>';
		echo '<pre>Affected rows: '. db_affected() .'</pre>';
		echo '<pre>total sql time: '. $GLOBALS['__DB_INC_INTERNALS__']['total_sql_time'] .'</pre>';

		return $r; 
	}
}

function db_rowobj($result)
{
	return sqlsrv_fetch_object($result);
}

function db_rowarr($result)
{
	return sqlsrv_fetch_array($result, SQLSRV_FETCH_NUMERIC);
}

function q_singleval($query)
{
	if (($res = sqlsrv_fetch_array(q($query), SQLSRV_FETCH_NUMERIC)) !== false) {
		return $res[0];
	}
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
	return sqlsrv_fetch_array(q($q), SQLSRV_FETCH_NUMERIC);
}

function db_sab($q)
{
	return sqlsrv_fetch_object(q($q));
}

function db_qid($q)
{
	q($q);
	return q_singleval('SELECT @@identity');
}

function db_arr_assoc($q)
{
	return sqlsrv_fetch_array(q($q), SQLSRV_FETCH_ASSOC);
}

function db_fetch_array($q)
{
        return sqlsrv_fetch_array($q, SQLSRV_FETCH_ASSOC);
}

function db_li($q, &$ef, $li=0)
{
	$r = @sqlsrv_query(fud_sql_lnk, $q);
	if ($r) {
		return ($li ? q_singleval('SELECT @@identity') : $r);
	}

	/* Ignore error 2601/2627: Cannot insert duplicate key row in object/Violation of PRIMARY KEY constraint. */
	if ($err = sqlsrv_errors()) {
		if ($err[0]['code'] == 2601 || $err[0]['code'] == 2627) {
			$ef = ltrim(strrchr($err[0]['message'], ' '));
			return null;
		} else {
			fud_sql_error_handler($q, $err[0]['message'], $err[0]['code'], db_version());
		}
	}
}

function ins_m($tbl, $flds, $types, $vals)
{
	// MS-SQL 2008 and higher supports multiple value pairs.
	sqlsrv_query(fud_sql_lnk, 'INSERT INTO '. $tbl. ' ('. $flds. ') VALUES ('. implode('),(', $vals). ')');
	
	/* Ignore error 2601: Cannot insert duplicate key row in object. */
	if ($err = sqlsrv_errors()) {
		if ($err[0]['code'] == 2601) {
			return null;
		} else {
			fud_sql_error_handler($q, $err[0]['message'], $err[0]['code'], db_version());
		}
	}
}

function db_all($q)
{
	$f = array();
	$c = uq($q);
	while ($r = sqlsrv_fetch_array($c, SQLSRV_FETCH_NUMERIC)) {
		$f[] = $r[0];
	}
	return $f;
}

function _esc($s)
{
	// There is no sqlsrv_real_escape_string().
	return '\''. stripslashes(str_replace("'", "''", $s)) .'\'';
}

function _mssql_syntax($q)
{
	// Workaround for error: 'LENGTH' is not a recognized built-in function name.
	$q = preg_replace('/LENGTH\(/i', 'DataLength(', $q);
	
	return $q;
}

?>
