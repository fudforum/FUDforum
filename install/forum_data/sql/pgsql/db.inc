<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: db.inc,v 1.11 2002/09/09 23:47:47 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

if( !function_exists("fud_sql_error_handler") ) fud_use('err.inc'); 

if ( !defined('_db_connection_ok_') ) {
	$connect_func = ( $GLOBALS['DBHOST_PERSIST'] == 'Y' ) ? 'pg_pconnect' : 'pg_connect';	
	
	$connect_str = '';
	if ( $GLOBALS['DBHOST'] ) 	$connect_str .= 'host='.$GLOBALS['DBHOST'].' ';
	if ( $GLOBALS['DBHOST_PORT'] )	$connect_str .= 'port='.$GLOBALS['DBHOST_PORT'].' ';
	if ( $GLOBALS['DBHOST_USER'] )	$connect_str .= 'user='.$GLOBALS['DBHOST_USER'].' ';
	if ( $GLOBALS['DBHOST_PASSWORD'] ) $connect_str .= 'password='.$GLOBALS['DBHOST_PASSWORD'].' ';
	if ( $GLOBALS['DBHOST_TTY'] )	$connect_str .= 'tty='.$GLOBALS['DBHOST_TTY'].' ';
	if ( $GLOBALS['DBHOST_DBNAME'] ) $connect_str .= 'dbname='.$GLOBALS['DBHOST_DBNAME'].' ';
	$connect_str = substr($connect_str, 0 ,-1);
	$GLOBALS['__DB_INC__']['SQL_LINK'] = $connect_func($connect_str)
		or die (fud_sql_error_handler("Initiating $connect_func", pg_last_error($GLOBALS['__DB_INC__']['SQL_LINK']), '', "Unknown"));
	define('_db_connection_ok_', 1); 
	define('__dbtype__', 'pgsql');
}

function yn($val) 
{
	return ( strlen($val) && strtolower($val) != 'n' ) ? 'Y' : 'N';
} 

function intnull($val)
{
	return ( strlen($val) ) ? $val : 'NULL';
}

function intzero($val)
{
	return ( !empty($val) ) ? $val : '0';
}

function ifnull($val, $alt)
{
	return ( strlen($val) ) ? "'".$val."'" : $alt;
}

function strnull($val)
{
	return ( strlen($val) ) ? "'".$val."'" : 'NULL';
}

function db_lock($tables)
{
	if ( empty($tables) ) 
		fud_sql_error_handler("no tables to lock", "internal", "internal", q_singleval("SELECT VERSION()"));

	if ( !empty($GLOBALS['__DB_INC_INTERNALS__']['db_locked']) ) 
		fud_sql_error_handler("Recursive Lock", "internal", "internal", q_singleval("SELECT VERSION()"));

	$tables = str_replace("\t", '', $tables);
	
	$tbl_arr = explode(',', $tables);
	$tbl_n = count($tbl_arr);
	
	q("BEGIN WORK");
	$shr_str='';
	$ex_str='';
	for ( $i=0; $i<$tbl_n; $i++ ) {
		$tbl_arr[$i] = trim($tbl_arr[$i]);
		$lmode = substr($tbl_arr[$i], -1);
		$tbl_arr[$i] = substr($tbl_arr[$i], 0, strlen($tbl_arr[$i])-1);
		if ( $lmode == '+' )
			$ex_str .= $tbl_arr[$i].',';
		else
			$shr_str .= $tbl_arr[$i].',';
	}
	
	if ( $ex_str ) {
		$ex_str = substr($ex_str, 0, -1);
		q("LOCK TABLE $ex_str IN ACCESS EXCLUSIVE MODE");
	}
	
	if ( $shr_str ) {
		$shr_str = substr($shr_str, 0, -1);
		q("LOCK TABLES $shr_str IN SHARE MODE");
	}

	$GLOBALS['__DB_INC_INTERNALS__']['db_locked'] = $tables;
}

