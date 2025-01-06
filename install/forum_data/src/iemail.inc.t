<?php
/**
* copyright            : (C) 2001-2025 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id$
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; version 2 of the License.
**/

function is_email($email)
{
	if (empty($email)) {
		return false;
	}
	if (filter_var((string) $email, FILTER_VALIDATE_EMAIL)) {
		return true;	// Valid
	} else {
		return false;	// Invalid
	}
}

function encode_subject($text)
{
	/* HTML entities check. */
	if (strpos($text, '&') !== false) {
		$text = html_entity_decode($text);
	}

	$text = htmlspecialchars($text);  // Prevent XSS like <img src="1" onerror="alert()">
	
	if (preg_match('![\x7f-\xff]!', $text)) {
		$text = '=?{TEMPLATE: iemail_CHARSET}?B?'. base64_encode($text) .'?=';
	}

	return $text;
}

function send_email($from, $to, $subj, $body, $header='', $munge_newlines=1)
{
	if (empty($to)) {
		return 0;
	}

	if ($header) {
		$header = "\n" . str_replace("\r", '', $header);
	}
	$extra_header = '';
	if (strpos($header, 'MIME-Version') === false) {
		$extra_header = "\nMIME-Version: 1.0\nContent-Type: text/plain; charset={TEMPLATE: iemail_CHARSET}\nContent-Transfer-Encoding: 8bit". $header;
	}
	$addronly = preg_replace('/.*</', '<', $from);	// RFC 2822 Return-Path: <...>
	$header = 'From: '. $from ."\nReturn-Path: ". $addronly ."\nUser-Agent: FUDforum/". $GLOBALS['FORUM_VERSION'] . $extra_header . $header;

	$subj = encode_subject($subj);
	$body = str_replace("\r", '', $body);
	if ($munge_newlines) {
		$body = str_replace('\n', "\n", $body);
	}

	// Call PRE mail plugins.
	if (defined('plugins')) {
		list($to, $subj, $body, $header) = plugin_call_hook('PRE_MAIL', array($to, $subj, $body, $header));
	}

	if (defined('fud_logging')) {
		if (!function_exists('logaction')) {
			fud_use('logaction.inc');
		}
		logaction(_uid, 'SEND EMAIL', 0, 'To=['. implode(',', (array)$to) .']<br />Subject=['. $subj .']<br />Headers=['. str_replace("\n", '<br />', htmlentities($header)) .']<br />Message=['. $body .']');
	}

	if ($GLOBALS['FUD_OPT_1'] & 512) {
		if (!class_exists('fud_smtp')) {
			fud_use('smtp.inc');
		}
		$smtp = new fud_smtp;
		$smtp->msg = str_replace(array('\n', "\n."), array("\n", "\n.."), $body);
		$smtp->subject = $subj;
		$smtp->to = $to;
		$smtp->from = $from;
		$smtp->headers = $header;
		$smtp->send_smtp_email();
		return 1;
	}

	foreach ((array)$to as $email) {
		if (!@mail($email, $subj, $body, $header)) {
			fud_logerror('Your system didn\'t accept E-mail ['. $subj .'] to ['. $email .'] for delivery.', 'fud_errors', $header ."\n\n". $body);
			return -1;
		}
	}
	
	return 1;
}
?>
