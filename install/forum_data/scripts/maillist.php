#!/usr/local/bin/php -q
<?php

/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: maillist.php,v 1.1 2002/07/24 12:47:18 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
****************************************************************************

****************************************************************************
*
*	Credits:
*	 Richard Heyes <richard@phpguru.org>
*	 Thank you for answering to my many questions about decoding
*	 of email messages on IRC, and for writing PEAR Mime Decoding
*	 class 'Mail Mime', from where I blantantly stole many of the
*	 concepts used in this code.
*
***************************************************************************/
	
	set_time_limit(100);

class fud_emsg
{
	var $subject, $body, $headers, $to, $from_email, $from_name, $ip, $msg_id;
	var $subject_cleanup_rgx, $body_cleanup_rgx, $subject_cleanup_rep, $body_cleanup_rep;
	var $user_id;
	var $reply_to_msg_id;
	var $reply_to, $thread_id;
	var $raw_msg;
	var $body_s, $body_sc;
	var $attachments;
	
	function read_data($data='')
	{
		if( empty($data) ) {
			$fp = fopen("php://stdin", "rb");
			while( !feof($fp) ) $this->raw_msg .= fread($fp, 10240);
			fclose($fp);
		}
		else
			$this->raw_msg = $data;
	}
	
	function split_hdr_body()
	{
		if( !preg_match("!^(.*?)\r?\n\r?\n(.*)!s", $this->raw_msg, $m) ) return;
		
		$this->body = $m[2];
		$this->headers = $m[1];
		//unset($this->raw_msg); // Free memory
	}
	
	function decode_header_value($val)
	{
		// check if string needs to be decoded
		if( !strpos($val, '?') ) return trim($val);	
		
		// Decode String
		if( preg_match_all('!(.*?)(=\?([^?]+)\?(Q|B)\?([^?]*)\?=)[[:space:]]*(.*)!i', $val, $m) ) {
			$newval = '';
			
			$c = count($m[4]);
			for( $i=0; $i<$c; $i++ ) {
				$ec_type = strtolower($m[4][$i]);
			
				if( $ec_type == 'q' )
					$newval .= $this->decode_string(str_replace('_', ' ', $m[5][$i]), 'quoted-printable');	
				else if( $ec_type == 'b' )
					$newval .= $this->decode_string($m[5][$i], 'base64');	

				if( !empty($m[5][$i]) ) $newval .= ' '.$m[6][$i];
				if( !empty($m[1][$i]) ) $newval = $m[1][$i].$newval;
			}
			$val = trim($newval);
		}
			
		return trim($val);
	}
	
	function format_header()
	{
		$this->headers = str_replace("\r\n", "\n", $this->headers);
		$this->headers = preg_replace("!\n(\t| )+!", ' ', $this->headers);
		$hdr = explode("\n", trim($this->headers));
		$this->headers = array();
		foreach ($hdr as $v) {
			$hk = substr($v, 0, ($p=strpos($v, ':')));
			// Skip non-valid header lines 
			if( empty($hk) || ($v[++$p] != ' ' && $v[$p] != "\t") ) continue;

			$hv = substr($v, $p);
			$hk = strtolower(trim($hk));
			
			if( !isset($this->headers[$hk]) ) 
				$this->headers[$hk] = $this->decode_header_value($hv);
			else
				$this->headers[$hk] .= ' '.$this->decode_header_value($hv);	
		}
	}
	
	function parse_multival_headers($val, $key)
	{
		if( ($p = strpos($val, ';')) !== false ) {
			$this->headers[$key] = strtolower(trim(substr($val, 0, $p)));
			$val = ltrim(substr($val, $p+1));
			if( !empty($val) && preg_match_all('!([-A-Za-z]+)="?(.*?)"?\s*(;|$)!', $val, $m) ) {
				$c = count($m[0]);
				for( $i=0; $i<$c; $i++ )
					$this->headers['__other_hdr__'][$key][strtolower($m[1][$i])] = $m[2][$i];
			}
		}
		else
			$this->headers[$key] = strtolower(trim($val));
	}
	
