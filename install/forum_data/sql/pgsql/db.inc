<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: db.inc,v 1.19 2003/05/08 12:19:01 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

if (!function_exists('fud_sql_error_handler')) {
	fud_use('err.inc'); 
}

if (!defined('FUD_SQL_LNK')) {
	$connect_func = ( $GLOBALS['DBHOST_PERSIST'] == 'Y' ) ? 'pg_pconnect' : 'pg_connect';	
	
	$connect_str = '';
	if ($GLOBALS['DBHOST']) {
		$connect_str .= 'host='.$GLOBALS['DBHOST'];
	}
	if ($GLOBALS['DBHOST_USER']) {
		$connect_str .= ' user='.$GLOBALS['DBHOST_USER'];
	}
	if ($GLOBALS['DBHOST_PASSWORD']) {
		$connect_str .= ' password='.$GLOBALS['DBHOST_PASSWORD'];
	}
	if ($GLOBALS['DBHOST_DBNAME']) {
		$connect_str .= ' dbname='.$GLOBALS['DBHOST_DBNAME'];
	}
	$conn = $connect_func(ltrim($connect_str)) or die (fud_sql_error_handler('Initiating '.$connect_func, pg_last_error($conn), '', 'Unknown'));
	define('FUD_SQL_LNK', $conn);
	define('__dbtype__', 'pgsql');
	define('__FUD_SQL_CONCAT__', 'textcat');
}

function get_version()
{
	if (!defined('__FUD_SQL_VERSION__')) {
		define('__FUD_SQL_VERSION__', @current(pg_fetch_row(pg_query('SELECT VERSION()', FUD_SQL_LNK))));
	}
	return __FUD_SQL_VERSION__;
}

function yn($val) 
{
	return (strcasecmp($val, 'y') ? 'N' : 'Y');
} 

function intnull($val)
{
	return (!is_null($val) ? $val : 'NULL');
}

function intzero($val)
{
	return (!empty($val) ? $val : '0');
}

function ifnull($val, $alt)
{
	return (!is_null($val) ? "'".$val."'" : $alt);
}

function strnull($val)
{
	return (!is_null($val) ? "'".$val."'" : 'NULL');
}

function db_lock($tables)
{
	if (!empty($GLOBALS['__DB_INC_INTERNALS__']['db_locked'])) {
		fud_sql_error_handler("Recursive Lock", "internal", "internal", get_version());
	} else {
		q('BEGIN WORK');
		q('LOCK TABLE '.str_replace(' WRITE', '', $tables).' IN ACCESS EXCLUSIVE MODE');
		$GLOBALS['__DB_INC_INTERNALS__']['db_locked'] = 1;
	}
}

function db_unlock()
{
	if (empty($GLOBALS['__DB_INC_INTERNALS__']['db_locked'])) {
		unset($GLOBALS['__DB_INC_INTERNALS__']['db_locked']);
		fud_sql_error_handler("DB_UNLOCK: no previous lock established", "internal", "internal", get_version());
	}
	
	if (--$GLOBALS['__DB_INC_INTERNALS__']['db_locked'] < 0) {
		unset($GLOBALS['__DB_INC_INTERNALS__']['db_locked']);
		fud_sql_error_handler("DB_UNLOCK: unlock overcalled", "internal", "internal", get_version());
	}
	unset($GLOBALS['__DB_INC_INTERNALS__']['db_locked']);
	q('COMMIT WORK', FUD_SQL_LNK);
}

function db_locked()
{
	return isset($GLOBALS['__DB_INC_INTERNALS__']['db_locked']);
}

function db_affected()
{
	return pg_affected_rows($GLOBALS['__FLPG__']);
}

function uq($q)
{
	return q($q);
}

