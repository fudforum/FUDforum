<?php
/**
* copyright            : (C) 2001-2023 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id$
*
* This program is free software; you can redistribute it and/or modify it 
* under the terms of the GNU General Public License as published by the 
* Free Software Foundation; version 2 of the License. 
**/
# define('fud_query_stats', 1);

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
	}
	
	db::$db->beginTransaction();

	$GLOBALS['__DB_INC_INTERNALS__']['db_locked'] = 1;
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

	// Clear result set to prevent COMMIT from failing with "There is already an active transaction".
	// To simulate, remove below line and post a reply to any existing topic.
	db::$res = null;

	db::$db->commit();

	unset($GLOBALS['__DB_INC_INTERNALS__']['db_locked']);
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
		if (!strncasecmp($query, 'SELECT', 6) || !strncasecmp($query, 'PRAGMA', 6)) {
			db::$res = null;
			db::$res = db::$db->query($query) or fud_sql_error_handler($query, __enifo(db::$db->errorInfo()), db::$db->errorCode(), db_version());
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
		if (!strncasecmp($query, 'SELECT', 6) || !strncasecmp($query, 'PRAGMA', 6)) {
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
			db::$res = db::$db->query($query) or fud_sql_error_handler($query, __enifo(db::$db->errorInfo()), db::$db->errorCode(), db_version());
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
	return $result->fetch(PDO::FETCH_OBJ);
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
	return $query .' LIMIT '. $limit .' OFFSET '. $off;
}

function q_concat($arg)
{
	$tmp = func_get_args();
	return implode('||', $tmp);
}

function q_rownum() {
	if (!function_exists('sqlite_rownum')) {
	 	function sqlite_rownum() {
			return ++$_SESSION['SQLITE_ROWNUM'];
		}
		db::$db->sqliteCreateFunction('rownum', 'sqlite_rownum');
	}
	$_SESSION['SQLITE_ROWNUM'] = 0;
	return 'rownum()';
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
	return q($q)->fetch(PDO::FETCH_NUM);
}

function db_sab($q)
{
	return q($q)->fetch(PDO::FETCH_OBJ);
}

function db_qid($q)
{
	q($q);
	return db::$db->lastInsertId();
}

function db_arr_assoc($q)
{
	return q($q)->fetch(PDO::FETCH_ASSOC);
}

function db_fetch_array($q)
{
	return is_object($q) ? $q->fetch(PDO::FETCH_ASSOC) : null;
}

function db_li($q, &$ef, $li=0)
{
	$r = db::$db->exec($q);
	
	if ($r !== false) {
		if (!$li) {
			return $r;
		}
		return db::$db->lastInsertId();
	}

	/* Duplicate key. */
	if (($c = db::$db->errorCode()) == '23000' || $c == '23505') {
		/* XXX unknown?? */
		return null;
	} else {
		fud_sql_error_handler($q, __enifo(db::$db->errorInfo()), db::$db->errorCode(), db_version());
	}
}

function ins_m($tbl, $flds, $types, $vals)
{
	$sth = db::$db->prepare('INSERT OR IGNORE INTO '. $tbl .' ('. $flds .') VALUES(?'. str_repeat(',?',substr_count($flds, ',')) .')');
	foreach ($vals as $v) {
		if (!$sth->execute(explode(',', trim($v,'\'')))) {
			// Note the use of undocumented $sth->queryString.
			fud_sql_error_handler($sth->queryString, __enifo(db::$db->errorInfo()), db::$db->errorCode(), db_version());
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

/* Connect to DB. */
if (empty(db::$db)) {
	$dsn = 'sqlite:'. $GLOBALS['DBHOST'];
	$opts = $GLOBALS['FUD_OPT_1'] & 256 ? array(PDO::ATTR_PERSISTENT=>true) : array();

	try {
		db::$db = new PDO($dsn, $GLOBALS['DBHOST_USER'], $GLOBALS['DBHOST_PASSWORD'], $opts);
	} catch (PDOException $e) {
		fud_sql_error_handler('Failed to establish database connection', 'PDO says: '. $e->getMessage(), '', '');
	}

	// Use ERRMODE_SILENT so we can check error codes manually.
	db::$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT);

	define('__dbtype__', substr($GLOBALS['DBHOST_DBTYPE'], 4));

	// SQLite optimization (dangerous, but very fast!).
	if (defined('SQLITE_FAST_BUT_WRECKLESS')) {
		q('PRAGMA temp_store=MEMORY');
		q('PRAGMA journal_mode=MEMORY');
		q('PRAGMA synchronous=OFF');
		q('PRAGMA count_changes=OFF');
	}
}

?>
