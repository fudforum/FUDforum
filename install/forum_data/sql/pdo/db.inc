<?php
/**
* copyright            : (C) 2001-2004 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: db.inc,v 1.3 2005/06/28 01:01:10 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it 
* under the terms of the GNU General Public License as published by the 
* Free Software Foundation; either version 2 of the License, or 
* (at your option) any later version.
**/
define('forum_debug', 1);
function fud_sql_error_handler($query, $error_string, $error_number, $server_version)
{
	if (db_locked()) {
		if ((__dbtype__ == 'mysql' && $query != 'UNLOCK TABLES') || (__dbtype__ == 'pgsql' && $query != 'COMMIT WORK')) {
			db_unlock();
		}
	}

	$_SERVER['PATH_TRANSLATED'] = '';
	foreach (debug_backtrace() as $v) {
		$_SERVER['PATH_TRANSLATED'] .= "{$v['file']}:{$v['line']}<br />\n";
	}

	$error_msg = "(".$_SERVER['PATH_TRANSLATED'].") ".$error_number.": ".$error_string."<br />\n";
	$error_msg .= "Query: ".htmlspecialchars($query)."<br />\n";
	if (!empty($_GET)) {
		$error_msg .= "_GET: ";
		foreach ($_GET as $k => $v) {
			$error_msg .= "{$k}=".htmlspecialchars($v)."&";
		}
		$error_msg .= "<br />\n";
	}
	if (!empty($_POST)) {
		$error_msg .= "_POST: ";
		foreach ($_POST as $k => $v) {
			$error_msg .= "{$k}=".htmlspecialchars($v)."&";
		}
		$error_msg .= "<br />\n";
	}
	$error_msg .= "Server Version: ".$server_version."<br />\n";
	if (isset($_SERVER['HTTP_REFERER'])) {
		$error_msg .= "[Referring Page] ".htmlspecialchars($_SERVER['HTTP_REFERER'])."<br />\n";
	}

	if (!error_log('['.gmdate("D M j G:i:s T Y", __request_timestamp__).'] '.base64_encode($error_msg)."\n", 3, $GLOBALS['ERROR_PATH'].'sql_errors')) {
		echo "<b>UNABLE TO WRITE TO SQL LOG FILE</b><br>\n";
		echo $error_msg;
	} else {
		if (defined('forum_debug') || (isset($GLOBALS['usr']->users_opt) && $GLOBALS['usr']->users_opt & 1048576)) {
			echo $error_msg;
		} else {
			trigger_error('SQL Error has occurred, please contact the <a href="mailto:'.$GLOBALS['ADMIN_EMAIL'].'?subject=SQL%20Error">administrator</a> of the forum and have them review the forum&#39;s SQL query log', E_USER_ERROR);
			if (ini_get('display_errors') !== 1) {
				exit('SQL Error has occurred, please contact the <a href="mailto:'.$GLOBALS['ADMIN_EMAIL'].'?subject=SQL%20Error">administrator</a> of the forum and have them review the forum&#39;s SQL query log');
			}
		}
	}
	exit;
}

class db { public static $db, $ar, $res; }

