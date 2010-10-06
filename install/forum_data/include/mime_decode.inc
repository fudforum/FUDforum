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

class fud_mime_msg
{
	var $subject, $body, $headers, $to, $from_email, $from_name, $ip, $msg_id;
	var $subject_cleanup_rgx, $body_cleanup_rgx, $subject_cleanup_rep, $body_cleanup_rep;
	var $user_id;
	var $reply_to_msg_id;
	var $reply_to, $thread_id;
	var $raw_msg;
	var $body_section, $body_sec_cnt;
	var $attachments  = array(), $inline_files = array();

	/* Split the message headers from the body. */
	function split_hdr_body()
	{
		if (!preg_match("!^(.*?)\r?\n\r?\n(.*)!s", $this->raw_msg, $m)) {
			return;
		}

		$this->body = $m[2];
		$this->headers = $m[1];
	}

	/* Extract and format the message's headers. */
	function format_header()
	{
		$this->headers = str_replace("\r\n", "\n", $this->headers);
		// Cleanup multiline headers.
		$this->headers = preg_replace("!\n(\t| )+!", ' ', $this->headers);
		$hdr = explode("\n", trim($this->headers));
		$this->headers = array();
		foreach ($hdr as $v) {
			$hk = substr($v, 0, ($p = strpos($v, ':')));
			// Skip non-valid header lines.
			if (!$hk || ++$p == strlen($v) || ($v{$p} != ' ' && $v{$p} != "\t")) {
				continue;
			}

			$hv = substr($v, $p);
			$hk = strtolower(trim($hk));

			if (!isset($this->headers[$hk])) {
				$this->headers[$hk] = decode_header_value($hv);
			} else {
				$this->headers[$hk] .= ' '. decode_header_value($hv);
			}
		}
	}

	/* Get parts of multi-value headers like 'Content-Type: text/plain; charset=ISO-8859-2'. */
	function parse_multival_headers($val, $key)
	{
		if (($p = strpos($val, ';')) !== false) {
			$this->headers[$key] = strtolower(trim(substr($val, 0, $p)));
			$val = ltrim(substr($val, $p+1));
			if (!empty($val) && preg_match_all('!([-A-Za-z]+)="?(.*?)"?\s*(?:;|$)!', $val, $m)) {
				$c = count($m[0]);
				for ($i=0; $i<$c; $i++) {
					$this->headers['__other_hdr__'][$key][strtolower($m[1][$i])] = $m[2][$i];
				}
			}
		} else {
			$this->headers[$key] = strtolower(trim($val));
		}
	}

	/* This functions performs special handling needed for parsing message data. */
	function handle_content_headers()
	{
		if (isset($this->headers['content-type'])) {
			$this->parse_multival_headers($this->headers['content-type'], 'content-type');
		} else {
			$this->headers['content-type'] = 'text/plain';
			$this->headers['__other_hdr__']['content-type']['charset'] = 'utf-8';
		}

		if (isset($this->headers['content-disposition'])) {
			$this->parse_multival_headers($this->headers['content-disposition'], 'content-disposition');
		} else {
			$this->headers['content-disposition'] = 'inline';
		}
		if (isset($this->headers['content-transfer-encoding'])) {
			$this->parse_multival_headers($this->headers['content-transfer-encoding'], 'content-transfer-encoding');
		} else {
			$this->headers['content-transfer-encoding'] = '7bit';
		}
	}

	/* Split message by mime boundaries. */
	function boundary_split($boundary, $html)
	{
		// Isolate boundary sections.
		$this->body_sec_cnt = 0;
		foreach (explode('--'. $boundary, $this->body) as $p) {
			if (!trim($p)) continue;
			// Parse inidividual body sections.
			$this->body_section[$this->body_sec_cnt] = new fud_mime_msg;
			$this->body_section[$this->body_sec_cnt++]->parse_message($p, $html);
		}
	}

