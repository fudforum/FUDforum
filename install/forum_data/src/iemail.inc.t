<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: iemail.inc.t,v 1.17 2003/04/16 10:35:52 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

function validate_email($email)
{
        return !preg_match('!([-_A-Za-z0-9\.]+)\@([-_A-Za-z0-9\.]+)\.([A-Za-z0-9]{2,4})$!', $email);
}
        
class fud_email_block
{
	var $id;
	var $type;
	var $string;
	
	var $e_list;
	
	function add($type, $string)
	{
		q("INSERT INTO {SQL_TABLE_PREFIX}email_block(type, string) VALUES('".$type."', '".$string."')");
	} 
	
	function sync($type, $string)
	{
		q("UPDATE {SQL_TABLE_PREFIX}email_block SET type='".$type."', string='".$string."' WHERE id=".$this->id);
	}
	
	function get($id)
	{
		$res = qobj("SELECT * FROM {SQL_TABLE_PREFIX}email_block WHERE id=".$id, $this);
		if ( !$this->id ) exit("no such email block");
	}
	
	function delete()
	{
		q("DELETE FROM {SQL_TABLE_PREFIX}email_block WHERE id=".$this->id);
	}
	
	function getall()
	{
		$res = q("SELECT * FROM {SQL_TABLE_PREFIX}email_block ORDER BY id");
		
		unset($this->e_list);
		while ( $obj = db_rowobj($res) ) {
			$this->e_list[] = $obj;
		}
		if ( isset($this->e_list) ) reset($this->e_list);
		qf($res); 
	}
	
	function counte()
	{
		if ( !isset($this->e_list) ) return;
		return count($this->e_list);
	}
	
	function resete()
	{
		if ( !isset($this->e_list) ) return;
		reset($this->e_list);
	}
	
	function eache()
	{
		if ( !isset($this->e_list) ) return;
		$obj = current($this->e_list);
		if ( !isset($obj) ) return;
		next($this->e_list);
		
		return $obj;
	}
}

function is_email_blocked($addr)
{
	if ( bq("SELECT * FROM {SQL_TABLE_PREFIX}email_block WHERE string='".addslashes($addr)."'") ) return 1;

	$r = q("SELECT * FROM {SQL_TABLE_PREFIX}email_block ORDER BY id");
	while ( $obj = db_rowobj($r) ) {
		if( $obj->string[0] == '#' ) {
			$obj->string = substr($obj->string, 1);
			$not = 1;
		} else {
			$not = 0;
		}
		
		if( ($obj->type == 'SIMPLE' && preg_match("!".preg_quote($obj->string, '!')."!i", $addr)) || ($obj->type == 'REGEX' && preg_match("!".$obj->string."!i", $addr)) ) {
			if( !$not ) return 1;	
		} else if ( $not ) {
			return 1;
		}	
	}
	qf($r);
	
	return 0;
}

function send_email($from, $to, $subj, $body, $header='')
{
	if( empty($to) || !count($to) ) return;	
	
	if( $GLOBALS['USE_SMTP'] == 'Y' ) {
		$smtp = new fud_smtp;
		$smtp->msg = $body;
		$smtp->subject = $subj;
		$smtp->to = $to;
		$smtp->from = $from;
		$smtp->headers = $header;
		$smtp->send_smtp_email();
	} else {
		$bcc='';
	
		if( is_array($to) ) {
			if( ($a = count($to)) > 1 ) {
				$bcc = "Bcc: ";
				for( $i=1; $i<$a; $i++ ) $bcc .= $to[$i].', ';
				$bcc = substr($bcc,0,-2);	
			}		
			$to = $to[0];
		}
		if( $header ) 
			$header = "\n".str_replace("\r", "", $header);
		else if( $bcc ) 
			$bcc = "\n".$bcc;
				
		mail($to, $subj, str_replace("\r", "", $body), "From: $from\nErrors-To: $from\nReturn-Path: $from\nX-Mailer: FUDforum v".$GLOBALS['FORUM_VERSION'].$header.$bcc);
	}		
}

function make_email_message(&$body, &$obj)
{
	$TITLE_EXTRA = $iemail_poll = $iemail_attach = '';
	if ($obj->poll_cache) {
		$pl = @unserialize($obj->poll_cache);
		if (is_array($pl) && count($pl)) {
			foreach ($pl as $k => $v) {
				$length = ($v[1] && $obj->total_votes) ? round($v[1] / $obj->total_votes * 100) : 0;
				$iemail_poll .= '{TEMPLATE: iemail_poll_result}';	
			}
			$iemail_poll = '{TEMPLATE: iemail_poll_tbl}';
		}
	}
	if ($obj->attach_cnt && $obj->attach_cache) {
		$atch = @unserialize($obj->attach_cache);
		if (is_array($atch) && count($atch)) {
			foreach ($atch as $v) {
				$sz = $v[2] / 1024;
				$sz = $sz < 1000 ? number_format($sz, 2).'KB' : number_format($sz/1024, 2).'MB';
				$iemail_attach .= '{TEMPLATE: iemail_attach_entry}';
			}
			$iemail_attach = '{TEMPLATE: iemail_attach}';
		}
	}

	return '{TEMPLATE: iemail_body}';
}

