<?php
/***************************************************************************
* copyright            : (C) 2001-2004 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: nntp.inc,v 1.47 2004/06/07 15:24:53 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; either version 2 of the License, or
* (at your option) any later version.
***************************************************************************/

class fud_nntp
{
	var $server, $port=119, $user, $pass, $timeout=25, $newsgroup;
	var $error, $lrc;
	var $fs;
	var $group_na, $group_first, $group_last, $nntp_opt;
	// Per Message Varaibles
	var $headers, $body;
	var $raw_msg;
	var $msg_id, $reply_to, $reply_to_msg_id, $from_email, $from_name, $ip, $thread_id;
	var $user_id;
	var $attachments;

	function get_return_code()
	{
		$this->lrc = fgets($this->fs, 1024);
		return trim(substr($this->lrc, 0, strpos($this->lrc,' ')));
	}

	function compare_return_codes($code, $str='')
	{
		if (empty($str)) {
			$str = $this->get_return_code();
		}

		return ($str == $code);
	}

	function auth_cmd()
	{
		if ($this-> nntp_opt & 128) {
			switch ($this->cmd('AUTHINFO user '.$this->user, false)) {
				case 281:
					return true;
					break 2;
				case 381:
					if ($this->cmd('AUTHINFO pass '.$this->pass, false) != 281) {
						$this->error = "Authentication failed\n";
						return false;
					} else {
						return true;
					}
					break;
				default:
					return false;
					break;
			}
		} else if (!($this->nntp_opt & 64)) {
			if ($this->cmd('AUTHINFO SIMPLE', false) != 350) {
				$this->error = "Authentication failed\n";
				return false;
			} else {
				if ($this->cmd($this->user." ".$this->pass, false) == 250) {
					return true;
				} else {
					return false;
				}
			}
		} else {
			$this->error = "NNTP Authentication required, but no authentication method specified\n";
			return false;
		}

		return false;
	}

	function cmd($cmd, $auth=true)
	{
		fputs($this->fs, $cmd."\r\n");
		$code = $this->get_return_code();
		if ($auth && ($code == 450 || $code == 480)) {
			if (!$this->auth_cmd()) {
				return false;
			}
			$code = $this->cmd($cmd, false);
		}
		return $code;
	}

