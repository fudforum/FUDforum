<?php
/**
* copyright            : (C) 2001-2025 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id$
*
* This program is free software; you can redistribute it and/or modify it 
* under the terms of the GNU General Public License as published by the 
* Free Software Foundation; version 2 of the License. 
**/
# define('fud_query_stats', 1);

if (!defined('fud_sql_lnk')) {
	$connect_func = $GLOBALS['FUD_OPT_1'] & 256 ? 'pg_pconnect' : 'pg_connect';

	$dsn = '';
	if ($GLOBALS['DBHOST']) {
		$dbhost = $GLOBALS['DBHOST'];

		// Expand optional port number (e.g. [::1]:5432).
		if ( preg_match('/^(.+):(\d*)$/', $dbhost, $m)) {
			$dbhost = $m[1] .' port='. $m[2];
		}
		// Remove IPv6 block quotes (if present).
		$dsn .= preg_replace('/\[|\]/', '', 'host='. $dbhost);
	}
	if ($GLOBALS['DBHOST_USER']) {
		$dsn .= ' user='. $GLOBALS['DBHOST_USER'];
	}
	if ($GLOBALS['DBHOST_PASSWORD']) {
		$dsn .= ' password='. $GLOBALS['DBHOST_PASSWORD'];
	}
	if ($GLOBALS['DBHOST_DBNAME']) {
		$dsn .= ' dbname='. $GLOBALS['DBHOST_DBNAME'];
	}

	// The pgsql module cannot return connection errors. Don't event try pg_last_error(), it will fail!
	$conn = $connect_func(ltrim($dsn)) or fud_sql_error_handler('Initiating '. $connect_func, 'Unable to connect', '', 'Unknown');
	pg_set_client_encoding($conn, 'UTF8');

	define('fud_sql_lnk', $conn);
	define('__dbtype__', 'pgsql');
}

function db_close()
{
	 pg_close(fud_sql_lnk);
}

function db_version()
{
	if (!defined('__FUD_SQL_VERSION__')) {
		$v = pg_version(fud_sql_lnk);
		define('__FUD_SQL_VERSION__', $v['server']);
	}
	return __FUD_SQL_VERSION__;
}

function db_lock($tables)
{
	if (!empty($GLOBALS['__DB_INC_INTERNALS__']['db_locked'])) {
		fud_sql_error_handler('Recursive Lock', 'internal', 'internal', db_version());
	} else {
		$tbl = array();
		foreach (explode(',', str_replace(' WRITE', '', $tables)) as $v) {
			if (!($v = trim($v))) continue;
			$tbl[] = strtok($v, ' ');
		}
		q('BEGIN WORK');
		q('LOCK TABLE '. implode(',', $tbl) .' IN ACCESS EXCLUSIVE MODE');
		$GLOBALS['__DB_INC_INTERNALS__']['db_locked'] = 1;
	}
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
		$GLOBALS['__FLPG__'] = pg_query(fud_sql_lnk, $q) or fud_sql_error_handler($q, pg_last_error(fud_sql_lnk), '', db_version());
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
	
		$s = microtime(true);
		$GLOBALS['__FLPG__'] = pg_query(fud_sql_lnk, $q) or fud_sql_error_handler($q, pg_last_error(fud_sql_lnk), '', db_version());
		$e = microtime(true);
	
		$GLOBALS['__DB_INC_INTERNALS__']['last_time'] = ($e - $s);;
		$GLOBALS['__DB_INC_INTERNALS__']['total_sql_time'] += $GLOBALS['__DB_INC_INTERNALS__']['last_time'];

		echo '<hr><b>Query #'. $GLOBALS['__DB_INC_INTERNALS__']['query_count'] .'</b><small>';
		echo ': time taken:     <i>'. number_format($GLOBALS['__DB_INC_INTERNALS__']['last_time'], 4) .'</i>';
		echo ', affected rows:  <i>'. db_affected() .'</i>';
		echo ', total sql time: <i>'.  number_format($GLOBALS['__DB_INC_INTERNALS__']['total_sql_time'], 4) .'</i>';
		echo '<pre>'. preg_replace('!\s+!', ' ', htmlspecialchars($q)) .'</pre></small>';

		return $GLOBALS['__FLPG__'];
	}
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
	return $val ? $val[0] : false;
}

