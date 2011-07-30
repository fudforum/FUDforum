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

/* ---------------------------------------------------------------------------------------------------
 * THIS DRIVER IS EXPERIMENTAL AND DOESN'T WORK PROPERLY!!!
 *
 * This driver doesn't work because of a PHP bug that will create tables with "NULL"
 * columns as "NOT NULL". The problem is described at -
 * http://dba.fyicenter.com/faq/sql_server_2/PHP_MSSQL_Making_Columns_to_Take_NULL.html
 * ---------------------------------------------------------------------------------------------------
 */

if (!defined('fud_sql_lnk')) {
	$connect_func = $GLOBALS['FUD_OPT_1'] & 256 ? 'mssql_pconnect' : 'mssql_connect';

	$conn = $connect_func($GLOBALS['DBHOST'], $GLOBALS['DBHOST_USER'], $GLOBALS['DBHOST_PASSWORD']) or fud_sql_error_handler('Initiating '. $connect_func, mssql_get_last_message(), null, 'Unknown');
	define('fud_sql_lnk', $conn);

	@mssql_select_db($GLOBALS['DBHOST_DBNAME'], fud_sql_lnk) or fud_sql_error_handler('Opening database '. $GLOBALS['DBHOST_DBNAME'], mssql_get_last_message(), null, 'Unknown');
	@ini_set('mssql.charset', 'UTF-8');
	@ini_set('mssql.textlimit', 2147483647);
	@ini_set('mssql.textsize', 2147483647);

	define('__dbtype__', 'mssql');
}

function db_close()
{
	 mssql_close(fud_sql_lnk);
}

function db_version()
{
	if (!defined('__FUD_SQL_VERSION__')) {
		$ver = q_singleval('SELECT SERVERPROPERTY(\'productversion\')');
		define('__FUD_SQL_VERSION__', $ver);
	}
	return __FUD_SQL_VERSION__;
}

function db_lock($tables)
{
	if (!empty($GLOBALS['__DB_INC_INTERNALS__']['db_locked'])) {
		fud_sql_error_handler('Recursive Lock', 'internal', 'internal', db_version());
	} else {
		// q('LOCK '.$tables.' in exclusive mode');
		$GLOBALS['__DB_INC_INTERNALS__']['db_locked'] = 1;
	}
	q('BEGIN TRANSACTION');
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
	q('COMMIT');
}

function db_locked()
{
	return isset($GLOBALS['__DB_INC_INTERNALS__']['db_locked']);
}

function db_affected()
{
	return mssql_affected_rows(fud_sql_lnk);	
}

function uq($query)
{
	return q($query);
}

if (!defined('fud_query_stats')) {
	function q($query)
	{
		$r = mssql_query(_mssql_syntax($query), fud_sql_lnk) or fud_sql_error_handler($query, mssql_get_last_message(), null, db_version());
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
		$r = mssql_query(_mssql_syntax($query), fud_sql_lnk) or fud_sql_error_handler($query, mssql_get_last_message(), null, db_version());
		$e = microtime(true);

		$GLOBALS['__DB_INC_INTERNALS__']['last_time'] = ($e - $s);
		$GLOBALS['__DB_INC_INTERNALS__']['total_sql_time'] += $GLOBALS['__DB_INC_INTERNALS__']['last_time'];

		echo '<hr><b>Query #'. $GLOBALS['__DB_INC_INTERNALS__']['query_count'] .'</b><small>';
		echo ': time taken:     <i>'. number_format($GLOBALS['__DB_INC_INTERNALS__']['last_time'], 4) .'</i>';
		echo ', affected rows:  <i>'. db_affected() .'</i>';
		echo ', total sql time: <i>'.  number_format($GLOBALS['__DB_INC_INTERNALS__']['total_sql_time'], 4) .'</i>';
		echo '<pre>'. preg_replace('!\s+!', ' ', htmlspecialchars($query)) .'</pre></small>';

		return $r; 
	}
}

function db_rowobj($result)
{
	return mssql_fetch_object($result);
}

function db_rowarr($result)
{
	return mssql_fetch_row($result);
}

function q_singleval($query)
{
	if (($res = mssql_fetch_row(q($query))) !== false) {
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
	return mssql_fetch_row(q($q));
}

function db_sab($q)
{
	return mssql_fetch_object(q($q));
}

function db_qid($q)
{
	q($q);
	return q_singleval('SELECT @@identity');
}

function db_arr_assoc($q)
{
	return mssql_fetch_array(q($q), MSSQL_ASSOC);
}

function db_fetch_array($q)
{
        return mssql_fetch_array($q, MSSQL_ASSOC);
}

function db_li($q, &$ef, $li=0)
{
	$r = @mssql_query($q, fud_sql_lnk);
	if ($r) {
		return ($li ? q_singleval('SELECT @@identity') : $r);
	}

	/* Error 2601 is Cannot insert duplicate key row in object. */
	if ($err = mssql_get_last_message()) {
		if (q_singleval('SELECT @@ERROR') == 2601) {
			$ef = ltrim(strrchr(mssql_get_last_message(), ' '));
			return null;
		} else {
			fud_sql_error_handler($q, $err, null, db_version());
		}
	}
}

function ins_m($tbl, $flds, $types, $vals)
{
	// MS-SQL 2008 and higher supports multiple value pairs.
	@q('SET XACT_ABORT OFF; INSERT INTO '. $tbl. ' ('. $flds. ') VALUES ('. implode('),(', $vals). ')');
}

function db_all($q)
{
	$f = array();
	$c = uq($q);
	while ($r = mssql_fetch_row($c)) {
		$f[] = $r[0];
	}
	return $f;
}

function _esc($s)
{
	// There is no mssql_real_escape_string().
	return '\''. stripslashes(str_replace("'", "''", $s)) .'\'';
}

function _mssql_syntax($q)
{
	// Difficult to belief, but there is still a database that doesn't support LENGTH()!
	// Workaround for error: 'LENGTH' is not a recognized built-in function name.
	$q = preg_replace('/LENGTH\(/i', 'DataLength(', $q);
	
	return $q;
}

?>
