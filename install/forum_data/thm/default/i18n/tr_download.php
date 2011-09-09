#!/usr/local/bin/php -q
<?php
/**
* copyright            : (C) 2001-2011 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id$
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; version 2 of the License.
**/

require '../../../include/url.inc';
$FORUM_VERSION = '';

$en_count = count(file('en/msg'));
echo "Number of English messages: $en_count\n";
$not_translated = 7;	// Hard coded.

$i = 0;
$dp = opendir('.');
while ($de = readdir($dp)) {
	if (!is_dir($de) || $de == '.' || $de == '..' || $de == 'en' || !@file_exists($de . '/msg')) {
		continue;
	}

	echo 'Downloading '. $de .' from translatewiki.net...';

	$url = 'http://translatewiki.net/w/i.php?title=Special%3ATranslate&task=export-to-file&group=out-fudforum&language='. $de;
	$messages = get_remote_file($url);

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
		echo "$msgcount messages / ". number_format(($msgcount+$not_translated)/$en_count*100, 0) ."%\n";
	}

	sleep(5);
	$i++;
}
closedir($dp);
echo "\nTotal translated languages: $i\n";