	function handle_content_headers()
	{
		// This functions performs special handling needed for parsing message data
		
		if( isset($this->headers['content-type']) ) 
			$this->parse_multival_headers($this->headers['content-type'], 'content-type');
		else {
			$this->headers['content-type'] = 'text/plain';
			$this->headers['__other_hdr__']['content-type']['charset'] = 'us-ascii';
		}		
		
		if( isset($this->headers['content-disposition']) ) 
			$this->parse_multival_headers($this->headers['content-disposition'], 'content-disposition');
		else
			$this->headers['content-disposition'] = 'inline';	
		
		if( isset($this->headers['content-transfer-encoding']) )
			$this->parse_multival_headers($this->headers['content-transfer-encoding'], 'content-transfer-encoding');
		else
			$this->headers['content-transfer-encoding'] = '7bit';
	}
	
	function boudry_split($boundry, $html)
	{
		$boundry = '--'.$boundry;
		$b_len = strlen($boundry);
	
		// Remove 1st & last boundry since they are not needed for our perpouses
		$this->body = substr($this->body, strpos($this->body, $boundry)+$b_len);
		$this->body = substr($this->body, 0, strrpos($this->body, $boundry)-$b_len-1);

		// Isolate boundry sections
		$tmp = explode($boundry, $this->body);
		
		$this->body_sc=0; 
		foreach( $tmp as $p ) { 
			// Parse inidividual body sections
			$this->body_s[$this->body_sc] = new fud_emsg;
			$this->body_s[$this->body_sc]->parse_input($html, $p, true);
			$this->body_sc++; 
		}
	}

	function decode_body($html='N')
	{
		switch( $this->headers['content-type'] ) 
		{
			case 'text/plain':
				$this->decode_message_body();
				break;
			case 'text/html':
				$this->decode_message_body();
				$this->body = ($html == 'N' ? strip_tags($this->body) : $this->body);
				break;
			case 'multipart/parallel': // Apparently same as multipart/mixed but order of body parts does not matter
	                case 'multipart/report': // RFC1892 ( 1st part is human readable, identical to multipart/mixed )
        	        case 'multipart/signed': // PGP or OpenPGP (appear same) ( 1st part is human readable )
			case 'multipart/alternative': // various alternate formats of message most common html or text
			case 'multipart/related': // ignore those, contains urls/links to 'stuff' on the net
 			case 'multipart/mixed':	
 			case 'message/rfc822': // *scary*
				if( !isset($this->headers['__other_hdr__']['content-type']['boundary']) ) {
					$this->body = '';
					return;
				}
				
				$this->boudry_split($this->headers['__other_hdr__']['content-type']['boundary'], $html);
				
				// In some cases in multi-part messages there will only be 1 body,
				// in those situations we assing that body and info to the primary message
				// and hide the fact this was multi-part message
				if( $this->body_sc == 1 ) {
					$this->body = $this->body_s[0]->body;
					$this->headers['__other_hdr__'] = $this->body_s[0]->headers['__other_hdr__'];
				}
				else if( $this->body_sc > 1 ) { 
					// We got many bodies to pick from, Yey!. Lets find something we can use,
					// preference given to 'text/plain' or if not found go for 'text/html'
					
					$final_id = $html_id = NULL;
					
					for( $i=0; $i<$this->body_sc; $i++ ) {
						switch( $this->body_s[$i]->headers['content-type'] )
						{
							case 'text/html':
								if( !isset($html_id) ) $html_id = $i;
								break;
							case 'text/plain':
								if( !isset($final_id) ) $final_id = $i;
								break;
						}
						// look if message has any attached files
						if( $this->body_s[$i]->headers['content-disposition'] == 'attachment' ) {
							// Determine the file name 
							if( isset($this->body_s[$i]->headers['__other_hdr__']['content-disposition']['filename']) ) 
								$file_name = $this->body_s[$i]->headers['__other_hdr__']['content-disposition']['filename'];
							else if( isset($this->body_s[$i]->headers['__other_hdr__']['content-type']['name']) )
								$file_name = $this->body_s[$i]->headers['__other_hdr__']['content-type']['name'];
							else // No name for file, skipping 
								continue;		
							
							$this->attachments[$file_name] = $this->body_s[$i]->body;
						}
					}
					
					if( !isset($final_id) && isset($html_id) ) 
						$final_id = $html_id;	
					
					if( isset($final_id) ) {
						$this->body = $this->body_s[$final_id]->body;
						$this->headers['__other_hdr__'] = $this->body_s[$final_id]->headers['__other_hdr__'];	
					}
					else {
						$this->body = '';
					}
					
				}
				else { // Bad mail client didn't format message properly.
					$this->body = '';
				}
				unset($this->body_s); $this->body_sc=0; /* Free Memory */
				break;
			default:
				$this->decode_message_body();
				break;
			
			// case 'multipart/digest':  will/can contain many messages, ignore for our perpouse	
		}
	}
	
