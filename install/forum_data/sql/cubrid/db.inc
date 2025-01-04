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

/* ---------------------------------------------------------------------------------------------------
 * THIS DRIVER IS EXPERIMENTAL AND DOESN'T WORK PROPERLY!!!
 *
 * PROBLEM IS THAT IT CANNOT RETURN CLOB VALUES IN-LINE (THERE IS NO "CUBRID_RETURN_LOBS").
 * HENCE, LOBS ARE RETURNED AS FILE NAMES.
 * ---------------------------------------------------------------------------------------------------
 */

if (!defined('fud_sql_lnk')) {
	$connect_func = $GLOBALS['FUD_OPT_1'] & 256 ? 'cubrid_pconnect' : 'cubrid_connect';

	if ($GLOBALS['DBHOST']) {
		$dbhost = $GLOBALS['DBHOST'];
		$port   = 33000;	// Default port.

		// Expand optional port number (e.g. localhost:5432).
		if ( preg_match('/^(.+):(\d*)$/', $dbhost, $m)) {
			$dbhost = $m[1];
			$port   = $m[2];
		}
	}

	/* Connect to database. */
	$conn = @$connect_func($dbhost, $port, $GLOBALS['DBHOST_DBNAME'], $GLOBALS['DBHOST_USER'], $GLOBALS['DBHOST_PASSWORD']) or fud_sql_error_handler('Initiating '. $connect_func, cubrid_error_msg(), cubrid_error_code(), 'Unknown');

	// Enable autocommit mode
	cubrid_set_autocommit($conn, true);

	define('fud_sql_lnk', $conn);
	define('__dbtype__', 'cubrid');
}

function db_close()
{
	cubrid_close(fud_sql_lnk);
}

function db_version()
{
	if (!defined('__FUD_SQL_VERSION__')) {
		$ver = q_singleval('SELECT VERSION()');
		define('__FUD_SQL_VERSION__', $ver);
	}
	return __FUD_SQL_VERSION__;
}

function db_lock($tables)
{
	if (!empty($GLOBALS['__DB_INC_INTERNALS__']['db_locked'])) {
		fud_sql_error_handler('Recursive Lock', 'internal', 'internal', db_version());
	} else {
		// q('BEGIN WORK');
		// q('LOCK TABLES '. $tables);
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
	// q('UNLOCK TABLES');
}

function db_locked()
{
	return isset($GLOBALS['__DB_INC_INTERNALS__']['db_locked']);
}

function db_affected()
{
	return cubrid_affected_rows(fud_sql_lnk);	
}

if (!defined('fud_query_stats')) {
	function q($query)
	{
		$r = cubrid_query($query, fud_sql_lnk) or fud_sql_error_handler($query, cubrid_error_msg(), cubrid_error_code(), db_version());
		return $r;
	}

	function uq($query)
	{
		$r = cubrid_unbuffered_query($query,fud_sql_lnk) or fud_sql_error_handler($query, cubrid_error_msg(), cubrid_error_code(), db_version());
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
		$result = cubrid_query($query, fud_sql_lnk) or fud_sql_error_handler($query, cubrid_error_msg(), cubrid_error_code(), db_version());
		$e = microtime(true);

		$GLOBALS['__DB_INC_INTERNALS__']['last_time'] = ($e - $s);
		$GLOBALS['__DB_INC_INTERNALS__']['total_sql_time'] += $GLOBALS['__DB_INC_INTERNALS__']['last_time'];

		echo '<hr><b>Query #'. $GLOBALS['__DB_INC_INTERNALS__']['query_count'] .'</b><small>';
		echo ': time taken:     <i>'. number_format($GLOBALS['__DB_INC_INTERNALS__']['last_time'], 4) .'</i>';
		echo ', affected rows:  <i>'. db_affected() .'</i>';
		echo ', total sql time: <i>'.  number_format($GLOBALS['__DB_INC_INTERNALS__']['total_sql_time'], 4) .'</i>';
		echo '<pre>'. preg_replace('!\s+!', ' ', htmlspecialchars($query)) .'</pre></small>';

		return $result; 
	}

	function uq($query)
	{
		return q($query);
	}
}

function db_rowobj($result)
{
	return cubrid_fetch_object($result);
}

function db_rowarr($result)
{
	return cubrid_fetch_row($result);
}

function q_singleval($query)
{
	if (($res = cubrid_fetch_row(q($query))) !== false) {
		return isset($res) ? $res[0] : '';
	}
}

function q_limit($query, $limit, $off=0)
{
	// LIMIT [offset,] row_count
	if ($off) {
		return $query .' LIMIT '. $off .','. $limit;
	} else {
		return $query .' LIMIT '. $limit;
	}
}

function q_concat($arg)
{
	// cubrid badly breaks the SQL standard by redefining || to mean OR. 
	$tmp = func_get_args();
	return 'CONCAT('. implode(',', $tmp) .')';
}

function q_rownum() {
	q('SET @seq=0');		// For simulating rownum.
	return '(@seq:=@seq+1)';
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
	return cubrid_fetch_row(q($q));
}

function db_sab($q)
{
	return cubrid_fetch_object(q($q));
}

function db_qid($q)
{
	q($q);
	return cubrid_insert_id(fud_sql_lnk);
}

function db_arr_assoc($q)
{
	return cubrid_fetch_array(q($q), CUBRID_ASSOC);
}

function db_fetch_array($q)
{
        return cubrid_fetch_array($q,  CUBRID_ASSOC);
}

function db_li($q, &$ef, $li=0)
{
	$r = @cubrid_query($q, fud_sql_lnk);
	if ($r) {
		return ($li ? cubrid_insert_id(fud_sql_lnk) : $r);
	}

	/* Duplicate key. */
	if (cubrid_error_code() == -670) {
		$ef = ltrim(strrchr(cubrid_error_msg(), ' '));
		return null;
	} else {
		fud_sql_error_handler($q, cubrid_error_msg(), cubrid_error_code(), db_version());
	}
}

function ins_m($tbl, $flds, $types, $vals)
{
 	foreach($vals as $val) {
		@cubrid_query('INSERT INTO '. $tbl .' ('. $flds .') VALUES ('. $val. ')');
	}
}

function db_all($q)
{
	$f = array();
	$c = uq($q);
	while ($r = cubrid_fetch_row($c)) {
		$f[] = $r[0];
	}
	return $f;
}

function _esc($s)
{
	return '\''. cubrid_real_escape_string($s, fud_sql_lnk ?? '') .'\'';
}
?>
