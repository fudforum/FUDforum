<?php
/**
* copyright            : (C) 2001-2009 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: compact.php,v 1.86 2009/09/30 16:47:33 frank Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; version 2 of the License.
**/

	@set_time_limit(0);

	require('./GLOBALS.php');
	if ($FUD_OPT_3 & 32768) {
		exit('Unnecessary if messages are stored in a database.');
	}

	// Run from command line.
	if (php_sapi_name() == 'cli') {
		if (empty($_SERVER['argv'][1]) || $_SERVER['argv'][1] != 'yes') {
			echo "Usage: php compact.php yes\n";
			echo " - specify 'yes' to confirm execution.\n";
			die();
		}

		fud_use('adm_cli.inc', 1);	// Contains cli_execute().
		cli_execute(1);
	}

	fud_use('db.inc');
	fud_use('fileio.inc');
	fud_use('adm.inc', true);
	fud_use('private.inc');
	fud_use('glob.inc', true);
	fud_use('imsg_edt.inc');

	if (isset($_POST['btn_cancel'])) {
		header('Location: '. $WWW_ROOT .'adm/index.php?'.__adm_rsid);
	}

	include($WWW_ROOT_DISK . 'adm/header.php');

	if (!isset($_POST['conf'])) {
?>
<h2>Compact Messages</h2>
<div class="alert">
The compactor will rebuild the storage files were the message bodies are kept. 
While the compactor is running your forum will be temporarily inaccessible. 
This process may take a while to run, depending on your hard drive speed and the amount of messages your forum has. 
Please <a href="admdump.php?<?php echo __adm_rsid; ?>">backup</a> all files before proceeding!
</div><br />
<form method="post" action="compact.php">

<?php if (@extension_loaded('iconv')) { 
$charsets = ARRAY(
	'big5','euc-jp','gb2312',
	'iso-8859-1','iso-8859-2','iso-8859-3','iso-8859-4','iso-8859-5','iso-8859-6','iso-8859-7',
	'iso-8859-8','iso-8859-9','iso-8859-10','iso-8859-11','iso-8859-13','iso-8859-14',
	'iso-8859-15','iso-8859-16','koi8-r','windows-874','windows-936','windows-1250',
	'windows-1251','windows-1252','windows-1253','windows-1254','windows-1255','windows-1256',
	'windows-1257','windows-1258');
?>
<fieldset>
	<legend><b>Optional character set conversion:</b></legend>
	<p>Non-English forums that are not using UTF-8 might want to convert their messages to UTF-8. Converting twice will corrupt your messages. Please leave empty if you don't require a character set conversion or if you are unsure:</p>
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

<p>Do you wish to proceed?</p>
<input type="submit" name="btn_cancel" value="No" />&nbsp;&nbsp;&nbsp;<input type="submit" name="conf" value="Yes" />
<?php echo _hs; ?>
</form>
<?php
		require($WWW_ROOT_DISK . 'adm/footer.php');
		exit;
	}
?>
<?php

$GLOBALS['__FUD_TMP_F__'] = array();
set_error_handler('error_handler');

function error_handler ($level, $message, $file, $line, $context) {
	if (error_reporting() != 0) {
		echo <<<_END_
<p>An error was generated in file $file on line $line.</p>
<p><font color="red">The error message was: $message</font></p>
_END_;
		exit;
	} else {
		return;
	}
} 

function write_body_c($data, &$len, &$offset, $fid)
{
	$MAX_FILE_SIZE = 2140000000;

	if (!empty($_POST['fromcharset']) || !empty($_POST['tocharset'])) {
		$newdata = iconv($_POST['fromcharset'], $_POST['tocharset'], $data);
		$data = $newdata;
	}

	$len = strlen($data);

	$s = $fid * 10000;

	$f =& $GLOBALS['__FUD_TMP_F__'];

	while (!isset($f[$s]) || $f[$s][1] + $len > $MAX_FILE_SIZE) {
		if (isset($f[$s])) ++$s;

		$f[$s][0] = fopen($GLOBALS['MSG_STORE_DIR'] . 'tmp_msg_'.$s, 'ab');
		flock($f[$s][0], LOCK_EX);
		$f[$s][1] = __ffilesize($f[$s][0]);
	}

	if (fwrite($f[$s][0], $data) != $len) {
		exit('FATAL ERROR: system has ran out of disk space.');
	}
	$offset = $f[$s][1];
	$f[$s][1] += $len;

	return $s;
}

function eta_calc($start, $pos, $pc)
{
	$cur = time();
	$prg = $pos / $pc;
	$eta = ($cur - $start) / $prg * (10 - $prg);
	if ($eta > 60) {
		pf( ($prg * 10) .'% done; ETA: '. sprintf('%.2f', $eta/60) .' minutes');
	} else {
		pf( ($prg * 10) .'% done; ETA: '. $eta .' seconds');
	}
}

	if ($FUD_OPT_1 & 1) {
		pf('Disabling the forum for the duration of maintenance run.');
		maintenance_status('Undergoing maintenance, please come back later.', 1);
	}

	pf('Please wait while forum is being compacted. This may take a while depending on the size of your forum.');

	$mode = ($FUD_OPT_2 & 8388608 ? 0600 : 0666);
	$tbl =& $DBHOST_TBL_PREFIX;
	$stm = time();

	/* Normal Messages */
	pf('Compacting normal messages...');

	$pc = ceil(q_singleval('SELECT count(*) FROM '.$tbl.'msg WHERE file_id>0') / 10);
	$i = 0;
	if ($pc) {
		db_lock($tbl.'msg m WRITE, '.$tbl.'thread t WRITE, '.$tbl.'forum f WRITE, '.$tbl.'msg WRITE, '.$tbl.'msg_store WRITE');

		while (1) {
			$j = $i;
			$c = q('SELECT m.id, m.foff, m.length, m.file_id, f.message_threshold, f.id FROM '.$tbl.'msg m INNER JOIN '.$tbl.'thread t ON m.thread_id=t.id INNER JOIN '.$tbl.'forum f ON t.forum_id=f.id WHERE m.file_id>0 LIMIT 100');
			while ($r = db_rowarr($c)) {
				if ($r[4] && $r[2] > $r[4]) {
					$m2 = write_body_c(trim_html(read_msg_body($r[1], $r[2], $r[3]), $r[4]), $len2, $off2, $r[5]);
				} else {
					$m2 = $len2 = $off2 = 0;
				}
				$m1 = write_body_c(read_msg_body($r[1], $r[2], $r[3]), $len, $off, $r[5]);
				q('UPDATE '.$tbl.'msg SET foff='.$off.', length='.$len.', file_id='.(-$m1).', file_id_preview='.(-$m2).', offset_preview='.$off2.', length_preview='.$len2.' WHERE id='.$r[0]);

				if ($i && !($i % $pc)) {
					eta_calc($stm, $i, $pc);
				}
				$i++;
			}
			unset($c);
			if ($i == $j) break;
		}

		/* Rename our temporary files & update the database. */
		q('UPDATE '.$tbl.'msg SET file_id=-file_id, file_id_preview=-file_id_preview WHERE file_id<0');

		/* Close message files before we delete them. */
		if (isset($GLOBALS['__MSG_FP__'])) {
			foreach ($GLOBALS['__MSG_FP__'] as $id => $fp) {
				fclose($GLOBALS['__MSG_FP__'][$id]);
			}      
		}

		/* Remove old message files. */
		foreach (glob($MSG_STORE_DIR.'msg_*') as $f) {
			if (!unlink($f)) {
				exit('FATAL ERROR: unable to remove file '.$f.'.');
			}
		}

		/* Move new message files to the new location. */
		foreach ($GLOBALS['__FUD_TMP_F__'] as $k => $f) {
			fclose($GLOBALS['__FUD_TMP_F__'][$k][0]);
			if(!rename($MSG_STORE_DIR . 'tmp_msg_'.$k, $MSG_STORE_DIR . 'msg_'.$k)) {
				exit('FATAL ERROR: unable to rename tmp_msg_'.$k.' to msg_'.$k.'.');
			}
			chmod($MSG_STORE_DIR . 'msg_'.$k, $mode);
		}

		db_unlock();
	}
	pf('100% Done.');

	/* Private Messages */
	pf('Compacting private messages...');

	q('CREATE INDEX '.$tbl.'pmsg_foff_idx ON '.$tbl.'pmsg (foff)');

	db_lock($tbl.'pmsg WRITE');
	$i = $off = $len = 0;
	$stm2 = time();
	$fp = fopen($MSG_STORE_DIR.'private_tmp', 'wb');
	if (!$fp) {
		exit("Failed to open temporary private message store.");
	}
	$pc = q_singleval('SELECT count(*) FROM '.$tbl.'pmsg');
	if ($pc) {
		$pc = ceil($pc / 10);

		$c = q('SELECT distinct(foff), length FROM '.$tbl.'pmsg');

		while ($r = db_rowarr($c)) {
			$data = read_pmsg_body($r[0], $r[1]);
			if (!empty($_POST['fromcharset']) || !empty($_POST['tocharset'])) {
				$newdata = iconv($_POST['fromcharset'], $_POST['tocharset'], $data);
				$data = $newdata;
			}

			if (($len = fwrite($fp, $data)) === FALSE || !fflush($fp)) {
				exit('FATAL ERROR: system has ran out of disk space.');
			}
			q('UPDATE '.$tbl.'pmsg SET foff='.$off.', length='.$len.' WHERE foff='.$r[0]);
			$off += $len;

			if ($i && !($i % $pc)) {
				eta_calc($stm2, $i, $pc);
			}
			$i++;
		}
		unset($c);
	}
	fclose($fp);

	q('DROP INDEX '.$tbl.'pmsg_foff_idx'.(__dbtype__ == 'mysql' ? ' ON '.$tbl.'pmsg' : ''));

	pf('100% Done.');

	@unlink($MSG_STORE_DIR . 'private');
	if (!$i) {
		@unlink($MSG_STORE_DIR . 'private_tmp');
	} else {
		rename($MSG_STORE_DIR . 'private_tmp', $MSG_STORE_DIR . 'private');
		chmod($MSG_STORE_DIR . 'private', $mode);
	}

	db_unlock();

	pf(sprintf('All done in %.2f minutes', (time() - $stm) / 60));

	if ($FUD_OPT_1 & 1) {
		pf('Re-enabling the forum.');
		maintenance_status($DISABLED_REASON, 0);
	} else {
		echo '<br /><font size="+1" color="red">Your forum is currently disabled, to re-enable it go to the <a href="admglobal.php?'.__adm_rsid.'">Global Settings Manager</a> and re-enable it.</font>';
	}

	pf('<br /><div class="tutor">Messages successfully compacted.</div>');
	require($WWW_ROOT_DISK . 'adm/footer.php');
?>
