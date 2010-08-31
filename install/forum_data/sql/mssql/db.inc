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
#define('fud_query_stats', 1);

if (!defined('fud_sql_lnk')) {
	$connect_func = $GLOBALS['FUD_OPT_1'] & 256 ? 'mssql_pconnect' : 'mssql_connect';

	$conn = $connect_func($GLOBALS['DBHOST'], $GLOBALS['DBHOST_USER'], $GLOBALS['DBHOST_PASSWORD']) or fud_sql_error_handler('Initiating '. $connect_func, mssql_get_last_message(fud_sql_lnk), null, 'Unknown');
	define('fud_sql_lnk', $conn);
	mssql_select_db($GLOBALS['DBHOST_DBNAME'], fud_sql_lnk) or fud_sql_error_handler('Opening database '. $GLOBALS['DBHOST_DBNAME'], mssql_get_last_message(), null, db_version());
	@ini_set('mssql.charset', 'UTF-8');
	@ini_set('mssql.textlimit', 2147483647);
	@ini_set('mssql.textsize', 2147483647);

	define('__dbtype__', 'mysql');
}

function db_version()
{
	if (!defined('__FUD_SQL_VERSION__')) {
		$ver = mssql_fetch_row(mssql_query('SELECT SERVERPROPERTY(\'productversion\')', fud_sql_lnk));
		define('__FUD_SQL_VERSION__', $ver[0]);
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

if (!defined('fud_query_stats')) {
	function q($query)
	{
		$r = mssql_query($query, fud_sql_lnk) or fud_sql_error_handler($query, mssql_get_last_message(), null, db_version());
		return $r;
	}
	function uq($query)
	{
		$r = mssql_query($query,fud_sql_lnk) or fud_sql_error_handler($query, mssql_get_last_message(), null, db_version());
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
	
		$s = gettimeofday();
		$result = mssql_query($query, fud_sql_lnk) or fud_sql_error_handler($query, mssql_get_last_message(), null, db_version());
		$e = gettimeofday(); 

		$GLOBALS['__DB_INC_INTERNALS__']['last_time'] = ($e['sec'] - $s['sec'] + (($e['usec'] - $s['usec'])/1000000));
		$GLOBALS['__DB_INC_INTERNALS__']['total_sql_time'] += $GLOBALS['__DB_INC_INTERNALS__']['last_time'];
		$GLOBALS['__DB_INC_INTERNALS__']['last_query'] = $query;

		echo '<pre>'.preg_replace('!\s+!', ' ', $query).'</pre>';
		echo '<pre>query count: '.$GLOBALS['__DB_INC_INTERNALS__']['query_count'].' time taken: '.$GLOBALS['__DB_INC_INTERNALS__']['last_time'].'</pre>';
		echo '<pre>Affected rows: '.db_affected().'</pre>';
		echo '<pre>total sql time: '.$GLOBALS['__DB_INC_INTERNALS__']['total_sql_time'].'</pre>';

		return $result; 
	}

	function uq($query)
	{
		return q($query);
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

function q_concat($arg)
{
	// Breaks the standard by using the '+' operator instead of '||'. 
	$tmp = func_get_args();
	return implode('+', $tmp);
}

function q_limit($query, $limit, $off)
{
	if ($off) {
		return 'SELECT TOP '. $limit .' FROM (SELECT TOP '. ($limit/$off)*$limit . substr($query, 6) .')';
	} else {
		return 'SELECT TOP '. $limit . substr($query, 6);
	}
/*
	SELECT TOP limitnumber
    FROM (
        SELECT TOP (limitnumber/ offset) * limitnumber) * 
        FROM tablename 
            AS T1 
            WHERE clauses 
            ORDER BY sortfield DESC) 
        AS T2 
        ORDER BY sortfield ASC;
*/
}

function q_bitand($fieldLeft, $fieldRight) {
	return $fieldLeft .'&'. $fieldRight;
}

function q_bitor($fieldLeft, $fieldRight) {
	return $fieldLeft .'|'. $fieldRight;
}

function q_bitnot($bitField) {
	return '~'. $bitField;
}

function get_fud_table_list($tbl='')
{
	if ($tbl) {
		return q_all('EXEC sp_tables @table_name = \''. $tbl .'\'');
	}

	return db_all('EXEC sp_tables @table_name = \''. $GLOBALS['DBHOST_TBL_PREFIX'] .'%\'');
}

function optimize_tables($tbl_list=null)
{
	if (!$tbl_list) {
		$tbl_list = get_fud_table_list();
	}

	q('OPTIMIZE TABLE '. implode(', ', $tbl_list));
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
	return mssql_insert_id(fud_sql_lnk);
}
function db_arr_assoc($q)
{
	return mssql_fetch_array(q($q), MSSQL_ASSOC);
}

function db_fetch_array($q)
{
        return mssql_fetch_array($q);
}

function db_li($q, &$ef, $li=0)
{
	$r = mssql_query($q, fud_sql_lnk);
	if ($r) {
		return ($li ? mssql_insert_id(fud_sql_lnk) : $r);
	}

	/* duplicate key */
	if (mssql_errno(fud_sql_lnk) == 1062) {
		$ef = ltrim(strrchr(mssql_get_last_message(), ' '));
		return null;
	} else {
		fud_sql_error_handler($q, mssql_get_last_message(), null, db_version());
	}
}

function ins_m($tbl, $flds, $types, $vals)
{
	// MS-SQL 2008 and higher supports multiple value pairs.
	q('INSERT INTO '. $tbl. ' ('. $flds. ') VALUES ('. implode('),(', $vals). ')');
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
	return '\''. mssql_real_escape_string($s, fud_sql_lnk) .'\'';
}

/* Implement missing driver functions. */
/***
function mssql_unbuffered_query($query,$fud_sql_lnk) {
	return mssql_query($query, $fud_sql_lnk);
}

function mssql_errno($fud_sql_lnk) {
	return -1;
}

function mssql_error($fud_sql_lnk) {
	return mssql_get_last_message();
}
***/
?>
