<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: db.inc,v 1.15 2003/05/03 18:16:18 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/
#define('fud_query_stats', 1);

if (!function_exists('fud_sql_error_handler')) {
	fud_use('err.inc'); 
}

if (!defined('FUD_SQL_LNK')) {
	$connect_func = ( $GLOBALS['DBHOST_PERSIST'] == 'Y' ) ? 'mysql_pconnect' : 'mysql_connect';
	
	$conn = $connect_func($GLOBALS['DBHOST'], $GLOBALS['DBHOST_USER'], $GLOBALS['DBHOST_PASSWORD']) or die (fud_sql_error_handler("Initiating $connect_func", mysql_error(FUD_SQL_LNK), mysql_errno(FUD_SQL_LNK), "Unknown"));
	define('FUD_SQL_LNK', $conn);
	mysql_select_db($GLOBALS['DBHOST_DBNAME'], FUD_SQL_LNK) or die (fud_sql_error_handler("Opening database ".$GLOBALS['DBHOST_DBNAME'], mysql_error(FUD_SQL_LNK), mysql_errno(FUD_SQL_LNK), q_singleval("SELECT VERSION()")));
		
	define('__dbtype__', 'mysql');
	define('__FUD_SQL_CONCAT__', 'CONCAT');
}

function yn($val) 
{
	return (strcasecmp($val, 'n') ? 'Y' : 'N');
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
		fud_sql_error_handler("Recursive Lock", "internal", "internal", q_singleval("SELECT VERSION()"));
	} else {
		q('LOCK TABLES '.$tables);
		$GLOBALS['__DB_INC_INTERNALS__']['db_locked'] = 1;
	}
}

function db_unlock()
{
	q('UNLOCK TABLES',FUD_SQL_LNK);
	
	if ( !isset($GLOBALS['__DB_INC_INTERNALS__']['db_locked']) ) {
		fud_sql_error_handler("DB_UNLOCK: no previous lock established", "internal", "internal", q_singleval("SELECT VERSION()"));
	}
	
	if ( --$GLOBALS['__DB_INC_INTERNALS__']['db_locked'] < 0 ) {
		fud_sql_error_handler("DB_UNLOCK: unlock overcalled", "internal", "internal", q_singleval("SELECT VERSION()"));
	}
}

function db_locked()
{
	return isset($GLOBALS['__DB_INC_INTERNALS__']['db_locked'])?$GLOBALS['__DB_INC_INTERNALS__']['db_locked']:NULL;
}

function db_affected()
{
	return mysql_affected_rows(FUD_SQL_LNK);	
}