	function connect()
	{
		$this->fs = @fsockopen($this->server, $this->port, $errno, $errstr, $this->timeout);
		if (!@is_resource($this->fs)) {
			$this->error = "Unable to establish connection to $this->server on port $this->port failed\nWith Error #$errno : $errstr\n";
			return false;
		}
		if (!socket_set_blocking($this->fs, true)) {
			$this->error = "Unable to make socket to blocking mode\n";
			return false;
		}

		$ret = $this->get_return_code();

		if (!$this->compare_return_codes(200, $ret) && !$this->compare_return_codes(201, $ret)) {
			$this->error = "Failed to recieve proper response from NNTP Server, got ".$this->lrc."\n";
			return false;
		}

		$ret = $this->cmd("MODE reader");

		if (!$this->compare_return_codes(200, $ret) && !$this->compare_return_codes(201, $ret)) {
			$this->error = "Failed to recieve proper response from NNTP Server when setting 'MODE reader', got ".$this->lrc."\n";
			return false;
		}

		if (($this->cmd("GROUP ".$this->newsgroup) != 211)) {
			$this->error = "Unable to use ".$this->newsgroup." newsgroup NTTP Msg: ".$this->lrc."\n";
			return false;
		} else {
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
		$this->attachments=$this->user_id=$this->headers=$this->body=$this->raw_msg=$this->msg_id=$this->reply_to_msg_id=$this->from_email=$this->from_name=$this->ip=null;

		if ($this->cmd("ARTICLE $id") != 220) {
			$this->error = "Unable to Fetch Article #".$id.", NTTP Msg: ".$this->lrc."\n";
			return false;
		}
		while (!feof($this->fs)) {
			$line = fgets($this->fs, 1024);
			if (!$line || $line == ".\r\n" || $line == ".\n") {
				break;
			}
			$this->raw_msg .= $line;
		}

		if (!preg_match("!^(.*?)\r?\n\r?\n(.*)!s", $this->raw_msg, $m)) {
			return false;
		}
		$this->body = trim($m[2]);
		$this->headers = trim($m[1]);

		return true;
	}

	function close_connection()
	{
		if (@is_resource($this->fs)) {
			unset($this->fs);
		}
	}

	function format_headers()
	{
		$this->headers = str_replace("\r\n", "\n", $this->headers);
		$hdr = explode("\n", trim($this->headers));
		$this->headers = array();
		foreach($hdr as $v) {
			$hk = substr($v, 0, ($p = strpos($v, ':')));
			// Skip non-valid header lines
			if (empty($hk) || ($v[++$p] != ' ' && $v[$p] != "\t")) {
				continue;
			}

			$hk = strtolower(trim($hk));

			$this->headers[$hk] = trim(substr($v, $p));
		}

		// Fetch Message ID
		if (isset($this->headers['message-id'])) {
			$this->msg_id = substr(trim($this->headers['message-id']), 1, -1);
		} else {
			nntp_error_log("No message id", $this->raw_msg);
		}

		// This fetches the id of the message if this is a reply to an existing message
		if (!empty($this->headers['references']) && preg_match_all('!<?([^\s<>]+)>?!', trim($this->headers['references']), $match)) {
			$this->reply_to_msg_id = array_reverse($match[1]);
		} else if (!empty($this->headers['reply-to']) && preg_match('!.*<?([^>]+)>?$!', trim($this->headers['reply-to']), $match)) {
			$this->reply_to_msg_id = array($match[1]);
		}

		// Fetch From email and Possible name
		if (preg_match('!(.*?)<(.*?)>!', $this->headers['from'], $matches)) {
			$this->from_email = trim($matches[2]);

			if (!empty($matches[1])) {
				$matches[1] = trim($matches[1]);
				if ($matches[1][0] == '"' && substr($matches[1], -1) == '"') {
					$this->from_name = substr($matches[1], 1, -1);
				} else {
					$this->from_name = $matches[1];
				}
			} else {
				$this->from_name = $this->from_email;
			}
			if (preg_match('![^A-Za-z0-9\-_\s]!', $this->from_name)) {
				$this->from_name = substr($this->from_email, 0, strpos($this->from_email, '@'));
			}
		} else {
			$this->from_email = trim($this->headers['from']);
			$this->from_name = substr($this->from_email, 0, strpos($this->from_email, '@'));
		}

		$this->subject = htmlspecialchars(trim(decode_header_value($this->headers['subject'])));

		// Attempt to Get Poster's IP from fields commonly used to store it
		if (isset($this->headers['nntp-posting-host'])) {
			$this->ip = parse_ip($this->headers['nntp-posting-host']);
		}
		if (!$this->ip && isset($this->headers['x-trace'])) {
			$this->ip = parse_ip($this->headers['x-trace']);
		}
		if (!$this->ip && isset($this->headers['path'])) {
			$this->ip = parse_ip($this->headers['path']);
		}
	}

	function fud_uudecode($data)
	{
		$data = trim($data);
		// begin 0-7{3} (.*)\r\n (filename)
		if (strncmp($data, 'begin', 5)) {
			return;
		}
		$filename = substr($data, 0, ($e=strpos($data, "\n")));
		$filename = substr($filename, strpos($filename, " ", 6)+1);

		if (($e2 = strrpos($data, 'end')) === false) {
			return;
		}
		$data = trim(substr($data, $e, ($e2-$e)));
		$tmp = explode("\n", $data);

		$out = '';
		foreach($tmp as $line) {
			$p = 0;
			$n = ((ord($line[$p]) -32) & 077);
			if ($n <= 0) {
				break;
			}

			for (++$p; $n > 0; $n -= 3) {
				if ($n >= 3) {
					$out .= chr(((ord($line[$p++]) - 32) & 077) << 2 | ((ord($line[$p]) - 32) & 077) >> 4);
					$out .= chr(((ord($line[$p++]) - 32) & 077) << 4 | ((ord($line[$p]) - 32) & 077) >> 2);
					$out .= chr(((ord($line[$p++]) - 32) & 077) << 6 | ((ord($line[$p++]) - 32) & 077));
				} else {
					if ($n >= 1) {
						$out .= chr(((ord($line[$p]) - 32) & 077) << 2 | ((ord($line[$p+1]) - 32) & 077) >> 4);
					}
					if ($n >= 2) {
						$out .= chr(((ord($line[$p+1]) - 32) & 077) << 4 | ((ord($line[$p+2]) - 32) & 077) >> 2);
					}

					$p += 4;
				}
			}
		}
		$this->attachments[$filename] = $out;
	}

	function fud_base64decode($data)
	{
		if (strncmp($data, 'begin-base64', 12)) {
			return;
		}

		$filename = substr($data, 0, ($e=strpos($data, "\n")));
		$filename = substr($filename, strpos($filename, " ", 13)+1);

		if (($e2 = strpos($data, "====", $e)) === false) {
			return;
		}

		$data = trim(substr($data, $e, ($e2-$e)));
		$data = str_replace("\r", "", $data);

		$this->attachments[$filename] = base64_decode($data);
	}

	function parse_attachments()
	{
		// uu encoded
		$s = 0;
		while (($s = @strpos($this->body, "begin ", $s)) !== false) {
			if (!$s || ($this->body[$s - 2] == "\r" && $this->body[$s - 1] == "\n")) {
				if (($e = strpos($this->body, "\r\nend\r\n", $s)) === false) {
					if (substr($this->body, -3) == 'end') {
						$e = strlen($this->body) - 5;
					} else {
						$s += 6;
						continue;
					}
				}
				if ($this->nntp_opt & 8) {
					$this->fud_uudecode(str_replace("\r", "", substr($this->body, $s, ($e + 5 - $s))));
				}
				$this->body = substr($this->body, 0, $s) . substr($this->body, ($e + 7));

				$s = 0;
			}
			$s += 6;
		}

		// base64 encoded
		$s = 0;
		while (($s = strpos($this->body, "begin-base64 ", $s)) !== false) {
			if (!$s || ($this->body[$s - 2] == "\r" && $this->body[$s - 1] == "\n")) {
				if (($e = strpos($this->body, "====\r\n", $s)) === false) {
					if (substr($this->body, -4) == '====') {
						$e = strlen($this->body) - 4;
					} else {
						$s += 13;
						continue;
					}
				}
				if ($this->nntp_opt & 8) {
					$this->fud_base64decode(str_replace("\r", "", substr($this->body, $s, ($e+4-$s))));
				}
				$this->body = substr($this->body, 0, $s) . substr($this->body, ($e + 5));
				$s = 0;
			}
			$s += 13;
		}
	}

	function exit_handler()
	{
		if (!empty($this->error)) {
			$u = umask(0111);
			$fp = fopen($GLOBALS['ERROR_PATH'].".nntp/error_log", "ab");
				fwrite($fp, $this->error);
			fclose($fp);
			umask($u);
		}
		exit($this->close_connection());
	}

	function parse_msgs($frm, $nntp_adm, $start_id=0)
	{
		if (!$this->connect()) {
			$this->exit_handler();
		}

		$this->group_last++;

		if ($start_id && $start_id > $this->group_first && $start_id <= $this->group_last) {
			$this->group_first = $start_id;
		}

		for ($i = $this->group_first; $i < $this->group_last; $i++) {
			if (!$this->get_message($i)) {
				$this->error = null;
				continue;
			}

			$this->format_headers();
			$this->parse_attachments();

			$msg_post = new fud_msg_edit;

			// Handler for our own messages, which do not need to be imported.
			if (isset($this->headers['x-fudforum']) && preg_match('!([A-Za-z0-9]{32}) <([0-9]+)>!', $this->headers['x-fudforum'], $m)) {
				if ($m[1] == md5($GLOBALS['WWW_ROOT'])) {
					q("UPDATE ".sql_p."msg SET mlist_msg_id='".addslashes($this->msg_id)."' WHERE id=".intval($m[2])." AND mlist_msg_id IS NULL");
					if (db_affected()) {
						continue;
					}
				}
			}

			if (isset($this->headers['date'])) {
				$msg_post->post_stamp = strtotime($this->headers['date']);
				if ($msg_post->post_stamp <= 0 || $msg_post->post_stamp > __request_timestamp__) {
					$msg_post->post_stamp = __request_timestamp__;
				}
			}

			$msg_post->subject = apply_custom_replace($this->subject);
			$msg_post->poster_id = match_user_to_post($this->from_email, $this->from_name, $this->nntp_opt & 32, $this->user_id, $msg_post->post_stamp);
			$msg_post->body = $this->body;

			/* for anonymous users prefix 'contact' link */
			if (!$msg_post->poster_id) {
				if ($frm->forum_opt & 16) {
					$msg_post->body = "[b]Originally posted by:[/b] [email={(!empty($this->from_name) ? $this->from_name : '')}]{$this->from_email}[/email]\n\n".$msg_post->body;
				} else {
					$msg_post->body = "Originally posted by: ".str_replace('@', '&#64', $this->from_email)."\n\n".$msg_post->body;
				}
			}

			$msg_post->body = apply_custom_replace($msg_post->body);
			if ($frm->forum_opt & 16) {
				$msg_post->body = tags_to_html($msg_post->body, 'N');
			} else {
				$msg_post->body = nl2br($msg_post->body);
			}

			fud_wordwrap($msg_post->body);

			$msg_post->ip_addr = $this->ip;
			$msg_post->mlist_msg_id = addslashes($this->msg_id);
			$msg_post->attach_cnt = 0;
			$msg_post->msg_opt = 2;
			$msg_post->poll_id = 0;

			/* try to determine whether this message is a reply or a new thread */
			list($msg_post->reply_to, $msg_post->thread_id) = get_fud_reply_id(($nntp_adm->nntp_opt & 16), $frm->id, $msg_post->subject, $this->reply_to_msg_id);

			$msg_post->add($frm->id, $frm->message_threshold, 0, 0, false);

			// Handle File Attachments
			if (isset($this->attachments) && is_array($this->attachments)) {
				foreach($this->attachments as $key => $val) {
					if (!($nntp_adm->nntp_opt & 8) && (strlen($val) > $frm->max_attach_size || (isset($attach_list) && count($attach_list) > $frm->max_file_attachments) || filter_ext($key))) {
						continue;
					}

					$tmpfname = tempnam($GLOBALS['TMP'], 'FUDf_');
					$fp = fopen($tmpfname, 'wb');
					fwrite($fp, $val);
					fclose($fp);

					$id = attach_add(array('name' => $key, 'size' => strlen($val), 'tmp_name' => $tmpfname), $msg_post->poster_id, 0, 1);
					$attach_list[$id] = $id;
				}
				if (isset($attach_list)) {
					attach_finalize($attach_list, $msg_post->id);
				}
			}
			if (!($nntp_adm->nntp_opt & 1)) {
				fud_msg_edit::approve($msg_post->id, true);
			}
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
		if (!@file_exists($GLOBALS['ERROR_PATH'].'.nntp/'.$this->server.'-'.$this->newsgroup)) {
			return;
		}

		return (int) trim(file_get_contents($GLOBALS['ERROR_PATH'].'.nntp/'.$this->server.'-'.$this->newsgroup));
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
		if (!$this->connect()) {
			$this->exit_handler();
		}

		if ($this->cmd("POST") != 340) {
	        	$this->error = "Failed to recieve proper response to POST command, NNTP server replied: ".$this->lrc."\n";
			$this->exit_handler();
		}

		if ($GLOBALS['FUD_OPT_3'] & 8) {
			$from = str_replace(array('.', '@'), array('[dot]','[at]'), $from);
		}

		fputs($this->fs, "From: $from\r\n");
	        fputs($this->fs, "Newsgroups: $this->newsgroup\r\n");
	        fputs($this->fs, "Subject: $subject\r\n");

	        if ($GLOBALS['FORUM_TITLE']) {
	        	fputs($this->fs, "Organization: ".$GLOBALS['FORUM_TITLE']."\r\n");
	        }

	        fputs($this->fs, "Date: ".date("r")."\r\n");
	        fputs($this->fs, "Reply-To: $from\r\n");
	        fputs($this->fs, "Lines: ".substr_count($body,"\n")."\r\n");

	        // will be used to identify forum's messages
	        fputs($this->fs, "X-FUDforum: ".md5($GLOBALS['WWW_ROOT'])." <$forum_msg_id>\r\n");

	        if ($reply_to) {
	        	fputs($this->fs, "References: <".$reply_to.">\r\n");
	        }

	        fputs($this->fs, "User-Agent: FUDforum ".$GLOBALS['FORUM_VERSION']."\r\n");
	        fputs($this->fs, "\r\n$body\r\n");

	        // handle file attachments, if there are any
	        if (is_array($attch)) {
	        	fputs($this->fs, "\r\n");
	        	foreach ($attch as $fname => $file_data) {
	                        fputs($this->fs, uuencode($file_data, $fname));
			}
		}

		fputs($this->fs, ".\r\n");

		if (!$this->compare_return_codes(240)) {
			$this->error = "Posting Failed, NNTP Server Said: ".$this->lrc."\n";
			$this->exit_handler();
		}

		$this->close_connection();
		return true;
	}
}

function uuencode($in, $file_name)
{
	$out = 'begin 644 ' . $file_name . "\r\n";
	$n = strlen($in);

	for ($i = 0; $i < $n; $i += 3) {
		if (!($i % 45)) {
			if ($i) {
				$out .= "\r\n";
			}
			if (($i + 45) < $n) {
				$out .= 'M';
			} else {
				if (($c = ($n - $i))) {
					$out .= chr(($c & 077) + 32);
				} else {
					$out .= '`';
				}
			}
		}

		$n1 = ($i + 1 < $n) ? ord($in[$i + 1]) : 0;
		$n2 = ($i + 2 < $n) ? ord($in[$i + 2]) : 0;

		$c = ord($in[$i]) >> 2;
		$out .= $c ? chr(($c & 077) + 32) : '`';
		$c = ((ord($in[$i]) << 4) & 060) | (($n1 >> 4) & 017);
		$out .= $c ? chr(($c & 077) + 32) : '`';
		$c = (($n1 << 2) & 074) | (($n2 >> 6) & 03);
		$out .= $c ? chr(($c & 077) + 32) : '`';
		$c = $n2 & 077;
		$out .= $c ? chr(($c & 077) + 32) : '`';

	}
	return $out . "\r\n`\r\nend\r\n";
}
?>