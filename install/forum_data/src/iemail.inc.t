<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: iemail.inc.t,v 1.8 2002/07/29 19:10:35 hackie Exp $
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
		$res = q("SELECT * FROM {SQL_TABLE_PREFIX}email_block WHERE id=".$id);
		if ( !is_result($res) ) exit("no such email block");
		
		$obj = db_singleobj($res);
		
		$this->id 	= $obj->id;
		$this->type	= $obj->type;
		$this->string	= $obj->string;
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

	if ( bq("SELECT * FROM {SQL_TABLE_PREFIX}email_block WHERE string='".$addr."'") ) return 1;

	$r = q("SELECT * FROM {SQL_TABLE_PREFIX}email_block ORDER BY id");
	while ( $obj = db_rowobj($r) ) {
		if ( $obj->type == 'SIMPLE' ) {
			$reg = $obj->string;
			$reg = str_replace('.', '\.', $reg);
			$reg = str_replace('*', '.*?', $reg);			
			if ( preg_match("!".$reg."!i", $addr, $res) ) {
				return 1;
			}
		}
		else if ( $obj->type == 'REGEX' ) {
			$reg = $obj->string;
			if ( preg_match("!".$reg."!i", $addr, $res) ) {
				return 1;
			}	
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
	}
	else {
		$bcc='';
	
		if( is_array($to) ) {
			if( ($a = count($to)) > 1 ) {
				$bcc = "Bcc: ";
				for( $i=1; $i<$a; $i++ ) $bcc .= $to[$i].', ';
				$bcc = substr($bcc,0,-2);	
			}		
			$to = $to[0];
		}
		mail($to, $subj, $body, "From: $from\r\nErrors-To: $from\r\nReturn-Path: $from\r\nX-Mailer: FUDforum v".$GLOBALS['FORUM_VERSION']."\r\n".$header.$bcc);
	}		
}