if (!defined('fud_query_stats')) {
	if (!defined('debug')) {
		function q($q)
		{
			$GLOBALS['__FLPG__'] = pg_query(FUD_SQL_LNK, $q) or die (fud_sql_error_handler($q, pg_last_error(FUD_SQL_LNK), '', get_version()));
			return $GLOBALS['__FLPG__'];
		}
	} else {
		function q($q)
		{
			$GLOBALS['__FLPG__'] = pg_query(FUD_SQL_LNK, $q) or die (fud_sql_error_handler($q, pg_last_error(FUD_SQL_LNK), '', get_version()));
			$GLOBALS['__DB_INC_INTERNALS__']['last_query'] = $q;
			return $GLOBALS['__FLPG__'];
		}
	}
} else {
	function q($q)
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
		$GLOBALS['__FLPG__'] = pg_query(FUD_SQL_LNK, $q) or die (fud_sql_error_handler($q, pg_last_error(FUD_SQL_LNK), '', get_version()));
		$e = gettimeofday(); 
	
		$GLOBALS['__DB_INC_INTERNALS__']['last_time'] = ($e['sec'] - $s['sec'] + (($e['usec'] - $s['usec'])/1000000));;
		$GLOBALS['__DB_INC_INTERNALS__']['total_sql_time'] += $GLOBALS['__DB_INC_INTERNALS__']['last_time'];
		$GLOBALS['__DB_INC_INTERNALS__']['last_query'] = $q;

		echo '<pre>'.preg_replace('!\s+!', ' ', $q).'</pre>';
		echo '<pre>query count: '.$GLOBALS['__DB_INC_INTERNALS__']['query_count'].' time taken: '.$GLOBALS['__DB_INC_INTERNALS__']['last_time'].'</pre>';
		echo '<pre>Affected rows: '.db_affected().'</pre>';
		echo '<pre>total sql time: '.$GLOBALS['__DB_INC_INTERNALS__']['total_sql_time'].'</pre>';

		return $GLOBALS['__FLPG__'];
	}

	function query_count()
	{
		return $GLOBALS['__DB_INC_INTERNALS__']['query_count'];
	}

	function last_query($filter='')
	{
		if ($filter) {
			return str_replace("\t", "", str_replace("\n", " ", $GLOBALS['__DB_INC_INTERNALS__']['last_query']));
		} else {
			return $GLOBALS['__DB_INC_INTERNALS__']['last_query'];
		}
	}

	function last_time()
	{
		return $GLOBALS['__DB_INC_INTERNALS__']['last_time'];
	}

	function total_time()
	{
		return $GLOBALS['__DB_INC_INTERNALS__']['total_sql_time'];
	}
}

function qf(&$result)
{
	unset($result);
}

function db_count($r)
{
	$r = @pg_num_rows($r);
	if ($r < 0) {
		return 0;
	}
	return $r;
}

function db_seek($result, $pos)
{
	return pg_result_seek($result, $pos);
}

function db_rowobj($result)
{
	return pg_fetch_object($result);
}

function db_rowarr(&$result)
{
	return pg_fetch_row($result);
}

function q_singleval($query)
{
	return @current(@pg_fetch_row(q($query)));
}

function get_field_list($tbl)
{
	return q("SELECT a.attname AS Field FROM pg_class c, pg_attribute a WHERE c.relname = '$tbl' AND a.attnum > 0 AND a.attrelid = c.oid ORDER BY a.attnum");
}

function qry_limit($limit, $off)
{
	return $limit.' OFFSET '.$off;
}

function get_fud_table_list()
{
	$r = q("SELECT relname FROM pg_class WHERE relkind='r' AND relname LIKE '".$GLOBALS['DBHOST_TBL_PREFIX']."%'");
	while (list($ret[]) = db_rowarr($r));
	qf($r);
	array_pop($ret);

	return $ret;
}

function optimize_tables($tbl_list=NULL)
{
	if (!$tbl_list) {
		$tbl_list = get_fud_table_list();
	}
	q('VACUUM ANALYZE ' . implode(', ', $tbl_list));
}

function db_saq($q)
{
	return @pg_fetch_row(q($q));
}

function db_sab($q)
{
	return @pg_fetch_object(q($q));
}

function db_qid($q)
{
	$r = q($q);
	preg_match('!('.$GLOBALS['DBHOST_TBL_PREFIX'].'[A-Za-z0-9_]+)!', $q, $m);
	return q_singleval('SELECT id FROM '.$m[1].' WHERE oid='.pg_last_oid($r));
}

function db_arr_assoc($q)
{
	return @pg_fetch_array(q($q), PGSQL_ASSOC);
}

function ssn($str)
{
	if (is_null($str) || $str === '') {
		return 'NULL';
	} else {
		return "'" . addslashes($str) . "'";
	}
}

function in($str)
{
	return (!(int)$str) ? 'NULL' : (int)$str;
}

function iz($str)
{
	return (int)$str;
}
?>