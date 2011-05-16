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

/* Handle database errors (already defined if we include GLOBALS.php). */
if (!function_exists('fud_sql_error_handler')) {
	function fud_sql_error_handler($query, $error_string, $error_number, $server_version)
	{
		pf('<hr>ERROR: '. $error_number .': '. $error_string .'<br />QUERY: <pre>'. $query .'</pre><hr>');
		throw new Exception($error_string);
	}
}

/* main */
error_reporting(-1);
@ini_set('display_errors', 1);

// Run from command line?
if (php_sapi_name() == 'cli') {
	if (empty($_SERVER['argv'][1])) {
		echo "Usage: php admsqltest2.php DATA_DIR /path/to/dump_file\n";
		die();
	}
	$_POST['DATA_DIR']          = $_SERVER['argv'][1];
	$_POST['DBHOST']            = $_SERVER['argv'][2];
	$_POST['DBHOST_DBTYPE']     = $_SERVER['argv'][3];
	$_POST['DBHOST_DBNAME']     = $_SERVER['argv'][4];
	$_POST['DBHOST_USER']       = $_SERVER['argv'][5];
	$_POST['DBHOST_PASSWORD']   = $_SERVER['argv'][6];
}

if (empty($_POST['DATA_DIR']) || !is_dir($_POST['DATA_DIR'])) {
	die('Go away!');
}

$GLOBALS['DATA_DIR']          = $_POST['DATA_DIR'];
$GLOBALS['FUD_OPT_1']         = 0;
$GLOBALS['DBHOST']            = $_POST['CF_DBHOST'];
$GLOBALS['DBHOST_DBTYPE']     = $_POST['CF_DBHOST_DBTYPE'];
$GLOBALS['DBHOST_DBNAME']     = $_POST['CF_DBHOST_DBNAME'];
$GLOBALS['DBHOST_USER']       = $_POST['CF_DBHOST_USER'];
$GLOBALS['DBHOST_PASSWORD']   = $_POST['CF_DBHOST_PASSWORD'];
$GLOBALS['DBHOST_TBL_PREFIX'] = $_POST['CF_DBHOST_TBL_PREFIX'];

// Load common ACP functions.
include $GLOBALS['DATA_DIR'] .'include/adm_common.inc';

pf('<h1>Test '. $DBHOST_DBTYPE .' driver</h1>', true);
$tab = $DBHOST_TBL_PREFIX .'test_table';

/* Load FUDforum's DB driver. It will attempt to connect by iteself. */
pf('Loading DB driver...');
$time_start = microtime(true);
include $GLOBALS['DATA_DIR'] .'sql/'. $GLOBALS['DBHOST_DBTYPE'] .'/db.inc';

/* Get database type and version. */
pf('DB type is: '. __dbtype__);
pf('DB Version is: '. db_version());
pf();

/* Load FUDforum's DBA driver. */
pf('Loading DBA class...');
include_once '../include/dbadmin.inc';

/* Cleanup failed previous runs. */
drop_table($tab, true);

pf('Creating a test table...');
// Be careful, function may return 0 for zero rows and not FALSE!  A test like this is wrong: if (!q('CREATE... 
$stmt = 'CREATE TABLE '. $tab .'(id INT NOT NULL AUTO_INCREMENT PRIMARY KEY, msg TEXT)';
if (create_table($stmt) === FALSE) {
	pf('FATAL ERROR: your database account does not have permissions to create new tables.');
}
pf('Alter table...');
if (q('ALTER TABLE '. $tab .' ADD test_val INT') === FALSE) {
	pf('FATAL ERROR: your database account does not have permissions to run ALTER queries on existing tables.');
}
if (q('ALTER TABLE '. $tab .' ADD test_val2 INT') === FALSE) {
	pf('FATAL ERROR: your database account does not have permissions to run ALTER queries on existing tables.');
}
pf('Create index...');
sleep(2);
create_index($tab, $tab .'_ind', true, 'test_val');

/* q() - buffered query. */
// Buffered query: Client waits for the complete result set to be returned before continuing.

/* uq() - unbuffered query. */
// Unbuffered query: Returns after the query is compiled and begins execution, but before any rows are returned. 
// The client will only wait when it asks for a row and one is not already in the client's memory.
// Only available in MySQL. Other DB's must alias this function to q().

/* db_lock() - Lock database tables. */
// Some databases, like MS-SQL doesn't have an explisit LOCK TABLE statement. How will it impact FUDforum? It it good enouth to simply start a transaction?
pf('Lock the table...');
db_lock($tab .' WRITE');

