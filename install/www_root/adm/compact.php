<?php
/***************************************************************************
* copyright            : (C) 2001-2004 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: compact.php,v 1.45 2004/06/27 21:16:45 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; either version 2 of the License, or
* (at your option) any later version.
***************************************************************************/

	@set_time_limit(6000);

	define('back_to_main', 1);

	require('./GLOBALS.php');
	// uncomment the lines below if you wish to run this script via command line
	// fud_use('adm_cli.inc', 1); // this contains cli_execute() function.
	// cli_execute(1);

	fud_use('db.inc');
	fud_use('fileio.inc');
	fud_use('adm.inc', true);
	fud_use('private.inc');
	fud_use('glob.inc', true);
	fud_use('imsg_edt.inc');

	include($WWW_ROOT_DISK . 'adm/admpanel.php');

	if (!isset($_POST['conf'])) {
?>
<form method="post" action="compact.php">
<div class="alert">
The compactor will rebuild the storage files were the message bodies are kept. While the compactor is running
your forum will be temporarily inaccessible. This process may take a while to run, depending on your harddrive speed
and the amount of messages your forum has.
</div>
<h2>Do you wish to proceed?</h2>
<input type="submit" name="cancel" value="No">&nbsp;&nbsp;&nbsp;<input type="submit" name="conf" value="Yes">
<?php echo _hs; ?>
</form>
<?php
		readfile($WWW_ROOT_DISK . 'adm/admclose.html');
		exit;
	}
?>
<script language="Javascript1.2">
	var intervalID;
	function scrolldown()
	{
		window.scroll(0, 30000);
	}
	intervalID = setInterval('scrolldown()', 100);
</script>
<?php

function write_body_c($data, $i, &$len, &$offset)
{
	$MAX_FILE_SIZE = 2147483647;
	$len = strlen($data);

	if (!isset($GLOBALS['__FUD_TMP_F__'])) {
		$GLOBALS['__FUD_TMP_F__'][$i][0] = fopen($GLOBALS['MSG_STORE_DIR'] . 'tmp_msg_'.$i, 'ab');
		flock($GLOBALS['__FUD_TMP_F__'][$i][0], LOCK_EX);
		$GLOBALS['__FUD_TMP_F__'][$i][1] = __ffilesize($GLOBALS['__FUD_TMP_F__'][$i][0]);
	}
	while ($GLOBALS['__FUD_TMP_F__'][$i][1] + $len > $MAX_FILE_SIZE) {
		$i++;
		$GLOBALS['__FUD_TMP_F__'][$i][0] = fopen($GLOBALS['MSG_STORE_DIR'] . 'tmp_msg_'.$i, 'ab');
		flock($GLOBALS['__FUD_TMP_F__'][$i][0], LOCK_EX);
		$GLOBALS['__FUD_TMP_F__'][$i][1] = __ffilesize($GLOBALS['__FUD_TMP_F__'][$i][0]);
	}
	if (fwrite($GLOBALS['__FUD_TMP_F__'][$i][0], $data) != $len || !fflush($GLOBALS['__FUD_TMP_F__'][$i][0])) {
		exit("FATAL ERROR: system has ran out of disk space<br>\n");
	}
	$offset = $GLOBALS['__FUD_TMP_F__'][$i][1];
	$GLOBALS['__FUD_TMP_F__'][$i][1] += $len;

	return $i;
}

