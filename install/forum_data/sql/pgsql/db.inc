<?php
/**
* copyright            : (C) 2001-2004 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: db.inc,v 1.65 2005/08/16 21:25:44 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it 
* under the terms of the GNU General Public License as published by the 
* Free Software Foundation; either version 2 of the License, or 
* (at your option) any later version.
**/

if (!defined('fud_sql_lnk')) {
	$connect_func = $GLOBALS['FUD_OPT_1'] & 256 ? 'pg_pconnect' : 'pg_connect';	
	
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
	define('fud_sql_lnk', $conn);
	define('__dbtype__', 'pgsql');
	define('__FUD_SQL_CONCAT__', 'textcat');
}

function get_version()
{
	if (!defined('__FUD_SQL_VERSION__')) {
		$val = pg_fetch_row(pg_query('SELECT VERSION()', fud_sql_lnk));
		define('__FUD_SQL_VERSION__', ($val ? $val[0] : null));
	}
	return __FUD_SQL_VERSION__;
}

function db_lock($tables)
{
	if (!empty($GLOBALS['__DB_INC_INTERNALS__']['db_locked'])) {
		fud_sql_error_handler("Recursive Lock", "internal", "internal", get_version());
	} else {
		$tbls = explode(',', str_replace(' WRITE', '', $tables));
		foreach ($tbls as $k => $t) {
			$t = trim($t);
			if (($p = strpos($t, ' '))) {
				$tbls[$k] = substr($t, 0, $p);
			}
		}
		$tables = implode(',', $tbls);

		q('BEGIN WORK');
		q('LOCK TABLE '.$tables.' IN ACCESS EXCLUSIVE MODE');
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
	q('COMMIT WORK', fud_sql_lnk);
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
	function q($q)
	{
		$GLOBALS['__FLPG__'] = pg_query(fud_sql_lnk, $q) or die (fud_sql_error_handler($q, pg_last_error(fud_sql_lnk), '', get_version()));
		return $GLOBALS['__FLPG__'];
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
		$GLOBALS['__FLPG__'] = pg_query(fud_sql_lnk, $q) or die (fud_sql_error_handler($q, pg_last_error(fud_sql_lnk), '', get_version()));
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
}

function db_count($r)
{
	$r = pg_num_rows($r);
	if ($r < 0) {
		return 0;
	}
	return $r;
}

function db_rowobj($result)
{
	return pg_fetch_object($result);
}

function db_rowarr($result)
{
	return pg_fetch_row($result);
}

function q_singleval($query)
{
	$val = pg_fetch_row(q($query));
	return $val ? $val[0] : null;
}

function qry_limit($limit, $off)
{
	return $limit.' OFFSET '.$off;
}

function get_fud_table_list($tbl='')
{
	if (!$tbl) {
		$r = q("SELECT relname FROM pg_class WHERE relkind='r' AND relname LIKE '".str_replace("_", "\\_", $GLOBALS['DBHOST_TBL_PREFIX'])."%'");
		while (list($ret[]) = db_rowarr($r));
		array_pop($ret);
	} else {
		return q_singleval("SELECT relname FROM pg_class WHERE relkind='r' AND relname='".$tbl."'");
	}

	return $ret;
}

function optimize_tables($tbl_list=null)
{
	if (!$tbl_list) {
		$tbl_list = get_fud_table_list();
	}
	foreach ($tbl_list as $t) {
		q('VACUUM ANALYZE ' . $t);
	}
}

function db_saq($q)
{
	return pg_fetch_row(q($q));
}

function db_sab($q)
{
	return pg_fetch_object(q($q));
}

function db_qid($q)
{
	$r = q($q);
	preg_match('!('.$GLOBALS['DBHOST_TBL_PREFIX'].'[A-Za-z0-9_]+)!', $q, $m);
	return q_singleval('SELECT id FROM '.$m[1].' WHERE oid='.pg_last_oid($r));
}

function db_arr_assoc($q)
{
	return pg_fetch_array(q($q), 0, PGSQL_ASSOC);
}

function db_li($q, &$ef, $li=0)
{
	$r = @pg_query(fud_sql_lnk, $q);
	if ($r) {
		if ($li) {
			preg_match('!('.$GLOBALS['DBHOST_TBL_PREFIX'].'[A-Za-z0-9_]+)!', $q, $m);
			return q_singleval('SELECT id FROM '.$m[1].' WHERE oid='.pg_last_oid($r));
		}
		return $r;
	}

	$err = pg_last_error(fud_sql_lnk);

	/* duplicate key */
	if (strpos($err, 'duplicate key') !== false) {
		$ef = ltrim(strrchr($err, ' '));
		return null;
	} else {
		die(fud_sql_error_handler($q, pg_last_error(fud_sql_lnk), '', get_version()));
	}
}

function ins_m($tbl, $flds, $vals, $type)
{
	$pid = $GLOBALS['DBHOST_TBL_PREFIX'] . substr(md5(mt_rand()), 0, 10);

	pg_query(fud_sql_lnk, 'PREPARE '.$pid.' ('.$type.') AS INSERT INTO '.$tbl.' ('.$flds.') VALUES($'.implode(',$', range(1, substr_count($flds, ',') + 1)).')');

	$err = error_reporting(0);
	foreach ($vals as $v) {
		pg_query(fud_sql_lnk, 'EXECUTE '.$pid.' ('.$v.')');
	}
	error_reporting($err);

	/* if persistent connections are used de-allocte the prepared statement to prevent query failures */
	if ($GLOBALS['FUD_OPT_1'] & 256) {
		pg_query(fud_sql_lnk, 'DEALLOCATE '.$pid);
	}
}
function db_all($q)
{
	if (function_exists('pg_fetch_all_columns')) {
		return pg_fetch_all_columns(q($q));
	}

	if (!($r = pg_fetch_all(q($q)))) {
		return array();
	}
	$f = array();
	foreach ($r as $v) {
		$f[] = current($v);
	}
	return $f;
}
?>