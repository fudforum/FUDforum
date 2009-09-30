<?php
/**
// http://www.phpclasses.org/browse/file/16059.html

* copyright            : (C) 2001-2009 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: db.inc,v 1.2 2009/09/30 16:02:20 frank Exp $
*
* This program is free software; you can redistribute it and/or modify it 
* under the terms of the GNU General Public License as published by the 
* Free Software Foundation; version 2 of the License. 
**/
// define('fud_query_stats', 1);

if (!defined('fud_sql_lnk')) {
	$connect_func = $GLOBALS['FUD_OPT_1'] & 256 ? 'oci_pconnect' : 'oci_connect';	

	if (! extension_loaded('oci8')) {
		fud_sql_error_handler('None', '', 'We were unable to use an Oracle database because the OCI8 extension for PHP is not installed. Check your PHP.ini to see how it can be enabled', 'Unknown');
	}

	$conn = $connect_func($GLOBALS['DBHOST_USER'], $GLOBALS['DBHOST_PASSWORD'], $GLOBALS['DBHOST_DBNAME'] ) 
		or fud_sql_error_handler('Initiating '.$connect_func, oci_error($conn), '', 'Unknown');

	define('fud_sql_lnk', $conn);
	define('__dbtype__', 'oracle');
	define('__FUD_SQL_CONCAT__', 'textcat');
}

function get_version()
{
	if (!defined('__FUD_SQL_VERSION__')) {
		if (function_exists(oci_server_version)) {
			$val = oci_server_version($fud_sql_lnk);
		} else {
			$val = ociserverversion($fud_sql_lnk);
		}
		define('__FUD_SQL_VERSION__', ($val ? $val[0] : null));
	}
	return __FUD_SQL_VERSION__;
}

