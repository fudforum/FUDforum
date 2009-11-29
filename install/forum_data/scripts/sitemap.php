#!/usr/local/bin/php -q
<?php
/**
* copyright            : (C) 2001-2009 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id$
*
* This program is free software; you can redistribute it and/or modify it 
* under the terms of the GNU General Public License as published by the 
* Free Software Foundation; version 2 of the License. 
**/

	/* Google sitemap settings. */
	$frequency = 'weekly';
	$priority  = '0.5';

	set_time_limit(0);
	ini_set('memory_limit', '128M');
	define('forum_debug', 1);
	unset($_SERVER['REMOTE_ADDR']);

	if (strncmp($_SERVER['argv'][0], '.', 1)) {
		require (dirname($_SERVER['argv'][0]) .'/GLOBALS.php');
	} else {
		require (getcwd() .'/GLOBALS.php');
	}

	fud_use('err.inc');
	fud_use('db.inc');

	$c = uq('SELECT id, last_post_date FROM '. $GLOBALS['DBHOST_TBL_PREFIX'] .'thread ORDER BY last_post_date DESC LIMIT 50000');

	echo "Writing sitemap.xml file to ${GLOBALS['WWW_ROOT_DISK']}\n";
	$fh = fopen($GLOBALS['WWW_ROOT_DISK'].'/sitemap.xml', 'w');
	$xmlhead = <<<EOF
<?xml version='1.0' encoding='UTF-8'?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
	xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
	xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9
			    http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd">\n
EOF;
	fwrite($fh, $xmlhead);

	while ($r = db_rowarr($c)) {
		$thread_id = $r[0];
		// $post_stamp = date('H:i:s', $r[1]) .'T'. date('Y-m-d', $r[1]);
		$post_stamp = date('H:i:s\TY-m-d', $r[1]);

		$filetext = "<url>\n";
		if ($FUD_OPT_2 & 32768) {	// USE_PATH_INFO
			$filetext .= "\t<loc>${WWW_ROOT}index.php/t/${thread_id}/</loc>\n";			
		} else {
			$filetext .= "\t<loc>${WWW_ROOT}index.php?t=msg&amp;th=${thread_id}&amp;start=0</loc>\n";
		}
		$filetext .= "\t<lastmod>${post_stamp}+00:00</lastmod>\n";
		$filetext .= "\t<changefreq>$frequency</changefreq>\n";
		$filetext .= "\t<priority>$priority</priority>\n";
		$filetext .= "</url>\n";
		fwrite($fh, $filetext);
	}

	fwrite($fh, "</urlset>\n");
	fclose($fh);

	$google = 'www.google.com';
	echo "Notify $google...";
	if($fp = @fsockopen($google, 80)) {
		$req = "GET /webmasters/sitemaps/ping?sitemap=". urlencode($GLOBALS['WWW_ROOT'].'sitemap.xml') ." HTTP/1.1\r\n".
		       "Host: $google\r\n".
		       "User-Agent: FUDforum $FORUM_VERSION\r\n".
		       "Connection: Close\r\n\r\n";
		fwrite($fp, $req);
		while(!feof($fp)) {
			if( @preg_match('~^HTTP/\d\.\d (\d+)~i', fgets($fp, 128), $m) ) {
				echo ' status: '. intval($m[1]) ."\n";
				break;
			}
		}
		fclose($fp);
	}

	echo "Done!\n";
?>
