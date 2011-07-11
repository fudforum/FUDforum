<?php
/**
* copyright            : (C) 2001-2011 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id$
*
* This program is free software; you can redistribute it and/or modify it 
* under the terms of the GNU General Public License as published by the 
* Free Software Foundation; version 2 of the License. 
**/
# define('fud_query_stats', 1);

if (!defined('fud_sql_lnk')) {
	$connect_func = $GLOBALS['FUD_OPT_1'] & 256 ? 'ibase_pconnect' : 'ibase_connect';

	$conn = $connect_func($GLOBALS['DBHOST'] .':'. $GLOBALS['DBHOST_DBNAME'], $GLOBALS['DBHOST_USER'], $GLOBALS['DBHOST_PASSWORD'], 'UTF-8') or fud_sql_error_handler('Initiating '. $connect_func, ibase_errmsg(), ibase_errcode(), 'Unknown');
	define('fud_sql_lnk', $conn);

	// Interbase driver used to implement Firebird access.
	define('__dbtype__', 'firebird');
}

function db_close()
{
	 ibase_close(fud_sql_lnk);
}

function db_version()
{
	if (!defined('__FUD_SQL_VERSION__')) {
		$ver = q_singleval('SELECT rdb$get_context(\'SYSTEM\', \'ENGINE_VERSION\') from rdb$database');
		define('__FUD_SQL_VERSION__', $ver);
	}
	return __FUD_SQL_VERSION__;
}

function db_lock($tables)
{
	if (!empty($GLOBALS['__DB_INC_INTERNALS__']['db_locked'])) {
		fud_sql_error_handler('Recursive Lock', 'internal', 'internal', db_version());
	} else {
		// q('LOCK TABLES '.$tables);
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
	q('COMMIT');
}

function db_locked()
{
	return isset($GLOBALS['__DB_INC_INTERNALS__']['db_locked']);
}

function db_affected()
{
	return ibase_affected_rows(fud_sql_lnk);	
}

function uq($query)
{
	return q($query);
}

if (!defined('fud_query_stats')) {
	function q($query)
	{
		// echo "DEBUG: RUN Q=[$query]<HR>";
		$r = @ibase_query(_firebird_syntax($query)) or fud_sql_error_handler($query, ibase_errmsg(), ibase_errcode(), db_version());
		if (!db_locked() && strncasecmp($query, 'SELECT', 6) && strncasecmp($query, 'COMMIT', 6)) {
			@ibase_commit();	// DB not locked and not a SELECT or COMMIT, autocommit.
		}
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
		$result = @ibase_query(_firebird_syntax($query)) or fud_sql_error_handler($query, ibase_errmsg(), ibase_errcode(), db_version());
		if (!db_locked() && strncasecmp($query, 'SELECT', 6) && strncasecmp($query, 'COMMIT', 6)) {
			@ibase_commit();	// DB not locked and not a SELECT or COMMIT, autocommit.
		}
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
}

function db_rowobj($result)
{
	// We cannot use ibase_fetch_object as it returns uppercase class variables. 
	$obj = new stdClass();
	$tmp = ibase_fetch_assoc($result, IBASE_TEXT);
	if (!$tmp) return NULL;
	foreach ($tmp as $key => $val) {
		$obj->{strtolower($key)} = $val;
	}
	return $obj;
}

function db_rowarr($result)
{
	return ibase_fetch_row($result, IBASE_TEXT);
}

function q_singleval($query)
{
	if (($res = ibase_fetch_row(q($query), IBASE_TEXT)) !== false) {
		return $res[0];
	}
}

function q_limit($query, $limit, $off=0)
{
	return 'SELECT FIRST '. $limit . ((!empty($off)) ? ' SKIP ' . $off : '') . substr($query, 6);
}

function q_concat($arg)
{
	$tmp = func_get_args();
	return implode('||', $tmp);
}

function q_rownum() {
	// q('DELETE FROM RDB$GENERATORS WHERE RDB$GENERATOR_NAME=\'FUD_ROWID\'');
	// q('INSERT INTO RDB$GENERATORS (RDB$GENERATOR_NAME) VALUES (\'FUD_ROWID\')');
	// return 'GEN_ID(FUD_ROWID,1)';

	return 'cast(case rdb$set_context(\'USER_SESSION\', \'rownum\', cast(rdb$get_context(\'USER_SESSION\', \'rownum\') as int) + 1) when \'0\' then rdb$set_context(\'USER_SESSION\', \'rownum\', 1) + 1 else rdb$get_context(\'USER_SESSION\', \'rownum\') end as int)';
}

function q_bitand($fieldLeft, $fieldRight) {
	return 'BIN_AND('. $fieldLeft .', '. $fieldRight .')';
}

function q_bitor($fieldLeft, $fieldRight) {
	return 'BIN_OR('. $fieldLeft .', '. $fieldRight .')';
}

function q_bitnot($bitField) {
	return 'BIN_NOT('. $bitField .')';
}

function db_saq($q)
{
	return ibase_fetch_row(q($q), IBASE_TEXT);
}

function db_sab($q)
{
	$r = q($q);
	return db_rowobj($r);
}

function db_qid($q)
{
	q($q);

	$seqname = preg_replace('/.*INSERT\s+INTO\s+(\w+)[\s\(\,].*$/si', '\\1', $q) .'_id_seq';
	return q_singleval('SELECT gen_id('. $seqname .', 0) FROM RDB$DATABASE');
}

function db_arr_assoc($q)
{
	$r = q($q);
	return db_fetch_array($r);
}

function db_fetch_array($q)
{
	$tmp = ibase_fetch_assoc($q, IBASE_TEXT);
	if (!$tmp) return NULL;

	// Change Firebird's UPPERCASE keys to lowercase.
	return array_change_key_case($tmp, CASE_LOWER);
		
}

function db_li($q, &$ef, $li=0)
{
	$r = @ibase_query($q);
	if ($r) {
		if ($li) {
			$seqname = preg_replace('/.*INSERT\s+INTO\s+(\w+)[\s\(\,].*$/si', '\\1', $q) .'_id_seq';
			return q_singleval('SELECT gen_id('. $seqname .', 0) FROM RDB$DATABASE');
		}
		return $r;
	}

	/* ERROR: -803: attempt to store duplicate value (visible to active transactions) in unique index. */
	if (ibase_errcode() == -803) {
		$ef = ltrim(strrchr(ibase_errmsg(), ' '));
		return null;
	} else {
		fud_sql_error_handler($q, ibase_errmsg(), ibase_errcode(), db_version());
	}
}

function ins_m($tbl, $flds, $types, $vals)
{
	foreach($vals as $val) {
		@ibase_query('INSERT INTO '. $tbl .' ('. $flds .') VALUES ('. $val. ')');
	}
}

function db_all($q)
{
	$f = array();
	$c = uq($q);
	while ($r = ibase_fetch_row($c, IBASE_TEXT)) {
		$f[] = $r[0];
	}
	return $f;
}

function _esc($s)
{
	// Replace any single ' with ''.
	$s = str_replace('\'', '\'\'', $s);

	return '\''. $s .'\'';
}

function _firebird_syntax($q)
{
	// Difficult to belief, but there is still a database that doesn't support LENGTH()!
	$q = preg_replace('/LENGTH\(/i', 'CHAR_LENGTH(', $q);
	
	// SELECT without FROM, we need to append 'FROM rdb$database'.
	if (preg_match('/^SELECT /i', $q) && !preg_match('/FROM/i', $q)) {
		$q .= ' FROM rdb$database';
	}
	
	return $q;
}
?>
