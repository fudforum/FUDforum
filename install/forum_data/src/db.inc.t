<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: db.inc.t,v 1.1.1.1 2002/06/17 23:00:09 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

if ( !function_exists('error_handler') ) fud_use('err.inc');

if ( !defined('_db_connection_ok_') ) {
	$connect_func = ( $GLOBALS['MYSQL_PERSIST'] == 'Y' ) ? 'mysql_pconnect' : 'mysql_connect';
		
	if ( !($GLOBALS['__DB_INC__']['SQL_LINK']=$connect_func($GLOBALS['MYSQL_SERVER'], $GLOBALS['MYSQL_LOGIN'], $GLOBALS['MYSQL_PASSWORD'])) ) {
		error_handler("db.inc", "unable to establish mysql connection on ".$GLOBALS['MYSQL_SERVER'], 0);
	}
		
	if ( !@mysql_select_db($GLOBALS['MYSQL_DB'],$GLOBALS['__DB_INC__']['SQL_LINK']) ) {
		error_handler("db.inc", "unable to connect to database", 0);
	}
		
	define('_db_connection_ok_', 1); 
}

function YN($val) 
{
	return ( strlen($val) && strtolower($val) != 'n' ) ? 'Y' : 'N';
} 

function INTNULL($val)
{
	return ( strlen($val) ) ? $val : 'NULL';
}

function INTZERO($val)
{
	return ( !empty($val) ) ? $val : '0';
}

function IFNULL($val, $alt)
{
	return ( strlen($val) ) ? "'".$val."'" : $alt;
}

function STRNULL($val)
{
	return ( strlen($val) ) ? "'".$val."'" : 'NULL';
}

function DB_LOCK($tables)
{
	if ( !empty($GLOBALS['__DB_INC_INTERNALS__']['db_locked']) ) {
		exit("recursive lock");
	}

	$tables = str_replace("\t", '', $tables);
	
	$tbl_arr = explode(',', $tables);
	$tbl_n = count($tbl_arr);
	
	$sql_str='';
	for ( $i=0; $i<$tbl_n; $i++ ) {
		$tbl_arr[$i] = trim($tbl_arr[$i]);
		if ( substr($tbl_arr[$i], -1) == '+' ) {
			$mode = ' WRITE';
			$tbl_arr[$i] = substr($tbl_arr[$i], 0, strlen($tbl_arr[$i])-1);
		}
		else {
			$mode = ' READ';
		}
		$sql_str .= ' '.$tbl_arr[$i].$mode.',';
	}
	
	$sql_str = substr($sql_str, 0, strlen($sql_str)-1);
	$query = "LOCK TABLES".$sql_str;
	
	if ( !Q($query) ) {
		exit("DB_LOCK() error (".mysql_error($GLOBALS['__DB_INC__']['SQL_LINK']).")\n"); 
	}
	
	$GLOBALS['__DB_INC_INTERNALS__']['db_locked'] = 1;	
}

function DB_UNLOCK()
{
	if ( !Q('UNLOCK TABLES',$GLOBALS['__DB_INC__']['SQL_LINK']) ) {
		exit("DB_UNLOCK FAILED\n");
	}
	
	if ( !isset($GLOBALS['__DB_INC_INTERNALS__']['db_locked']) ) {
		exit("DB_UNLOCK: no previous lock established\n");
	}
	
	if ( --$GLOBALS['__DB_INC_INTERNALS__']['db_locked'] < 0 ) {
		exit("DB_UNLOCK: unlock overcalled\n");
	}
}

function db_locked()
{
	return isset($GLOBALS['__DB_INC_INTERNALS__']['db_locked'])?$GLOBALS['__DB_INC_INTERNALS__']['db_locked']:NULL;
}

function DB_AFFECTED()
{
	return mysql_affected_rows($GLOBALS['__DB_INC__']['SQL_LINK']);	
}

