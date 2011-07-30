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

class db { public static $db, $slave, $affected_rows, $res; }

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
	q('LOCK TABLES '. $tables);

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

	q('UNLOCK TABLES');
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
	function uq($query, $buf=0)
	{
		// Assume master DB, route SELECT's to slave DB.
		// Force master if DB is locked (in transaction) or 'SELECT /* USE MASTER */'.
		$db = db::$db;
		if (!empty(db::$slave) && !db_locked() && !strncasecmp($query, 'SELECT', 6) && strncasecmp($query, 'SELECT /* USE MASTER */', 23)) {
			$db = db::$slave;
		}

		if (!strncasecmp($query, 'SELECT', 6) || !strncasecmp($query, 'SHOW', 4) || !strncasecmp($query, 'OPTIMIZE', 8) || !strncasecmp($query, 'SET', 3)) {
			db::$res = null;
			if ($buf) $db->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, 1);
			db::$res = $db->query($query) or fud_sql_error_handler($query, __enifo(db::$db->errorInfo()), db::$db->errorCode(), db_version());
			if ($buf) $db->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, 0);
			return db::$res;
		}

		db::$res = null;
		if ((db::$affected_rows = $db->exec($query)) === FALSE) {
			fud_sql_error_handler($query, __enifo($db->errorInfo()), $db->errorCode(), db_version());
		}
		return db::$affected_rows;
	}

	function q($query)
	{
		return uq($query, 1);
	}
} else {
	function uq($query, $buf=0)
	{
		if (!isset($GLOBALS['__DB_INC_INTERNALS__']['query_count'])) {
			$GLOBALS['__DB_INC_INTERNALS__']['query_count'] = 1;
		} else {
			++$GLOBALS['__DB_INC_INTERNALS__']['query_count'];
		}

		if (!isset($GLOBALS['__DB_INC_INTERNALS__']['total_sql_time'])) {
			$GLOBALS['__DB_INC_INTERNALS__']['total_sql_time'] = 0;
		}

		// Assume master DB, route SELECT's to slave DB.
		// Force master if DB is locked (in transaction) or 'SELECT /* USE MASTER */'.
		$db = db::$db;
		if (!empty(db::$slave) && !db_locked() && !strncasecmp($query, 'SELECT', 6) && strncasecmp($query, 'SELECT /* USE MASTER */', 23)) {
			$db = db::$slave;
		}

		if (!strncasecmp($query, 'SELECT', 6) || !strncasecmp($query, 'SHOW', 4)) {
			$s = microtime(true);
			db::$res = null;
			if ($buf) $db->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, 1);
			db::$res = $db->query($query) or fud_sql_error_handler($query, __enifo($db->errorInfo()), $db->errorCode(), db_version());
			if ($buf) $db->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, 0);
			$e = microtime(true);

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
		if ((db::$affected_rows = $db->exec($query)) === FALSE) {
			fud_sql_error_handler($query, __enifo($db->errorInfo()), $db->errorCode(), db_version());
		}
		return db::$affected_rows;
	}

	function q($query)
	{
		return uq($query, 1);
	}
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
		$ef = ltrim(strrchr(__enifo(db::$db->errorInfo()), ' '));
		return null;
	} else {
		fud_sql_error_handler($q, __enifo(db::$db->errorInfo()), db::$db->errorCode(), db_version());
	}
}

function ins_m($tbl, $flds, $types, $vals)
{
	return q('INSERT IGNORE INTO '. $tbl .' ('. $flds .') VALUES ('. implode('),(', $vals). ')');
}

function db_all($q)
{
	return uq($q)->fetchAll(PDO::FETCH_COLUMN);
}

function _esc($s)
{
	return db::$db->quote($s);
}

/* Connect to DB. */
if (empty(db::$db)) {
	if ($GLOBALS['DBHOST']{0} == ':') {
		$host = 'unix_socket='. substr($GLOBALS['DBHOST'], 1);
	} else {
		$host = 'host='. $GLOBALS['DBHOST'];
	}

	$dsn = 'mysql:'. $host .';dbname='. $GLOBALS['DBHOST_DBNAME'];
	$opts = $GLOBALS['FUD_OPT_1'] & 256 ? array(PDO::ATTR_PERSISTENT=>true) : array();
	$opts[] = array(PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8 COLLATE utf8_unicode_ci');

	try {
		db::$db = new PDO($dsn, $GLOBALS['DBHOST_USER'], $GLOBALS['DBHOST_PASSWORD'], $opts);
	} catch (PDOException $e) {
		fud_sql_error_handler('Failed to establish database connection', 'PDO says: '. $e->getMessage(), '', '');
	}

	/* Connect to slave, if specified. */
	if (!empty($GLOBALS['DBHOST_SLAVE_HOST']) && !$GLOBALS['is_post']) {
		try {
			db::$slave = new PDO($dsn, $GLOBALS['DBHOST_USER'], $GLOBALS['DBHOST_PASSWORD'], $opts);
		} catch (PDOException $e) {
			fud_logerror('Unable to init SlaveDB, fallback to MasterDB: '. $e->getMessage(), 'sql_errors');
		}
	}

	define('__dbtype__', substr($GLOBALS['DBHOST_DBTYPE'], 4));
}

?>