function db_lock($tables)
{
	// echo "DEBUG db_lock..............<br>";
	if (!empty($GLOBALS['__DB_INC_INTERNALS__']['db_locked'])) {
		fud_sql_error_handler("Recursive Lock", "internal", "internal", get_version());
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
	// echo "DEBUG db_unlock..............<br>";
	if (empty($GLOBALS['__DB_INC_INTERNALS__']['db_locked'])) {
		unset($GLOBALS['__DB_INC_INTERNALS__']['db_locked']);
		fud_sql_error_handler("DB_UNLOCK: no previous lock established", "internal", "internal", get_version());
	}

	if (--$GLOBALS['__DB_INC_INTERNALS__']['db_locked'] < 0) {
		unset($GLOBALS['__DB_INC_INTERNALS__']['db_locked']);
		fud_sql_error_handler("DB_UNLOCK: unlock overcalled", "internal", "internal", get_version());
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
	return oci_num_rows($GLOBALS['__FLPG__']);
}

function uq($q)
{
	return q($q);
}

if (!defined('fud_query_stats')) {
	function q($q)
	{
		$GLOBALS['__FLPG__'] = ora_query(fud_sql_lnk, $q) or fud_sql_error_handler($q, oci_error(fud_sql_lnk), '', get_version());
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

		$s = gettimeofday();
		$GLOBALS['__FLPG__'] = ora_query(fud_sql_lnk, $q) or fud_sql_error_handler($q, oci_error(fud_sql_lnk), '', get_version());
		$e = gettimeofday();
	
		$GLOBALS['__DB_INC_INTERNALS__']['last_time'] = ($e['sec'] - $s['sec'] + (($e['usec'] - $s['usec'])/1000000));
		$GLOBALS['__DB_INC_INTERNALS__']['total_sql_time'] += $GLOBALS['__DB_INC_INTERNALS__']['last_time'];
		$GLOBALS['__DB_INC_INTERNALS__']['last_query'] = $q;

		echo '<pre>'.preg_replace('!\s+!', ' ', $q).'</pre>';
		echo '<pre>query count: '.$GLOBALS['__DB_INC_INTERNALS__']['query_count'].' time taken: '.$GLOBALS['__DB_INC_INTERNALS__']['last_time'].'</pre>';
		echo '<pre>Affected rows: '.db_affected().'</pre>';
		echo '<pre>total sql time: '.$GLOBALS['__DB_INC_INTERNALS__']['total_sql_time'].'</pre>';

		return $GLOBALS['__FLPG__'];
	}
}

function db_count($r)
{
	$r = oci_num_rows($r);
	if ($r < 0) {
		return 0;
	}
	return $r;
}

function db_rowobj($result)
{
	return oci_fetch_object($result, OCI_RETURN_LOBS);
}

function db_rowarr($result)
{
	return oci_fetch_array($result, OCI_RETURN_LOBS+OCI_ASSOC);
}

function q_singleval($query)
{
	// echo "DEBUG q_singleval: Q=".$query."<br>\n";
	$val = oci_fetch_array(q($query), OCI_RETURN_LOBS+OCI_ASSOC);
	return $val ? $val[0] : null;
}

function qry_limit($limit, $off)
{
	return $limit.' OFFSET '.$off;
}

function get_fud_table_list($tbl='')
{
	if (!$tbl) {
		return db_all("SELECT table_name FROM user_tables WHERE table_name LIKE '".addcslashes($GLOBALS['DBHOST_TBL_PREFIX'],"_")."%'");
	} else {
		return q_singleval("SELECT table_name FROM user_tables WHERE table_name='".$tbl."'");
	}

	return $ret;
}

function optimize_tables($tbl_list=null)
{
	if (!$tbl_list) {
		$tbl_list = get_fud_table_list();
	}
	foreach ($tbl_list as $t) {
		q('ANALYZE TABLE  ' . $t.' COMPUTE STATISTICS');
	}
}

function db_saq($q)
{
	return oci_fetch_array(q($q), OCI_RETURN_LOBS+OCI_ASSOC);
}

function db_sab($q)
{
	return oci_fetch_object(q($q), OCI_RETURN_LOBS);
}

function db_qid($q)
{
	$r = q($q.' RETURNING id');
	//TODO return q_singleval('SELECT lastval()');
	// echo "DEBUG db_qid: q=".$q.", r=".$r."<br />\n";
	return $r;
}

function db_arr_assoc($q)
{
	return oci_fetch_array(q($q), 0, OCI_RETURN_LOBS+OCI_ASSOC);
}

function db_li($q, &$ef, $li=0)
{
	$id = 0;

	// echo "DEBUG db_li: q=".$q."<br />\n";
	$r = oci_parse(fud_sql_lnk, $q.' RETURNING id INTO :id');
	oci_bind_by_name($r, ":id", $id, -1, SQLT_INT);
	oci_execute($r);

	if ($r) {
		if ($li) {
			//TODO return q_singleval('SELECT lastval()');
			// echo "DEBUG db_li: q=".$q.", id=".$id."<br />\n";
			return $id;
		}
		return $r;
	}

	/* duplicate key */
	$ef = ltrim(strrchr(oci_error(fud_sql_lnk), ' '));
	return null;
}

function ins_m($tbl, $flds, $vals, $type)
{
	// echo "DEBUG ins_m: Q=".$tbl."<br/>\n";
	$pid = $GLOBALS['DBHOST_TBL_PREFIX'] . substr(md5(mt_rand()), 0, 10);

	ora_query(fud_sql_lnk, 'PREPARE '.$pid.' ('.$type.') AS INSERT INTO '.$tbl.' ('.$flds.') VALUES($'.implode(',$', range(1, substr_count($flds, ',') + 1)).')');

	$err = error_reporting(0);
	foreach ($vals as $v) {
		ora_query(fud_sql_lnk, 'EXECUTE '.$pid.' ('.$v.')');
	}
	error_reporting($err);
}

function db_all($q)
{
	if (!(oci_fetch_all(q($q), $r, OCI_RETURN_LOBS))) {
		return array();
	}
	$f = array();
	foreach ($r as $v) {
		$f[] = current($v);
	}
	return $f;
}

function _esc($s)
{
	// Trim first 4000 characters, based on Oracle VARCHAR2(4000) limitation.
	$s = preg_replace('/(.{0,4000})(.*)/', '\1', $s);

	// Replace any single ' with ''
	$s = str_replace('\'', '\'\'', $s);

	return '\''.$s.'\'';
}

function ora_query($fud_sql_lnk, $q)
{
	// LIMIT -> rownum
	$q = preg_replace('/LIMIT (\d+)/', 'AND ROWNUM <= \1', $q);

	// Convert MySQL's bitwise & to Oracle's BITAND() function
	$q = preg_replace('/\(([\.\w]+) \& (\d+)\)/', 'BITAND(\1, \2)', $q);

	// OR values together in PHP - there is no BITOR() in Oracle
	$q = preg_replace_callback('/\b(\d[\d\|]+\d\b)/',
			create_function('$matches',
				'$or=0; foreach( explode(\'|\', $matches[0]) as $val) {$or = $or|$val;} return $or;'),
			$q);

	// 
	// $query = 'SELECT * FROM (SELECT /*+ FIRST_ROWS */ rownum AS xrownum, a.* FROM (' . $query . ') a WHERE rownum <= ' . ($offset + $total) . ') WHERE xrownum >= ' . $offset;
			
	echo "<HR>DEBUG ora_query: Q=".$q."<br/>\n";

	$r = oci_parse($fud_sql_lnk, $q);
	oci_execute($r);
	return $r;
}

?>
