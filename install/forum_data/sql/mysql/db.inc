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
// define('fud_query_stats', 1);

if (!defined('fud_sql_lnk')) {
	$connect_func = $GLOBALS['FUD_OPT_1'] & 256 ? 'mysql_pconnect' : 'mysql_connect';

	$conn = $connect_func($GLOBALS['DBHOST'], $GLOBALS['DBHOST_USER'], $GLOBALS['DBHOST_PASSWORD']) or fud_sql_error_handler('Initiating '. $connect_func, mysql_error(fud_sql_lnk), mysql_errno(fud_sql_lnk), 'Unknown');
	define('fud_sql_lnk', $conn);
	mysql_select_db($GLOBALS['DBHOST_DBNAME'], fud_sql_lnk) or fud_sql_error_handler('Opening database '. $GLOBALS['DBHOST_DBNAME'], mysql_error(fud_sql_lnk), mysql_errno(fud_sql_lnk), db_version());
	mysql_set_charset('utf8');

	define('__dbtype__', 'mysql');
}

function db_version()
{
	if (!defined('__FUD_SQL_VERSION__')) {
		$ver = mysql_fetch_row(mysql_query('SELECT VERSION()', fud_sql_lnk));
		define('__FUD_SQL_VERSION__', $ver[0]);
	}
	return __FUD_SQL_VERSION__;
}

function db_lock($tables)
{
	if (!empty($GLOBALS['__DB_INC_INTERNALS__']['db_locked'])) {
		fud_sql_error_handler('Recursive Lock', 'internal', 'internal', db_version());
	} else {
		q('LOCK TABLES '.$tables);
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
	q('UNLOCK TABLES');
}

function db_locked()
{
	return isset($GLOBALS['__DB_INC_INTERNALS__']['db_locked']);
}

function db_affected()
{
	return mysql_affected_rows(fud_sql_lnk);	
}

if (!defined('fud_query_stats')) {
	function q($query)
	{
		$r = mysql_query($query, fud_sql_lnk) or fud_sql_error_handler($query, mysql_error(fud_sql_lnk), mysql_errno(fud_sql_lnk), db_version());
		return $r;
	}
	function uq($query)
	{
		$r = mysql_unbuffered_query($query,fud_sql_lnk) or fud_sql_error_handler($query, mysql_error(fud_sql_lnk), mysql_errno(fud_sql_lnk), db_version());
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
		$result = mysql_query($query, fud_sql_lnk) or fud_sql_error_handler($query, mysql_error(fud_sql_lnk), mysql_errno(fud_sql_lnk), db_version());
		$e = microtime(true);

		$GLOBALS['__DB_INC_INTERNALS__']['last_time'] = ($e - $s);
		$GLOBALS['__DB_INC_INTERNALS__']['total_sql_time'] += $GLOBALS['__DB_INC_INTERNALS__']['last_time'];
		$GLOBALS['__DB_INC_INTERNALS__']['last_query'] = $query;

		echo '<pre>'. preg_replace('!\s+!', ' ', $query) .'</pre>';
		echo '<pre>query count: '. $GLOBALS['__DB_INC_INTERNALS__']['query_count'] .' time taken: '. $GLOBALS['__DB_INC_INTERNALS__']['last_time'] .'</pre>';
		echo '<pre>Affected rows: '. db_affected() .'</pre>';
		echo '<pre>total sql time: '. $GLOBALS['__DB_INC_INTERNALS__']['total_sql_time'] .'</pre>';

		return $result; 
	}

	function uq($query)
	{
		return q($query);
	}
}

function db_rowobj($result)
{
	return mysql_fetch_object($result);
}

function db_rowarr($result)
{
	return mysql_fetch_row($result);
}

function q_singleval($query)
{
	if (($res = mysql_fetch_row(q($query))) !== false) {
		return $res[0];
	}
}

function q_limit($query, $limit, $off=0)
{
	// OLD SYNTAX: return $query .' LIMIT '. $off .','. $limit;
	return $query .' LIMIT '. $limit .' OFFSET '. $off;
}

function q_concat($arg)
{
	// MySQL badly breaks the SQL standard by redefining || to mean OR. 
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
	return $fieldLeft .' | '. $fieldRight;
}

function q_bitnot($bitField) {
	return '~'. $bitField;
}

function db_saq($q)
{
	return mysql_fetch_row(q($q));
}
function db_sab($q)
{
	return mysql_fetch_object(q($q));
}
function db_qid($q)
{
	q($q);
	return mysql_insert_id(fud_sql_lnk);
}
function db_arr_assoc($q)
{
	return mysql_fetch_array(q($q), MYSQL_ASSOC);
}

function db_fetch_array($q)
{
        return mysql_fetch_array($q,  MYSQL_ASSOC);
}

function db_li($q, &$ef, $li=0)
{
	$r = mysql_query($q, fud_sql_lnk);
	if ($r) {
		return ($li ? mysql_insert_id(fud_sql_lnk) : $r);
	}

	/* Duplicate key. */
	if (mysql_errno(fud_sql_lnk) == 1062) {
		$ef = ltrim(strrchr(mysql_error(fud_sql_lnk), ' '));
		return null;
	} else {
		fud_sql_error_handler($q, mysql_error(fud_sql_lnk), mysql_errno(fud_sql_lnk), db_version());
	}
}

function ins_m($tbl, $flds, $types, $vals)
{
	q('INSERT IGNORE INTO '. $tbl .' ('. $flds .') VALUES ('. implode('),(', $vals) .')');
}

function db_all($q)
{
	$f = array();
	$c = uq($q);
	while ($r = mysql_fetch_row($c)) {
		$f[] = $r[0];
	}
	return $f;
}

function _esc($s)
{
	return '\''. mysql_real_escape_string($s, fud_sql_lnk) .'\'';
}
?>
