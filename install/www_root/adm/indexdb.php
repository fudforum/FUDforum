<?php
/***************************************************************************
* copyright            : (C) 2001-2004 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: indexdb.php,v 1.19 2004/04/15 21:02:57 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it 
* under the terms of the GNU General Public License as published by the 
* Free Software Foundation; either version 2 of the License, or 
* (at your option) any later version.
***************************************************************************/

	@set_time_limit(2400);

	require('./GLOBALS.php');
	// uncomment the lines below if you wish to run this script via command line
	// fud_use('adm_cli.inc', 1); // this contains cli_execute() function.
	// cli_execute(1);

	fud_use('adm.inc', true);
	fud_use('glob.inc', true);
	fud_use('isearch.inc');
	fud_use('fileio.inc');
	fud_use('rev_fmt.inc');

	require($WWW_ROOT_DISK . 'adm/admpanel.php');

	if (!isset($_POST['conf'])) {
?>
<form method="post" action="indexdb.php">
<div class="alert">
This script will attempt to rebuild the search indices for the entire forum. This is a VERY CPU-intensive process
and can take a VERY LONG time, especially on large forums. You should ONLY run this if you absolutely must.
</div>
<h2>Do you wish to proceed?</h2>
<input type="submit" name="btn_cancel" value="No">&nbsp;&nbsp;&nbsp;<input type="submit" name="conf" value="Yes">
<?php echo _hs; ?>
</form>
<?php
		require($WWW_ROOT_DISK . 'adm/admclose.html');
		exit;
	}

	if ($FUD_OPT_1 & 1) {
		echo '<br>Disabling the forum for the duration of maintenance run<br>';
		maintenance_status('Undergoing maintenance, please come back later.', 1);
	}

	echo '<br>Please wait while index is being rebuilt.<br>This may take a while depending on the size of your forum.';

	$tbl =& $DBHOST_TBL_PREFIX;

	db_lock($tbl.'search_cache WRITE, '.$tbl.'search WRITE, '.$tbl.'index WRITE, '.$tbl.'title_index WRITE, '.$tbl.'msg WRITE');
	if (!($sid = q_singleval("SELECT MIN(query_type) FROM ".$tbl."search_cache WHERE srch_query='' AND query_type<0"))) {
		q('DELETE FROM '.$tbl.'search');
		q('DELETE FROM '.$tbl.'index');
		q('DELETE FROM '.$tbl.'title_index');
		q('DELETE FROM '.$tbl.'search_cache');
	}

	$c = q('SELECT id, subject, length, foff, file_id FROM '.$tbl.'msg WHERE '.($sid ? ' id>'.$sid.' AND ' : '').' apr=1 ORDER BY subject');
	$old_subject = '';
	while ($r = db_rowarr($c)) {
		if ($old_subject != $r[1]) {
			$subj = $old_subject = $r[1];
		} else {
			$subj = '';
		}
		q('INSERT INTO '.$tbl.'search_cache (srch_query, query_type, expiry, msg_id, n_match) VALUES(\'\', -'.$r[0].', 0,0,0)');
		index_text($subj, read_msg_body($r[3], $r[2], $r[4]), $r[0]);
	}
	unset($c);
	un_register_fps();
	q('DELETE FROM '.$tbl.'search_cache');
	db_unlock();

	echo 'Done<br>';

	if ($FUD_OPT_1 & 1) {
		echo '<br>Re-enabling the forum.<br>';
		maintenance_status($GLOBALS['DISABLED_REASON'], 0);
	} else {
		echo '<br><font size=+1 color="red">Your forum is currently disabled, to re-enable it go to the <a href="admglobal.php?'._rsidl.'">Global Settings Manager</a> and re-enable it.</font>';
	}

	require($WWW_ROOT_DISK . 'adm/admclose.html');
?>
