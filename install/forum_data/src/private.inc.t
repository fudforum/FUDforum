<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: private.inc.t,v 1.6 2002/07/21 22:13:20 hackie Exp $
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
	var $id='';
	var $to_list='';
	var $ouser_id='';
	var $duser_id='';
	var $pdest='';
	var $ip_addr='';
	var $host_name='';
	var $post_stamp='';
	
	var $icon='';
	var $mailed='';

	var $folder_id='';

	var $subject='';
	var $attach_cnt='';
	var $smiley_disabled='';
	var $show_sig='';
	var $track='';
	var $length='';
	var $foff='';
	var $login='';
	var $ref_msg_id='';	
	var $body;
	
	function add($track='')
	{
		if ( !db_locked() ) {
			$ll = 1;
			db_lock("{SQL_TABLE_PREFIX}pmsg+");
		}
	
		$this->post_stamp = __request_timestamp__;
		$this->ip_addr = $GLOBALS['HTTP_SERVER_VARS']['REMOTE_ADDR'];
	
		if ( $GLOBALS['PUBLIC_RESOLVE_HOST'] == 'Y' ) $this->host_name = get_host($this->ip_addr);
		
		if( empty($this->mailed) ) $this->mailed = ( $this->folder_id=='SENT' ) ? 'Y' : 'N';
		
		$r = q("INSERT INTO {SQL_TABLE_PREFIX}pmsg (
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
			ref_msg_id
			)
			VALUES(
				".$this->ouser_id.",
				".$this->ouser_id.",
				".intzero($GLOBALS['recv_user_id'][0]).",
				".strnull($this->to_list).",
				".ifnull($this->ip_addr, "'0.0.0.0'").",
				".strnull($this->host_name).",
				".$this->post_stamp.",
				".strnull($this->icon).",
				'".yn($this->mailed)."',
				'DRAFT',
				'".$this->subject."',
				".intzero($this->attach_cnt).",
				'".yn($this->smiley_disabled)."',
				'".yn($this->show_sig)."',
				'".yn($this->track)."',
				".$this->post_stamp.",
				".strnull($this->ref_msg_id)."
			)");
			
		$this->id = db_lastid("{SQL_TABLE_PREFIX}pmsg", $r);
			
		list($this->foff, $this->length) = write_pmsg_body($this->body);
		
		q("UPDATE {SQL_TABLE_PREFIX}pmsg SET foff=".$this->foff.", length=".$this->length.", folder_id='".$this->folder_id."' WHERE id=".$this->id);
		
		if( $this->folder_id == 'SENT' && empty($track) ) $this->send_pmsg();
		if ( $ll ) db_unlock();
	}
	
	function send_pmsg()
	{
		while( list(, $v) = each($GLOBALS['recv_user_id']) ) {
			$r = q("INSERT INTO 
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
				".strnull($this->to_list).",
				".$this->ouser_id.",
				".ifnull($this->ip_addr, "'0.0.0.0'").",
				".strnull($this->host_name).",
				".$this->post_stamp.",
				".strnull($this->icon).",
				'Y',
				'INBOX',
				'".$this->subject."',
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
			$GLOBALS["send_to_array"][] = array($v, db_lastid("{SQL_TABLE_PREFIX}pmsg", $r));
		}	
	}
	
	function move_folder($folder_id)
	{
		if( empty($folder_id) ) return;
		q("UPDATE {SQL_TABLE_PREFIX}pmsg SET folder_id='".$folder_id."' WHERE duser_id="._uid." AND id=".$this->id);
		$this->mark_read();
	}
	
	function sync()
	{
		list($this->foff, $this->length) = write_pmsg_body($this->body);
		
		$this->post_stamp = __request_timestamp__;
		$this->ip_addr = $GLOBALS['HTTP_SERVER_VARS']['REMOTE_ADDR'];
		
		if ( $GLOBALS['PUBLIC_RESOLVE_HOST'] == 'Y' ) $this->host_name = get_host($this->ip_addr);
		
		$this->mailed = ( $this->folder_id=='SENT' ) ? 'Y' : 'N';
		
		q("UPDATE {SQL_TABLE_PREFIX}pmsg 
			SET
				to_list=".strnull($this->to_list).",
				icon=".strnull($this->icon).",
				ouser_id=".$this->ouser_id.",
				duser_id=".$this->ouser_id.",
				post_stamp=".$this->post_stamp.",
				subject='".$this->subject."',
				ip_addr='".$this->ip_addr."',
				host_name='".$this->host_name."',
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
				
		if( $this->folder_id == 'SENT' ) $this->send_pmsg();		
	}
	
	function del_pmsg($fldr_id='')
	{
		if( empty($fldr_id) ) {
			$fldr_id = q_singleval("SELECT folder_id FROM {SQL_TABLE_PREFIX}pmsg WHERE duser_id="._uid." AND id=".$this->id);
			$this->mark_read();
		}	
		if( empty($fldr_id) ) return;
			
		if( $fldr_id != 'TRASH' ) 
			$this->move_folder('TRASH');				
		else {
			$this->get($this->id, 1);
			
			q("DELETE FROM {SQL_TABLE_PREFIX}pmsg WHERE duser_id="._uid." AND id=".$this->id);
			if ( $this->attach_cnt ) {
				$res = q("SELECT location FROM {SQL_TABLE_PREFIX}attach WHERE message_id=".$this->id." AND private='Y'");
				if( db_count($res) ) {
					while ( list($loc) = db_rowarr($res) ) {
						if( file_exists($loc) ) unlink($loc);				
					}
				}
				qf($res);
				q("DELETE FROM {SQL_TABLE_PREFIX}attach WHERE message_id=".$this->id." AND private='Y'");
			}
		}	
	}
	
	function get($id, $re='')
	{
		qobj("SELECT {SQL_TABLE_PREFIX}pmsg.*,{SQL_TABLE_PREFIX}users.alias AS login FROM {SQL_TABLE_PREFIX}pmsg LEFT JOIN {SQL_TABLE_PREFIX}users ON {SQL_TABLE_PREFIX}pmsg.ouser_id={SQL_TABLE_PREFIX}users.id WHERE {SQL_TABLE_PREFIX}pmsg.duser_id="._uid." AND {SQL_TABLE_PREFIX}pmsg.id=".$id, $this);
		if( !empty($this->id) && empty($re) ) 
			$this->body = read_pmsg_body($this->foff, $this->length);
	}
	
	function send_notify_msg()
	{
		$track_msg = new fud_pmsg;
		$track_msg->ouser_id = $track_msg->duser_id = $this->ouser_id;
		$track_msg->ip_addr = $track_msg->host_name = NULL;
		$track_msg->post_stamp = __request_timestamp__;
		$track_msg->mailed='Y';
		$track_msg->folder_id='INBOX';
		$track_msg->track='N';
		$this->subject = addslashes($this->subject);
		$track_msg->subject = '{TEMPLATE: private_msg_notify_subj}';
		$track_msg->body = '{TEMPLATE: private_msg_notify_body}';
		$track_msg->add(1);
		$this->mark_notify();	
	}

	function mark_read()
	{
		q("UPDATE {SQL_TABLE_PREFIX}pmsg SET read_stamp=".__request_timestamp__." WHERE id=".$this->id);
	}
	
	function mark_notify()
	{
		q("UPDATE {SQL_TABLE_PREFIX}pmsg SET track='SENT' WHERE id=".$this->id);
	}
}

function set_nrf($nrf, $id)
{
	q("UPDATE {SQL_TABLE_PREFIX}pmsg SET nrf_status='".$nrf."' WHERE id=".$id);		
}	

function write_pmsg_body($text)
{
	$fp = fopen($GLOBALS['MSG_STORE_DIR'].'private', 'ab');
	
	if( !($s = ftell($fp)) ) $s = __ffilesize($fp);

	fwrite($fp, $text);
	fflush($fp);
	$len = (ftell($fp)-$s);
	fclose($fp);
	
	if( !$s ) chmod($GLOBALS['MSG_STORE_DIR'].'private', 0600);
	
	return array($s,$len);
}

function read_pmsg_body($offset, $length)
{
	if( empty($length) ) return;
	
	$fp = fopen($GLOBALS['MSG_STORE_DIR'].'private', 'rb');
	
	fseek($fp, $offset, SEEK_SET);
	$str = fread($fp, $length);
	
	fclose($fp);
	
	return $str;
}
?>