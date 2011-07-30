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

class db { public static $db, $slave; }

if (empty(db::$db)) {
	if (substr($GLOBALS['DBHOST'], 0, 1) == ':') {	// Socket connection.
		$socket = substr($GLOBALS['DBHOST'], 1);
		$GLOBALS['DBHOST'] = 'localhost';
	} else {
		$socket = NULL;
	}

	if ($GLOBALS['FUD_OPT_1'] & 256 && version_compare(PHP_VERSION, '5.3.0', '>=')) {	// Enable pconnect for PHP 5.3+.
		$GLOBALS['DBHOST'] .= 'p:';
	}

	db::$db = new mysqli($GLOBALS['DBHOST'], $GLOBALS['DBHOST_USER'], $GLOBALS['DBHOST_PASSWORD'], $GLOBALS['DBHOST_DBNAME'], NULL, $socket);
	if (mysqli_connect_errno()) {
		fud_sql_error_handler('Failed to establish database connection', 'MySQLi says: '. mysqli_connect_error(), mysqli_connect_errno(), '');
	}
	db::$db->set_charset('utf8');

	/* Connect to slave, if specified. */
	if (!empty($GLOBALS['DBHOST_SLAVE_HOST']) && !$GLOBALS['is_post']) {
		db::$slave = new mysqli($GLOBALS['DBHOST'], $GLOBALS['DBHOST_USER'], $GLOBALS['DBHOST_PASSWORD'], $GLOBALS['DBHOST_DBNAME'], NULL, $socket);
		if (mysqli_connect_errno()) {
			fud_logerror('Unable to init SlaveDB, fallback to MasterDB: '. mysqli_connect_error(), 'sql_errors');
		} else {
			db::$db->set_charset('utf8');
		}
	}

	define('__dbtype__', 'mysql');
}

function db_close()
{
	 db::$db->close();
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
		q('LOCK TABLES '. $tables);
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
	return db::$db->affected_rows;
}

function uq($query)
{
	return q($query);
}

if (!defined('fud_query_stats')) {
	function q($query)
	{
		// Assume master DB, route SELECT's to slave DB.
		// Force master if DB is locked (in transaction) or 'SELECT /* USE MASTER */'.
		$db = db::$db;
		if (!empty(db::$slave) && !db_locked() && !strncasecmp($query, 'SELECT', 6) && strncasecmp($query, 'SELECT /* USE MASTER */', 23)) {
			$db = db::$slave;
		}

		$r = $db->query($query);
		if ($db->error) {
			fud_sql_error_handler($query, $db->error, $db->errno, db_version());
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

		// Assume master DB, route SELECT's to slave DB.
		// Force master if DB is locked (in transaction) or 'SELECT /* USE MASTER */'.
		$db = db::$db;
		if (!empty(db::$slave) && !db_locked() && !strncasecmp($query, 'SELECT', 6) && strncasecmp($query, 'SELECT /* USE MASTER */', 23)) {
			$db = db::$slave;
		}

		$s = microtime(true);
		$result = $db->query($query);
		if ($db->error) {
			fud_sql_error_handler($query, $db->error, $db->errno, db_version());
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
	return $result->fetch_object();
}

function db_rowarr($result)
{
	return $result->fetch_row();
}

function q_singleval($query)
{
	$r = q($query);
	if (($result = $r->fetch_row()) !== false) {
		return $result[0];
	}
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
        return is_object($r) ? $r->fetch_array(MYSQLI_ASSOC) : null;
}

function db_li($q, &$ef, $li=0)
{
	$r = db::$db->query($q);
	if ($r) {
		return ($li ? db::$db->insert_id : $r);
	}

	/* Duplicate key. */
	if (db::$db->errno == 1062) {
		$ef = ltrim(strrchr(db::$db->error, ' '));
		return null;
	} else {
		fud_sql_error_handler($q, db::$db->error, db::$db->errno, db_version());
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
	while ($r = $c->fetch_row()) {
		$f[] = $r[0];
	}
	return $f;
}

function _esc($s)
{
	return '\''. db::$db->real_escape_string($s) .'\'';
}
?>
