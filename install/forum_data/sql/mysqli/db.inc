<?php
/**
* copyright            : (C) 2001-2009 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: db.inc,v 1.2 2009/08/09 11:33:37 frank Exp $
*
* This program is free software; you can redistribute it and/or modify it 
* under the terms of the GNU General Public License as published by the 
* Free Software Foundation; version 2 of the License. 
**/
#define('fud_query_stats', 1);

class db { public static $db, $ar, $res, $buf; }

if (empty(db::$db)) {
	// TODO: pconnect support

	db::$db = new mysqli($GLOBALS['DBHOST'], $GLOBALS['DBHOST_USER'], $GLOBALS['DBHOST_PASSWORD'], $GLOBALS['DBHOST_DBNAME']);
	if (mysqli_connect_errno()) {
		fud_sql_error_handler("Failed to establish database connection, MySQLi says: ".mysqli_connect_error(), '', '', '');
	}
	db::$db->set_charset('utf8');

	define('__dbtype__', 'mysql');
	define('__FUD_SQL_CONCAT__', 'CONCAT');
}

function get_version()
{
	if (!defined('__FUD_SQL_VERSION__')) {
		$r = db::$db->query('SELECT VERSION()');
		$ver = $r->fetch_row();
		define('__FUD_SQL_VERSION__', $ver[0]);
	}
	return __FUD_SQL_VERSION__;
}

function db_lock($tables)
{
	if (!empty($GLOBALS['__DB_INC_INTERNALS__']['db_locked'])) {
		fud_sql_error_handler("Recursive Lock", "internal", "internal", get_version());
	} else {
		q('LOCK TABLES '.$tables);
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
	q('UNLOCK TABLES');
}

function db_locked()
{
	return isset($GLOBALS['__DB_INC_INTERNALS__']['db_locked']);
}

function db_affected()
{
	return db::$db-> affected_rows;
}

if (!defined('fud_query_stats')) {
	function q($query)
	{
		$r = db::$db->query($query);
		if (db::$db->error) {
			fud_sql_error_handler($query, db::$db->error, db::$db->errno, get_version());
		}
		return $r;
	}
	function uq($query)
	{
		return q($query);
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
		$result = db::$db->query($query);
		if (db::$db->error) {
			fud_sql_error_handler($query, db::$db->error, db::$db->errno, get_version());
		}
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

function db_count($result)
{
	return (int) $result->num_rows;
}

function db_rowobj($result)
{
	return $result->fetch_object();
}

function db_rowarr($result)
{
	return $result->fetch_row();
}

function q_singleval($query)
{
	$r = q($query);
	if (($res = $r->fetch_row()) !== false) {
		return $res[0];
	}
}

function qry_limit($limit, $off)
{
	return $off.','.$limit;
}

function get_fud_table_list($tbl='')
{
	if ($tbl) {
		return q_singleval("SHOW TABLES LIKE '".$tbl."'");
	}

	$ret = array();
	$c = uq("SHOW TABLES LIKE '".$GLOBALS['DBHOST_TBL_PREFIX']."%'");
	while ($r = db_rowarr($c)) {
		$ret[] = $r[0];
	}

	return $ret;	
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
	$r = q($q);
	return $r->fetch_row() ;
}

function db_sab($q)
{
	$r = q($q);
	return $r->fetch_object();
}

function db_qid($q)
{
	q($q);
	return db::$db->insert_id;
}
function db_arr_assoc($q)
{
	$r = q($q);
	return $r->fetch_array(MYSQLI_ASSOC);
}

function db_fetch_array($r)
{
        return $r->fetch_array();
}

function db_li($q, &$ef, $li=0)
{
	$r = db::$db->query($q);
	if ($r) {
		return ($li ? db::$db->insert_id : $r);
	}

	/* duplicate key */
	if (db::$db->errno == 1062) {
		$ef = ltrim(strrchr(db::$db->error, ' '));
		return null;
	} else {
		fud_sql_error_handler($q, db::$db->error, db::$db->errno, get_version());
	}
}

function ins_m($tbl, $flds, $vals, $type=0)
{
	q("INSERT IGNORE INTO ".$tbl." (".$flds.") VALUES (".implode('),(', $vals).")");
}

function db_all($q)
{
	$f = array();
	$c = uq($q);
	while ($r = $c->fetch_row()) {
		$f[] = $r[0];
	}
	return $f;
}

function _esc($s)
{
	return "'".db::$db->real_escape_string($s)."'";
}
?>