function q_limit($query, $limit, $off=0)
{
	return $query .' LIMIT '. $limit .' OFFSET '. $off;
}

function q_concat($args)
{
	$args = func_get_args();
	return implode('::text ||', $args); 
}

function q_rownum() {
	q('DROP SEQUENCE IF EXISTS fud_rnum');
	q('CREATE TEMPORARY SEQUENCE fud_rnum');
	return 'nextval(\'fud_rnum\')';
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
	return pg_fetch_row(q($q));
}

function db_sab($q)
{
	return pg_fetch_object(q($q));
}

function db_qid($q)
{
	$r = q($q);
	return q_singleval('SELECT lastval()');
}

function db_arr_assoc($q)
{
	return pg_fetch_array(q($q), 0, PGSQL_ASSOC);
}

function db_fetch_array($q)
{
	return pg_fetch_array($q, NULL, PGSQL_ASSOC);
}

function db_li($q, &$ef, $li=0)
{
	// PgSQL will fail the entire tran if dup val error is encountered.
	if (pg_transaction_status(fud_sql_lnk) === PGSQL_TRANSACTION_INTRANS) {
	      pg_exec(fud_sql_lnk, 'SAVEPOINT pre_db_li');
	}

	$r = @pg_query(fud_sql_lnk, $q);
	if ($r) {
		if ($li) {
			$r = q_singleval('SELECT lastval()');
		}
	}

	/* Ignore duplicate key errors. */
	if ($err = pg_last_error(fud_sql_lnk)) {
		if (pg_transaction_status(fud_sql_lnk) === PGSQL_TRANSACTION_INTRANS) {
			pg_exec(fud_sql_lnk, 'ROLLBACK TO pre_db_li');
		}
		$ef = ltrim(strrchr($err, ' '));
	} else {
		if (pg_transaction_status(fud_sql_lnk) === PGSQL_TRANSACTION_INTRANS) {
			pg_exec(fud_sql_lnk, 'RELEASE pre_db_li');
		}
	}

	return $r;
}

function ins_m($tbl, $flds, $types, $vals)
{
	$pid = $GLOBALS['DBHOST_TBL_PREFIX'] . substr(md5(mt_rand()), 0, 10);
	pg_query(fud_sql_lnk, 'PREPARE '. $pid .' ('. $types .') AS INSERT INTO '. $tbl .' ('. $flds .') VALUES($'. implode(',$', range(1, substr_count($flds, ',') + 1)) .')');
	// pg_prepare(fud_sql_lnk, $pid, 'INSERT INTO '. $tbl .' ('. $flds .') VALUES($'. implode(',$', range(1, substr_count($flds, ',') + 1)) .')');

	$err = error_reporting(0);	// Simply ignore DUPLICATE errors.
	foreach ($vals as $v) {
		if (!empty($GLOBALS['__DB_INC_INTERNALS__']['db_locked'])) pg_exec('SAVEPOINT pre_ins_m');
		pg_exec(fud_sql_lnk, 'EXECUTE '. $pid .' ('. $v .')');
		// pg_execute(fud_sql_lnk, $pid,  explode(',', $v));	// $v must be unquoted before we can use this method.
		if (pg_last_error(fud_sql_lnk)) {
			if (!empty($GLOBALS['__DB_INC_INTERNALS__']['db_locked'])) pg_exec(fud_sql_lnk, 'ROLLBACK TO pre_ins_m');
		} else {
			if (!empty($GLOBALS['__DB_INC_INTERNALS__']['db_locked'])) pg_exec(fud_sql_lnk, 'RELEASE pre_ins_m');
		}
	}
	error_reporting($err);

	/* De-allocte the prepared statement to prevent query failures. */
	pg_query(fud_sql_lnk, 'DEALLOCATE '. $pid);
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

function _esc($s)
{
	return '\''. pg_escape_string(fud_sql_lnk, $s) .'\'';
}
?>
