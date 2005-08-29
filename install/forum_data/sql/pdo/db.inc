<?php
/**
* copyright            : (C) 2001-2004 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: db.inc,v 1.21 2005/08/29 16:18:32 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it 
* under the terms of the GNU General Public License as published by the 
* Free Software Foundation; either version 2 of the License, or 
* (at your option) any later version.
**/
define('forum_debug', 1);

class db { public static $db, $ar, $res, $buf; }

if (!defined('fud_sql_lnk')) {
	switch ($GLOBALS['DBHOST_DBTYPE']) {
		case 'pdo_mysql':
			if ($GLOBALS['DBHOST']{0} == ':') {
				$host = 'unix_socket='.substr($GLOBALS['DBHOST'], 1);
			} else {
				$host = 'host='.$GLOBALS['DBHOST'];
			}
		
			$dsn = 'mysql:'.$host.';dbname='.$GLOBALS['DBHOST_DBNAME'];
			define('__FUD_SQL_CONCAT__', 'CONCAT');
			break;
		case 'pdo_sqlite':
			$dsn = 'sqlite:'.$GLOBALS['DBHOST'];
			break;
		case 'pdo_pgsql':
			$dsn = 'pgsql:';
			if ($GLOBALS['DBHOST']) {
				$dsn .= 'host='.$GLOBALS['DBHOST'];
			}
			if ($GLOBALS['DBHOST_USER']) {
				$dsn .= ' user='.$GLOBALS['DBHOST_USER'];
			}
			if ($GLOBALS['DBHOST_PASSWORD']) {
				$dsn .= ' password='.$GLOBALS['DBHOST_PASSWORD'];
			}
			if ($GLOBALS['DBHOST_DBNAME']) {
				$dsn .= ' dbname='.$GLOBALS['DBHOST_DBNAME'];
			}
			define('__FUD_SQL_CONCAT__', 'textcat');
			break;
	}

	try {
		db::$db = new PDO($dsn, $GLOBALS['DBHOST_USER'], $GLOBALS['DBHOST_PASSWORD']);
	} catch (PDOException $e) {
		fud_sql_error_handler("Failed to establish database connection, PDO says: ".$e->getMessage(), '', '', '');
	}

	define('__dbtype__', substr($GLOBALS['DBHOST_DBTYPE'], 4));

	/* special sqlite handling for lack of native concat function */
	if ($GLOBALS['DBHOST_DBTYPE'] == 'pdo_sqlite') {
		db::$db->sqliteCreateFunction('concat', 'pdo_concat');
		define('__FUD_SQL_CONCAT__', 'concat');
	}
}

function pdo_concat()
{
	$tmp = func_get_args();
	return implode('', $tmp);
}

function get_version()
{
	if (!defined('__FUD_SQL_VERSION__')) {
		define('__FUD_SQL_VERSION__', db::$db->getAttribute(PDO_ATTR_SERVER_VERSION));
	}
	return __FUD_SQL_VERSION__;
}

function db_lock($tables)
{
	if (!empty($GLOBALS['__DB_INC_INTERNALS__']['db_locked'])) {
		fud_sql_error_handler("Recursive Lock", "internal", "internal", get_version());
	}
	
	switch (__dbtype__) {
		case 'mysql':
			q('LOCK TABLES '.$tables);
			break;

		case 'pgsql':
			// need this because pgsql can't handle lock aliases
			$tbl = array();
			foreach (explode(',', str_replace(' WRITE', '', $tables)) as $v) {
				if (!($v = trim($v))) continue;
				$tbl[] = ($p = strpos($v, ' ')) !== false ? substr($v,0,$p) : $v;
			}
			q('BEGIN WORK');
			q('LOCK TABLE '.implode(',', $tbl).' IN ACCESS EXCLUSIVE MODE');
			break;

		case 'sqlite':
			q('BEGIN IMMEDIATE');
			break;
	}
	$GLOBALS['__DB_INC_INTERNALS__']['db_locked'] = 1;
}

function db_unlock()
{
	if (empty($GLOBALS['__DB_INC_INTERNALS__']['db_locked'])) {
		unset($GLOBALS['__DB_INC_INTERNALS__']['db_locked']);
		fud_sql_error_handler("DB_UNLOCK: no previous lock established", "internal", "internal", get_version());
	}
	
	if (--$GLOBALS['__DB_INC_INTERNALS__']['db_locked'] < 0) {
		unset($GLOBALS['__DB_INC_INTERNALS__']['db_locked']);
		fud_sql_error_handler("DB_UNLOCK: unlock overcalled", "internal", "internal", get_version());
	}

	switch (__dbtype__) {
		case 'mysql':
			q('UNLOCK TABLES');
			break;

		case 'pgsql':
			q('COMMIT WORK');
			break;

		case 'sqlite':
			q('COMMIT');
			break;
	}
	unset($GLOBALS['__DB_INC_INTERNALS__']['db_locked']);
}

function db_locked()
{
	return isset($GLOBALS['__DB_INC_INTERNALS__']['db_locked']);
}

function db_affected()
{
	return db::$ar;
}

function __enifo($a)
{
	return end($a);
}

