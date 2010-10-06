<?php
/**
* copyright            : (C) 2001-2010 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id$
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; version 2 of the License.
**/

class fud_nntp
{
	var $server, $port=119, $user, $pass, $timeout=25, $newsgroup;
	var $error, $lrc;
	var $fs;
	var $group_na, $group_first, $group_last, $nntp_opt, $custom_sig;

	// Per message variables.
	var $raw_msg;
	var $attachments = array();

	function get_return_code()
	{
		$this->lrc = fgets($this->fs, 1024);
		return trim(substr($this->lrc, 0, strpos($this->lrc, ' ')));
	}

	function compare_return_codes($code, $str='')
	{
		if (empty($str)) {
			$str = $this->get_return_code();
		}

		return ($str == $code);
	}

	/* Log in to NNTP server with supplied username and password. */
	function auth_cmd()
	{
		if ($this-> nntp_opt & 128) {	// Auth (ORIGINAL/SIMPLE).
			switch ($this->cmd('AUTHINFO user '. $this->user, false)) {
				case 281:
					return true;
					break 2;
				case 381:
					if ($this->cmd('AUTHINFO pass '. $this->pass, false) != 281) {
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
		} else if (!($this->nntp_opt & 64)) {	// Auth NONE.
			if ($this->cmd('AUTHINFO SIMPLE', false) != 350) {
				$this->error = "Authentication failed\n";
				return false;
			} else {
				if ($this->cmd($this->user .' '. $this->pass, false) == 250) {
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

	/* Send NNTP command to the server. */
	function cmd($cmd, $auth=true)
	{
		fputs($this->fs, $cmd ."\r\n");
		$code = $this->get_return_code();
		if ($auth && ($code == 450 || $code == 480)) {
			if (!$this->auth_cmd()) {
				return false;
			}
			$code = $this->cmd($cmd, false);
		}
		return $code;
	}

	/* Connect to newsgroup server and get metadata for the group. */
	function connect()
	{
		$this->fs = @fsockopen($this->server, $this->port, $errno, $errstr, $this->timeout);
		if (!@is_resource($this->fs)) {
			$this->error = 'Unable to establish connection to '. $this->server .' on port '. $this->port ." failed\nWith Error #". $errno .': '. $errstr ."\n";
			return false;
		}
		if (!socket_set_blocking($this->fs, true)) {
			$this->error = "Unable to make socket to blocking mode\n";
			return false;
		}

		$ret = $this->get_return_code();

		if (!$this->compare_return_codes(200, $ret) && !$this->compare_return_codes(201, $ret)) {
			$this->error = 'Failed to recieve proper response from NNTP Server, got '. $this->lrc ."\n";
			return false;
		}

		$ret = $this->cmd('MODE reader');

		if (($this->cmd('GROUP '. $this->newsgroup) != 211)) {
			$this->error = 'Unable to use '. $this->newsgroup .' newsgroup NTTP Msg: '. $this->lrc ."\n";
			return false;
		} else {
			$tmp = explode(' ', $this->lrc);
			$this->group_na = $tmp[1];
			$this->group_first = $tmp[2];
			$this->group_last = $tmp[3];
		}

		return true;
	}

	/* Fetch a sengle post from a newsgroup. */
	function get_message($id)
	{
		// Zero the vars.
		$this->raw_msg = $this->attachments = null;

		if ($this->cmd('ARTICLE '. $id) != 220) {
			$this->error = 'Unable to fetch article #'. $id .', NTTP Msg: '. $this->lrc ."\n";
			return false;
		}
		while (!feof($this->fs)) {
			$line = fgets($this->fs, 1024);
			if (!$line || $line == ".\r\n" || $line == ".\n") {
				break;
			}
			$this->raw_msg .= $line;
		}

		// We may want to rather return $this->raw_msg in a future release.
		return true;
	}

	/* Close the connection. */
	function close_connection()
	{
		if (@is_resource($this->fs)) {
			unset($this->fs);
		}
	}

	/* Check if we need to log an error and/or abort processing. */
	function exit_handler($exit=1)
	{
		if (!empty($this->error)) {
			$this->log_script_error($this->error, null, 'LOG');
		}
		$this->close_connection();
		if ($exit) {
			exit($this->error);
		}
	}

	/* Acquire lock to prevent concurrent NNTP inports. */ 
	function get_lock()
	{
		$u = umask(0111);
		$fp = fopen($GLOBALS['ERROR_PATH'] .'.nntp/'. $this->server .'-'. $this->newsgroup.'. lock' , 'wb');
		flock($fp, LOCK_EX);
		umask($u);

		return $fp;
	}

	/* Release the lock. */
	function release_lock($fp)
	{
		fclose($fp);
	}

	/* Save message number as restarting point for the next run. */
	function set_tracker_end($rule_id, $tracker)
	{
		if (++$tracker > $this->group_last) {
			$tracker = $this->group_last;
		}

		q('UPDATE '. $GLOBALS['DBHOST_TBL_PREFIX'] .'nntp SET tracker = '. $tracker .' WHERE id='. $rule_id);
	}

	/* Post a message back to the USENET newsgroup. Attachemnets will be UUencoded. */
	function post_message($subject, $body, $from, $forum_msg_id, $reply_to='', $attch='')
	{
		if (!$this->connect()) {
			$this->exit_handler(0);
			return;
		}

		if ($this->cmd('POST') != 340) {
	        	$this->error = 'Failed to recieve proper response to POST command, NNTP server replied: '. $this->lrc ."\n";
			$this->exit_handler(0);
			return;
		}

		if ($GLOBALS['FUD_OPT_3'] & 8) {	// NNTP_OBFUSCATE_EMAIL
			$from = str_replace(array('.', '@'), array('[dot]','[at]'), $from);
		}

		fputs($this->fs, "From: $from\r\n");
		fputs($this->fs, "Newsgroups: $this->newsgroup\r\n");
		if (function_exists('iconv_mime_encode')) {
			fputs($this->fs, iconv_mime_encode('Subject', $subject,
				array('scheme'=>'Q', 'input-charset'=>$GLOBALS['CHARSET'], 'output-charset'=>$GLOBALS['CHARSET']))
				."\r\n"); 
		} else {
		        fputs($this->fs, "Subject: $subject\r\n");
		}

		if ($GLOBALS['FORUM_TITLE']) {
	        	fputs($this->fs, 'Organization: '. $GLOBALS['FORUM_TITLE'] ."\r\n");
		}

		fputs($this->fs, 'Date: '. date('r') ."\r\n");
		fputs($this->fs, "Reply-To: $from\r\n");
		fputs($this->fs, 'Lines: '. substr_count($body,"\n") ."\r\n");

		// Will be used to identify forum's messages. Prevent circular loads.
		fputs($this->fs, 'X-FUDforum: '. md5($GLOBALS['WWW_ROOT']) ." <$forum_msg_id>\r\n");

		// Reference parent's Message-ID so it is seen as a reply.
		if ($reply_to) {
			fputs($this->fs, 'References: <'. $reply_to .">\r\n");
		}

		fputs($this->fs, 'User-Agent: FUDforum '. $GLOBALS['FORUM_VERSION'] ."\r\n");
		fputs($this->fs, 'Content-Type: text/plain; charset='. $GLOBALS['CHARSET'] ."; format=flowed\r\n");

		// Split body at first signature.
		$sigpos = strpos($body, "\n-- \n");
		$body_before_sig = $sigpos ? substr($body, 0, $sigpos) : $body;
		$body_after_sig  = $sigpos ? substr($body, $sigpos)    : '';

		fputs($this->fs, "\r\n$body_before_sig");

		// Insert file attachments before the first signature to prevent signature pruning from removing them.
		if (is_array($attch)) {
			fputs($this->fs, "\r\n");
			foreach ($attch as $fname => $file_data) {
				fputs($this->fs, $this->uuencode($file_data, $fname));
			}
		}

		fputs($this->fs, "$body_after_sig\r\n");
		fputs($this->fs, ".\r\n");

		if (!$this->compare_return_codes(240)) {
			$this->error = 'Posting Failed, NNTP Server Said: '. $this->lrc ."\n";
			$this->exit_handler(0);
			return;
		}

		$this->close_connection();
		return true;
	}
	
	/* UUencoded an attachement. Used by post_message(). */
	function uuencode($in, $file_name)
	{
		$out = 'begin 644 '. $file_name ."\r\n";
		if (function_exists('convert_uuencode')) {
			return $out . convert_uuencode($in) ."end\r\n";
		}

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
		return $out ."\r\n`\r\nend\r\n";
	}

	/* Remove attachements from post and return body. 
	 * Attachements will be decoded and added to $this->attachments.
     */
	function parse_attachments($body)
	{
		$this->attachments = null;
		
		// UU encoded.
		$s = 0;
		while (($s = strpos($body, 'begin ', $s)) !== false) {
			if (!$s || ($body[$s - 2] == "\r" && $body[$s - 1] == "\n")) {
				if (($e = strpos($body, "\r\nend\r\n", $s)) === false) {
					if (($e = strpos($body, "\nend\n", $s)) === false) { // Invalid line endings.
						if (substr($body, -3) == 'end') {
							$e = strlen($body) - 5;
						} else {
							$s += 6;
							continue;
						}
					} else {
						$e -= 2;
					}
				}
				if ($this->nntp_opt & 8) {	// allow_nntp_attch
					$this->fud_uudecode(str_replace("\r", '', substr($body, $s, ($e + 5 - $s))));
				}
				$body = substr($body, 0, $s) . substr($body, ($e + 7));
				$s = 0;
			} else {
				$s += 6;
			}
		}

		// Base64 encoded.
		$s = 0;
		while (($s = strpos($body, 'begin-base64 ', $s)) !== false) {
			if (!$s || ($body[$s - 2] == "\r" && $body[$s - 1] == "\n")) {
				if (($e = strpos($body, "====\r\n", $s)) === false) {
					if (substr($body, -4) == '====') {
						$e = strlen($body) - 4;
					} else {
						$s += 13;
						continue;
					}
				}
				if ($this->nntp_opt & 8) {
					$this->fud_base64decode(str_replace("\r", '', substr($body, $s, ($e+4-$s))));
				}
				$body = substr($body, 0, $s) . substr($body, ($e + 5));
				$s = 0;
			}
			$s += 13;
		}

		// Send the body (witout attchements) back.
		return $body;
	}

	/* Decode a BASE64 attachement and add to $this->attachments. */
	function fud_base64decode($data)
	{
		if (strncmp($data, 'begin-base64', 12)) {
			return;
		}

		$filename = substr($data, 0, ($e=strpos($data, "\n")));
		$filename = substr($filename, strpos($filename, ' ', 13)+1);

		if (($e2 = strpos($data, '====', $e)) === false) {
			return;
		}

		$data = trim(substr($data, $e, ($e2-$e)));
		$data = str_replace("\r", '', $data);

		$this->attachments[$filename] = base64_decode($data);
	}

	/* Decode a UUEncoded attachement and add to $this->attachments. */
	function fud_uudecode($data)
	{
		$data = trim($data);
		// begin 0-7{3} (.*)\r\n (filename)
		if (strncmp($data, 'begin', 5)) {
			return;
		}
		$filename = substr($data, 0, ($e=strpos($data, "\n")));
		$filename = substr($filename, strpos($filename, ' ', 6)+1);

		if (($e2 = strrpos($data, 'end')) === false) {
			return;
		}
		if (function_exists('convert_uudecode')) {
			$this->attachments[$filename] = convert_uudecode(trim(substr($data, $e, ($e2-$e))));
		}

		$data = trim(substr($data, $e, ($e2-$e)));

		$out = '';
		foreach(explode("\n", $data) as $line) {
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
}

?>
