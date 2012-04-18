<?php
/**
* copyright            : (C) 2001-2012 Advanced Internet Designs Inc.
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
			echo "Usage: php compact.php yes\n";
			echo " - specify 'yes' to confirm execution.\n";
			die();
		}

		fud_use('adm_cli.inc', 1);
		$_POST['conf'] = 1;
	}

	fud_use('fileio.inc');
	fud_use('adm.inc', true);
	fud_use('private.inc');
	fud_use('page_adm.inc', true);
	fud_use('glob.inc', true);
	fud_use('dbadmin.inc', true);
	fud_use('imsg_edt.inc');

	if (isset($_POST['btn_cancel'])) {
		header('Location: '. $WWW_ROOT .'adm/index.php?'. __adm_rsidl);
		exit;
	}

	include($WWW_ROOT_DISK .'adm/header.php');

	if (!isset($_POST['conf'])) {
?>
<h2>Message Rebuilder</h2>
<div class="alert">
The rebuilder can be used to cleanup message bodies, apply text changes or to move messages between file and database based storage.
While the rebuilder is running your forum will be temporarily inaccessible. 
This process may take a while to run, depending on your hard drive speed and the amount of messages your forum has. 
Please <a href="admdump.php?<?php echo __adm_rsid; ?>">backup</a> all files before proceeding!
</div><br />
<form method="post" action="compact.php">

<?php if (defined('fud_debug') && @extension_loaded('iconv')) { 
$charsets = ARRAY(
	'big5','euc-jp','gb2312',
	'iso-8859-1','iso-8859-2','iso-8859-3','iso-8859-4','iso-8859-5','iso-8859-6','iso-8859-7',
	'iso-8859-8','iso-8859-9','iso-8859-10','iso-8859-11','iso-8859-13','iso-8859-14',
	'iso-8859-15','iso-8859-16','koi8-r','windows-874','windows-936','windows-1250',
	'windows-1251','windows-1252','windows-1253','windows-1254','windows-1255','windows-1256',
	'windows-1257','windows-1258');
?>
<fieldset>
	<legend>Character set conversion:</legend>
	<p>Non-English forums that are not using UTF-8 might want to convert their messages to UTF-8. Converting twice will <u>corrupt your messages</u>. Please leave empty if you don't require a character set conversion or if you are unsure:</p>
	<table class="datatable">
    <tr class="field"><td>From character set:</td>
	    <td><select name="fromcharset" id="fromcharset" class="input">
			<option value=''>&nbsp;</option>
			<?php foreach($charsets as $charset) { ?>
				<option><?php echo $charset; ?></option>
			<?php } ?>
			</select></td>
    </tr>
	<tr class="field"><td>To character set:</td>
	    <td><select name="tocharset" id="tocharset" class="input">
			<option value=''>&nbsp;</option>
			<option value='utf-8'>utf-8</option>
			</select></td>
	</tr></table>
</fieldset>
<?php } ?>

<fieldset>
	<legend>Search and replace text:</legend>
	<label><p>
		Permanently apply <a href="admreplace.php?<?php echo __adm_rsid; ?>">Replacement and Censorship</a> rules to message bodies:
		<input name="replace" value="1" type="checkbox">
	</p></label>
</fieldset>

<p>Do you wish to proceed?</p>
<input type="submit" name="btn_cancel" value="No" />&nbsp;&nbsp;&nbsp;<input type="submit" name="conf" value="Yes" />
<?php echo _hs; ?>
</form>
<?php
		require($WWW_ROOT_DISK .'adm/footer.php');
		exit;
	}
?>
<?php

// List of temporary message files.
$GLOBALS['__FUD_TMP_F__'] = array();

function write_body_copy($data, &$len, &$offset, $file_id, $forum_id)
{
	$MAX_FILE_SIZE = 2140000000;

	// Convert between character sets.
	if (!empty($_POST['fromcharset']) && !empty($_POST['tocharset'])) {
		$newdata = iconv($_POST['fromcharset'], $_POST['tocharset'], $data);
		$data = $newdata;
	}

	// Replacement and censorship (code from replace.inc.t).
	if (!empty($_POST['replace'])) {
		if (!defined('__fud_replace_init')) {
			$GLOBALS['__FUD_REPL__']['pattern'] = $GLOBALS['__FUD_REPL__']['replace'] = array();
			$a =& $GLOBALS['__FUD_REPL__']['pattern'];
			$b =& $GLOBALS['__FUD_REPL__']['replace'];

			$c = uq('SELECT with_str, replace_str FROM '. $GLOBALS['DBHOST_TBL_PREFIX'] .'replace WHERE replace_str IS NOT NULL AND with_str IS NOT NULL AND LENGTH(replace_str)>0');
			while ($r = db_rowarr($c)) {
				$a[] = $r[1];
				$b[] = $r[0];
			}
			unset($c);

			define('__fud_replace_init', 1);
		}

		$data = preg_replace($GLOBALS['__FUD_REPL__']['pattern'], $GLOBALS['__FUD_REPL__']['replace'], $data);
	}

	$prev_len = $len;
	$len      = strlen($data);

	if ($GLOBALS['FUD_OPT_3'] & 32768) {	// DB_MESSAGE_STORAGE
		if ($offset == -1) {	// Already in DB.
			q('DELETE FROM '. $GLOBALS['DBHOST_TBL_PREFIX'] .'msg_store WHERE id='. $file_id);
		}
		$s = db_qid('INSERT INTO '. $GLOBALS['DBHOST_TBL_PREFIX'] .'msg_store (data) VALUES ('. _esc($data) .')');
		$offset = -1;
	} else {
		$s = $forum_id * 10000;
		$f =& $GLOBALS['__FUD_TMP_F__'];

		while (!isset($f[$s]) || $f[$s][1] + $len > $MAX_FILE_SIZE) {
			if (isset($f[$s])) ++$s;

			$f[$s][0] = fopen($GLOBALS['MSG_STORE_DIR'] .'tmp_msg_'. $s, 'ab');
			flock($f[$s][0], LOCK_EX);
			$f[$s][1] = __ffilesize($f[$s][0]);
		}

		if (fwrite($f[$s][0], $data) != $len) {
			exit('FATAL ERROR: system has ran out of disk space.');
		}
		$offset = $f[$s][1];
		$f[$s][1] += $len;
	}

	return $s;
}

	pf('<h3>Message Rebuilder progress</h3>');

	if ($FUD_OPT_1 & 1) {
		pf('Disabling the forum for the duration of maintenance run.');
		maintenance_status('Undergoing maintenance, please come back later.', 1);
	}

	pf('Please wait while we rebuild the forum\'s messages. This may take a while depending on the size of your forum.');

	$mode = ($FUD_OPT_2 & 8388608 ? 0600 : 0666);
	$tbl =& $DBHOST_TBL_PREFIX;
	$start_time = time();

	/* Rebuild normal messages. */
	pf('<br />');
	pf('<b>Rebuilding normal messages:</b>');

	$i = 0;
	$i_count = q_singleval('SELECT count(*) FROM '. $tbl .'msg WHERE file_id>0');
	$i_commit = ($i_count > 10000) ? 1000 : 100;
	if ($i_count) {
		db_lock($tbl .'msg m WRITE, '. $tbl .'thread t WRITE, '. $tbl .'forum f WRITE, '. $tbl .'msg WRITE, '. $tbl .'msg_store WRITE');

		while (1) {
			$j = $i;
			$c = q(q_limit('SELECT m.id, m.foff, m.length, m.file_id, f.message_threshold, f.id as forum_id FROM '. $tbl .'msg m INNER JOIN '. $tbl .'thread t ON m.thread_id=t.id INNER JOIN '. $tbl .'forum f ON t.forum_id=f.id WHERE m.file_id>0', 100));
			while ($r = db_rowobj($c)) {
				if ($r->message_threshold && $r->length > $r->message_threshold) {	// Body longer than threshold.
					$len2 = $r->length; $off2 = $r->foff;	// Pass in, function will change them.
					$m2 = write_body_copy(trim_html(read_msg_body($r->foff, $r->length, $r->file_id), $r->message_threshold), $len2, $off2, $r->file_id, $r->forum_id);
				} else {
					$m2 = $len2 = $off2 = 0;
				}
				$len = $r->length; $off = $r->foff;	// Pass in, function will change them.
				$m1 = write_body_copy(read_msg_body($r->foff, $r->length, $r->file_id), $len, $off, $r->file_id, $r->forum_id);
				// Minus on -$m1 / -m2 to mark message as rebuilt.
				q('UPDATE '. $tbl .'msg SET foff='. $off .', length='. $len .', file_id='. (-$m1) .', file_id_preview='. (-$m2) .', offset_preview='. $off2 .', length_preview='. $len2 .' WHERE id='. $r->id);

				if ((($i+1) % $i_commit) == 0) {
					eta_calc($start_time, $i, $i_count);
				}
				$i++;
			}
			unset($c);
			if ($i == $j) break;
		}

		/* Rename our temporary files & update the database. */
		q('UPDATE '. $tbl .'msg SET file_id=-file_id, file_id_preview=-file_id_preview WHERE file_id<0');

		/* Close message files before we delete them. */
		if (isset($GLOBALS['__MSG_FP__'])) {
			foreach ($GLOBALS['__MSG_FP__'] as $id => $fp) {
				fclose($GLOBALS['__MSG_FP__'][$id]);
			}
		}

		/* Remove old message files. */
		foreach (glob($MSG_STORE_DIR .'msg_*') as $f) {
			if (!unlink($f)) {
				exit('FATAL ERROR: unable to remove file '. $f .'.');
			}
		}

		/* Move new message files to the new location. */
		foreach ($GLOBALS['__FUD_TMP_F__'] as $k => $f) {
			fclose($GLOBALS['__FUD_TMP_F__'][$k][0]);
			if(!rename($MSG_STORE_DIR .'tmp_msg_'. $k, $MSG_STORE_DIR .'msg_'. $k)) {
				exit('FATAL ERROR: unable to rename tmp_msg_'. $k .' to msg_'. $k .'.');
			}
			chmod($MSG_STORE_DIR .'msg_'. $k, $mode);
		}

		db_unlock();
	}
	pf('100% Done.<br /><br />');

	/* Rebuild private messages. */
	pf('<b>Rebuilding private messages:</b>');

	// Index messages offsets for faster processing.
	create_index($tbl .'pmsg', $tbl .'pmsg_foff_idx', false, 'foff');

	db_lock($tbl .'pmsg WRITE, '. $tbl .'msg_store WRITE');
	$i = $off = $len = 0;
	$start_time2 = time();
	$fp = fopen($MSG_STORE_DIR .'private_tmp', 'wb');
	if (!$fp) {
		exit('Failed to open temporary private message store.');
	}
	$i_count = q_singleval('SELECT count(*) FROM '. $tbl .'pmsg');
	if ($i_count) {
		$c = q('SELECT id, foff, length FROM '. $tbl .'pmsg');

		while ($r = db_rowobj($c)) {
			$data = read_pmsg_body($r->foff, $r->length);

			if (!empty($_POST['fromcharset']) || !empty($_POST['tocharset'])) {
				$newdata = iconv($_POST['fromcharset'], $_POST['tocharset'], $data);
				$data = $newdata;
			}

			if ($FUD_OPT_3 & 32768) {	// Write message body into the DB.
				if ($r->foff == -1) {	// Already in DB.
					q('DELETE FROM '. $tbl .'msg_store WHERE id='. $r->length);
				}
				$len = db_qid('INSERT INTO '. $GLOBALS['DBHOST_TBL_PREFIX'] .'msg_store (data) VALUES ('. _esc($data) .')');
				$off = -1;
			} else {
				if (($len = fwrite($fp, $data)) === FALSE || !fflush($fp)) {
					exit('FATAL ERROR: system has ran out of disk space.');
				}
			}
			q('UPDATE '. $tbl .'pmsg SET foff='. $off .', length='. $len .' WHERE id = '. $r->id);
			$off += $len;

			if ((($i+1) % $i_commit) == 0) {
				eta_calc($start_time2, $i, $i_count);
			}
			$i++;
		}
		unset($c);
	}
	fclose($fp);

	@unlink($MSG_STORE_DIR .'private');
	if (!$i) {
		@unlink($MSG_STORE_DIR .'private_tmp');
	} else {
		rename($MSG_STORE_DIR .'private_tmp', $MSG_STORE_DIR .'private');
		chmod($MSG_STORE_DIR .'private', $mode);
	}

	db_unlock();
	drop_index($tbl .'pmsg', $tbl .'pmsg_foff_idx');
	pf('100% Done.<br /><br />');

	/* Rebuild private messages. */
	pf('<b>Rebuilding static pages:</b>');

	db_lock($tbl .'pages WRITE');
	$i = $off = $len = 0;
	$start_time2 = time();
	$fp = fopen($MSG_STORE_DIR .'pages_tmp', 'wb');
	if (!$fp) {
		exit('Failed to open temporary file for storing pages.');
	}
	$i_count = q_singleval('SELECT count(*) FROM '. $tbl .'pages');
	if ($i_count) {
		$c = q('SELECT id, foff, length FROM '. $tbl .'pages');

		while ($r = db_rowobj($c)) {
			$data = fud_page::read_page_body($r->foff, $r->length);

			if (!empty($_POST['fromcharset']) || !empty($_POST['tocharset'])) {
				$newdata = iconv($_POST['fromcharset'], $_POST['tocharset'], $data);
				$data = $newdata;
			}

			if ($FUD_OPT_3 & 32768) {	// Write page body into the DB.
				if ($r->foff == -1) {	// Already in DB.
					q('DELETE FROM '. $tbl .'msg_store WHERE id='. $r->length);
				}
				$len = db_qid('INSERT INTO '. $GLOBALS['DBHOST_TBL_PREFIX'] .'msg_store (data) VALUES ('. _esc($data) .')');
				$off = -1;
			} else {
				if (($len = fwrite($fp, $data)) === FALSE || !fflush($fp)) {
					exit('FATAL ERROR: system has ran out of disk space.');
				}
			}
			q('UPDATE '. $tbl .'pages SET foff='. $off .', length='. $len .' WHERE id = '. $r->id);
			$off += $len;

			if ((($i+1) % $i_commit) == 0) {
				eta_calc($start_time2, $i, $i_count);
			}
			$i++;
		}
		unset($c);
	}
	fclose($fp);

	@unlink($MSG_STORE_DIR .'pages');
	if (!$i) {
		@unlink($MSG_STORE_DIR .'pages_tmp');
	} else {
		rename($MSG_STORE_DIR .'pages_tmp', $MSG_STORE_DIR .'pages');
		chmod($MSG_STORE_DIR .'pages', $mode);
	}

	db_unlock();
	pf('100% Done.<br /><br />');

	/* Remove any messages that may be left in DB. */
	if (!($GLOBALS['FUD_OPT_3'] & 32768)) {	// Not DB_MESSAGE_STORAGE.
		q('DELETE FROM '. $tbl .'msg_store');
	}

	pf(sprintf('All done in %.2f minutes.', (time() - $start_time) / 60));

	if ($FUD_OPT_1 & 1) {
		pf('Re-enabling the forum.');
		maintenance_status($DISABLED_REASON, 0);
	} else {
		echo '<br /><font size="+1" color="red">Your forum is currently disabled, to re-enable it go to the <a href="admglobal.php?'. __adm_rsid .'">Global Settings Manager</a> and re-enable it.</font>';
	}

	pf('<br /><div class="tutor">Messages successfully rebuilt.</div>');
	require($WWW_ROOT_DISK .'adm/footer.php');
?>
