#!/usr/local/bin/php -q
<?php
/**
* copyright            : (C) 2001-2009 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: xmlagg.php,v 1.1 2009/07/11 09:55:24 frank Exp $
*
* This program is free software; you can redistribute it and/or modify it 
* under the terms of the GNU General Public License as published by the 
* Free Software Foundation; version 2 of the License. 
**/

 	/* prevent session initialization */
 	define('forum_debug', 1);
 	unset($_SERVER['REMOTE_ADDR']);
 
	if (!ini_get("register_argc_argv")) {
		exit("Enable the 'register_argc_argv' php.ini directive\n");
	}
	if ($_SERVER['argc'] < 2) {
		exit("Missing Forum ID Parameter\n");
	}

	if (strncmp($_SERVER['argv'][0], '.', 1)) {
		require (dirname($_SERVER['argv'][0]) . '/GLOBALS.php');
	} else {
		require (getcwd() . '/GLOBALS.php');
	}

	if (!($FUD_OPT_1 & 1)) {
		exit("Forum is currently disabled.\n");
	}

	/* include all the necessary FUDforum includes */
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
		$config = db_sab('SELECT * FROM '.sql_p.'xmlagg WHERE id='.$_SERVER['argv'][1]);
	} else {
		$config = db_sab("SELECT * FROM ".sql_p."xmlagg WHERE name=".esc($_SERVER['argv'][1]));
	}
	if (!$config) {
		exit('Invalid feed identifier');
	}

	/* set language & locale */
	list($GLOBALS['usr']->lang, $locale) = db_saq("SELECT lang, locale FROM ".sql_p."themes WHERE theme_opt=1|2 LIMIT 1");
	$GLOBALS['good_locale'] = setlocale(LC_ALL, $locale);

	$frm = new fud_forum;
	$frm->id = $config->forum_id;		// Load into forum 
 
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
	echo "Fetching XML data from ".$config->url."\n";
	$doc->load($config->url);
	$server = preg_replace('#^https?\://(.+?)\/.*$$#i', '\\1', $config->url);
	$ip_addr = gethostbyname($server);

 	$arrFeeds = array();
	foreach ($doc->getElementsByTagName('item') as $node) {  // RSS Items
		array_push($arrFeeds, $node);
	}
	foreach ($doc->getElementsByTagName('entry') as $node) {  // ATOM Entries
		array_push($arrFeeds, $node);
	}

	foreach (array_reverse($arrFeeds) as $node) {
		$m = new fud_msg_edit;
	 	$m->msg_opt    = 0;
		$m->msg_opt    = 1|2;
		$m->reply_to   = 0;
		$m->thread_id  = 0;
		$m->ip_addr    = $ip_addr;

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
 			$m->post_stamp = strtotime($date);
			if ($m->post_stamp <= 0 || $m->post_stamp > __request_timestamp__) {
				$m->post_stamp = __request_timestamp__;
			}
		}

		if ($m->post_stamp > $config->last_load_date) {
			$new_last_load_date = $m->post_stamp;
		} else {
			if ($config->last_load_date != 0) {
				continue;	// skip already loaded
			}
		}

 		$subject = $node->getElementsByTagName('title')->item(0)->nodeValue;
		$m->subject = '(no subject)';
		if (isset($subject)) {
			$m->subject = apply_custom_replace($subject);
		}

		if (isset($node->getElementsByTagName('encoded')->item(0)->nodeValue)) {
			$body = $node->getElementsByTagName('encoded')->item(0)->nodeValue;
		} elseif (isset($node->getElementsByTagName('content')->item(0)->nodeValue)) {
			$body = $node->getElementsByTagName('content')->item(0)->nodeValue;
		} elseif (isset($node->getElementsByTagName('description')->item(0)->nodeValue)) {
			$body = $node->getElementsByTagName('description')->item(0)->nodeValue;
		}
		$m->body = '(no body)';
		if (isset($body)) {
			$m->body = apply_custom_replace($body) . trim($config->custom_sig);
		}
 
		if ( isset($node->getElementsByTagName('creator')->item(0)->nodeValue)) {
			$poster = $node->getElementsByTagName('creator')->item(0)->nodeValue;
		} else if ( isset($node->getElementsByTagName('author')->item(0)->nodeValue)) {
			$poster = $node->getElementsByTagName('author')->item(0)->nodeValue;
		} else if ( isset($node->getElementsByTagName('contributor')->item(0)->nodeValue)) {
			$poster = $node->getElementsByTagName('contributor')->item(0)->nodeValue;
		}
		$m->poster_id = 0;
		if (isset($poster)) {
			$email = $poster.'@'.$server;	// generate dummy email address
			$m->poster_id = match_user_to_post($email, $poster, $config->xml_opt & 2, $m->poster_id, $m->post_stamp);
		}
		// skip_non_forum_users is set
		if (!$m->poster_id && $config->xml_opt & 4) {
			continue;
		}

 		echo "Loading article: ". $m->subject ." (".$poster.")\n";
		try {
			$m->add($frm->id, 0, 2, 0, 0, $config->name);
			if (!($config->xml_opt & 1)) {
				$m->approve($m->id);
			}
		} catch (Exception $e) {
			print "ERROR: ". $e->getMessage() ."\n";
		}
	}

	// Store last article date to prevent loading duplicates
	if (isset($new_last_load_date)) {
		q('UPDATE '.sql_p.'xmlagg SET last_load_date = '.$new_last_load_date.' WHERE id = '.$config->id);
	}
?>
