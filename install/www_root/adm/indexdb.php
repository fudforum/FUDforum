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

	@set_time_limit(0);
	@ini_set('memory_limit', '128M');

	require('./GLOBALS.php');

	// Run from command line.
	if (php_sapi_name() == 'cli') {
		if (empty($_SERVER['argv'][1]) || $_SERVER['argv'][1] != 'yes') {
			echo "Usage: php indexdb.php yes\n";
			echo " - specify 'yes' to confirm execution.\n";
			die();
		}

		fud_use('adm_cli.inc', 1);	// Contains cli_execute().
		cli_execute(1);
	}

	fud_use('adm.inc', true);
	fud_use('glob.inc', true);
	fud_use('isearch.inc');
	fud_use('fileio.inc');
	fud_use('rev_fmt.inc');

	if (isset($_POST['btn_cancel'])) {
		header('Location: '. $WWW_ROOT .'adm/index.php?'.__adm_rsid);
		exit;
	}

	require($WWW_ROOT_DISK . 'adm/header.php');

	if (!isset($_POST['conf'])) {
?>
<h2>Rebuild Search Index</h2>
<div class="alert">
This script will attempt to rebuild the search indices for the entire forum. This is a VERY CPU-intensive process
and can take a VERY LONG time, especially on large forums. You should ONLY run this if you absolutely must.
</div>
<form method="post" action="indexdb.php">
<p>Do you wish to proceed?</p>
<input type="submit" name="btn_cancel" value="No" />&nbsp;&nbsp;&nbsp;<input type="submit" name="conf" value="Yes" />
<?php echo _hs; ?>
</form>
<?php
		require($WWW_ROOT_DISK . 'adm/footer.php');
		exit;
	}

	if ($FUD_OPT_1 & 1) {
		pf('Disabling the forum for the duration of maintenance run.');
		maintenance_status('Undergoing maintenance, please come back later.', 1);
	}

	pf('Please wait while index is being rebuilt. This may take a while depending on the size of your forum.');

	$tbl =& $DBHOST_TBL_PREFIX;

	if (defined('shell_script')) {
		list($locale, $GLOBALS['usr']->lang) = db_saq("SELECT locale, lang FROM {$tbl}themes WHERE theme_opt & (1|2) LIMIT 1");
		$GLOBALS['good_locale'] = setlocale(LC_ALL, $locale);
	}

	db_lock($tbl.'msg_store WRITE, '.$tbl.'search_cache WRITE, '.$tbl.'search WRITE, '.$tbl.'index WRITE, '.$tbl.'title_index WRITE, '.$tbl.'msg WRITE');
	q('DELETE FROM '.$tbl.'search');
	q('DELETE FROM '.$tbl.'index');
	q('DELETE FROM '.$tbl.'title_index');
	if (!($sid = q_singleval('SELECT MIN(query_type) FROM '.$tbl."search_cache WHERE srch_query='' AND query_type<0"))) {
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
	q('DELETE FROM '.$tbl.'search_cache');
	db_unlock();

	pf('Done.');

	if ($FUD_OPT_1 & 1) {
		pf('Re-enabling the forum.');
		maintenance_status($GLOBALS['DISABLED_REASON'], 0);
	} else {
		echo '<br /><font size=+1 color="red">Your forum is currently disabled, to re-enable it go to the <a href="admglobal.php?'.__adm_rsid.'">Global Settings Manager</a> and re-enable it.</font>';
	}

	pf('<br /><div class="tutor">Messages successfully reindexed.</div>');
	require($WWW_ROOT_DISK . 'adm/footer.php');
?>