function db_unlock()
{
	q('COMMIT WORK',$GLOBALS['__DB_INC__']['SQL_LINK']);
	
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

function db_affected($r)
{
	return pg_affected_rows($r['res']);
}

if( !defined("fud_query_stats") ) {
	function q($query)
	{
		$result = pg_query($GLOBALS['__DB_INC__']['SQL_LINK'], $query) 
			or die (fud_sql_error_handler($query, pg_result_error($result), '', q_singleval("SELECT VERSION()")));

		$GLOBALS['__DB_INC_INTERNALS__']['last_query'] = $query;
	
		if( defined('debug') ) 
			echo "--<br>\n<b>(--</b>&nbsp;&nbsp; ".htmlspecialchars($query)." &nbsp;&nbsp;<b>--)</b><br><br>\n\n";
	
		$rs['res'] = $result;
		$rs['count'] = @pg_num_rows($result);
		$rs['pos'] = 0;
		return $rs; 
	}
}
else {
	function q($query)
	{
		if ( !isset($GLOBALS['__DB_INC_INTERNALS__']['query_count']) )
			$GLOBALS['__DB_INC_INTERNALS__']['query_count'] = 1;
		else 
			++$GLOBALS['__DB_INC_INTERNALS__']['query_count'];
	
		if ( !isset($GLOBALS['__DB_INC_INTERNALS__']['total_sql_time']) ) $GLOBALS['__DB_INC_INTERNALS__']['total_sql_time'] = 0;
	
		$ts = db_getmicrotime();
		$result = pg_query($GLOBALS['__DB_INC__']['SQL_LINK'], $query)
			or die (fud_sql_error_handler($query, pg_result_error($result), '', q_singleval("SELECT VERSION()")));
		$te = db_getmicrotime(); 
	
		$GLOBALS['__DB_INC_INTERNALS__']['last_time'] = $te-$ts;
		$GLOBALS['__DB_INC_INTERNALS__']['total_sql_time'] += $GLOBALS['__DB_INC_INTERNALS__']['last_time'];
		$GLOBALS['__DB_INC_INTERNALS__']['last_query'] = $query;
	
		if( defined('debug') ) 
			echo "--<br>\n<b>(--</b>&nbsp;&nbsp; ".htmlspecialchars($query)." &nbsp;&nbsp;<b>--) <b>(</b>".$GLOBALS['__DB_INC_INTERNALS__']['last_time']."<b>)</b></b><br><br>\n\n";
	
		$rs['res'] = $result;
		$rs['count'] = @pg_num_rows($result);
		$rs['pos'] = 0;
		return $rs; 
	}
}

function qf(&$result)
{
	unset($result);
}

function query_count()
{
	return $GLOBALS['__DB_INC_INTERNALS__']['query_count'];
}

function last_query($filter='')
{
	if ( $filter ) 
		return str_replace("\t", "", str_replace("\n", " ", $GLOBALS['__DB_INC_INTERNALS__']['last_query']));
	else
		return $GLOBALS['__DB_INC_INTERNALS__']['last_query'];
}

function last_time()
{
	return $GLOBALS['__DB_INC_INTERNALS__']['last_time'];
}

function total_time()
{
	return $GLOBALS['__DB_INC_INTERNALS__']['total_sql_time'];
}

function db_count(&$result)
{
	if ( $n=@$result['count'] ) 
		return $n;
	else
		return 0;
}

function db_lastid($tblname=NULL, $rs)
{	
	return q_singleval("SELECT id FROM $tblname WHERE oid=".pg_last_oid($rs['res']));
}

function db_seek(&$result,$pos)
{
	return $result['pos'] = $pos;
}
function db_rowobj(&$result)
{
	if ( $result['pos'] < $result['count'] )
		return pg_fetch_object($result['res'], $result['pos']++);

	return;
}

function db_rowarr(&$result)
{
	if ( $result['pos'] < $result['count'] )
		return pg_fetch_array($result['res'], $result['pos']++);

	return;
}

function bq($query)
{
	$res = q($query);
	if ( is_result($res) ) { qf($res); return 1; }
	return 0;
}

function qobj($qry, &$obj)
{
	$r = q($qry);
	$robj = db_singleobj($r);
	if ( !$robj ) return;

	foreach($robj as $k => $v) $obj->{$k} = $v;
	
	return $robj;
}

function is_result(&$res)
{
	if ( db_count($res) ) 
		return $res;
	
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
	$r = q($query);
	if( !is_result($r) ) return;
	
	list($val) = db_singlearr($r);
	
	return $val;
}

function get_field_list($tbl)
{
	$r = q("SELECT a.attname AS Field FROM pg_class c, pg_attribute a WHERE c.relname = '$tbl' AND a.attnum > 0 AND a.attrelid = c.oid ORDER BY a.attnum");
	return $r;
}

function qry_limit($limit, $off)
{
	return $limit.','.$off;
}

function get_fud_table_list()
{
	$ret = array();

	$r = q("SELECT relname FROM pg_class WHERE relkind='r' AND relname LIKE '".$GLOBALS['DBHOST_TBL_PREFIX']."%'");
	while( list($name) = db_rowarr($r) ) $ret[] = $name;
	qf($r);
	
	return $ret;	
}

function optimize_tables($tbl_list=NULL)
{
	if( !$tbl_list ) $tbl_list = get_fud_table_list();

	foreach($tbl_list as $v) q("VACUUM ANALYZE ".$v);
}
?>