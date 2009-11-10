<?php
/**
* copyright            : (C) 2001-2009 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id$
*
* This program is free software; you can redistribute it and/or modify it 
* under the terms of the GNU General Public License as published by the 
* Free Software Foundation; version 2 of the License. 
**/
# define('forum_debug', 1);

class db { public static $db, $ar, $res, $buf; }

if (empty(db::$db)) {
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

	try {
		db::$db = new PDO($dsn, $GLOBALS['DBHOST_USER'], $GLOBALS['DBHOST_PASSWORD']);
	} catch (PDOException $e) {
		fud_sql_error_handler("Failed to establish database connection, PDO says: ".$e->getMessage(), '', '', '');
	}

	define('__dbtype__', substr($GLOBALS['DBHOST_DBTYPE'], 4));
}

function get_version()
{
	if (!defined('__FUD_SQL_VERSION__')) {
		define('__FUD_SQL_VERSION__', db::$db->getAttribute(PDO::ATTR_SERVER_VERSION));
	}
	return __FUD_SQL_VERSION__;
}

function db_lock($tables)
{
	if (!empty($GLOBALS['__DB_INC_INTERNALS__']['db_locked'])) {
		fud_sql_error_handler("Recursive Lock", "internal", "internal", get_version());
	}
	
	// need this because pgsql can't handle lock aliases
	$tbl = array();
	foreach (explode(',', str_replace(' WRITE', '', $tables)) as $v) {
		if (!($v = trim($v))) continue;
		$tbl[] = strtok($v, ' ');
	}
	q('BEGIN WORK');
	q('LOCK TABLE '.implode(',', $tbl).' IN ACCESS EXCLUSIVE MODE');
	
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

	q('COMMIT WORK');
	
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
	if (!strncasecmp($query, 'SELECT', 6)) {
		db::$res = null;
		db::$buf = $buf;
		db::$res = db::$db->query($query) or fud_sql_error_handler($query, db::$db->errorCode(), __enifo(db::$db->errorInfo()), get_version());
		return db::$res;
	}
	if (!db::$buf) {
		db::$res = null;
	}
	if ((db::$ar = db::$db->exec($query)) === FALSE) {
		fud_sql_error_handler($query, db::$db->errorCode(), __enifo(db::$db->errorInfo()), get_version());
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
	return $result->fetch(PDO::FETCH_OBJ);
}

function db_rowarr($result)
{
	return $result->fetch(PDO::FETCH_NUM);
}

function q_singleval($query)
{
	return uq($query)->fetchColumn();
}

function qry_limit($limit, $off)
{
	return $limit.' OFFSET '.$off;
}

function get_fud_table_list($tbl='')
{
	if (!$tbl) {
		$idx = str_replace("_", "\\_", $GLOBALS['DBHOST_TBL_PREFIX']);
	}

	db::$buf = db::$res = null;

	if (!$tbl) {
		return db::$db->query("SELECT relname FROM pg_class WHERE relkind='r' AND relname LIKE '".$idx."%'")->fetchAll(PDO::FETCH_COLUMN);
	}
	return q_singleval("SELECT relname FROM pg_class WHERE relkind='r' AND relname='".$tbl."'");
}

function optimize_tables($tbl_list=null)
{
	if (!$tbl_list) {
		$tbl_list = get_fud_table_list();
	}

	foreach ($tbl_list as $t) { 
		uq('VACUUM ANALYZE ' . $t); 
	}
}

function db_saq($q)
{
	return uq($q)->fetch(PDO::FETCH_NUM);
}

function db_sab($q)
{
	return uq($q)->fetch(PDO::FETCH_OBJ);
}

function db_qid($q)
{
	uq($q);
	$p = strpos($q, $GLOBALS['DBHOST_TBL_PREFIX']);
	$e = strcspn($q, "\r\n\t (", $p);
	return q_singleval("SELECT currval('".substr($q, $p, $e)."_id_seq')");
}

function db_arr_assoc($q)
{
	return uq($q)->fetch(PDO::FETCH_ASSOC);
}

function db_fetch_array($q)
{
	return $q->fetch(PDO::FETCH_ASSOC);
}

function db_li($q, &$ef, $li=0)
{
	db::$buf = db::$res = null;
	$r = db::$db->exec($q);
	
	if ($r !== false) {
		if (!$li) {
			return $r;
		}
		$p = strpos($q, $GLOBALS['DBHOST_TBL_PREFIX']);
		$e = strcspn($q, "\r\n\t (", $p);
		return db::$db->lastInsertId(substr($q, $p, $e)."_id_seq");
	}

	/* duplicate key */
	if (($c = db::$db->errorCode()) == '23000' || $c == '23505') {
		$ef = ltrim(strrchr(__enifo(db::$db->errorInfo()), ' '));
		return null;
	} else {
		fud_sql_error_handler($q, db::$db->errorCode(), __enifo(db::$db->errorInfo()), get_version());
	}
}

function ins_m($tbl, $flds, $vals, $type=0, $strict=1)
{
	$sth = db::$db->prepare('INSERT INTO '.$tbl.' ('.$flds.') VALUES(?'.str_repeat(',?',substr_count($flds, ',')).')');
	foreach ($vals as $v) {
		if (!$sth->execute(explode(',', trim($v,"'"))) && $strict) {
			fud_sql_error_handler($sth->queryString, db::$db->errorCode(), __enifo(db::$db->errorInfo()), get_version());
		}
	}
}

function db_all($q)
{
	db::$buf = db::$res = null;
	return db::$db->query($q)->fetchAll(PDO::FETCH_COLUMN);
}

function _esc($s)
{
	return db::$db->quote($s);
}
?>