function uq($query, $buf=0)
{
	if (__dbtype__ == 'sqlite') {
		$query = stripslashes($query);
	}

	if (!strncasecmp($query, 'SELECT', 6)) {
		db::$res = null;
		db::$buf = $buf;
		if ($buf && __dbtype__ == 'mysql') db::$db->setAttribute(PDO_MYSQL_ATTR_USE_BUFFERED_QUERY, 1);
		db::$res = db::$db->query($query) or die (fud_sql_error_handler($query, db::$db->errorCode(), __enifo(db::$db->errorInfo()), get_version()));
		if ($buf && __dbtype__ == 'mysql') db::$db->setAttribute(PDO_MYSQL_ATTR_USE_BUFFERED_QUERY, 0);
		return db::$res;
	}
	if (!db::$buf) {
		db::$res = null;
	}
	if ((db::$ar = db::$db->exec($query)) === FALSE) {
		die (fud_sql_error_handler($query, db::$db->errorCode(), __enifo(db::$db->errorInfo()), get_version()));
	}
	return db::$ar;
}

function q($query)
{
	return uq($query, 1);
}

function db_count($result)
{
	return (int) $result->rowCount();
}

function db_rowobj($result)
{
	return $result->fetch(PDO_FETCH_OBJ);
}

function db_rowarr($result)
{
	return $result->fetch(PDO_FETCH_NUM);
}

function q_singleval($query)
{
	return uq($query)->fetchColumn();
}

function qry_limit($limit, $off)
{
	if (__dbtype__ == 'mysql') {
		return $off.','.$limit;
	}
	return $limit.' OFFSET '.$off;
}

function get_fud_table_list($tbl='')
{
	if (!$tbl) {
		$idx = str_replace("_", "\\_", $GLOBALS['DBHOST_TBL_PREFIX']);
	}

	db::$buf = db::$res = null;

	switch (__dbtype__) {
		case 'mysql':
			if (!$tbl) {
				return db::$db->query("SHOW TABLES LIKE '".$idx."%'")->fetchAll(PDO_FETCH_COLUMN);
			}
			return q_singleval("SHOW TABLES LIKE '".str_replace("_", "\\_", $tbl)."'");

		case 'pgsql':
			if (!$tbl) {
				return db::$db->query("SELECT relname FROM pg_class WHERE relkind='r' AND relname LIKE '".$idx."%'")->fetchAll(PDO_FETCH_COLUMN);
			}
			return q_singleval("SELECT relname FROM pg_class WHERE relkind='r' AND relname='".$tbl."'");

		case 'sqlite':
			if (!$tbl) {
				return db::$db->query("SELECT tbl_name FROM sqlite_master WHERE type='table' AND  name LIKE '".$GLOBALS['DBHOST_TBL_PREFIX']."%'")->fetchAll(PDO_FETCH_COLUMN);
			}
			return q_singleval("SELECT tbl_name FROM sqlite_master WHERE type='table' AND name='".$tbl."'");
	}
}

function optimize_tables($tbl_list=null)
{
	if (!$tbl_list) {
		$tbl_list = get_fud_table_list();
	}

	switch (__dbtype__) {
		case 'mysql':
			uq('OPTIMIZE TABLE '. implode(', ', $tbl_list));
			break;

		case 'pgsql':
			foreach ($tbl_list as $t) { uq('VACUUM ANALYZE ' . $t); }
			break;

		case 'sqlite':
			uq("VACUUM");
			break;
	}
}

function db_saq($q)
{
	return uq($q)->fetch(PDO_FETCH_NUM);
}

function db_sab($q)
{
	return uq($q)->fetch(PDO_FETCH_OBJ);
}

function db_qid($q)
{
	uq($q);
	switch (__dbtype__) {
		case 'mysql':
		case 'sqlite':
			return db::$db->lastInsertId();

		case 'pgsql':
			$p = strpos($q, $GLOBALS['DBHOST_TBL_PREFIX']);
			$e = strcspn($q, "\r\n\t (", $p);
			return q_singleval("SELECT currval('".substr($q, $p, $e)."_id_seq')");
	}
}

function db_arr_assoc($q)
{
	return uq($q)->fetch(PDO_FETCH_ASSOC);
}

function db_li($q, &$ef, $li=0)
{
	db::$buf = db::$res = null;
	$r = db::$db->exec($q);
	
	if ($r) {
		if (!$li) {
			return $r;
		}
		switch (__dbtype__) {
			case 'mysql':
			case 'sqlite':
				return db::$db->lastInsertId();

			case 'pgsql':
				$p = strpos($q, $GLOBALS['DBHOST_TBL_PREFIX']);
				$e = strcspn($q, "\r\n\t (", $p);
				return db::$db->lastInsertId(substr($q, $p, $e)."_id_seq");
		}
	}

	/* duplicate key */
	if (($c = db::$db->errorCode()) == '23000' || $c == '23505') {
		switch (__dbtype__) {
			case 'mysql':
			case 'pgsql':
				$ef = ltrim(strrchr(__enifo(db::$db->errorInfo()), ' '));
				break;

			case 'sqlite':
				/* XXX unknown?? */
				break;
		}
		return null;
	} else {
		die(fud_sql_error_handler($q, db::$db->errorCode(), __enifo(db::$db->errorInfo()), get_version()));
	}
}

function ins_m($tbl, $flds, $vals, $type=0, $strict=1)
{
	if (__dbtype__ == 'mysql') {
		return uq("INSERT IGNORE INTO ".$tbl." (".$flds.") VALUES (".implode('),(', $vals).")");
	}
				
	$sth = db::$db->prepare('INSERT INTO '.$tbl.' ('.$flds.') VALUES(?'.str_repeat(',?',substr_count($flds, ',')).')');
	foreach ($vals as $v) {
		if (!$sth->execute(explode(',', trim($v,"'"))) && $strict) {
			die(fud_sql_error_handler($sth->queryString, db::$db->errorCode(), __enifo(db::$db->errorInfo()), get_version()));
		}
	}
}

function db_all($q)
{
	db::$buf = db::$res = null;
	return db::$db->query($q)->fetchAll(PDO_FETCH_COLUMN);
}
?>