function send_notifications($to, $msg_id, $thr_subject, $poster_login, $id_type, $id, $frm_name='')
{
	if (isset($to['EMAIL']) && (is_string($to['EMAIL']) || (is_array($to['EMAIL']) && count($to['EMAIL'])))) {
		$do_email = 1;
		$goto_url['email'] = '{ROOT}?t=rview&goto='.$msg_id;
		if ($GLOBALS['NOTIFY_WITH_BODY'] == 'Y') {
			
			$obj = db_sab("SELECT p.total_votes, p.name AS poll_name, m.subject, m.id, m.post_stamp, m.poster_id, m.foff, m.length, m.file_id, u.alias, m.attach_cnt, m.attach_cache, m.poll_cache FROM {SQL_TABLE_PREFIX}msg m LEFT JOIN {SQL_TABLE_PREFIX}users u ON m.poster_id=u.id LEFT JOIN {SQL_TABLE_PREFIX}poll p ON m.poll_id=p.id WHERE m.id=".$msg_id." AND m.approved='Y'");
		
			$headers  = "MIME-Version: 1.0\r\n";
			$split = get_random_value(128)                                                                            ;
			$headers .= "Content-Type: multipart/alternative; boundary=\"------------" . $split . "\"\r\n";
			$boundry = "\r\n--------------" . $split . "\r\n";
		
			$CHARSET = '{TEMPLATE: CHARSET}';
		
			$plain_text = read_msg_body($obj->foff, $obj->length, $obj->file_id);
		
			$body_email = $boundry . "Content-Type: text/plain; charset=" . $CHARSET . "; format=flowed\r\nContent-Transfer-Encoding: 7bit\r\n\r\n" . strip_tags($plain_text) . "\r\n\r\n" . '{TEMPLATE: iemail_participate}' . ' ' . '{ROOT}?t=rview&th=' . $id . "&notify=1&opt=off\r\n" . 
			$boundry . "Content-Type: text/html; charset=" . $CHARSET . "\r\nContent-Transfer-Encoding: 7bit\r\n\r\n" . make_email_message($plain_text, $obj) . "\r\n" . substr($boundry, 0, -2) . "--\r\n";
		}
	}
	if (isset($to['ICQ']) && (is_string($to['ICQ']) || (is_array($to['ICQ']) && count($to['ICQ'])))) {
		$do_icq = 1;
		$icq = str_replace('http://', "http'+'://'+'", $GLOBALS['WWW_ROOT']);
		$icq = str_replace('www.', "www'+'.", $icq);
		$goto_url['icq'] = "javascript:window.location='".$icq."{ROOT}?t=rview&goto=".$msg_id."';";
	} else if (!isset($do_email)) {
		/* nothing to do */
		return;
	}

	reverse_FMT($thr_subject);
	reverse_FMT($poster_login);
	
	if ($id_type == 'thr') {
		$subj = '{TEMPLATE: iemail_thr_subject}';
		
		if (!isset($body_email)) {
			$unsub_url['email'] = '{ROOT}?t=rview&th='.$id.'&notify=1&opt=off';
			$body_email = '{TEMPLATE: iemail_thr_bodyemail}';
		}	
		
		if (isset($do_icq)) {
			$body_icq = '{TEMPLATE: iemail_thr_bodyicq}';
			$unsub_url['icq'] = "javascript:window.location='".$icq."{ROOT}?t=rview&th=".$id."&notify=1&opt=off';";
		}
	} else if ($id_type == 'frm') {
		reverse_FMT($frm_name);

		$subj = '{TEMPLATE: iemail_frm_subject}';

		if (isset($do_icq)) {
			$unsub_url['icq'] = "javascript:window.location='".$icq."{ROOT}?t=thread&unsub=1&frm_id=".$id."';";
			$body_icq = '{TEMPLATE: iemail_frm_bodyicq}';
		}
		if (!isset($body_email)) {
			$unsub_url['email'] = '{ROOT}?t=thread&unsub=1&frm_id='.$id;
			$body_email = '{TEMPLATE: iemail_frm_bodyemail}';
		}	
	}	
	
	if (isset($do_email)) {
		send_email($GLOBALS['NOTIFY_FROM'], $to['EMAIL'], $subj, $body_email, (isset($headers) ? $headers : ''));
	}
	if (isset($do_icq)) {
		send_email($GLOBALS['NOTIFY_FROM'], $to['ICQ'], $subj, $body_icq);
	}
}
?>