	/* Decide what to do based on Content-Type. */
	function decode_body($html=0)
	{
		switch ($this->headers['content-type']) {
			case 'text/plain':
				$this->decode_message_body();
				break;

			case 'text/html':
				$this->decode_message_body();
				if (!$html) {
					$this->body = str_ireplace('<p>', "\n\n", $this->body);
					$this->body = str_ireplace(array('<br />', '<br>', '</p>'), "\n", $this->body);
					$this->body = strip_tags($this->body);
				}
				break;

			case 'multipart/parallel': // Apparently same as multipart/mixed but order of body parts does not matter.
	                case 'multipart/report': // RFC1892 (1st part is human readable, identical to multipart/mixed).
        	        case 'multipart/signed': // PGP or OpenPGP (appear same) (1st part is human readable).
			case 'multipart/alternative': // Various alternate formats of message most common html or text.
			case 'multipart/related': // Ignore those, contains urls/links to 'stuff' on the net.
 			case 'multipart/mixed':
 			case 'message/rfc822': // *scary*

				if (!isset($this->headers['__other_hdr__']['content-type']['boundary'])) {
					$this->body = '';
					return;
				}
				$this->boundary_split($this->headers['__other_hdr__']['content-type']['boundary'], $html);
				// In some cases in multi-part messages there will only be 1 body,
				// in those situations we assing that body and info to the primary message
				// and hide the fact this was multi-part message.
				if ($this->body_sec_cnt == 1) {
					$this->body = $this->body_section[0]->body;
					$this->headers['__other_hdr__'] = $this->body_section[0]->headers['__other_hdr__'];
				} else if ($this->body_sec_cnt > 1) {
					// We got many bodies to pick from, Yey!. Lets find something we can use,
					// preference given to 'text/plain' or if not found go for 'text/html'.
					$final_id = $html_id = array();

					for ($i = 0; $i < $this->body_sec_cnt; $i++) {
						switch ($this->body_section[$i]->headers['content-type']) {
							case 'text/html':
								$html_id[] = $i;
								break;

							case 'text/plain':
								$final_id[] = $i;
								break;
						}

						// Some attachemets should not be inported.
						if ($this->body_section[$i]->headers['content-type'] == 'application/pgp-signature') {
							continue;
						}

						// Look if message has any attached files.
						if ($this->body_section[$i]->headers['content-disposition'] == 'attachment' || 
								$this->body_section[$i]->headers['content-disposition'] == 'inline' ||
								isset($this->body_section[$i]->headers['content-id'])) {
							// Determine the file name.
							if (isset($this->body_section[$i]->headers['__other_hdr__']['content-disposition']['filename'])) {
								$file_name = $this->body_section[$i]->headers['__other_hdr__']['content-disposition']['filename'];
							} else if (isset($this->body_section[$i]->headers['__other_hdr__']['content-type']['name'])) {
								$file_name = $this->body_section[$i]->headers['__other_hdr__']['content-type']['name'];
							} else { // No name for file, skipping.
								continue;
							}

							$this->attachments[$file_name] = $this->body_section[$i]->body;
							if (isset($this->body_section[$i]->headers['content-id']) && $this->body_section[$i]->headers['content-disposition'] == 'inline') {
								$this->inline_files[$file_name] = trim($this->body_section[$i]->headers['content-id'], ' <>');
							}
						}
					}
					if (($html || !$final_id) && $html_id) {
						$final_id = $html_id;
					}
					if ($final_id) {
						$this->body = '';
						foreach ($final_id as $fid) {
							$this->body .= $this->body_section[$fid]->body;
							foreach ($this->body_section[$fid]->attachments as $k => $v) {
								$this->attachments[$k] = $v;
							}
							foreach ($this->body_section[$fid]->inline_files as $k => $v) {
								$this->inline_files[$k] = $v;
							}
						}
						if (isset($this->body_section[$final_id[0]]->headers['__other_hdr__'])) {
							$this->headers['__other_hdr__'] = $this->body_section[$final_id[0]]->headers['__other_hdr__'];
						}
						$this->headers['content-type'] = $this->body_section[$final_id[0]]->headers['content-type'];
					} else {
						$this->body = '';
					}
				} else { // Bad mail client didn't format message properly.
					$this->body = '';
				}
				break;

			default:
				$this->decode_message_body();
				break;

			// case 'multipart/digest':  // Can contain many messages, ignore for our purpose.
		}
	}

