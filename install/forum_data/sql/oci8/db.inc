<?php
/**
* copyright            : (C) 2001-2010 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id$
*
* This program is free software; you can redistribute it and/or modify it 
* under the terms of the GNU General Public License as published by the 
* Free Software Foundation; version 2 of the License. 
**/
// define('fud_query_stats', 1);

if (!defined('fud_sql_lnk')) {
	$connect_func = $GLOBALS['FUD_OPT_1'] & 256 ? 'oci_pconnect' : 'oci_connect';	

	if (! extension_loaded('oci8')) {
		fud_sql_error_handler('Loading driver', 'We were unable to use an Oracle database because the OCI8 extension for PHP is not installed. Check your PHP.ini to see how it can be enabled', '', 'Unknown');
	}

	$conn = $connect_func($GLOBALS['DBHOST_USER'], $GLOBALS['DBHOST_PASSWORD'], $GLOBALS['DBHOST_DBNAME'] );
	if (!$conn) {
		$err = oci_error();
		fud_sql_error_handler('Initiating '. $connect_func, $err['message'], $err['code'], 'Unknown');
	}

	define('fud_sql_lnk', $conn);
	define('__dbtype__', 'oracle');
}

function db_version()
{
	if (!defined('__FUD_SQL_VERSION__')) {
		if (function_exists('oci_server_version')) {
			$val = oci_server_version(fud_sql_lnk);
		} else {
			$val = ociserverversion(fud_sql_lnk);
		}
		if (preg_match('/([0-9]+\.([0-9\.])+)/', $val, $ver)) {
			define('__FUD_SQL_VERSION__', $ver[1]);
		} else {
			define('__FUD_SQL_VERSION__', 'unknown');
		}
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
		$c = oci_parse(fud_sql_lnk, _ora_syntax($q));
		$GLOBALS['__FLPG__'] = @oci_execute($c);
		if ($GLOBALS['__FLPG__'] === FALSE) {
			$err = oci_error($c);
			fud_sql_error_handler($err['sqltext'], $err['message'], $err['code'], db_version());
		}
		return $c;
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
		$c = oci_parse(fud_sql_lnk, _ora_syntax($q));
		$GLOBALS['__FLPG__'] = @oci_execute($c);
		if ($GLOBALS['__FLPG__'] === FALSE) {
			$err = oci_error($c);
			fud_sql_error_handler($err['sqltext'], $err['message'], $err['code'], db_version());
		}
		$e = gettimeofday();

		$GLOBALS['__DB_INC_INTERNALS__']['last_time'] = ($e['sec'] - $s['sec'] + (($e['usec'] - $s['usec'])/1000000));
		$GLOBALS['__DB_INC_INTERNALS__']['total_sql_time'] += $GLOBALS['__DB_INC_INTERNALS__']['last_time'];
		$GLOBALS['__DB_INC_INTERNALS__']['last_query'] = $q;

		echo '<pre>'.preg_replace('!\s+!', ' ', $q).'</pre>';
		echo '<pre>query count: '.$GLOBALS['__DB_INC_INTERNALS__']['query_count'].' time taken: '.$GLOBALS['__DB_INC_INTERNALS__']['last_time'].'</pre>';
		echo '<pre>Affected rows: '.db_affected().'</pre>';
		echo '<pre>total sql time: '.$GLOBALS['__DB_INC_INTERNALS__']['total_sql_time'].'</pre>';

		return $c;
	}
}

function db_rowobj($result)
{
	$obj = new stdClass();
	// We cannot use oci_fetch_object as it returns uppercase class variables. 
	$tmp = oci_fetch_array($result, OCI_RETURN_LOBS+OCI_RETURN_NULLS+OCI_ASSOC);
	if (!$tmp) return NULL;
	foreach ($tmp as $key => $val) {
		$obj->{strtolower($key)} = $val;
	}
	return $obj;
}

function db_rowarr($result)
{
	return oci_fetch_array($result, OCI_RETURN_LOBS+OCI_RETURN_NULLS+OCI_NUM);
}

function q_singleval($query)
{
	$val = oci_fetch_array(q($query), OCI_RETURN_LOBS+OCI_RETURN_NULLS);
	return $val ? $val[0] : null;
}

function q_concat($arg)
{
	$tmp = func_get_args();
	return implode('||', $tmp);
}

function q_limit($query, $limit, $off)
{
	return 'SELECT * FROM (SELECT /*+ FIRST_ROWS */ a.*, rownum AS xrownum FROM ('. $query .') a WHERE rownum <= '. ($off + $limit) .') WHERE xrownum >= '. ($off+1);
}

function q_bitand($fieldLeft, $fieldRight) {
	return 'BITAND('. $fieldLeft .', '. $fieldRight .')'; 
}

function q_bitor($fieldLeft, $fieldRight) {
	return $fieldLeft .' + '. $fieldRight .' - BITAND('. $fieldLeft .', '. $fieldRight .')';
}

function q_bitnot($bitField) {
	return '(0 - '. $bitField .') - 1';
}