function Q($query)
{
	if ( !isset($GLOBALS['__DB_INC_INTERNALS__']['query_count']) )
		$GLOBALS['__DB_INC_INTERNALS__']['query_count'] = 1;
	else 
		++$GLOBALS['__DB_INC_INTERNALS__']['query_count'];
	
	if ( !isset($GLOBALS['__DB_INC_INTERNALS__']['total_sql_time']) ) $GLOBALS['__DB_INC_INTERNALS__']['total_sql_time'] = 0;
	
	$ts = db_getmicrotime();
	if ( !($result=mysql_query($query,$GLOBALS['__DB_INC__']['SQL_LINK'])) ) {
		$error_reason = mysql_error($GLOBALS['__DB_INC__']['SQL_LINK']);
		error_handler("db.inc", "query failed: %( $query )% because %( $error_reason )%", 1);
		echo "<b>Query Failed:</b> ".htmlspecialchars($query)."<br>\n<b>Reason:</b> ".$error_reason."<br>\n<b>From:</b> ".$GLOBALS['SCRIPT_FILENAME']."<br>\n<b>Server Version:</b> ".Q_SINGLEVAL("SELECT VERSION()")."<br>\n";
		if( db_locked() ) DB_UNLOCK();
		exit;
	}
	$te = db_getmicrotime(); 
	
	$GLOBALS['__DB_INC_INTERNALS__']['last_time'] = $te-$ts;
	$GLOBALS['__DB_INC_INTERNALS__']['total_sql_time'] += $GLOBALS['__DB_INC_INTERNALS__']['last_time'];
	$GLOBALS['__DB_INC_INTERNALS__']['last_query'] = $query;
	
	return $result; 
}

function QF($result)
{
	mysql_free_result($result);
}

function QUERY_COUNT()
{
	return $GLOBALS['__DB_INC_INTERNALS__']['query_count'];
}

function LAST_QUERY($filter='')
{
	if ( $filter ) 
		return str_replace("\t", "", str_replace("\n", " ", $GLOBALS['__DB_INC_INTERNALS__']['last_query']));
	else
		return $GLOBALS['__DB_INC_INTERNALS__']['last_query'];
}

function LAST_TIME()
{
	return $GLOBALS['__DB_INC_INTERNALS__']['last_time'];
}

function TOTAL_TIME()
{
	return $GLOBALS['__DB_INC_INTERNALS__']['total_sql_time'];
}

function DB_COUNT($result)
{
	if ( $n=@mysql_num_rows($result) ) 
		return $n;
	else
		return 0;
}

function DB_LASTID()
{
	return mysql_insert_id($GLOBALS['__DB_INC__']['SQL_LINK']);
}

function DB_SEEK($result,$pos)
{
	return mysql_data_seek($result,$pos);
}
function DB_ROWOBJ($result)
{
	return mysql_fetch_object($result);
}

function DB_ROWARR($result)
{
	return mysql_fetch_row($result);
}

function BQ($query)
{
	$res = Q($query);
	if ( IS_RESULT($res) ) { QF($res); return 1; }
	return 0;
}

function QOBJ($qry, &$obj)
{
	$r = Q($qry);
	$robj = DB_SINGLEOBJ($r);
	if ( !$robj ) return;

	reset($robj);
	while ( list($k, $v) = each($robj) ) {
		$obj->{$k} = $v;
	}
	
	return $robj;
}

function IS_RESULT($res)
{
	if ( DB_COUNT($res) ) 
		return $res;
	
	QF($res);

	return;
}

function DB_SINGLEOBJ($res)
{
	$obj = DB_ROWOBJ($res);
	QF($res);
	return $obj;
}

function DB_SINGLEARR($res)
{
	$arr = DB_ROWARR($res);
	QF($res);
	return $arr;
}

function Q_SINGLEVAL($query)
{
	$r = Q($query);
	if( !IS_RESULT($r) ) return;
	
	list($val) = DB_SINGLEARR($r);
	
	return $val;
}
?>