	/* Decode the message body based on charset. */
	function decode_message_body()
	{
		$this->body = decode_string($this->body, $this->headers['content-transfer-encoding'], $this->headers['__other_hdr__']['content-type']['charset']);
	}

	/* Main entry point for this class. May want to make it an init() in future. */
	function parse_message($data, $html=0)
	{
		$this->raw_msg = $data;

		$this->split_hdr_body();
		$this->format_header();
		$this->handle_content_headers();
		$this->decode_body($html);
	}

	/* Extract the headers we need. */
	function fetch_useful_headers()
	{
		$this->subject = htmlspecialchars(decode_header_value($this->headers['subject']));

		// Attempt to Get Poster's IP from fields commonly used to store it.
		if (isset($this->headers['x-posted-by'])) {
			$this->ip = parse_ip($this->headers['x-posted-by']);
		} else if (isset($this->headers['x-originating-ip'])) {
			$this->ip = parse_ip($this->headers['x-originating-ip']);
		} else if (isset($this->headers['x-senderip'])) {
			$this->ip = parse_ip($this->headers['x-senderip']);
		} else if (isset($this->headers['x-mdremoteip'])) {
			$this->ip = parse_ip($this->headers['x-mdremoteip']);
		} else if (isset($this->headers['received'])) {
			$this->ip = parse_ip($this->headers['received']);
		}

		// Fetch From email and possible name.
		if (preg_match('!(.+?)<(.+?)>!', $this->headers['from'], $matches)) {
			$this->from_email = trim($matches[2]);

			if (!empty($matches[1])) {
				$matches[1] = decode_header_value($matches[1]);
				if ($matches[1][0] == '"' && substr($matches[1], -1) == '"') {
					$this->from_name = substr($matches[1], 1, -1);
				} else {
					$this->from_name = $matches[1];
				}
			} else {
				$this->from_name = $this->from_email;
			}

			if (!preg_match('!^\w+!', $this->from_name)) {
				$this->from_name = substr($this->from_email, 0, strpos($this->from_email, '@'));
			}
		} else {
			$this->from_email = trim($this->headers['from']);
			$this->from_name = substr($this->from_email, 0, strpos($this->from_email, '@'));
		}

		if (empty($this->from_email) || empty($this->from_name)) {
			log_script_error('No name or email for '. $this->headers['from'], $this->raw_msg);
		}

		if (isset($this->headers['message-id'])) {
			$this->msg_id = substr(trim($this->headers['message-id']), 1, -1);
		} else if (isset($this->headers['x-qmail-scanner-message-id'])) {
			$this->msg_id = substr(trim($this->headers['x-qmail-scanner-message-id']), 1, -1);
		} else {
			log_script_error('No message id', $this->raw_msg);
		}

		// This fetches the id of the message if this is a reply to an existing message.
		if (!empty($this->headers['in-reply-to']) && preg_match('!<([^>]+)>$!', trim($this->headers['in-reply-to']), $match)) {
			$this->reply_to_msg_id = $match[1];
		} else if (!empty($this->headers['references']) && preg_match('!<([^>]+)>$!', trim($this->headers['references']), $match)) {
			$this->reply_to_msg_id = $match[1];
		}
	}

	/* Apply regex filters for subject & body mangling. */
	function clean_up_data()
	{
		if ($this->subject_cleanup_rgx) {
			$this->subject = trim(preg_replace($this->subject_cleanup_rgx, $this->subject_cleanup_rep, $this->subject));
		}
		if ($this->body_cleanup_rgx) {
			$this->body = trim(preg_replace($this->body_cleanup_rgx, $this->body_cleanup_rep, $this->body));
		}
	}
}

?>