function eta_calc($start, $pos, $pc)
{
	$cur = time();
	$prg = $pos / $pc;
	$eta = ($cur - $start) / $prg * (10 - $prg);
	if ($eta > 60) {
		echo ($prg * 10) . "% done<br>\nETA: ".sprintf('%.2f', $eta/60)." minutes<br>\n";
	} else {
		echo ($prg * 10) . "% done<br>\nETA: " . $eta . " seconds<br>\n";
	}
}

	if ($FUD_OPT_1 & 1) {
		echo '<br>Disabling the forum for the duration of maintenance run<br>';
		maintenance_status('Undergoing maintenance, please come back later.', 1);
	}

	echo "<br>Please wait while forum is being compacted.<br>This may take a while depending on the size of your forum.<br>\n";

	define('__file_perms__', ($FUD_OPT_2 & 8388608 ? 0600 : 0666));

	/* Normal Messages */
	echo "Compacting normal messages...<br>\n";

	$tbl =& $DBHOST_TBL_PREFIX;
	$base = $magic_file_id = 10000001;
	$base -= 1;
	$pc = round(q_singleval('SELECT count(*) FROM '.$tbl.'msg WHERE file_id<'.$magic_file_id) / 10);
	$i = 0;
	$stm = time();
	if ($pc) {
		db_lock($tbl.'msg m WRITE, '.$tbl.'thread t WRITE, '.$tbl.'forum f WRITE, '.$tbl.'msg WRITE');
		$c = q('SELECT m.id, m.foff, m.length, m.file_id, f.message_threshold FROM '.$tbl.'msg m INNER JOIN '.$tbl.'thread t ON m.thread_id=t.id INNER JOIN '.$tbl.'forum f ON t.forum_id=f.id WHERE m.file_id<'.$magic_file_id);
		while ($r = db_rowarr($c)) {
			if ($r[4] && $r[2] > $r[4]) {
				$m1 = $magic_file_id = write_body_c(($body = read_msg_body($r[1], $r[2], $r[3])), $magic_file_id, $len, $off);
				$magic_file_id = write_body_c(trim_html($body, $r[4]), $magic_file_id, $len2, $off2);
				q('UPDATE '.$tbl.'msg SET foff='.$off.', length='.$len.', file_id='.$m1.', file_id_preview='.$magic_file_id.', offset_preview='.$off2.', length_preview='.$len2.' WHERE id='.$r[0]);
			} else {
				$magic_file_id = write_body_c(read_msg_body($r[1], $r[2], $r[3]), $magic_file_id, $len, $off);
				q('UPDATE '.$tbl.'msg SET foff='.$off.', length='.$len.', file_id='.$magic_file_id.' WHERE id='.$r[0]);
			}
			if ($i && !($i % $pc)) {
				eta_calc($stm, $i, $pc);
			}
			$i++;
		}
		unset($c);
		un_register_fps();

		if (isset($GLOBALS['__FUD_TMP_F__'])) {
			foreach ($GLOBALS['__FUD_TMP_F__'] as $f) {
				fclose($f[0]);
			}
		}
		$magic_file_id++;
		/* rename our temporary files & update the database */
		q('UPDATE '.$tbl.'msg SET file_id=file_id-'.$base.' WHERE file_id>'.$base);
		q('UPDATE '.$tbl.'msg SET file_id_preview=file_id_preview-'.$base.' WHERE file_id_preview>'.$base);
		$j = $base + 1;
		$u = umask(0);
		for ($j; $j < $magic_file_id; $j++) {
			$mode = fileperms($MSG_STORE_DIR . 'msg_'.($j - $base));
			rename($MSG_STORE_DIR . 'tmp_msg_'.$j, $MSG_STORE_DIR . 'msg_'.($j - $base));
			chmod($MSG_STORE_DIR . 'msg_'.($j - $base), $mode);
		}
		umask($u);
		$j = $magic_file_id - $base;
		while (@file_exists($MSG_STORE_DIR . 'msg_' . $j)) {
			@unlink($MSG_STORE_DIR . 'msg_' . $j++);
		}
		db_unlock();
	}
	/* Private Messages */
	echo "100% Done<br>\n";
	echo "Compacting private messages...<br>\n";

	if (__dbtype__ == 'mysql') {
		q('ALTER TABLE '.$tbl.'pmsg ADD INDEX(foff)');
	} else {
		q('CREATE INDEX '.$tbl.'pmsg_foff_idx ON '.$tbl.'pmsg (foff)');
	}

	db_lock($tbl.'pmsg WRITE');
	$i = $off = $len = 0;
	$stm2 = time();
	$fp = fopen($MSG_STORE_DIR.'private_tmp', 'wb');
	$pc = round(q_singleval('SELECT count(*) FROM '.$tbl.'pmsg') / 10);
	$c = q('SELECT distinct(foff), length FROM '.$tbl.'pmsg');

	while ($r = db_rowarr($c)) {
		if (($len = fwrite($fp, read_pmsg_body($r[0], $r[1]))) != $r[1] || !fflush($fp)) {
			exit("FATAL ERROR: system has ran out of disk space<br>\n");
		}
		q('UPDATE '.$tbl.'pmsg SET foff='.$off.', length='.$len.' WHERE foff='.$r[0]);
		$off += $len;

		if ($i && !($i % $pc)) {
			eta_calc($stm2, $i, $pc);
		}
		$i++;
	}
	unset($c);
	fclose($fp);

	if (__dbtype__ == 'mysql') {
		q('ALTER TABLE '.$tbl.'pmsg DROP index foff');
	} else {
		q('DROP INDEX '.$tbl.'pmsg_foff_idx');
	}

	echo "100% Done<br>\n";

	if (!$i) {
		@unlink($MSG_STORE_DIR . 'private_tmp');
		@unlink($MSG_STORE_DIR . 'private');
	} else {
		$u = umask(0);
		$mode = fileperms($MSG_STORE_DIR . 'private');
		rename($MSG_STORE_DIR . 'private_tmp', $MSG_STORE_DIR . 'private');
		chmod($MSG_STORE_DIR . 'private', $mode);
		umask($u);
		@chmod($MSG_STORE_DIR . 'private', __file_perms__);
	}

	db_unlock();

	printf("Done in %.2f minutes<br>\n", (time() - $stm) / 60);

	if ($FUD_OPT_1 & 1) {
		echo '<br>Re-enabling the forum.<br>';
		maintenance_status($DISABLED_REASON, 0);
	} else {
		echo '<br><font size="+1" color="red">Your forum is currently disabled, to re-enable it go to the <a href="admglobal.php?'.__adm_rsidl.'">Global Settings Manager</a> and re-enable it.</font>';
	}

	echo '<script language="Javascript1.2">clearInterval(intervalID);</script>';
	readfile($WWW_ROOT_DISK . 'adm/admclose.html');
?>
