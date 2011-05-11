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

	// Need this because pgsql can't handle lock aliases.
	$tbl = array();
	foreach (explode(',', str_replace(' WRITE', '', $tables)) as $v) {
		if (!($v = trim($v))) continue;
		$tbl[] = strtok($v, ' ');
	}

	db::$db->beginTransaction();
	q('LOCK TABLE '. implode(',', $tbl) .' IN ACCESS EXCLUSIVE MODE');

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
		if (!strncasecmp($query, 'SELECT', 6)) {
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

function q_concat($args)
{
	$args = func_get_args();
	return implode('::text ||', $args); 
}

function q_rownum() {
	q('CREATE TEMPORARY SEQUENCE rnum');
	return 'nextval(\'rnum\')';
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
	$p = strpos($q, $GLOBALS['DBHOST_TBL_PREFIX']);
	$e = strcspn($q, "\r\n\t (", $p);
	return q_singleval('SELECT currval(\''. substr($q, $p, $e) .'_id_seq\')');
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
	// PgSQL will fail the entire tran if dup val error is encountered.
	if (!empty($GLOBALS['__DB_INC_INTERNALS__']['db_locked'])) {	// In a transaction.
	      db::$db->exec('SAVEPOINT pre_db_li');
	}

	$r = db::$db->exec($q);

	if ($r !== false) {
		if (!$li) {
			return $r;
		}
		$p = strpos($q, $GLOBALS['DBHOST_TBL_PREFIX']);
		$e = strcspn($q, "\r\n\t (", $p);
		return db::$db->lastInsertId(substr($q, $p, $e) .'_id_seq');
	}

	/* Ignore: 23000=integrity_constraint_violation and 23505=unique_violation. */
	if (($c = db::$db->errorCode()) == '23000' || $c == '23505') {
		$ef = ltrim(strrchr(__enifo(db::$db->errorInfo()), ' '));
		if (!empty($GLOBALS['__DB_INC_INTERNALS__']['db_locked'])) {	// In a transaction.
			db::$db->exec('ROLLBACK TO pre_db_li');
		}
		return null;
	} else {
		fud_sql_error_handler($q, __enifo(db::$db->errorInfo()), db::$db->errorCode(), db_version());
	}
}

function ins_m($tbl, $flds, $types, $vals)
{
	$sth = db::$db->prepare('INSERT INTO '. $tbl .' ('. $flds .') VALUES(?'.str_repeat(',?',substr_count($flds, ',')) .')');
	foreach ($vals as $v) {
		if (!empty($GLOBALS['__DB_INC_INTERNALS__']['db_locked'])) db::$db->exec('SAVEPOINT pre_ins_m');
		if (!$sth->execute(explode(',', trim($v, '\'')))) {
			if (!empty($GLOBALS['__DB_INC_INTERNALS__']['db_locked'])) db::$db->exec('ROLLBACK TO pre_ins_m');
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

/* Connect to DB. */
if (empty(db::$db)) {
	$dsn = 'pgsql:';
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
	$opts = $GLOBALS['FUD_OPT_1'] & 256 ? array(PDO::ATTR_PERSISTENT=>true) : array();

	try {
		db::$db = new PDO($dsn, $GLOBALS['DBHOST_USER'], $GLOBALS['DBHOST_PASSWORD'], $opts);
	} catch (PDOException $e) {
		fud_sql_error_handler('Failed to establish database connection', 'PDO says: '. $e->getMessage(), '', '');
	}

	define('__dbtype__', substr($GLOBALS['DBHOST_DBTYPE'], 4));
}

?>
