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

 	/* Prevent session initialization. */
 	define('forum_debug', 1);
 	unset($_SERVER['REMOTE_ADDR']);

	if (!ini_get('register_argc_argv')) {
		exit("Please enable the 'register_argc_argv' php.ini directive.\n");
	}
	if ($_SERVER['argc'] < 2) {
		exit("Missing Forum ID Parameter.\n");
	}

	if (strncmp($_SERVER['argv'][0], '.', 1)) {
		require (dirname($_SERVER['argv'][0]) . '/GLOBALS.php');
	} else {
		require (getcwd() . '/GLOBALS.php');
	}

	if (!($FUD_OPT_1 & 1)) {
		exit("The forum is currently disabled.\n");
	}

	/* Include all the necessary FUDforum includes. */
	fud_use('err.inc');
	fud_use('db.inc');
	fud_use('users.inc');
	fud_use('users_reg.inc');
	fud_use('imsg_edt.inc');
	fud_use('th.inc');
	fud_use('th_adm.inc');
	fud_use('rev_fmt.inc');
	fud_use('fileio.inc');
	fud_use('isearch.inc');
	fud_use('forum_adm.inc', true);
	fud_use('glob.inc', true);
	fud_use('replace.inc');
	fud_use('scripts_common.inc', true);

	define('sql_p', $GLOBALS['DBHOST_TBL_PREFIX']);

	if (is_numeric($_SERVER['argv'][1])) {
		$config = db_sab('SELECT * FROM '. sql_p .'xmlagg WHERE id='. $_SERVER['argv'][1]);
	} else {
		$config = db_sab('SELECT * FROM '. sql_p .'xmlagg WHERE name='. _esc($_SERVER['argv'][1]));
	}
	if (!$config) {
		exit('Invalid feed identifier.');
	}

	/* Set language & locale. */
	$GLOBALS['usr'] = new stdClass();
	list($GLOBALS['usr']->lang, $locale) = db_saq('SELECT lang, locale FROM '. sql_p .'themes WHERE theme_opt=1|2 LIMIT 1');
	$GLOBALS['good_locale'] = setlocale(LC_ALL, $locale);

	$frm = new fud_forum;
	$frm->id = $config->forum_id;		// Load into forum.

	$opts = array(
		'http' => array(
			// 'proxy' => 'tcp://127.0.0.1:8080',
			// 'request_fulluri' => True,
			'user_agent' => 'FUDforum XML Aggrigator',
		)
	);
	$context = stream_context_create($opts);
	libxml_set_streams_context($context);

	$doc = new DOMDocument();
	echo 'Fetching XML data from '. $config->url ."\n";
	$doc->load($config->url);
	$server = preg_replace('#^https?\://(.+?)\/.*$$#i', '\\1', $config->url);
	$ip_addr = gethostbyname($server);

 	$arrFeeds = array();
	foreach ($doc->getElementsByTagName('item') as $node) {  // RSS items.
		array_push($arrFeeds, $node);
	}
	foreach ($doc->getElementsByTagName('entry') as $node) {  // ATOM entries.
		array_push($arrFeeds, $node);
	}
	unset($doc);

	/* Loop through entries and extract requrired data into a date sortable structure. */
	$articles = array();
	foreach ($arrFeeds as $node) {
		if (isset($node->getElementsByTagName('pubDate')->item(0)->nodeValue)) {
			$date = $node->getElementsByTagName('pubDate')->item(0)->nodeValue;
		} else if (isset($node->getElementsByTagName('issued')->item(0)->nodeValue)) {
			$date = $node->getElementsByTagName('issued')->item(0)->nodeValue;
		} else if (isset($node->getElementsByTagName('modified')->item(0)->nodeValue)) {
			$date = $node->getElementsByTagName('modified')->item(0)->nodeValue;
		} else if (isset($node->getElementsByTagName('updated')->item(0)->nodeValue)) {
			$date = $node->getElementsByTagName('updated')->item(0)->nodeValue;
		} else if (isset($node->getElementsByTagName('date')->item(0)->nodeValue)) {
			$date = $node->getElementsByTagName('date')->item(0)->nodeValue;
		}
		if (isset($date)) {
 			$date = strtotime($date);
			if ($date <= 0 || $date > __request_timestamp__) {
				$date = __request_timestamp__;
			}
		} else {
 			echo "WARNING: Unable to extract date, use load time.\n";
			$date = __request_timestamp__;
		}

 		$subject = $node->getElementsByTagName('title')->item(0)->nodeValue;
		if (isset($subject)) {
			$articles[$date]['subject'] = apply_custom_replace($subject);
		} else {
			$articles[$date]['subject'] = '(no subject)';
		}

		if (isset($node->getElementsByTagName('encoded')->item(0)->nodeValue)) {
			$body = $node->getElementsByTagName('encoded')->item(0)->nodeValue;
		} elseif (isset($node->getElementsByTagName('content')->item(0)->nodeValue)) {
			$body = $node->getElementsByTagName('content')->item(0)->nodeValue;
		} elseif (isset($node->getElementsByTagName('description')->item(0)->nodeValue)) {
			$body = $node->getElementsByTagName('description')->item(0)->nodeValue;
		}
		if (isset($body)) {
			$articles[$date]['body'] = apply_custom_replace($body);
		} else {
			$articles[$date]['body'] = '(no body)';
		}

		if ( isset($node->getElementsByTagName('creator')->item(0)->nodeValue)) {
			$poster = $node->getElementsByTagName('creator')->item(0)->nodeValue;
		} else if ( isset($node->getElementsByTagName('author')->item(0)->nodeValue)) {
			$poster = $node->getElementsByTagName('author')->item(0)->nodeValue;
		} else if ( isset($node->getElementsByTagName('contributor')->item(0)->nodeValue)) {
			$poster = $node->getElementsByTagName('contributor')->item(0)->nodeValue;
		}
		if (isset($poster)) {
			$articles[$date]['poster'] = $poster;
			$email = $poster.'@'.$server;	// Generate dummy email address.
			$poster_id = 0;
			$articles[$date]['poster_id'] = match_user_to_post($email, $poster, $config->xmlagg_opt & 2, $poster_id, $date);
		} else {
			$articles[$date]['poster'] = $GLOBALS['ANON_NICK'];
			$articles[$date]['poster_id'] = 0;
		}

		if ( isset($node->getElementsByTagName('link')->item(0)->nodeValue)) {
			$articles[$date]['link'] = $node->getElementsByTagName('link')->item(0)->nodeValue;
		} else if ( $node->getElementsByTagName('link')->length > 0) {
			$articles[$date]['link'] = $node->getElementsByTagName('link')->item(0)->getAttribute('href');
		} else {
			$articles[$date]['link'] = '';
		}
	}
	unset($arrFeeds);

	/* Sort and start to post to forum. */
	ksort($articles);
	foreach ($articles as $date => $article) {
		$m = new fud_msg_edit;
	 	$m->msg_opt    = 0;
		$m->msg_opt    = 1|2;
		$m->reply_to   = 0;
		$m->thread_id  = 0;
		$m->ip_addr    = $ip_addr;
 		$m->post_stamp = $date;
 		$m->subject    = $articles[$date]['subject'];
		$m->body       = $articles[$date]['body'];
		$m->poster     = $articles[$date]['poster'];
		$m->poster_id  = $articles[$date]['poster_id'];

		// Apply custom signature, may contain {link} tags.
		$m->body .= str_ireplace('{link}', $articles[$date]['link'], $config->custom_sig);

		// Track articles already loaded.
		if ($m->post_stamp > $config->last_load_date) {
			$new_last_load_date = $m->post_stamp;
		} else {
			if ($config->last_load_date != 0) {
				continue;	// Skip already loaded.
			}
		}

		// 'skip_non_forum_users' is set.
		if (!$m->poster_id && $config->xmlagg_opt & 4) {
			continue;
		}

 		echo 'Loading article: '. $m->subject .' ('. $m->poster .")\n";
		try {
			// Try to determine whether this message is a reply.
			list($m->reply_to, $m->thread_id) = get_fud_reply_id(($config->xmlagg_opt & 8), $frm->id, $m->subject, null);

			$m->add($frm->id, 0, 2, 0, 0, $config->name);
			if (!($config->xmlagg_opt & 1)) {	// Manual approval not required.
				$m->approve($m->id);
			}
		} catch (Exception $e) {
			print 'ERROR: '. $e->getMessage() ."\n";
		}
	}

	/* Store last article date to prevent loading duplicates. */
	if (isset($new_last_load_date)) {
		q('UPDATE '. sql_p .'xmlagg SET last_load_date = '. $new_last_load_date .' WHERE id = '. $config->id);
	}

	echo 'Done!';
?>
