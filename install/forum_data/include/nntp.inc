<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: nntp.inc,v 1.6 2002/08/28 20:29:16 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

class fud_nntp
{
	var $server=NULL, $port=119, $user=NULL, $pass=NULL, $timeout=25, $auth_mode=NULL, $newsgroup=NULL;
	var $error=NULL, $lrc=NULL;
	var $fs=NULL;
	var $group_na=NULL, $group_first=NULL, $group_last=NULL;
	// Per Message Varaibles
	var $headers=NULL, $body=NULL;
	var $raw_msg=NULL;
	var $msg_id=NULL, $reply_to=NULL, $reply_to_msg_id=NULL, $from_email=NULL, $from_name=NULL, $ip=NULL;
	var $user_id=NULL;
	var $attachments=NULL;
	
	function get_return_code()
	{
		$this->lrc = fgets($this->fs, 1024);
		return trim(substr($this->lrc, 0, strpos($this->lrc,' ')));
	}
	
	function compare_return_codes($code, $str='')
	{
		if( empty($str) ) $str = $this->get_return_code();
	
		return ($str == $code);
	}
	
	function auth_cmd()
	{
		switch ( $this->auth ) 
		{
			case 'ORIGINAL':
				switch( $this->cmd("AUTHINFO user ".$this->user, false) )
				{
					case 281:
						return true;
						break 2;
					case 381:
						if( $this->cmd("AUTHINFO pass ".$this->pass, false) != 281 ) {
							$this->error = "Authentication failed\n";
							return false;
						}
						else
							return true;	
						break 2;
					default:
						return false;
						break 2;		
				}
				break;
			case 'SIMPLE':
				if( $this->cmd("AUTHINFO SIMPLE", false) != 350 ) {
					$this->error = "Authentication failed\n";
					return false;
				}
				else {
					if( $this->cmd($this->user." ".$this->pass, false) == 250 )
						return true;
					else
						return false;	
				}
				break;
			case 'GENERIC':
				// Not Supported
				break;
			default:
				$this->error = "NNTP Authentication required, but no authentication method specified\n";
				return false;
				break;		
		}
		
		return false;
	}
	
	function cmd($cmd, $auth=true)
	{
		fputs($this->fs, $cmd."\r\n");
		$code = $this->get_return_code();
		if( $auth && ($code == 450 || $code == 480) ) {
			if( !$this->auth_cmd() ) return false;
		
			$code = $this->cmd($cmd, false);	
		}
		return $code;
	}
	
	function connect()
	{
		$this->fs = @fsockopen($this->server, $this->port, $errno, $errstr, $this->timeout);
		if( !@is_resource($this->fs) ) {
			$this->error = "Unable to establish connection to $this->server on port $this->port failed\nWith Error #$errno : $errstr\n";
			return false;
		}
		if( !socket_set_blocking($this->fs, true) ) {
			$this->error = "Unable to make socket to blocking mode\n";
			return false;
		}
		
		$ret = $this->get_return_code();
		
		if( !$this->compare_return_codes(200, $ret) && !$this->compare_return_codes(201, $ret) ) {
			$this->error = "Failed to recieve proper response from NNTP Server, got ".$this->lrc."\n";
			return false;
		}
		
		if( ($this->cmd("GROUP ".$this->newsgroup) != 211) ) {
			$this->error = "Unable to use ".$this->newsgroup." newsgroup NTTP Msg: ".$this->lrc."\n";
			return false;
		}
		else {
			$tmp = explode(" ", $this->lrc);
			$this->group_na = $tmp[1];
			$this->group_first = $tmp[2];
			$this->group_last = $tmp[3];
		}	
		
		return true;
	}

	function get_message($id)
	{
		// Zero the vars
		$this->attachments=$this->user_id=$this->headers=$this->body=$this->raw_msg=$this->msg_id=$this->reply_to_msg_id=$this->from_email=$this->from_name=$this->ip=NULL;
	
		if( $this->cmd("ARTICLE $id") != 220 ) {
			$this->error = "Unable to Fetch Article #$id, NTTP Msg: ".$this->lrc."\n";
			return false;
		}
		while( !feof($this->fs) ) {
			$line = fgets($this->fs, 1024);
			if( !$line || $line == ".\r\n" || $line == ".\n" ) break;
			$this->raw_msg .= $line;
		}	

		if( !preg_match("!^(.*?)\r?\n\r?\n(.*)!s", $this->raw_msg, $m) ) return false;
		$this->body = trim($m[2]);
		$this->headers = trim($m[1]);
		
		return true;
	}
	
	function parse_ip($str)
	{
		if( preg_match('!([0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3})!', $str, $m) )
			return $m[1];
		else
			return;	
	}
	
	function close_connection()
	{
		if( @is_resource($this->fs) ) unset($this->fs);
	}
	