if (!defined('fud_query_stats')) {
	if (!defined('debug')) {
		function q($query)
		{
			$r = mysql_query($query, FUD_SQL_LNK) or die (fud_sql_error_handler($query, mysql_error(FUD_SQL_LNK), mysql_errno(FUD_SQL_LNK), q_singleval("SELECT VERSION()")));
			return $r;
		}
		function uq($query)
		{
			$r = mysql_unbuffered_query($query,FUD_SQL_LNK) or die (fud_sql_error_handler($query, mysql_error(FUD_SQL_LNK), mysql_errno(FUD_SQL_LNK), q_singleval("SELECT VERSION()")));
			return $r;
		}
		function last_query() {}
	} else {
		function q($query) 
		{
			$GLOBALS['__DB_INC_INTERNALS__']['last_query'] = $query;
			$r = mysql_query($query,FUD_SQL_LNK) or die (fud_sql_error_handler($query, mysql_error(FUD_SQL_LNK), mysql_errno(FUD_SQL_LNK), q_singleval("SELECT VERSION()")));
			return $r;
		}
		function uq($query) 
		{
			$GLOBALS['__DB_INC_INTERNALS__']['last_query'] = $query;
			$r = mysql_unbuffered_query($query,FUD_SQL_LNK) or die (fud_sql_error_handler($query, mysql_error(FUD_SQL_LNK), mysql_errno(FUD_SQL_LNK), q_singleval("SELECT VERSION()")));
			return $r;
		}
		function last_query($filter='')
		{
			if ($filter) {
				return str_replace("\t", " ", str_replace("\n", " ", $GLOBALS['__DB_INC_INTERNALS__']['last_query']));
			} else {
				return $GLOBALS['__DB_INC_INTERNALS__']['last_query'];
			}
		}
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

		$result = mysql_query($query, FUD_SQL_LNK) or die (fud_sql_error_handler($query, mysql_error(FUD_SQL_LNK), mysql_errno(FUD_SQL_LNK), q_singleval("SELECT VERSION()")));
		
		$e = gettimeofday(); 

		echo '<pre>'.preg_replace('!\s+!', ' ', $query).'</pre>';

		$GLOBALS['__DB_INC_INTERNALS__']['last_time'] = ($e['sec'] - $s['sec'] + (($e['usec'] - $s['usec'])/1000000));
		$GLOBALS['__DB_INC_INTERNALS__']['total_sql_time'] += $GLOBALS['__DB_INC_INTERNALS__']['last_time'];
		$GLOBALS['__DB_INC_INTERNALS__']['last_query'] = $query;
		echo '<pre>query count: '.$GLOBALS['__DB_INC_INTERNALS__']['query_count'].' time taken: '.$GLOBALS['__DB_INC_INTERNALS__']['last_time'].'</pre>';
		echo '<pre>Affected rows: '.db_affected().'</pre>';
		echo '<pre>total sql time: '.$GLOBALS['__DB_INC_INTERNALS__']['total_sql_time'].'</pre>';
		
		return $result; 
	}
	function uq($query)
	{
		return q($query);
	}
	function query_count()
	{
		return $GLOBALS['__DB_INC_INTERNALS__']['query_count'];
	}
	function last_time()
	{
		return $GLOBALS['__DB_INC_INTERNALS__']['last_time'];
	}
	function total_time()
	{
		return $GLOBALS['__DB_INC_INTERNALS__']['total_sql_time'];
	}
	function last_query($filter='')
	{
		if ($filter) {
			return str_replace("\t", "", str_replace("\n", " ", $GLOBALS['__DB_INC_INTERNALS__']['last_query']));
		} else {
			return $GLOBALS['__DB_INC_INTERNALS__']['last_query'];
		}
	}
}

function qf(&$result)
{
	unset($result);
}
function db_count($result)
{
	return (int) @mysql_num_rows($result);
}
function db_lastid($dummy=FALSE, $dummy2=FALSE)
{
	return mysql_insert_id(FUD_SQL_LNK);
}
function db_seek($result, $pos)
{
	return mysql_data_seek($result, $pos);
}
function db_rowobj($result)
{
	return mysql_fetch_object($result);
}
function db_rowarr($result)
{
	return mysql_fetch_row($result);
}
function bq($query)
{
	$res = q($query);
	if (is_result($res)) {
		qf($res);
		return 1;
	}
	return 0;
}
function qobj($qry, &$obj)
{
	$r = q($qry);
	$robj = db_singleobj($r);
	if (!$robj) {
		return;
	}
	foreach($robj as $k => $v) {
		$obj->{$k} = $v;
	}
	
	return $robj;
}
function is_result(&$res)
{
	if (db_count($res)) {
		return $res;
	}
	qf($res);

	return;
}

function db_singleobj(&$res)
{
	$obj = db_rowobj($res);
	qf($res);
	return $obj;
}

function db_singlearr(&$res)
{
	$arr = db_rowarr($res);
	qf($res);
	return $arr;
}

function q_singleval($query)
{
	if (($res = @mysql_fetch_row(q($query))) === false) {
		return;
	} else {
		return $res[0];
	}
}

function get_field_list($tbl)
{
	return uq('show fields from ' . $tbl);
}

function qry_limit($limit, $off)
{
	return $off.','.$limit;
}

function get_fud_table_list()
{
	$r = uq("SHOW TABLES LIKE '".$GLOBALS['DBHOST_TBL_PREFIX']."%'");
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

	q('OPTIMIZE TABLE '. implode(', ', $tbl_list));
}

function db_saq($q)
{
	return @mysql_fetch_row(q($q));
}
function db_sab($q)
{
	return @mysql_fetch_object(q($q));
}
function db_qid($q)
{
	q($q);
	return mysql_insert_id(FUD_SQL_LNK);
}
function db_arr_assoc($q)
{
	return mysql_fetch_array(q($q), MYSQL_ASSOC);
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