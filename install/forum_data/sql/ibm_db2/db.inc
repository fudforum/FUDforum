<?php
/**
* copyright            : (C) 2001-2020 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id$
*
* This program is free software; you can redistribute it and/or modify it 
* under the terms of the GNU General Public License as published by the 
* Free Software Foundation; version 2 of the License. 
**/
# define('fud_query_stats', 1);

if (!defined('fud_sql_lnk')) {
	if (! extension_loaded('ibm_db2')) {
		fud_sql_error_handler('Loading driver', 'We were unable to use an DB2 database because the IBM_DB2 extension for PHP is not installed. Check your PHP.ini to see how it can be enabled', '', 'Unknown');
	}

	$connect_func = $GLOBALS['FUD_OPT_1'] & 256 ? 'db2_pconnect' : 'db2_connect';	
	$opts = array('db2_attr_case' => DB2_CASE_LOWER);

	$dsn = 'DRIVER={IBM DB2 ODBC DRIVER};PROTOCOL=TCPIP;';
	if ($GLOBALS['DBHOST_DBNAME']) {
		$dsn .= 'DATABASE='. $GLOBALS['DBHOST_DBNAME'] .';';
	}
	if ($GLOBALS['DBHOST']) {
		$dbhost = $GLOBALS['DBHOST'];
		// Expand optional port number (e.g. [::1]:5432).
		if ( preg_match('/^(.+):(\d*)$/', $dbhost, $m)) {
			$dbhost = $m[1] .';PORT='. $m[2];
		}
		// Remove IPv6 block quotes (if present).
		$dsn .= preg_replace('/\[|\]/', '', 'HOSTNAME='. $dbhost .';');
	}
	if ($GLOBALS['DBHOST_USER']) {
		$dsn .= 'UID='. $GLOBALS['DBHOST_USER'] .';';
	}
	if ($GLOBALS['DBHOST_PASSWORD']) {
		$dsn .= 'PWD='. $GLOBALS['DBHOST_PASSWORD'] .';';
	}
	
	$conn = $connect_func($dsn, NULL, NULL, $opts);
	if (!$conn) {
		fud_sql_error_handler('Initiating '. $connect_func, db2_conn_errormsg(), db2_conn_error(), 'Unknown');
	}

	define('fud_sql_lnk', $conn);
	define('__dbtype__', 'db2');
}

function db_close()
{
	 db2_close(fud_sql_lnk);
}

function db_version()
{
	if (!defined('__FUD_SQL_VERSION__')) {
		$server = db2_server_info(fud_sql_lnk);
		define('__FUD_SQL_VERSION__', $server->DBMS_VER);
	}
	return __FUD_SQL_VERSION__;
}