if (!defined('fud_sql_lnk')) {
	switch ($GLOBALS['DBHOST_DBTYPE']) {
		case 'pdo_mysql':
			if ($GLOBALS['DBHOST']{0} == ':') {
				$host = 'unix_socket='.substr($GLOBALS['DBHOST'], 1);
			} else {
				$host = 'host='.$GLOBALS['DBHOST'];
			}
		
			$dsn = 'mysql:'.$host.';dbname='.$GLOBALS['DBHOST_DBNAME'];
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
			break;
	}

	try {
		db::$db = new PDO($dsn, $GLOBALS['DBHOST_USER'], $GLOBALS['DBHOST_PASSWORD']);
	} catch (PDOException $e) {
		fud_sql_error_handler("Failed to establish database connection, PDO says: ".$e->getMessage(), '', '', '');
	}

	define('__dbtype__', substr($GLOBALS['DBHOST_DBTYPE'], 4));
	define('__FUD_SQL_CONCAT__', 'CONCAT');
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
			foreach (explode(',', str_replace(' WRITE', '', $tables)) as $k => $t) {
				$t = trim($t);
				if (($p = strpos($t, ' '))) {
					$tbls[$k] = substr($t, 0, $p);
				}
			}
			$tables = implode(',', $tbls);

			q('BEGIN WORK');
			q('LOCK TABLE '.$tables.' IN ACCESS EXCLUSIVE MODE');
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
	unset($GLOBALS['__DB_INC_INTERNALS__']['db_locked']);

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
		db::$res = NULL;
		if ($buf) db::$db->setAttribute(PDO_MYSQL_ATTR_USE_BUFFERED_QUERY, 1);
		db::$res = db::$db->query($query) or die (fud_sql_error_handler($query, db::$db->errorCode(), __enifo(db::$db->errorInfo()), get_version()));
		if ($buf) db::$db->setAttribute(PDO_MYSQL_ATTR_USE_BUFFERED_QUERY, 0);
		return db::$res;
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

function &db_rowobj($result)
{
	return $result->fetch(PDO_FETCH_OBJ);
}

function &db_rowarr($result)
{
	return $result->fetch(PDO_FETCH_NUM);
}

function q_singleval($query)
{
	return q($query)->fetchColumn();
}

function qry_limit($limit, $off)
{
	switch (__dbtype__) {
		case 'mysql':
			return $off.','.$limit;

		case 'pgsql':
		case 'sqlite':
			return $limit.' OFFSET '.$off;
	}
}

function __all_pdo($array)
{
	$out = array();
	foreach ($array as $v) {
		$out[] = $v[0];
	}
	return $out;
}

function get_fud_table_list($tbl='')
{
	if (!$tbl) {
		$idx = str_replace("_", "\\_", $GLOBALS['DBHOST_TBL_PREFIX']);
	}

	db::$res = NULL;

	switch (__dbtype__) {
		case 'mysql':
			if (!$tbl) {
				return __all_pdo(db::$db->query("SHOW TABLES LIKE '".$idx."%'")->fetchAll(PDO_FETCH_NUM));
			}
			return q_singleval("SHOW TABLES LIKE '".str_replace("_", "\\_", $tbl)."'");

		case 'pgsql':
			if (!$tbl) {
				return __all_pdo(db::$db->query("SELECT relname FROM pg_class WHERE relkind='r' AND relname LIKE '".$idx."%'")->fetchAll(PDO_FETCH_NUM));
			}
			return q_singleval("SELECT relname FROM pg_class WHERE relkind='r' AND relname='".$tbl."'");

		case 'sqlite':
			if (!$tbl) {
				return __all_pdo(db::$db->query("SELECT tbl_name FROM sqlite_master WHERE type='table' AND  name LIKE '".$idx."%'")->fetchAll(PDO_FETCH_NUM));
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
			q('OPTIMIZE TABLE '. implode(', ', $tbl_list));
			break;

		case 'pgsql':
			foreach ($tbl_list as $t) { q('VACUUM ANALYZE ' . $t); }
			break;

		case 'sqlite':
			q("VACUUM");
			break;
	}
}

function &db_saq($q)
{
	return q($q)->fetch(PDO_FETCH_NUM);
}

function db_sab($q)
{
	return q($q)->fetch(PDO_FETCH_OBJ);
}

function db_qid($q)
{
	q($q);
	switch (__dbtype__) {
		case 'mysql':
		case 'sqlite':
			return db::$db->lastInsertId();

		case 'pgsql':
			// INSERT INTO 
			$tbl = substr($q, 12, strpos($q, ' ', 13) - 12);
			return q_singleval('SELECT id FROM '.$tbl.' WHERE oid='.db::$db->lastInsertId());
	}
}

function &db_arr_assoc($q)
{
	return q($q)->fetch(PDO_FETCH_ASSOC);
}

function db_li($q, &$ef, $li=0)
{
	db::$res = NULL;
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
				// INSERT INTO 
				$tbl = substr($q, 12, strpos($q, ' ', 13) - 12);
				return q_singleval('SELECT id FROM '.$tbl.' WHERE oid='.db::$db->lastInsertId());
		}
	}

	/* duplicate key */
	if (db::$db->errorCode() == '23000') {
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

function ins_m($tbl, $flds, $vals, $type=0)
{
	if (__dbtype__ == 'mysql') {
		return q("INSERT IGNORE INTO ".$tbl." (".$flds.") VALUES (".implode('),(', $vals).")");
	}
				
	$sth = db::$db->prepare('INSERT INTO '.$tbl.' ('.$flds.') VALUES(?'.str_repeat(',?',substr_count($flds, ',')).')');
	$err = error_reporting(0);
	foreach ($vals as $v) {
		$sth->execute(explode(',', $v));
	}
	error_reporting($err);
}
?>