	function decode_string($str, $encoding)
	{
		switch( $encoding )
		{
			case 'quoted-printable':
				// Remove soft line breaks
				$str = preg_replace("!=\r?\n!", '', $str);
			        // Replace encoded characters
	        		return preg_replace('!=([A-Fa-f0-9]{2})!e', "chr(hexdec('\\1'))", $str);
				break;
			case 'base64':
				return base64_decode($str);
				break;
			default:
				return $str;	
				break;
		}
	}
	
	function decode_message_body()
	{
		$this->body = $this->decode_string($this->body, $this->headers['content-transfer-encoding']);
	}
	
	function parse_input($html='N', $data='', $internal=false)
	{
		$this->read_data($data);
		$this->split_hdr_body();
		$this->format_header();
		$this->handle_content_headers();
		$this->decode_body($html);
	}
	
	function match_user_to_post()
	{
		/* Try to identify user by email */
		$this->user_id = q_singleval("SELECT id FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."users WHERE email='".addslashes($this->from_email)."'");

		/* If user was not found via email, try to look the user up by login */
		if( empty($this->user_id) ) {
			if( !isset($this->from_name) ) return 0;
			$this->user_id = q_singleval("SELECT id FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."users WHERE login='".addslashes($this->from_name)."'");
		}	
		
		if( empty($this->user_id) ) $this->user_id = $this->create_new_user();
		
		return $this->user_id;	
	}
	
	function create_new_user()
	{
		/* Since we assume every user created from a mailing list is already 'confirmed' 
	 	   this disables sending of the email confirmation
	 	*/
		$GLOBALS['EMAIL_CONFIRMATION'] == 'Y';
	
		db_lock($GLOBALS['DBHOST_TBL_PREFIX']."users+, ".$GLOBALS['DBHOST_TBL_PREFIX']."themes+");
	
		if( empty($this->from_name) ) 
			$login = addslashes($this->from_email);
		else
			$login = addslashes($this->from_name);	
	
		/* 
			This code ensures that creation of user does not fail in the event another user on the forum 
			is already signed up under the same login name and/or alias
		*/	
	
		$i=1;
	
		$user = new fud_user_reg;
	
		$user->login = $login;
		
		while ( bq("SELECT id FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."users WHERE login='".$user->login."'") ) $user->login = $login.'['.$i++.']';
		$alias = $user->alias = $user->login;
	
		if( $GLOBALS['USE_ALIASES'] == 'Y' ) {
			while ( bq("SELECT id FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."users WHERE alias='".$user->alias."'") ) $user->alias = $alias.'['.$i++.']';
		}
	
		$user->email = addslashes($this->from_email);
		$user->plaintext_passwd = substr(md5(get_random_value()), 0, 8);
		$user->display_email = 'N';
		$user->notify = 'N';
		$user->coppa = 'N';
		$user->email_conf = 'Y';
		$user->name = addslashes($this->from_name);
		$user->time_zone = addslashes($GLOBALS['SERVER_TZ']);
		$user->posts_ppg = $GLOBALS['POSTS_PER_PAGE'];
		$user->theme = q_singleval("SELECT id FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."themes WHERE t_default='Y' LIMIT 1");
		$user->gender = 'UNSPECIFIED';
		
		$this->user_id = $user->add_user();
		
		db_unlock();
		
		return $this->user_id;
	}
	
	function get_fud_reply_id($complex)
	{
		if( empty($this->reply_to_msg_id) && $complex == 'Y' ) {
			/* This is slow, but only way to match 'rouge' replies in the event no reference fields are avaliable */
			if( preg_match('!(Re|Wa)\s*:(.*)$!i', $this->subject, $matches) )
				$r = q("SELECT id,thread_id FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."msg WHERE subject='".addslashes(trim($matches[2]))."'");
		}	
		else 
			$r = q("SELECT id,thread_id FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."msg WHERE mlist_msg_id='".addslashes($this->reply_to_msg_id)."'");

		if( !is_resource($r) || !is_result($r) ) {
			$this->reply_to_msg_id = '';
			return;
		}	

		list($this->reply_to, $this->thread_id) = db_singlearr($r);
		
		return $this->reply_to;
	}
	
	function parse_ip($str)
	{
		if( preg_match('!([0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3})!', $str, $m) )
			return $m[1];
		else
			return;	
	}
	
	function fetch_useful_headers()
	{
		$this->subject = $this->headers['subject'];
		$this->to = $this->headers['to'];
		
		// Attempt to Get Poster's IP from fields commonly used to store it
		if( isset($this->headers['x-posted-by']) ) 
			$this->ip = $this->parse_ip($this->headers['x-posted-by']);
		else if( isset($this->headers['x-originating-ip']) ) 
			$this->ip = $this->parse_ip($this->headers['x-originating-ip']);	
		else if( isset($this->headers['x-senderip']) ) 
			$this->ip = $this->parse_ip($this->headers['x-senderip']);
		else if( isset($this->headers['x-mdremoteip']) ) 
			$this->ip = $this->parse_ip($this->headers['x-mdremoteip']);		
		else if( isset($this->headers['received']) ) 
			$this->ip = $this->parse_ip($this->headers['received']);
			
		// Fetch From email and Possible name
		if( preg_match('!(.*?)<(.*?)>!', $this->headers['from'], $matches) ) {
			$this->from_email = trim($matches[2]);
			
			if( !empty($matches[1]) ) {
				$matches[1] = trim($matches[1]);
				if( $matches[1][0] == '"' && substr($matches[1], -1) == '"' ) 
					$this->from_name = substr($matches[1], 1, -1);
				else
					$this->from_name = $matches[1];	
			}
			else
				$this->from_name = $this->from_email;
				
			if( preg_match('![^A-Za-z0-9\-_\s]!', $this->from_name) ) $this->from_name = substr($this->from_email, 0, strpos($this->from_email, '@'));
		}
		else {
			$this->from_email = trim($this->headers['from']);
			$this->from_name = substr($this->from_email, 0, strpos($this->from_email, '@'));
		}
		
		if( empty($this->from_email) || empty($this->from_name) )
			mlist_error_log("no name or email for ".$this->headers['from'], $this->raw_msg, 'ERROR');
		
		if( isset($this->headers['message-id']) ) 
			$this->msg_id = substr(trim($this->headers['message-id']), 1, -1);
		else
			mlist_error_log("No message id", $this->raw_msg);
		
		// This fetches the id of the message if this is a reply to an existing message
		if( !empty($this->headers['in-reply-to']) && preg_match('!.*<([^>]+)>$!', trim($this->headers['in-reply-to']), $match) )
			$this->reply_to_msg_id = $match[1];
		else if( !empty($this->headers['references']) && preg_match('!.*<([^>]+)>!', trim($this->headers['references']), $match) )
			$this->reply_to_msg_id = $match[1];
			
	}
	
	function clean_up_data()
	{
		if( $this->subject_cleanup_rgx ) $this->subject = trim(preg_replace($this->subject_cleanup_rgx, '', $this->subject));
		if( $this->body_cleanup_rgx ) $this->body = trim(preg_replace($this->body_cleanup_rgx, '', $this->body));
	}
}

function mlist_error_log($error, $msg_data, $level='WARNING')
{
	$error_log_path = $GLOBALS['ERROR_PATH'].'.mlist/error_log';
	$err_msg_cpy = $GLOBALS['ERROR_PATH'].'.mlist/'.time().'_'.md5($msg_data);

	if( !@is_writeable($error_log_path) ) return;
	if( !@is_writeable($err_msg_cpy) ) return;

	if( $level != 'LOG' ) {
		$fp = fopen($err_msg_cpy, "wb");
		fwrite($fp, $msg_data);
		flose($fp);
	}	
	$fp = fopen($error_log_path, "ab");
	fwrite($fp, $level." :: ".date("r")." :: $error :: $err_msg_cpy\n");
	fclose($fp);
	
	if( $level == 'ERROR' ) exit;
}
	
	/*
		SQL Work
			ALTER TABLE test_msg ADD mlist_login VARCHAR(100);
			ALTER TABLE test_msg ADD mlist_email VARCHAR(100);
			ALTER TABLE test_msg ADD mlist_msg_id VARCHAR(100);
			ALTER TABLE test_msg ADD INDEX(mlist_msg_id);
			ALTER TABLE test_msg ADD INDEX(subject);
	*/

	// To prevent init user from being called
	define('forum_debug', 1);

	include_once "GLOBALS.php";
	fud_use('err.inc');
	fud_use('db.inc');
	fud_use('imsg.inc');
	fud_use('imsg_edt.inc');
	fud_use('th.inc');
	fud_use('wordwrap.inc');
	fud_use('isearch.inc');
	fud_use('replace.inc');
	fud_use('forum.inc');
	fud_use('rev_fmt.inc');
	fud_use('iemail.inc');
	fud_use('allperms.inc');
	fud_use('post_proc.inc');
	fud_use('is_perms.inc');
	fud_use('users.inc');
	fud_use('users_reg.inc');
	fud_use('attach.inc');
	fud_use('mime.inc');
	fud_use('mlist.inc', TRUE);
	
	if( !isset($HTTP_SERVER_VARS['argv'][1]) || !is_numeric($HTTP_SERVER_VARS['argv'][1]) ) 
		exit("Missing Forum ID Paramater\n");
	
	$mlist = new fud_mlist;
	$mlist->get($HTTP_SERVER_VARS['argv'][1]);
	$forum_id = $mlist->forum_id;

	$emsg = new fud_emsg();
	
	$emsg->subject_cleanup_rgx = $mlist->subject_regex_haystack;
	$emsg->subject_cleanup_rep = $mlist->subject_regex_needle;
	$emsg->body_cleanup_rgx = $mlist->body_regex_haystack;
	$emsg->body_cleanup_rep = $mlist->body_regex_needle;
	
	$emsg->parse_input($mlist->allow_mlist_html);
	$emsg->clean_up_data();
	$emsg->fetch_useful_headers();
	
	$msg_post = new fud_msg_edit;
	$msg_post->body = apply_custom_replace($emsg->body);
	if( $mlist->allow_mlist_html == 'N' && $frm->tag_style == 'ML' ) $msg_post->body = tags_to_html($msg_post->body, 'N');
	fud_wordwrap($msg_post->body);
	$msg_post->subject = addslashes(htmlspecialchars(apply_custom_replace($emsg->subject)));
	$msg_post->poster_id = intzero($emsg->match_user_to_post());
	
	define('_uid', $msg_post->poster_id);
	$msg_post->ip_addr = $emsg->ip;
	$msg_post->mlist_msg_id = addslashes($emsg->msg_id);
	
	mlist_error_log('Importing message '.$msg_post->subject, '', 'LOG');
	
	$msg_post->attach_cnt = 0;
	$msg_post->smiley_disabled = 'Y';
	$msg_post->poll_id = 0;
	$msg_post->show_sig = 'N';
	
	if( !$emsg->get_fud_reply_id($mlist->complex_reply_match) ) {
		$msg_post->add_thread($forum_id, FALSE);
		
		$thr = new fud_thread;
		$thr->get_by_id($msg_post->thread_id);
	}
	else {
		$msg_post->thread_id = $emsg->thread_id;
		$msg_post->add_reply($emsg->reply_to, $emsg->thread_id, FALSE);
	}
	
	// Handle File Attachments
	if( $mlist->allow_mlist_attch == 'Y' && isset($emsg->attachments) && is_array($emsg->attachments) ) {
		foreach($emsg->attachments as $key => $val) {
			$fa = new fud_attach;
		
			$tmpfname = tempnam ($GLOBALS['TMP'], "FUDf_");
			$fp = fopen($tmpfname, "wb");
			fwrite($fp, $val);
			fclose($fp);
		
			$fa->add($msg_post->poster_id, $msg_post->id, addslashes($key), $tmpfname);
		
			unlink($tmpfname);
		}
	}	
	
	if( $mlist->mlist_post_apr == 'Y' ) $msg_post->approve(NULL, TRUE);
?>