	function format_headers()
	{
		$this->headers = str_replace("\r\n", "\n", $this->headers);
		$hdr = explode("\n", trim($this->headers));
		$this->headers = array();
		foreach($hdr as $v) {
			$hk = substr($v, 0, ($p=strpos($v, ':')));
			// Skip non-valid header lines 
			if( empty($hk) || ($v[++$p] != ' ' && $v[$p] != "\t") ) continue;
			
			$hk = strtolower(trim($hk));
			
			$this->headers[$hk] = trim(substr($v, $p));
		}
		
		// Fetch Message ID
		if( isset($this->headers['message-id']) )
			$this->msg_id = substr(trim($this->headers['message-id']), 1, -1);
		else
			nntp_error_log("No message id", $this->raw_msg);
		
		
		// This fetches the id of the message if this is a reply to an existing message
		if( !empty($this->headers['references']) && preg_match_all('!<?([^\s<>]+)>?!', trim($this->headers['references']), $match) )
			$this->reply_to_msg_id = array_reverse($match[1]);							
		else if( !empty($this->headers['reply-to']) && preg_match('!.*<?([^>]+)>?$!', trim($this->headers['reply-to']), $match) )
			$this->reply_to_msg_id = array($match[1]);
		
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
		
		$this->subject = trim($this->headers['subject']);	
		
		// Attempt to Get Poster's IP from fields commonly used to store it
		if( isset($this->headers['x-trace']) ) 
			$this->ip = $this->parse_ip($this->headers['x-trace']);
		else if( isset($this->headers['path']) ) 
			$this->ip = $this->parse_ip($this->headers['path']);
		
	}
	
	function fud_uudecode($data)
	{
		// begin 0-7{3} (.*)\r\n (filename)
		if( substr($data, 0, 5) != 'begin' ) return;
	
		$filename = substr($data, 0, ($e=strpos($data, "\n")));
		$filename = substr($filename, strpos($filename, " ", 6)+1);

		if( ($e2 = strpos($data, "end", $e)) === false ) return;
	
		$data = trim(substr($data, $e, ($e2-$e)));
		$tmp = explode("\n", $data);
	
		$out = '';
		foreach($tmp as $line) {
			$p = 0;
			$n = ((ord($line[$p]) -32) & 077);
			if( $n <= 0 ) break;
		
			for( ++$p; $n > 0; $n -= 3) {
				if( $n >= 3) {
					$out .= chr(((ord($line[$p++]) - 32) & 077) << 2 | ((ord($line[$p]) - 32) & 077) >> 4);
					$out .= chr(((ord($line[$p++]) - 32) & 077) << 4 | ((ord($line[$p]) - 32) & 077) >> 2);
					$out .= chr(((ord($line[$p++]) - 32) & 077) << 6 | ((ord($line[$p++]) - 32) & 077));
				}
				else {
					if( $n >= 1 )
						$out .= chr(((ord($line[$p]) - 32) & 077) << 2 | ((ord($line[$p+1]) - 32) & 077) >> 4);
					if( $n >= 2 )
						$out .= chr(((ord($line[$p+1]) - 32) & 077) << 4 | ((ord($line[$p+2]) - 32) & 077) >> 2);
					
					$p += 4;	
				}
			}
		}
		$this->attachments[$filename] = $out;
	}

	function fud_base64decode($data)
	{
		if( substr($data, 0, 12) != 'begin-base64' ) return;
	
		$filename = substr($data, 0, ($e=strpos($data, "\n")));
		$filename = substr($filename, strpos($filename, " ", 13)+1);

		if( ($e2 = strpos($data, "====", $e)) === false ) return;
	
		$data = trim(substr($data, $e, ($e2-$e)));
		$data = str_replace("\r", "", $data);
		
		$this->attachments[$filename] = base64_decode($data);
	}
	
	function parse_attachments()
	{
		// uu encoded
		while ( ($s = strpos($this->body, "\r\n\r\nbegin ")) !== false ) {
			if( ($e = strpos($this->body, "end\r\n", $s)) === false ) break;
			
			$this->fud_uudecode(str_replace("\r", "", substr($this->body, $s, ($e+3-$s))));
			$this->body = substr_replace($this->body, '', $s, ($e+5-$s));
		}
		
		// base64 encoded
		while ( ($s = strpos($this->body, "\r\n\r\nbegin-base64 ")) !== false ) {
			if( ($e = strpos($this->body, "====\r\n", $s)) === false ) break;
			
			$this->fud_base64decode(str_replace("\r", "", substr($this->body, $s, ($e+4-$s))));
			$this->body = substr_replace($this->body, '', $s, ($e+6-$s));
		}
	}
	
