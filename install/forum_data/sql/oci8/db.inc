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

if (!defined('fud_sql_lnk')) {
	$connect_func = $GLOBALS['FUD_OPT_1'] & 256 ? 'oci_pconnect' : 'oci_connect';	

	if (!extension_loaded('oci8')) {
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

function db_close()
{
	 oci_close(fud_sql_lnk);
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
		$GLOBALS['__FLPG__'] = oci_parse(fud_sql_lnk, _ora_syntax($q));
		if (@oci_execute($GLOBALS['__FLPG__']) === FALSE) {
			$err = oci_error($GLOBALS['__FLPG__']);
			fud_sql_error_handler($err['sqltext'], $err['message'], $err['code'], db_version());
		}
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

		$s = microtime(true);
		$GLOBALS['__FLPG__'] = oci_parse(fud_sql_lnk, _ora_syntax($q));
		if (@oci_execute($GLOBALS['__FLPG__']) === FALSE) {
			$err = oci_error($GLOBALS['__FLPG__']);
			fud_sql_error_handler($err['sqltext'], $err['message'], $err['code'], db_version());
		}
		$e = gettimeofday(true);

		$GLOBALS['__DB_INC_INTERNALS__']['last_time'] = ($e - $s);
		$GLOBALS['__DB_INC_INTERNALS__']['total_sql_time'] += $GLOBALS['__DB_INC_INTERNALS__']['last_time'];

		echo '<hr><b>Query #'. $GLOBALS['__DB_INC_INTERNALS__']['query_count'] .'</b><small>';
		echo ': time taken:     <i>'. number_format($GLOBALS['__DB_INC_INTERNALS__']['last_time'], 4) .'</i>';
		echo ', affected rows:  <i>'. db_affected() .'</i>';
		echo ', total sql time: <i>'.  number_format($GLOBALS['__DB_INC_INTERNALS__']['total_sql_time'], 4) .'</i>';
		echo '<pre>'. preg_replace('!\s+!', ' ', htmlspecialchars($q)) .'</pre></small>';

		return $GLOBALS['__FLPG__'];
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
	return $val ? $val[0] : false;
}

function q_limit($query, $limit, $off=0)
{

	if ($off) {
//		return 'SELECT * FROM (SELECT /*+ FIRST_ROWS */ a.*, rownum AS xrownum FROM ('. $query .') a WHERE rownum <= '. ($off + $limit) .') WHERE xrownum >= '. ($off+1);
		return $query .' OFFSET '. $off .' ROWS FETCH NEXT '. $limit .' ROWS ONLY';
	} else {
//		return 'SELECT * FROM (SELECT /*+ FIRST_ROWS */ a.* FROM ('. $query .') a WHERE rownum <= '. $limit .')';
		return $query .' FETCH FIRST '. $limit .' ROWS ONLY';
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
	return oci_fetch_array(q($q), OCI_RETURN_LOBS+OCI_RETURN_NULLS);
}

function db_sab($q)
{
	$r = q($q);
	return db_rowobj($r);
}

function db_qid($q)
{
//	$r = q($q);
//
//	$seqname = preg_replace('/.*INSERT\s+INTO\s+(\w+)[\s\(\,].*$/si', '\\1', $q) .'_id_seq';
//	return q_singleval('SELECT '. $seqname .'.CURRVAL from dual');

	$p = oci_parse(fud_sql_lnk, $q .' RETURNING ID INTO :id');
	oci_bind_by_name($p, ":id", $rowid);
	$r = @oci_execute($p);
	return $rowid;
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
	if ($li) {
		$p = oci_parse(fud_sql_lnk, $q . ' RETURNING ID INTO :id');
		oci_bind_by_name($p, ":id", $rowid);
	} else {
		$p = oci_parse(fud_sql_lnk, _ora_syntax($q));
	}

	$r = @oci_execute($p);

	if ($r) {
		if ($li) {
//			$seqname = preg_replace('/.*INSERT\s+INTO\s+(\w+)[\s\(\,].*$/si', '\\1', $q) .'_id_seq';
//			return q_singleval('SELECT '. $seqname .'.CURRVAL FROM dual');
			return $rowid;
		}
		return $r;
	}

	/* Ignore duplicate keys: ORA-00001: unique constraint violated. */
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
	$types_arr = explode(',', $types);
	foreach ($vals as $v) {
		$row = explode(',', trim($v, '\''));
		foreach ($row as $v_no => $v_val) {
			oci_bind_by_name($sth, ':'. $v_no, $row[$v_no], -1, (trim($types_arr[$v_no]) == 'integer') ? SQLT_INT : SQLT_CHR);
		}
		if (!@oci_execute($sth, OCI_DEFAULT)) {	// Don't auto commit.
			$err = oci_error($sth);
			if ( $err['code'] == 1 ) {	// Ignore ORA-00001: unique constraint violated.
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
	$nrows = oci_fetch_all($r, $rows, 0, -1, OCI_FETCHSTATEMENT_BY_ROW);
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
	// Replace any single ' with ''.
	$s = str_replace('\'', '\'\'', $s);

	return '\''. $s .'\'';
}

function _ora_syntax($q)
{
	// SELECT without FROM, we need to append 'FROM dual'.
	if (preg_match('/^SELECT /i', $q) && !preg_match('/FROM/i', $q)) {
		$q .= ' FROM dual';
	}

	return $q;
}

?>
