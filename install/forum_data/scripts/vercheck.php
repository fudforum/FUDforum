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

/* Attempts to fetch the provided URL using any available means. */
function get_remote_file($url, $timeout, $head_only = false, $max_redirects = 10)
{
	$result = null;
	$parsed_url = parse_url($url);
	$allow_url_fopen = strtolower(@ini_get('allow_url_fopen'));

	// Quite unlikely that this will be allowed on a shared host, but it can't hurt.
	if (function_exists('ini_set')) {
		@ini_set('default_socket_timeout', $timeout);
	}

	// If we have cURL, we might as well use it.
	if (function_exists('curl_init')) {
		// Setup the transfer.
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HEADER, true);
		curl_setopt($ch, CURLOPT_NOBODY, $head_only);
		curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
		curl_setopt($ch, CURLOPT_USERAGENT, 'FUDforum');

		// Grab the page.
		$content = curl_exec($ch);
		$responce_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		// Process 301/302 redirect.
		if ($content !== false && ($responce_code == '301' || $responce_code == '302') && $max_redirects > 0) {
			$headers = explode("\r\n", trim($content));
			foreach ($headers as $header)
				if (substr($header, 0, 10) == 'Location: ') {
					$responce = get_remote_file(substr($header, 10), $timeout, $head_only, $max_redirects - 1);
					if ($responce !== null)
						$responce['headers'] = array_merge($headers, $responce['headers']);
					return $responce;
				}
		}

		// Ignore everything except a 200 response code.
		if ($content !== false && $responce_code == '200') {
			if ($head_only)
				$result['headers'] = explode("\r\n", str_replace("\r\n\r\n", "\r\n", trim($content)));
			else {
				preg_match('#HTTP/1.[01] 200 OK#', $content, $match, PREG_OFFSET_CAPTURE);
				$last_content = substr($content, $match[0][1]);
				$content_start = strpos($last_content, "\r\n\r\n");
				if ($content_start !== false) {
					// $result['headers'] = explode("\r\n", str_replace("\r\n\r\n", "\r\n", substr($content, 0, $match[0][1] + $content_start)));
					// $result['content'] = substr($last_content, $content_start + 4);
					$result = substr($last_content, $content_start + 4);
				}
			}
		}
	}
	// fsockopen() is the second best thing.
	else if (function_exists('fsockopen')) {
		$remote = @fsockopen($parsed_url['host'], !empty($parsed_url['port']) ? intval($parsed_url['port']) : 80, $errno, $errstr, $timeout);
		if ($remote) {
			// Send a standard HTTP 1.0 request for the page.
			fwrite($remote, ($head_only ? 'HEAD' : 'GET') .' '. (!empty($parsed_url['path']) ? $parsed_url['path'] : '/') . (!empty($parsed_url['query']) ? '?'. $parsed_url['query'] : '') .' HTTP/1.0' ."\r\n");
			fwrite($remote, 'Host: '.$parsed_url['host'] . "\r\n");
			fwrite($remote, 'User-Agent: FUDforum' . "\r\n");
			fwrite($remote, 'Connection: Close' . "\r\n\r\n");

			stream_set_timeout($remote, $timeout);
			$stream_meta = stream_get_meta_data($remote);

			// Fetch the response 1024 bytes at a time and watch out for a timeout.
			$content = false;
			while (!feof($remote) && !$stream_meta['timed_out']) {
				$content .= fgets($remote, 1024);
				$stream_meta = stream_get_meta_data($remote);
			}

			fclose($remote);

			// Process 301/302 redirect.
			if ($content !== false && $max_redirects > 0 && preg_match('#^HTTP/1.[01] 30[12]#', $content)) {
				$headers = explode("\r\n", trim($content));
				foreach ($headers as $header)
					if (substr($header, 0, 10) == 'Location: ') {
						$responce = get_remote_file(substr($header, 10), $timeout, $head_only, $max_redirects - 1);
						if ($responce !== null)
							$responce['headers'] = array_merge($headers, $responce['headers']);
						return $responce;
					}
			}

			// Ignore everything except a 200 response code.
			if ($content !== false && preg_match('#^HTTP/1.[01] 200 OK#', $content)) {
				if ($head_only)
					$result['headers'] = explode("\r\n", trim($content));
				else {
					$content_start = strpos($content, "\r\n\r\n");
					if ($content_start !== false) {
						// $result['headers'] = explode("\r\n", substr($content, 0, $content_start));
						// $result['content'] = substr($content, $content_start + 4);
						$result = substr($last_content, $content_start + 4);
					}
				}
			}
		}
	}
	// Last case scenario, we use file_get_contents provided allow_url_fopen is enabled (any non 200 response results in a failure).
	else if (in_array($allow_url_fopen, array('on', 'true', '1'))) {
		// Setup a stream context.
		$stream_context = stream_context_create(
			array(
				'http' => array(
					'method'		=> $head_only ? 'HEAD' : 'GET',
					'user_agent'	=> 'FUDforum',
					'max_redirects'	=> $max_redirects + 1,	// PHP >=5.1.0 only.
					'timeout'		=> $timeout	// PHP >=5.2.1 only.
				)
			)
		);

		$content = @file_get_contents($url, false, $stream_context);

		// Did we get anything?
		if ($content !== false) {
			// Gotta love the fact that $http_response_header just appears in the global scope (*cough* hack! *cough*)
			// $result['headers'] = $http_response_header;
			if (!$head_only)
				// $result['content'] = $content;
				$result = $content;
		}
	}

	return $result;
}	// End of get_remote_file().

/* main */
	define('forum_debug', 1);
	unset($_SERVER['REMOTE_ADDR']);

	if (strncmp($_SERVER['argv'][0], '.', 1)) {
		require (dirname($_SERVER['argv'][0]) .'/GLOBALS.php');
	} else {
		require (getcwd() .'/GLOBALS.php');
	}

	if ((bool)ini_get('allow_url_fopen') == FALSE) {
		die("Unable to check version. Please enable allow_url_fopen in your php.ini.\n");
	}

	if (file_exists($FORUM_SETTINGS_PATH .'latest_version')) {
		$lastcheck = filemtime($FORUM_SETTINGS_PATH .'latest_version');
		if ($lastcheck > time() - 86400) {	// 1 day.
			die("Skip. Forum version was recently checked.\n");
		}
	}

	echo "Busy looking up the latest forum version from FUDforum's wiki...\n";
	$verinfo = get_remote_file('http://cvs.prohost.org/index.php?title=Current_version&action=raw', 30);

	if ($verinfo && strpos($verinfo, '::')) {
		// Write version to the forum's cache directory.
		file_put_contents($FORUM_SETTINGS_PATH .'latest_version', $verinfo);
	} else {
		$verinfo = 'unknown::Lookup failed!';
	}

	$display_ver = substr($verinfo, 0, strpos($verinfo, '::'));
	echo 'Done! Current version: '. $FORUM_VERSION .', latest version is: '. $display_ver ."\n";

	if (version_compare($FORUM_VERSION, $display_ver)) {
		fud_use('iemail.inc');
		send_email($NOTIFY_FROM, $ADMIN_EMAIL, 'New FUDforum version available', 'A new FUDforum version is now available. Please upgrade your site at '. $WWW_ROOT .' from '. $FORUM_VERSION .' to '. $display_ver .' ASAP.');
	}
?>