function get_fud_table_list($tbl='')
{
	if ($tbl) {
		return db_all('SELECT lower(table_name) FROM user_tables WHERE table_name LIKE \''. strtoupper($tbl) .'\'');
	}

	return db_all('SELECT lower(table_name) FROM user_tables WHERE table_name LIKE \''. strtoupper($GLOBALS['DBHOST_TBL_PREFIX'] .'%\''));
}

function optimize_tables($tbl_list=null)
{
	if (!$tbl_list) {
		$tbl_list = get_fud_table_list();
	}
	foreach ($tbl_list as $t) {
		q('ANALYZE TABLE '. $t .' COMPUTE STATISTICS');
	}
}

function db_saq($q)
{
	return oci_fetch_array(q($q), OCI_RETURN_LOBS+OCI_RETURN_NULLS);
}

function db_sab($q)
{
	$r = q($q);
	return db_rowobj($r);
}

function db_qid($q)
{
	$r = q($q);

	$seqname = preg_replace('/^.*\s+(FROM|INTO)\s+(\w+)[\s\(\,].*$/si', '\\2', $q) .'_id_seq';
	return q_singleval('SELECT '. $seqname .'.CURRVAL from dual');
}

function db_arr_assoc($q)
{
	$r = q($q);
	return db_fetch_array($r);
}

function db_fetch_array($q)
{
	$tmp = oci_fetch_array($q, OCI_RETURN_LOBS+OCI_RETURN_NULLS+OCI_ASSOC);
	if (!$tmp) return NULL;

	// Change Oracle's UPPERCASE keys to lowercase.
	return array_change_key_case($tmp, CASE_LOWER);
}

function db_li($q, &$ef, $li=0)
{
	$p = oci_parse(fud_sql_lnk, _ora_syntax($q));
	$r = @oci_execute($p);

	if ($r) {
		if ($li) {
			$seqname = preg_replace('/^.*\s+(FROM|INTO)\s+(\w+)[\s\(\,].*$/si', '\\2', $q) .'_id_seq';
			return q_singleval('SELECT '. $seqname .'.CURRVAL FROM dual');
		}
		return $r;
	}

	/* Ignore duplicate keys : ORA-00001: unique constraint violated. */
	$err = oci_error($p);
	if ( $err['code'] == 1 ) {
		return null;
	} else {
		fud_sql_error_handler($err['sqltext'], $err['message'], $err['code'], db_version());
	}
}

function ins_m($tbl, $flds, $types, $vals)
{
	$sth = oci_parse(fud_sql_lnk, 'INSERT INTO '. $tbl .' ('. $flds .') VALUES(:'. implode(',:', range(0, substr_count($flds, ','))) .')');
	foreach ($vals as $v) {
		foreach (explode(',', trim($v,'\'')) as $v_no => $v_val) {
			oci_bind_by_name($sth, ':'. $v_no, $v_val, -1);
		}
		if (!@oci_execute($sth, OCI_DEFAULT)) {
			$err = oci_error($sth);
			if ( $err['code'] == 1 ) {	// Ignore duplicate values!
				continue;
			} else {
				fud_sql_error_handler($err['sqltext'], $err['message'], $err['code'], db_version());
			}
		}
	}
}

function db_all($q)
{
	$r = q($q);
	$nrows = oci_fetch_all($r, $rows, null, null, OCI_FETCHSTATEMENT_BY_ROW);
	if (!$rows) {
		return array();
	}
	$f = array();
	foreach ($rows as $v) {
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

	return '\''. $s .'\'';
}

function _ora_syntax($q)
{
	// Change LIMIT to Oracle's ROWNUM.
	if (preg_match('/LIMIT (\d+)/', $q, $res)) {
		$off = 0;
		$limit = $res[1];
		$q = substr($q, 0, strpos($q, 'LIMIT '));
		$q = 'SELECT * FROM (SELECT /*+ FIRST_ROWS */ a.*, rownum AS xrownum FROM ('. $q .') a WHERE rownum <= '. ($off + $limit) .') WHERE xrownum >= '. ($off+1);
	}

	// SELECT without FROM, we need to append 'FROM dual'.
	if (preg_match('/^SELECT /i', $q) && !preg_match('/FROM/i', $q)) {
		$q .= ' FROM dual';
	}

	// OR numeric values together in PHP and pass as a single value to Oracle.
	// $q = preg_replace_callback('/\b(\d[\d\|]+\d\b)/',
	//		create_function('$matches',
	//			'$or=0; foreach( explode(\'|\', $matches[0]) as $val) {$or = $or|$val;} return $or;'),
	//		$q);

	// Convert bitwise & to Oracle's BITAND() function.
	// $q = preg_replace('/\(([\.\w]+)\s*\&\s*(~?\d+)\)/', 'BITAND(\1, \2)', $q);

	// Simulate BITOR().
	// $q = preg_replace('/\(([\.\w]+)\s*\|\s*(\d+)\)/', '\1 + \2 - BITAND(\1, \2)', $q);

	// Simulate BITNOT().
	// $q = preg_replace('/~\s?([\d\(\)]+)/', '(0 - \1) - 1', $q);

	return $q;
}

?>