	function exit_handler()
	{
		if( !empty($this->error) ) {
			$u = umask(0111);
			$fp = fopen($GLOBALS['ERROR_PATH'].".nntp/error_log", "ab");
				fwrite($fp, $this->error);
			fclose($fp);
			umask($u);	
		}	
		exit($this->close_connection());
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
	
		$GLOBALS['EMAIL_CONFIRMATION'] = 'N';
		
		$user->email = addslashes($this->from_email);
		$user->plaintext_passwd = substr(md5(get_random_value()), 0, 8);
		$user->default_view = $GLOBALS['DEFAULT_THREAD_VIEW'];
		$user->notify_method = 'EMAIL';
		$user->gender = 'UNSPECIFIED';
		$user->email_messages = 'Y';
		$user->pm_messages = 'Y';
		$user->show_avatars = 'Y';
		$user->show_sigs = 'Y';
		$user->name = addslashes($this->from_name);
		$user->time_zone = addslashes($GLOBALS['SERVER_TZ']);
		$user->posts_ppg = $GLOBALS['POSTS_PER_PAGE'];
		$user->theme = q_singleval("SELECT id FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."themes WHERE t_default='Y' LIMIT 1");
		
		$this->user_id = $user->add_user();
		q("UPDATE ".$GLOBALS['DBHOST_TBL_PREFIX']."users SET email_conf='Y' WHERE id=".$this->user_id);
		
		db_unlock();
		
		return $this->user_id;
	}
	
	function get_fud_reply_id($complex, $forum_id)
	{
		if( empty($this->reply_to_msg_id) && $complex == 'Y' ) {
			// This is slow, but only way to match 'rouge' replies in the event no reference fields are avaliable
			if( preg_match('!(Re|Wa)\s*:(.*)$!i', $this->subject, $matches) )
				$r = q("SELECT ".$GLOBALS['DBHOST_TBL_PREFIX']."msg.id,".$GLOBALS['DBHOST_TBL_PREFIX']."msg.thread_id FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."msg INNER JOIN ".$GLOBALS['DBHOST_TBL_PREFIX']."thread ON ".$GLOBALS['DBHOST_TBL_PREFIX']."msg.thread_id=".$GLOBALS['DBHOST_TBL_PREFIX']."thread.id WHERE ".$GLOBALS['DBHOST_TBL_PREFIX']."thread.forum_id=".$forum_id." AND subject='".addslashes(trim($matches[2]))."'");
		}	
		else {
			foreach( $this->reply_to_msg_id as $reply_id ) {
				$r = q("SELECT id,thread_id FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."msg WHERE mlist_msg_id='".addslashes($reply_id)."'");
				if( is_result($r) ) break;
			}
		}	

		if( !@is_resource($r) || !@is_result($r) ) {
			$this->reply_to_msg_id = '';
			return;
		}	

		list($this->reply_to, $this->thread_id) = db_singlearr($r);
		
		return $this->reply_to;
	}
	
	
	function parse_msgs($frm, $nntp_adm, $start_id=0)
	{
		if( !$this->connect() ) $this->exit_handler();
		
		$this->group_last++;
		
		if( $start_id && $start_id > $this->group_first && $start_id <= $this->group_last )
			$this->group_first = $start_id;
		
		for( $i=$this->group_first; $i<$this->group_last; $i++ ) {
			if( !$this->get_message($i) ) {
				$this->error = NULL;
				continue;
			}	
			
			$this->format_headers();
			$this->parse_attachments();
			
			$msg_post = new fud_msg_edit;
			
			// Handler for our own messages, which do not need to be imported.
			if( isset($this->headers['x-fudforum']) && preg_match('!([A-Za-z0-9]{32}) <([0-9]+)>!', $this->headers['x-fudforum'], $m) ) {
				if( $m[1] == md5($GLOBALS['WWW_ROOT']) ) {
					q("UPDATE ".$GLOBALS['DBHOST_TBL_PREFIX']."msg SET mlist_msg_id='".addslashes($this->msg_id)."' WHERE id=".intval($m[2])." AND mlist_msg_id IS NULL");
					if( db_affected() ) continue;
				}
			}
			
			$msg_post->body = apply_custom_replace($this->body);
			if( $frm->tag_style == 'ML' ) 
				$msg_post->body = tags_to_html($msg_post->body, 'N');
			else 
				$msg_post->body = nlb2r($msg_post->body);
			
			fud_wordwrap($msg_post->body);		
			
			if( isset($this->headers['date']) ) 
				$msg_post->post_stamp = strtotime($this->headers['date']);
			
			$msg_post->subject = addslashes(htmlspecialchars(apply_custom_replace($this->subject)));
			$msg_post->poster_id = intzero($this->match_user_to_post());
	
			$msg_post->ip_addr = $this->ip;
			$msg_post->mlist_msg_id = addslashes($this->msg_id);
			
			$msg_post->attach_cnt = 0;
			$msg_post->smiley_disabled = 'Y';
			$msg_post->poll_id = 0;
			$msg_post->show_sig = 'N';
	
			if( !$this->get_fud_reply_id('Y', $frm->id) ) {
				$msg_post->add_thread($frm->id, FALSE);
				$thr = new fud_thread;
				$thr->get_by_id($msg_post->thread_id);
			}
			else {
				$msg_post->thread_id = $this->thread_id;
				$msg_post->reply_to = $this->reply_to;
				$msg_post->add($frm->id, FALSE);
			}
	
			// Handle File Attachments
			if( $nntp_adm->allow_nntp_html == 'Y' && isset($this->attachments) && is_array($this->attachments) ) {
				foreach($this->attachments as $key => $val) {
					$fa = new fud_attach;
				
					$tmpfname = tempnam ($GLOBALS['TMP'], "FUDf_");
					$fp = fopen($tmpfname, "wb");
					fwrite($fp, $val);
					fclose($fp);
		
					$fa->add($msg_post->poster_id, $msg_post->id, addslashes($key), $tmpfname);
		
					unlink($tmpfname);
				}	
			}
			if( $nntp_adm->nntp_post_apr == 'N' ) $msg_post->approve(NULL, TRUE);	
			unset($msg_post);
		}	
		$this->set_end($this->group_last);
		$this->exit_handler();
	}
	
	function get_lock()
	{	
		$u = umask(0111);
		$fp = fopen($GLOBALS['ERROR_PATH'].'.nntp/'.$this->server.'-'.$this->newsgroup.'.lock' , "wb");
		flock($fp, LOCK_EX);
		umask($u);
		
		return $fp;
	}
	
	function release_lock($fp)
	{
		fclose($fp);
	}
	
	function read_start()
	{
		if( !@file_exists($GLOBALS['ERROR_PATH'].'.nntp/'.$this->server.'-'.$this->newsgroup) ) return;
	
		$fp = fopen($GLOBALS['ERROR_PATH'].'.nntp/'.$this->server.'-'.$this->newsgroup , "rb");
		flock($fp, LOCK_EX);
		$ret = fread($fp, 1024);
		fclose($fp);
		return intval(trim($ret));
	}
	
	function set_end($val)
	{
		$u = umask(0111);
		$fp = fopen($GLOBALS['ERROR_PATH'].'.nntp/'.$this->server.'-'.$this->newsgroup , "wb");
		flock($fp, LOCK_EX);
		fwrite($fp, $val);
		fclose($fp);
		umask($u);
	}

	function post_message($subject, $body, $from, $forum_msg_id, $reply_to='', $attch='')
	{
		if( !$this->connect() ) $this->exit_handler();
		
		if( $this->cmd("POST") != 340 ) {
	        	$this->error = "Failed to recieve proper response to POST command, NNTP server replied: ".$this->lrc."\n";
			$this->exit_handler();
		}
		
		plain_text($body);
		plain_text($subject);
		
		fputs($this->fs, "From: $from\r\n");
	        fputs($this->fs, "Newsgroups: $this->newsgroup\r\n");
	        fputs($this->fs, "Subject: $subject\r\n");
	        
	        if( $GLOBALS['FORUM_TITLE'] ) fputs($this->fs, "Organization: ".$GLOBALS['FORUM_TITLE']."\r\n");
	        
	        fputs($this->fs, "Date: ".date("r")."\r\n");
	        fputs($this->fs, "Reply-To: $from\r\n");
	        fputs($this->fs, "Lines: ".substr_count($body,"\n")."\r\n");
	        
	        // will be used to identify forum's messages
	        fputs($this->fs, "X-FUDforum: ".md5($GLOBALS['WWW_ROOT'])." <$forum_msg_id>\r\n");
	        
	        if( $reply_to ) fputs($this->fs, "References: <".$reply_to.">\r\n");
	        
	        fputs($this->fs, "User-Agent: FUDforum ".$GLOBALS['FORUM_VERSION']."\r\n");
	        fputs($this->fs, "\r\n$body\r\n");
	        
	        // handle file attachments, if there are any
	        if( is_array($attch) ) {
	        	foreach( $attch as $fname => $file_data ) {
	                	$file_data = wordwrap(base64_encode($file_data), 60, "\r\n", 1);
	                        fputs($this->fs, "begin-base64 644 $fname\r\n$file_data\r\====\r\n");
			}
		}
		
		fputs($this->fs, ".\r\n");
		
		if( !$this->compare_return_codes(240) ) {
			$this->error = "Posting Failed, NNTP Server Said: ".$this->lrc."\n";
			$this->exit_handler();
		}
		
		$this->close_connection();
		return true;
	}
}
?>