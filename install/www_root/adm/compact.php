<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: compact.php,v 1.6 2002/07/22 14:53:37 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

	@set_time_limit(6000);

	define('admin_form', 1);
	define('msg_edit', 1);
	define('user_reg', 1);

	include_once "GLOBALS.php";
	
	fud_use('db.inc');
	fud_use('fileio.inc');
	fud_use('adm.inc', TRUE);
	fud_use('private.inc');
	fud_use('glob.inc', TRUE);
	fud_use('imsg.inc');
	fud_use('imsg_edt.inc');
	fud_use('replace.inc');
	
function write_body_c($data, &$len, &$offset)
{
	$MAX_FILE_SIZE = 2147483647;
	$curdir = getcwd();
	chdir($GLOBALS["MSG_STORE_DIR"]);

	$len = strlen($data);
	$i=1;
	while( $i<100 ) {
		$fp = fopen('tmp_msg_'.$i, 'ab');
		if( !($off = ftell($fp)) ) $off = __ffilesize($fp);
		if( !$off || sprintf("%u", $off+$len)<$MAX_FILE_SIZE ) break;
		fclose($fp);
		$i++;
	}
	
	if( !is_array($GLOBALS['__NEW_FILES__']) || !in_array($i, $GLOBALS['__NEW_FILES__']) ) $GLOBALS['__NEW_FILES__'][] = $i;
	
	$len = fwrite($fp, $data);
	fclose($fp);
	
	chdir($curdir);
	
	if( $len == -1 ) exit("FATAL ERROR: system has ran out of disk space<br>\n");
	$offset = $off;
	
	return $i;
}
	
	list($ses, $usr) = initadm();
	
	if( !empty($HTTP_POST_VARS['cancel']) ) {
		header("Location: admglobal.php?"._rsid);
		exit;
	}
	include('admpanel.php');

	if( empty($HTTP_POST_VARS['conf']) ) {
?>		
<form method="post" action="compact.php">
<div align="center">
The compactor will rebuild the storage files were the message bodies are kept. While the compactor is running 
your forum will be temporarily inaccessible. This process may take a while to run, depending on your harddrive speed 
and the amount of messages your forum has.<br><br>
<h2>Do you wish to proceed?</h2>
<input type="submit" name="cancel" value="No">&nbsp;&nbsp;&nbsp;<input type="submit" name="conf" value="Yes">
</div>
<?php echo _hs; ?>
</form>	
<?php	
		readfile('admclose.html');
		exit;	
	}
	
	if( $GLOBALS['FORUM_ENABLED'] == 'Y' ) {
		echo '<br>Disabling the forum for the duration of maintenance run<br>';
		maintenance_status('Undergoing maintenance, please come back later.', 'N');
	}
	
	echo "<br>Please wait while forum is being compacted.<br>This may take a while depending on the size of your forum.<br>\n";
	flush();
	
	/* Normal Messages */
	echo "Compacting normal messages...<br>\n";
	flush();
	$stm = time();
	db_lock($GLOBALS['DBHOST_TBL_PREFIX'].'msg+, '.$GLOBALS['DBHOST_TBL_PREFIX'].'thread+, '.$GLOBALS['DBHOST_TBL_PREFIX'].'forum+, '.$GLOBALS['DBHOST_TBL_PREFIX'].'replace+');
	$files = array();
	$r = q("SELECT ".$GLOBALS['DBHOST_TBL_PREFIX']."msg.id,foff,length,file_id,message_threshold
			FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."msg 
			INNER JOIN ".$GLOBALS['DBHOST_TBL_PREFIX']."thread
				ON ".$GLOBALS['DBHOST_TBL_PREFIX']."msg.thread_id=".$GLOBALS['DBHOST_TBL_PREFIX']."thread.id
			INNER JOIN ".$GLOBALS['DBHOST_TBL_PREFIX']."forum	
				ON ".$GLOBALS['DBHOST_TBL_PREFIX']."thread.forum_id=".$GLOBALS['DBHOST_TBL_PREFIX']."forum.id
			ORDER BY thread_id, id ASC");


	$rpl_arr = make_replace_array();
	$rvs_rpl_arr = make_reverse_replace_array();

	$do_replace = $do_rvs_replace = 0;

	if( is_array($rpl_arr) && count($rpl_arr['pattern']) && count($rpl_arr['replace']) ) 
		$do_replace = 1;
	if( is_array($rvs_rpl_arr) && count($rvs_rpl_arr['pattern']) && count($rvs_rpl_arr['replace']) ) 
		$do_rvs_replace = 1;	

	$ten_percent = round(db_count($r)/10);
	$i=0;

	while( $obj = db_rowobj($r) ) {
		if( empty($files[$obj->file_id]) ) $files[$obj->file_id]=1;
		
		$msg = read_msg_body($obj->foff, $obj->length, $obj->file_id);

		if( $do_rvs_replace ) $msg = preg_replace($rvs_rpl_arr['pattern'], $rvs_rpl_arr['replace'], $msg);
		if( $do_replace ) $msg = preg_replace($rpl_arr['pattern'], $rpl_arr['replace'], $msg);
		
		$file_id = write_body_c($msg, $len, $off);
		
		if ( $obj->message_threshold && $obj->message_threshold < strlen($msg) ) {
			$thres_body = trim_html($msg, $obj->message_threshold);
			$file_id_preview = write_body_c($thres_body, $length_preview, $offset_preview);
		}
		
		q("UPDATE ".$GLOBALS['DBHOST_TBL_PREFIX']."msg SET 
			foff=".$off.", 
			length=".$len.",
			file_id=".$file_id.", 
			file_id_preview=".intzero($file_id_preview).",
			offset_preview=".intzero($offset_preview).",
			length_preview=".intzero($length_preview)."
		WHERE id=".$obj->id);
		
		if( !($i%$ten_percent) && $i ) {
			echo ($i/$ten_percent*10)."% done<br>\n";
			flush();
		}	
		
		$i++;
	}
	qf($r);
	un_register_fps();
	foreach($files as $k => $v) @unlink($GLOBALS['MSG_STORE_DIR'].'msg_'.$k);
	
	if( @is_array($GLOBALS['__NEW_FILES__']) ) {
		foreach($GLOBALS['__NEW_FILES__'] as $v) {
			rename($GLOBALS['MSG_STORE_DIR'].'tmp_msg_'.$v, $GLOBALS['MSG_STORE_DIR'].'msg_'.$v);
			@chmod($GLOBALS['MSG_STORE_DIR'].'msg_'.$v,0600);
		}	
	}	
	db_unlock();
	
	/* Private Messages */
	echo "100% Done<br>\n";
	echo "Compacting private messages...<br>\n";
	flush();
	db_lock($GLOBALS['DBHOST_TBL_PREFIX'].'pmsg+, '.$GLOBALS['DBHOST_TBL_PREFIX'].'replace+');
	$off=$len=0;
	$fp = fopen($GLOBALS['MSG_STORE_DIR'].'private_tmp', 'wb');
	set_file_buffer($fp, 40960);
	
	q("ALTER TABLE ".$GLOBALS['DBHOST_TBL_PREFIX']."pmsg ADD INDEX(foff)");
	
	$r = q("SELECT distinct(foff),length FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."pmsg");
	
	$i=0;
	$ten_percent = round(db_count($r)/10);
	
	while ( $obj = db_rowobj($r) ) {
		$b = read_pmsg_body($obj->foff, $obj->length); 

		if( $do_rvs_replace ) $b = preg_replace($rvs_rpl_arr['pattern'], $rvs_rpl_arr['replace'], $b);
		if( $do_replace ) $b = preg_replace($rpl_arr['pattern'], $rpl_arr['replace'], $b);

		$len = fwrite($fp, $b);
		
		q("UPDATE ".$GLOBALS['DBHOST_TBL_PREFIX']."pmsg SET foff=".$off.", length=".$len." WHERE foff=".$obj->foff);
		
		$off += $len;
		
		if( !($i%$ten_percent) && $i ) {
			echo ($i/$ten_percent*10)."% done<br>\n";
			flush();
		}	
		
		$i++;
	}
	
	q("ALTER TABLE ".$GLOBALS['DBHOST_TBL_PREFIX']."pmsg DROP index foff");
	
	echo "100% Done<br>\n";
	flush();
	
	if( !empty($fp) ) { 
		fclose($fp);
		rename($GLOBALS['MSG_STORE_DIR'].'private_tmp', $GLOBALS['MSG_STORE_DIR'].'private');
		@chmod($GLOBALS['MSG_STORE_DIR'].'private', 0600);
	}	
	
	db_unlock();
	$etm = time();
	echo "Done (in ".(($etm-$stm)/60)." min)<br>\n";
	
	if( $GLOBALS['FORUM_ENABLED'] == 'Y' ) {
		echo '<br>Re-enabling the forum.<br>';
		maintenance_status($GLOBALS['DISABLED_REASON'], 'Y');
	}
	else 
		echo '<br><font size=+1 color="red">Your forum is currently disabled, to re-enable it go to the <a href="admglobal.php?'._rsid.'">Global Settings Manager</a> and re-enable it.</font>';

	readfile('admclose.html');
?>