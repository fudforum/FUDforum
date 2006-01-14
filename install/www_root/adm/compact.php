<?php
/**
* copyright            : (C) 2001-2006 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: compact.php,v 1.61 2006/01/14 18:01:56 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; either version 2 of the License, or
* (at your option) any later version.
**/

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
$GLOBALS['__FUD_TMP_F__'] = array();

function write_body_c($data, &$len, &$offset, $fid)
{
	$MAX_FILE_SIZE = 2140000000;
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
		exit("FATAL ERROR: system has ran out of disk space<br>\n");
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

	$mode = ($FUD_OPT_2 & 8388608 ? 0600 : 0666);

	/* Normal Messages */
	echo "Compacting normal messages...<br>\n";

	$tbl =& $DBHOST_TBL_PREFIX;
	$pc = ceil(q_singleval('SELECT count(*) FROM '.$tbl.'msg WHERE file_id>0') / 10);
	$i = 0;
	$stm = time();
	if ($pc) {
		db_lock($tbl.'msg m WRITE, '.$tbl.'thread t WRITE, '.$tbl.'forum f WRITE, '.$tbl.'msg WRITE');

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

		/* rename our temporary files & update the database */
		q('UPDATE '.$tbl.'msg SET file_id=-file_id, file_id_preview=-file_id_preview WHERE file_id<0');

		/* remove old message files */
		foreach (glob($MSG_STORE_DIR.'msg_*') as $f) {
			unlink($f);
		}

		/* move new message files to the new location */
		foreach ($GLOBALS['__FUD_TMP_F__'] as $k => $f) {
			fclose($GLOBALS['__FUD_TMP_F__'][$k][0]);
			rename($MSG_STORE_DIR . 'tmp_msg_'.$k, $MSG_STORE_DIR . 'msg_'.$k);
			chmod($MSG_STORE_DIR . 'msg_'.$k, $mode);
		}

		db_unlock();
	}
	/* Private Messages */
	echo "100% Done<br>\n";
	echo "Compacting private messages...<br>\n";

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
		$pc = round($pc / 10);

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
	}
	fclose($fp);

	q('DROP INDEX '.$tbl.'pmsg_foff_idx'.(__dbtype__ == 'mysql' ? ' ON '.$tbl.'pmsg' : ''));

	echo "100% Done<br>\n";

	@unlink($MSG_STORE_DIR . 'private');
	if (!$i) {
		@unlink($MSG_STORE_DIR . 'private_tmp');
	} else {
		rename($MSG_STORE_DIR . 'private_tmp', $MSG_STORE_DIR . 'private');
		chmod($MSG_STORE_DIR . 'private', $mode);
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
