#!/usr/bin/php -q
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

	/* Sitemap settings. */
	$frequency    = 'weekly';
	$priority     = '0.5';
	$auth_as_user = 0;	// User 0 == anonymous.

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

	/* Limit topics to what the user has access to. */
	if ($auth_as_user) {
		$join = 'INNER JOIN '. $GLOBALS['DBHOST_TBL_PREFIX'] .'group_cache g1 ON g1.user_id=2147483647 AND g1.resource_id=f.id
				LEFT JOIN '. $GLOBALS['DBHOST_TBL_PREFIX'] .'group_cache g2 ON g2.user_id='. $auth_as_user .' AND g2.resource_id=f.id
				LEFT JOIN '. $GLOBALS['DBHOST_TBL_PREFIX'] .'mod mm ON mm.forum_id=t.forum_id AND mm.user_id='. $auth_as_user .' ';
		$lmt  = '(mm.id IS NOT NULL OR '. q_bitand('COALESCE(g2.group_cache_opt, g1.group_cache_opt)', 2) .' > 0)';
	} else {
		$join = 'INNER JOIN '. $GLOBALS['DBHOST_TBL_PREFIX'] .'group_cache g1 ON g1.user_id=0 AND g1.resource_id=t.forum_id ';
		$lmt  = q_bitand('g1.group_cache_opt', 2) .' > 0';
	}

	$c = uq('SELECT t.id, t.last_post_date, m.subject FROM '. $GLOBALS['DBHOST_TBL_PREFIX'] .'thread t '. $join .'INNER JOIN '. $GLOBALS['DBHOST_TBL_PREFIX'] .'msg m ON t.root_msg_id = m.id WHERE '. $lmt .' ORDER BY t.last_post_date DESC LIMIT 50000');

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
		$post_stamp = date('Y-m-d\TH:i:s', $r[1]);

		$filetext = "<url>\n";
		if ($FUD_OPT_2 & 32768) {	// USE_PATH_INFO
			$thread_title_SEO = preg_replace(array('/[^a-z0-9_]/', '/_[_]*/', '/-[-]*/'), '-', strtolower($r[2]));
			$thread_title_SEO = str_replace(array('-s-', '%'), array('s-', ''), $thread_title_SEO);

			$filetext .= "\t<loc>${WWW_ROOT}index.php/t/${thread_id}/${thread_title_SEO}</loc>\n";			
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

	/* Notify Google. */
	$google = 'www.google.com';
	echo 'Notify $google...';
	if($fp = @fsockopen($google, 80)) {
		$req = 'GET /webmasters/sitemaps/ping?sitemap='. urlencode($GLOBALS['WWW_ROOT'] .'sitemap.xml') ." HTTP/1.1\r\n".
		       "Host: $google\r\n".
		       "User-Agent: FUDforum $FORUM_VERSION\r\n".
		       "Connection: Close\r\n\r\n";
		fwrite($fp, $req);
		while(!feof($fp)) {
			if( @preg_match('~^HTTP/\d\.\d (\d+)~i', fgets($fp, 128), $m) ) {
				echo ' status: '. intval($m[1]);
				break;
			}
		}
		fclose($fp);
	}

	echo "\nDone!\n";
?>
