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

/* ---------------------------------------------------------------------------------------------------
 * THIS DRIVER IS EXPERIMENTAL AND DOESN'T WORK PROPERLY!!!
 *
 * PROBLEM IS THAT PDO_OCI DOESN'T HAVE AN EQUIVALENT FOR "OCI_RETURN_LOBS".
 * HENCE, LOBS ARE RETURNED AS STREAMS.
 * ---------------------------------------------------------------------------------------------------
 */

class db { public static $db, $affected_rows, $res; }

function db_close()
{
	db::$db = null;
}

function db_version()
{
	if (!defined('__FUD_SQL_VERSION__')) {
		define('__FUD_SQL_VERSION__', db::$db->getAttribute(PDO::ATTR_SERVER_VERSION));
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
		q('LOCK TABLE '.implode(',', $tbl).' IN EXCLUSIVE MODE');
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
	q('COMMIT WORK');
}

function db_locked()
{
	return isset($GLOBALS['__DB_INC_INTERNALS__']['db_locked']);
}

function db_affected()
{
	return db::$affected_rows;
}

function __enifo($a)
{
	return end($a);	// Return last element of error array.
}

if (!defined('fud_query_stats')) {
	function uq($query)
	{
		if (!strncasecmp($query, 'SELECT', 6)) {
			db::$res = null;
			db::$res = db::$db->query(_ora_syntax($query)) or fud_sql_error_handler($query, __enifo(db::$db->errorInfo()), db::$db->errorCode(), db_version());
			return db::$res;
		}

		db::$res = null;
		if ((db::$affected_rows = db::$db->exec($query)) === FALSE) {
			fud_sql_error_handler($query, __enifo(db::$db->errorInfo()), db::$db->errorCode(), db_version());
		}
		return db::$affected_rows;
	}
} else {
	function uq($query)
	{
		if (!strncasecmp($query, 'SELECT', 6)) {
			if (!isset($GLOBALS['__DB_INC_INTERNALS__']['query_count'])) {
				$GLOBALS['__DB_INC_INTERNALS__']['query_count'] = 1;
			} else {
				++$GLOBALS['__DB_INC_INTERNALS__']['query_count'];
			}

			if (!isset($GLOBALS['__DB_INC_INTERNALS__']['total_sql_time'])) {
				$GLOBALS['__DB_INC_INTERNALS__']['total_sql_time'] = 0;
			}

			$s = microtime(true);
			db::$res = null;
			db::$res = db::$db->query(_ora_syntax($query)) or fud_sql_error_handler($query, __enifo(db::$db->errorInfo()), db::$db->errorCode(), db_version());
			$e = gettimeofday(true);

			$GLOBALS['__DB_INC_INTERNALS__']['last_time'] = ($e - $s);
			$GLOBALS['__DB_INC_INTERNALS__']['total_sql_time'] += $GLOBALS['__DB_INC_INTERNALS__']['last_time'];

			echo '<hr><b>Query #'. $GLOBALS['__DB_INC_INTERNALS__']['query_count'] .'</b><small>';
			echo ': time taken:     <i>'. number_format($GLOBALS['__DB_INC_INTERNALS__']['last_time'], 4) .'</i>';
			echo ', affected rows:  <i>'. db_affected() .'</i>';
			echo ', total sql time: <i>'.  number_format($GLOBALS['__DB_INC_INTERNALS__']['total_sql_time'], 4) .'</i>';
			echo '<pre>'. preg_replace('!\s+!', ' ', htmlspecialchars($query)) .'</pre></small>';

			return db::$res;
		}

		db::$res = null;
		if ((db::$affected_rows = db::$db->exec($query)) === FALSE) {
			fud_sql_error_handler($query, __enifo(db::$db->errorInfo()), db::$db->errorCode(), db_version());
		}
		return db::$affected_rows;
	}
}

function q($query)
{
	return uq($query);
}

function db_rowobj($result)
{
	$obj = new stdClass();
	// We cannot use oci_fetch_object as it returns uppercase class variables. 
	$tmp = $result->fetch(PDO::FETCH_ASSOC);
	if (!$tmp) return NULL;
	foreach ($tmp as $key => $val) {
		$obj->{strtolower($key)} = $val;
	}
	return $obj;
}

function db_rowarr($result)
{
	return $result->fetch(PDO::FETCH_NUM);
}

function q_singleval($query)
{
	return q($query)->fetchColumn();
}

function q_limit($query, $limit, $off=0)
{
	if ($off) {
		return 'SELECT * FROM (SELECT /*+ FIRST_ROWS */ a.*, rownum AS xrownum FROM ('. $query .') a WHERE rownum <= '. ($off + $limit) .') WHERE xrownum >= '. ($off+1);
	} else {
		return 'SELECT * FROM (SELECT /*+ FIRST_ROWS */ a.* FROM ('. $query .') a WHERE rownum <= '. $limit .')';
	}
}

function q_concat($arg)
{
	$tmp = func_get_args();
	return implode('||', $tmp);
}

function q_rownum() {
	return 'ROWNUM';
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
	return q($q)->fetch(PDO::FETCH_NUM);
}

function db_sab($q)
{
	$obj = new stdClass();
	// We cannot use oci_fetch_object as it returns uppercase class variables. 
	$tmp = q($q)->fetch(PDO::FETCH_OBJ);
	if (!$tmp) return NULL;
	foreach ($tmp as $key => $val) {
		$obj->{strtolower($key)} = $val;
	}
	return $obj;
}

function db_qid($q)
{
	$r = q($q);

	$seqname = preg_replace('/.*INSERT\s+INTO\s+(\w+)[\s\(\,].*$/si', '\\1', $q) .'_id_seq';
	return q_singleval('SELECT '. $seqname .'.CURRVAL from dual');
}

function db_arr_assoc($q)
{
	$tmp = q($q)->fetch(PDO::FETCH_ASSOC);
	if (!$tmp) return NULL;

	// Change Oracle's UPPERCASE keys to lowercase.
	return array_change_key_case($tmp, CASE_LOWER);
}

function db_fetch_array($q)
{
	$tmp = $q->fetch(PDO::FETCH_ASSOC);
	if (!$tmp) return NULL;

	// Change Oracle's UPPERCASE keys to lowercase.
	return array_change_key_case($tmp, CASE_LOWER);
}

function db_li($q, &$ef, $li=0)
{
	$r = db::$db->exec(_ora_syntax($q));

	if ($r) {
		if ($li) {
			$seqname = preg_replace('/.*INSERT\s+INTO\s+(\w+)[\s\(\,].*$/si', '\\1', $q) .'_id_seq';
			return q_singleval('SELECT '. $seqname .'.CURRVAL FROM dual');
		}
		return $r;
	}

	/* Ignore duplicate keys: ORA-00001: unique constraint violated. */
	$err = db::$db->errorInfo();
	if ( $err[1] == 1 ) {
		return null;
	} else {
		fud_sql_error_handler($q, $err['2'], $err['1'], db_version());
	}
}

function ins_m($tbl, $flds, $types, $vals)
{
	$sth = db::$db->prepare('INSERT INTO '. $tbl .' ('. $flds .') VALUES(?'.str_repeat(',?',substr_count($flds, ',')) .')');
	foreach ($vals as $v) {
		if (!$sth->execute(explode(',', trim($v, '\'')))) {
			continue;
		}
	}
}

function db_all($q)
{
	return db::$db->query($q)->fetchAll(PDO::FETCH_COLUMN);
}

function _esc($s)
{
	return db::$db->quote($s);
}

function _ora_syntax($q)
{
	// SELECT without FROM, we need to append 'FROM dual'.
	if (preg_match('/^SELECT /i', $q) && !preg_match('/FROM/i', $q)) {
		$q .= ' FROM dual';
	}

	return $q;
}

/* Connect to DB. */
if (empty(db::$db)) {
	$dsn = 'oci:dbname='. $GLOBALS['DBHOST_DBNAME'];
	$opts = $GLOBALS['FUD_OPT_1'] & 256 ? array(PDO::ATTR_PERSISTENT=>true) : array();

	try {
		db::$db = new PDO($dsn, $GLOBALS['DBHOST_USER'], $GLOBALS['DBHOST_PASSWORD'], $opts);
	} catch (PDOException $e) {
		fud_sql_error_handler('Failed to establish database connection', 'PDO says: '. $e->getMessage(), '', '');
	}

	define('__dbtype__', 'oracle');
}

?>