/* db_locked() - Check if DB is locked. */
$locked = db_locked() ? 'yes' : 'NO?';
pf('Is DB currently locked? '. $locked);
if ($locked !== 'yes') die('DB should be locked!');

/* db_li() - Insert, but ignore DUPLICATE rows. */
pf('Insert single rows...');
db_li('INSERT INTO '. $tab .'(test_val) VALUES(123)', $tmp);
pf('Second row is a dup, error should be ignored...');
db_li('INSERT INTO '. $tab .'(test_val) VALUES(123)', $tmp); // Should be ignored.

/* db_qid() - Insert and return id. */
$id = db_qid('INSERT INTO '. $tab .'(test_val) VALUES(234)', $tmp);
pf('Insert row and return id = '.  $id);
if (!is_numeric($id)) die('Number expected!');

/* ins_m() -  Bulk insert operation, ignore DUPLICATE values. */
// Some databases support INSERT INTO...VALUES (..),(..); and others require individual INSERT statements.
pf('Insert multiple rows...');
ins_m($tab, 'test_val', 'integer', array(345, 456));
ins_m($tab, 'test_val', 'integer', array(456, 345));	// Should be ignored.

/* db_rowobj() - Fetch into object. */
pf('Test db_rowobj()...');
$data = '';
$c = uq('SELECT * FROM '. $tab);
while ($r = db_rowobj($c)) {
	// pf(' > '. $r->test_val);
	$data .= $r->test_val .'/';
}
if (strcmp($data, '123/234/345/456/') != 0) die('Wrong data returned: '. $data);

/* db_rowarr() - Fetch into array. */
pf('Test db_rowarr()...');
$data = '';
$c = uq('SELECT id, test_val FROM '. $tab);
while ($r = db_rowarr($c)) {
	// pf(' > '. $r[0] .', '. $r[1]);
	$data .= $r[1] .'/';
}
if (strcmp($data, '123/234/345/456/') != 0) die('Wrong data returned: '. $data);

/* db_fetch_array() - Fetch rows into array. */
// Only used in admsql.php to ALWAYS get column headings.
pf('Test db_fetch_array()...');
$data = '';
$q = uq('SELECT test_val FROM '. $tab);
while ($r = db_fetch_array($q)) {
	foreach ($r as $key => $val) {
		// pf(' > '. $key .' = '. $val);
		$data .= $key .'='. $val .'/';
	}
}
if (strcmp($data, 'test_val=123/test_val=234/test_val=345/test_val=456/') != 0) die('Wrong data returned: '. $data);

/*  q_singleval() - Single col & row. */
pf('Test q_singleval()...');
$data = q_singleval('SELECT test_val FROM '. $tab);
// pf(' > '. $data);
if (strcmp($data, '123') != 0) die('Wrong data returned: '. $data);

/* db_saq() - Execute query and fetch one row and return array of values. */
pf('Test db_saq()...');
list($data) = db_saq('SELECT test_val FROM '. $tab);
// pf(' > '. $data);
if (strcmp($data, '123') != 0) die('Wrong data returned: '. $data);

/* db_sab() - Execute query and fetch one row and return as associated array col -> val. */
// This function is equivalent to: db_rowobj(q($q));
pf('Test db_sab()...');
$r = db_sab('SELECT test_val FROM '. $tab);
// pf(' > '. $r->test_val);
if (strcmp($r->test_val, '123') != 0) die('Wrong data returned: '. $r->test_val);

/* db_arr_assoc() - Fetch single row into associative array. */
// This functions is equivalent to:  db_fetch_array(q($q));
pf('Test db_arr_assoc()...');
$data = '';
$c = db_arr_assoc('SELECT id, test_val, test_val2 FROM '. $tab .' WHERE test_val = 123');
foreach ($c as $k => $v) {
	// pf('COL=['. $k .'], Value=['. $v .']');
	$data .= $k .'='. is_numeric($v) .'/';
}
if (strcmp($data, 'id=1/test_val=1/test_val2=/') != 0) die('Wrong data returned: '. $data);

/*  db_all() - Fetch all rows from table. */
pf('Test db_all()...');
$data = '';
$all = db_all('SELECT test_val FROM '. $tab);
foreach($all as $k => $v) {
	// pf('Row=['. $k .'], Value=['. $v .']');
	$data .= $k .'='. is_numeric($v) .'/';
}
if (strcmp($data, '0=1/1=1/2=1/3=1/') != 0) die('Wrong data returned: '. $data);