function db_lock($tables)
{
	if (!empty($GLOBALS['__DB_INC_INTERNALS__']['db_locked'])) {
		fud_sql_error_handler('Recursive Lock', 'internal', 'internal', db_version());
	} else {
		// DB2 cannot lock a set of tables in one go and doesn't like table aliases in LOCK statements!
		foreach (explode(',', str_replace(' WRITE', '', $tables)) as $v) {
			if (!($v = trim($v))) continue;
			list($v, ) = explode(' ', $v);	// Remove table alias.
			q('LOCK TABLE '. $v .' IN EXCLUSIVE MODE');
		}
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
	return db2_num_rows($GLOBALS['__FLPG__']);
}

function uq($q)
{
	return q($q);
}

if (!defined('fud_query_stats')) {
	function q($q)
	{
		$GLOBALS['__FLPG__'] = @db2_exec(fud_sql_lnk, _db2_syntax($q));
		if ($GLOBALS['__FLPG__'] === FALSE) {
			fud_sql_error_handler($q, db2_stmt_errormsg(), db2_stmt_error(), db_version());
		}
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
		$GLOBALS['__FLPG__'] = @db2_exec(fud_sql_lnk, _db2_syntax($q));
		if ($GLOBALS['__FLPG__'] === FALSE) {
			fud_sql_error_handler($q, db2_stmt_errormsg(), db2_stmt_error(), db_version());
		}
		$e = gettimeofday(true);

		$GLOBALS['__DB_INC_INTERNALS__']['last_time'] = ($e - $s);
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

	return db2_fetch_object($result);
}

function db_rowarr($result)
{
	return db2_fetch_array($result);
}

function q_singleval($query)
{
	$val = db2_fetch_array(q($query));
	return $val ? $val[0] : false;
}

function q_limit($query, $limit, $off=0)
{
	if ($off) {
		return 'SELECT x2.* FROM ( SELECT ROW_NUMBER() OVER() AS "FUD_DB_ROWNUM", x1.* FROM ('. $query .') x1 ) x2 WHERE x2.FUD_DB_ROWNUM BETWEEN '. ($off+1) .' AND '. ($off+$limit);
	} else {
		return $query .' FETCH FIRST '. $limit .' ROWS ONLY';
	}
}

function q_concat($arg)
{
	$tmp = func_get_args();
	return implode('||', $tmp);
}

function q_rownum() {
	return 'row_number() OVER ()';
}

function q_bitand($fieldLeft, $fieldRight) {
	return 'BITAND('. $fieldLeft .', '. $fieldRight .')';
}

function q_bitor($fieldLeft, $fieldRight) {
	return '('. $fieldLeft .' + '. $fieldRight .' - BITAND('. $fieldLeft .', '. $fieldRight .'))';
}

function q_bitnot($bitField) {
	return '(0 - '. $bitField .') - 1';
}

function db_saq($q)
{
	return db2_fetch_array(q($q));
}

function db_sab($q)
{
	$r = q($q);
	return db_rowobj($r);
}

function db_qid($q)
{
	$r = q($q);
	return db2_last_insert_id(fud_sql_lnk);
}

function db_arr_assoc($q)
{
	$r = q($q);
	return db_fetch_array($r);
}

function db_fetch_array($q)
{
	return db2_fetch_assoc($q);
}

function db_li($q, &$ef, $li=0)
{
	$r = @db2_exec(fud_sql_lnk, _db2_syntax($q));

	if ($r) {
		return ($li ? db2_last_insert_id(fud_sql_lnk) : $r);
	}

	/* Ignore duplicate keys: DB2 error# 23505. */
	if ( db2_stmt_error() == 23505 ) {
		return null;
	} else {
		fud_sql_error_handler($q, db2_stmt_errormsg(), db2_stmt_error(), db_version());
	}
}

function ins_m($tbl, $flds, $types, $vals)
{
	$sth = db2_prepare(fud_sql_lnk, 'INSERT INTO '. $tbl .' ('. $flds .') VALUES(?'. str_repeat(',?', substr_count($flds, ',')) .')');
	foreach ($vals as $v) {
		$row = explode(',', trim($v, '\''));
		if (!@db2_execute($sth, $row)) {
			if ( db2_stmt_error() == 23505 ) {	// Ignore DB2 error# 23505: unique constraint violated.
				continue;
			} else {
				fud_sql_error_handler('ins_m() on '. $tbl, db2_stmt_errormsg(), db2_stmt_error(), db_version());
			}
		}
	}
}

function db_all($q)
{
	$f = array();
	$c = uq($q);
	while ($r = db2_fetch_array($c)) {
		$f[] = $r[0];
	}
	return $f;
}

function _esc($s)
{
	return '\''. db2_escape_string($s) .'\'';
}

function _db2_syntax($q)
{
	// SELECT without FROM, we need to append 'FROM sysibm.sysdummy1'.
	if (preg_match('/^SELECT /i', $q) && !preg_match('/FROM/i', $q)) {
		$q .= ' FROM sysibm.sysdummy1';
	}

	return $q;
}

?>
