<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: private.inc.t,v 1.16 2003/05/16 07:20:38 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/


class fud_pmsg
{
	var	$id, $to_list, $ouser_id, $duser_id, $pdest, $ip_addr, $host_name, $post_stamp, $icon, $mailed, $folder_id,
		$subject, $attach_cnt, $smiley_disabled, $show_sig, $track, $length, $foff, $login, $ref_msg_id, $body;

	function add($track='')
	{
		if (!db_locked()) {
			$ll = 1;
			db_lock('{SQL_TABLE_PREFIX}pmsg WRITE');
		}

		$this->post_stamp = __request_timestamp__;
		$this->ip_addr = get_ip();
		$this->host_name = $GLOBALS['PUBLIC_RESOLVE_HOST'] == 'Y' ? "'".addslashes(get_host($this->ip_addr))."'" : 'NULL';

		if (!$this->mailed) {
			$this->mailed = $this->folder_id == 'SENT' ? 'Y' : 'N';
		}
		if ($this->folder_id != 'INBOX') {
			$this->read_stamp = $this->post_stamp;
		}

		list($this->foff, $this->length) = write_pmsg_body($this->body);

		$this->id = db_qid("INSERT INTO {SQL_TABLE_PREFIX}pmsg (
			ouser_id,
			duser_id,
			pdest,
			to_list,
			ip_addr,
			host_name,
			post_stamp,
			icon,
			mailed,
			folder_id,
			subject,
			attach_cnt,
			smiley_disabled,
			show_sig,
			track,
			read_stamp,
			ref_msg_id,
			foff,
			length
			)
			VALUES(
				".$this->ouser_id.",
				".$this->ouser_id.",
				".(isset($GLOBALS['recv_user_id']) ? intzero($GLOBALS['recv_user_id'][0]) : '0').",
				".strnull(addslashes($this->to_list)).",
				'".$this->ip_addr."',
				".strnull($this->host_name).",
				".$this->post_stamp.",
				".strnull($this->icon).",
				'".yn($this->mailed)."',
				'".$this->folder_id."',
				'".addslashes($this->subject)."',
				".intzero($this->attach_cnt).",
				'".yn($this->smiley_disabled)."',
				'".yn($this->show_sig)."',
				'".yn($this->track)."',
				".$this->read_stamp.",
				".strnull($this->ref_msg_id).",
				".intzero($this->foff).",
				".intzero($this->length)."
			)");
			
		if ($this->folder_id == 'SENT' && !$track) {
			$this->send_pmsg();
		}
		if (isset($ll)) {
			db_unlock();
		}
	}

	function send_pmsg()
	{
		foreach($GLOBALS['recv_user_id'] as $v) {
			$id = db_qid("INSERT INTO 
			{SQL_TABLE_PREFIX}pmsg 
			(
				to_list,
				ouser_id,
				ip_addr,
				host_name,
				post_stamp,
				icon,
				mailed,
				folder_id,
				subject,
				attach_cnt,
				show_sig,
				smiley_disabled,
				track,
				foff,
				length,
				duser_id,
				ref_msg_id
			)
			VALUES
			(
				".strnull(addslashes($this->to_list)).",
				".$this->ouser_id.",
				'".$this->ip_addr."',
				".$this->host_name.",
				".$this->post_stamp.",
				".strnull($this->icon).",
				'Y',
				'INBOX',
				'".addslashes($this->subject)."',
				".intzero($this->attach_cnt).",
				'".yn($this->show_sig)."',
				'".yn($this->smiley_disabled)."',
				'".yn($this->track)."',
				".$this->foff.",
				".$this->length.",
				".$v.",
				".strnull($this->ref_msg_id)."
			)
			");
			$GLOBALS['send_to_array'][] = array($v, $id);
		}	
	}

	function sync()
	{
		list($this->foff, $this->length) = write_pmsg_body($this->body);
		$this->post_stamp = __request_timestamp__;
		$this->ip_addr = get_ip();
		$this->host_name = $GLOBALS['PUBLIC_RESOLVE_HOST'] == 'Y' ? "'".addslashes(get_host($this->ip_addr))."'" : 'NULL';
		$this->mailed = $this->folder_id=='SENT' ? 'Y' : 'N';
		
		q("UPDATE {SQL_TABLE_PREFIX}pmsg 
			SET
				to_list=".strnull(addslashes($this->to_list)).",
				icon=".strnull($this->icon).",
				ouser_id=".$this->ouser_id.",
				duser_id=".$this->ouser_id.",
				post_stamp=".$this->post_stamp.",
				subject='".addslashes($this->subject)."',
				ip_addr='".$this->ip_addr."',
				host_name=".$this->host_name.",
				mailed='".yn($this->mailed)."',
				attach_cnt=".intzero($this->attach_cnt).",
				show_sig='".yn($this->show_sig)."',
				smiley_disabled='".yn($this->smiley_disabled)."',
				track='".yn($this->track)."',
				folder_id='".$this->folder_id."',
				foff=".$this->foff.",
				length=".$this->length."
			WHERE
				id=".$this->id);
				
		if ($this->folder_id == 'SENT') {
			$this->send_pmsg();
		}
	}
}

function set_nrf($nrf, $id)
{
	q("UPDATE {SQL_TABLE_PREFIX}pmsg SET nrf_status='".$nrf."' WHERE id=".$id);		
}	

function write_pmsg_body($text)
{
	$fp = fopen($GLOBALS['MSG_STORE_DIR'].'private', 'ab');

	if (!($s = ftell($fp))) {
		$s = __ffilesize($fp);
	}

	if (($len = fwrite($fp, $text)) !== strlen($text)) {
		exit("FATAL ERROR: system has ran out of disk space<br>\n");
	}
	fclose($fp);

	if (!$s) {
		chmod($GLOBALS['MSG_STORE_DIR'].'private', ($GLOBALS['FILE_LOCK'] == 'Y' ? 0600 : 0666));
	}

	return array($s, $len);
}

function read_pmsg_body($offset, $length)
{
	if (!$length) {
		return;
	}

	$fp = fopen($GLOBALS['MSG_STORE_DIR'].'private', 'rb');
	fseek($fp, $offset, SEEK_SET);
	$str = fread($fp, $length);
	fclose($fp);

	return $str;
}

function pmsg_move($mid, $fid, $validate)
{
	if (!$validate && !q_singleval('SELECT id FROM {SQL_TABLE_PREFIX}pmsg WHERE duser_id='._uid.' AND id='.$mid)) {
		return;		
	}

	q('UPDATE {SQL_TABLE_PREFIX}pmsg SET folder_id=\''.$fid.'\' WHERE duser_id='._uid.' AND id='.$mid);
}

function pmsg_del($mid, $fldr_id='')
{
	if (!$fldr_id && !($fldr_id = q_singleval('SELECT folder_id FROM {SQL_TABLE_PREFIX}pmsg WHERE duser_id='._uid.' AND id='.$mid))) {
		return;
	}
	if ($fldr_id != 'TRASH') {
		pmsg_move($mid, 'TRASH', FALSE);
	} else {
		q('DELETE FROM {SQL_TABLE_PREFIX}pmsg WHERE id='.$mid);
		$c = uq('SELECT id FROM {SQL_TABLE_PREFIX}attach WHERE message_id='.$mid.' AND private=\'Y\'');
		while ($r = db_rowarr($c)) {
			@unlink($GLOBALS[''] . $r[0] . '.atch');
		}
		qf($c);
		q('DELETE FROM {SQL_TABLE_PREFIX}attach WHERE message_id='.$mid.' AND private=\'Y\'');
	}
}
?>