/* q_concat() - Rewrite phrase to DB's syntax for string concategation.*/
// MySQL Uses Concat(), SQL Lite ||, SQL Server +, etc.
pf('Test string concatenation...');
$data = q_singleval('SELECT '. q_concat(_esc('str'), 1, 'id') .' FROM '. $tab);
// pf(' > '. $data);
if (strcmp($data, 'str11') != 0) die('Wrong data returned: '. $data);

/* q_rownum() - Number rows in SELECT. */
pf('Test row numering...');
$data = '';
$all = db_all('SELECT '. q_rownum() .' FROM '. $tab);
foreach($all as $k => $v) {
	// pf('Row=['. $k .'], Value=['. $v .']');
	$data .= $v .'/';
}
if (strcmp($data, '1/2/3/4/') != 0) die('Wrong data returned: '. $data);

/* q_bitand(), q_bitor() and q_bitnot() - SQL bitwise operations. */
pf('Test bitwise operations...');
$data = q_singleval('SELECT '. q_bitand(3, 1));
if ($data != 1) die('Wrong bitand result returned: '. $data);
$data = q_singleval('SELECT '. q_bitor(4, 3));
if ($data != 7) die('Wrong bitor result returned: '. $data);
$data = q_singleval('SELECT '. q_bitand(3, q_bitnot(1)));
if ($data != 2) die('Wrong bitnot result returned: '. $data);
$data = q_singleval('SELECT '. q_bitor( q_bitand( q_bitor(4194304, 4194304|16777216), ~(4194304|16777216)), 8388608));
if ($data != 8388608) die('Complex bitwise operation returned: '. $data);

/* q_limit() - Rewrite query syntax to return limited rows. */
// Examples:
//	Firebird: SELECT FIRST 10 SKIP 20 column1, column2, column3 FROM foo
//	MySQL: SELECT column1, column2, column3 FROM foo LIMIT 10, 20
//	PostgreSQL: SELECT column1, column2, column3 FROM foo LIMIT 20, 10
pf('Test query limit - first row...');
$data = '';
$c = uq(q_limit('SELECT * FROM '. $tab, 1, 0));
while ($r = db_rowobj($c)) {
	// pf(' > '. $r->test_val);
	$data .= $r->test_val .'/';
}
if (strcmp($data, '123/') != 0) die('Wrong data returned: '. $data);
pf('Test query limit - second row...');
$data = '';
$c = uq(q_limit('SELECT * FROM '. $tab .' ORDER BY test_val', 1, 1));
while ($r = db_rowobj($c)) {
	// pf(' > '. $r->test_val);
	$data .= $r->test_val .'/';
}
if (strcmp($data, '234/') != 0) die('Wrong data returned: '. $data);
pf('Test query limit - second and third rows...');
$data = '';
$c = uq(q_limit('SELECT * FROM '. $tab .' ORDER BY test_val', 2, 1));
while ($r = db_rowobj($c)) {
	// pf(' > '. $r->test_val);
	$data .= $r->test_val .'/';
}
if (strcmp($data, '234/345/') != 0) die('Wrong data returned: '. $data);

pf('Test if LONG/ LOB values are returned...');
$data = '';
db_li('INSERT INTO '. $tab .'(msg) VALUES(\'A long message...\')', $tmp); //A TEXT/ CLOB value
$c = uq('SELECT * FROM '. $tab .' WHERE msg IS NOT NULL');
while ($r = db_rowobj($c)) {
	// pf(' > '. $r->msg);
	$data .= $r->msg .'/';
}
if (strcmp($data, 'A long message.../') != 0) die('Wrong data returned: '. $data);

/* db_unlock() - Unlock database tables. */
pf('Unlock the table...');
db_unlock($tab);

/* get_fud_table_list() - Return a list of FUDforum's tables. */
pf('List all tables...');
$data = '';
foreach( get_fud_table_list($tab) as $tbl) {
	// pf(' > ['. $tbl .']');
	$data .= $tbl .'/';
}
if (strcmp($data, 'fud30_test_table/') != 0) die('Wrong data returned: '. $data);
foreach( get_fud_table_list() as $tbl) {
	pf(' > '. $tbl);
}

/* optimize_fud_tables() - Optimize database tables. */
pf('Optimize tables...');
optimize_fud_tables();

pf('Drop table...');
if (drop_table($tab) === FALSE) {
	pf('FATAL ERROR: your database account does not have permissions to run DROP TABLE queries on existing tables.');
}

pf('<hr>');
$run_time = microtime(true) - $time_start;
pf('<i>Tests took '. $run_time .' seconds to complete.</i>');