function send_notifications($to, $msg_id, $thr_subject, $poster_login, $id_type, $id, $frm_name='')
{
	$icq = str_replace("http://", "http'+'://'+'", $GLOBALS['WWW_ROOT']);
	$icq = str_replace("www.", "www'+'.", $icq);

	$goto_url['icq'] = "javascript:window.location='".$icq."{ROOT}?t=rview&goto=".$msg_id."';";
	$goto_url['email'] = $GLOBALS['WWW_ROOT'].'{ROOT}?t=rview&goto='.$msg_id;
	
	
	if ( $GLOBALS['NOTIFY_WITH_BODY'] == 'Y' ) {
		$r = q("SELECT 
			{SQL_TABLE_PREFIX}msg.*, 
			{SQL_TABLE_PREFIX}thread.locked,
			{SQL_TABLE_PREFIX}thread.forum_id,
			{SQL_TABLE_PREFIX}avatar.img AS avatar, 
			{SQL_TABLE_PREFIX}users.id AS user_id, 
			{SQL_TABLE_PREFIX}users.alias AS login, 
			{SQL_TABLE_PREFIX}users.display_email, 
			{SQL_TABLE_PREFIX}users.avatar_approved,
			{SQL_TABLE_PREFIX}users.avatar_loc,
			{SQL_TABLE_PREFIX}users.email, 
			{SQL_TABLE_PREFIX}users.posted_msg_count, 
			{SQL_TABLE_PREFIX}users.join_date, 
			{SQL_TABLE_PREFIX}users.location, 
			{SQL_TABLE_PREFIX}users.sig,
			{SQL_TABLE_PREFIX}users.icq,
			{SQL_TABLE_PREFIX}users.jabber,
			{SQL_TABLE_PREFIX}users.aim,
			{SQL_TABLE_PREFIX}users.msnm,
			{SQL_TABLE_PREFIX}users.invisible_mode,
			{SQL_TABLE_PREFIX}users.email_messages,
			{SQL_TABLE_PREFIX}users.last_visit AS time_sec,
			{SQL_TABLE_PREFIX}users.is_mod,
			{SQL_TABLE_PREFIX}users.yahoo,
			{SQL_TABLE_PREFIX}users.custom_status,
			{SQL_TABLE_PREFIX}level.name AS level_name,
			{SQL_TABLE_PREFIX}level.pri AS level_pri,
			{SQL_TABLE_PREFIX}level.img AS level_img
		FROM 
			{SQL_TABLE_PREFIX}msg 
			LEFT JOIN {SQL_TABLE_PREFIX}users 
				ON {SQL_TABLE_PREFIX}msg.poster_id={SQL_TABLE_PREFIX}users.id 
			LEFT JOIN {SQL_TABLE_PREFIX}avatar 
				ON {SQL_TABLE_PREFIX}users.avatar={SQL_TABLE_PREFIX}avatar.id 
			INNER JOIN {SQL_TABLE_PREFIX}thread
				ON {SQL_TABLE_PREFIX}msg.thread_id={SQL_TABLE_PREFIX}thread.id	
			LEFT JOIN {SQL_TABLE_PREFIX}ses
				ON {SQL_TABLE_PREFIX}ses.user_id={SQL_TABLE_PREFIX}msg.poster_id
			LEFT JOIN {SQL_TABLE_PREFIX}level
				ON {SQL_TABLE_PREFIX}users.level_id={SQL_TABLE_PREFIX}level.id
		WHERE 
			{SQL_TABLE_PREFIX}msg.id=".$msg_id." AND {SQL_TABLE_PREFIX}msg.approved='Y'");
		
		$obj = db_singleobj($r);
		unset($obj->ip_addr);
		$headers  = "MIME-Version: 1.0\r\n";
		$split = get_random_value(128);
		$headers .= "Content-Type: multipart/alternative; boundary=\"------------$split\"\r\n";
		$boundry = "\r\n--------------$split\r\n";
		
		$CHARSET = '{TEMPLATE: CHARSET}';
		
		$plain_text = read_msg_body($obj->foff,$obj->length, $obj->file_id);
		$plain_text = $boundry."Content-Type: text/plain; charset=$CHARSET; format=flowed\r\nContent-Transfer-Encoding: 7bit\r\n\r\n".strip_tags($plain_text)."\r\n\r\nTo participate in the discussion, go here: ".$GLOBALS['WWW_ROOT'].'{ROOT}?t=rview&th='.$id."&notify=1&opt=off\r\n";
		
		$mod = $GLOBALS['MOD'];
		$GLOBALS['MOD'] = 1;
		$GLOBALS['pl_view'] = $obj->poll_id;
		$body_email = tmpl_drawmsg($obj,NULL,NULL,'');
		$GLOBALS['MOD'] = $mod;
		
		$body_email = $boundry."Content-Type: text/html; charset=$CHARSET\r\nContent-Transfer-Encoding: 7bit\r\n\r\n".'{TEMPLATE: iemail_body}'."\r\n";
		$body_email = $plain_text.$body_email.substr($boundry, 0, -2)."--\r\n";
	}
	
	reverse_FMT($thr_subject);
	reverse_FMT($poster_login);
	
	if( $id_type == 'thr' ) {
		$subj = '{TEMPLATE: iemail_thr_subject}';
		
		if( !$body_email ) {
			$unsub_url['email'] = $GLOBALS['WWW_ROOT'].'{ROOT}?t=rview&th='.$id.'&notify=1&opt=off';
			$body_email = '{TEMPLATE: iemail_thr_bodyemail}';
		}	
		
		$body_icq = '{TEMPLATE: iemail_thr_bodyicq}';
		$unsub_url['icq'] = "javascript:window.location='".$icq."{ROOT}?t=rview&th=".$id."&notify=1&opt=off';";
	}
	else if ( $id_type == 'frm' ) {
		reverse_FMT($frm_name);
		
		$subj = '{TEMPLATE: iemail_frm_subject}';
		
		$unsub_url['icq'] = "javascript:window.location='".$icq."{ROOT}?t=thread&unsub=1&frm_id=".$id."';";
		$body_icq = '{TEMPLATE: iemail_frm_bodyicq}';
		
		if( !$body_email ) {
			$unsub_url['email'] = $GLOBALS['WWW_ROOT'].'{ROOT}?t=thread&unsub=1&frm_id='.$id;
			$body_email = '{TEMPLATE: iemail_frm_bodyemail}';
		}	
	}	
	
	send_email($GLOBALS["FORUM_TITLE"].' <'.$GLOBALS['NOTIFY_FROM'].'>', $to['EMAIL'], $subj, $body_email, $headers);
	send_email($GLOBALS["FORUM_TITLE"].' <'.$GLOBALS['NOTIFY_FROM'].'>', $to['ICQ'], $subj, $body_icq);
}
?>