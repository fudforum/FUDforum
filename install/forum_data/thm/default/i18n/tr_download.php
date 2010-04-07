#!/usr/local/bin/php -q
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

$encount = count(file('en/msg'));
echo "Number of English messages: $encount\n";

$i = 0;
$dp = opendir('.');
while ($de = readdir($dp)) {
	if (!is_dir($de) || $de == '.' || $de == '..' || $de == 'en' || !@file_exists($de . '/msg')) {
		continue;
	}

	echo 'Downloading '. $de .' messages from translatewiki.net...';

	$url = 'http://translatewiki.net/w/i.php?title=Special%3ATranslate&task=export-to-file&group=out-fudforum&language='. $de;
	$url_stuff = parse_url($url);

	$fp = fsockopen($url_stuff['host'], 80, $errno, $errstr);
	if (!$fp) {
		echo 'ERROR: '. $errstr .' ('. $errno .")\n";
	} else {
		// $query = 'GET '. $url_stuff['path'] .'?'. $url_stuff['query'] ." HTTP/1.0\r\n";
		$query = 'GET '. $url ." HTTP/1.0\r\n";
		$query .= "User-Agent: FUDforum\r\n";
		$query .= "Connection: close\r\n";
		$query .= "\r\n\r\n";
		fwrite($fp, $query);

		$header   = 1;	// First part is headers.
		$messages = '';
		while( !feof( $fp ) ) {
			$line = fgets($fp);
			if (!$header) $messages .= $line;
			if ($line == "\r\n" && $header) $header = 0;
		}
		fclose($fp);

		if (!strlen($messages) || substr($messages,0,15) != '# Messages for ' ) {
			echo "Unexpected data. First part: [". substr($messages,0,15) ."]\n";
		} else {
			$msgfile = $de .'/msg';
			file_put_contents($msgfile, $messages);

			// Count messages.
			$msgcount = 0;
			foreach( explode("\n", $messages) as $msg) {
				if (preg_match('/(^#|^\s+|^$)/', $msg)) continue;
				$msgcount++;
			}
			echo "$msgcount messages.\n";
		}
	}

	sleep(5);
	$i++;
}
closedir($dp);
echo "\nTotal translated languages: $i\n";
