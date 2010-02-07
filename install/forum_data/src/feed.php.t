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

	if (function_exists('mb_internal_encoding')) {
		mb_internal_encoding('{TEMPLATE: forum_CHARSET}');
	}
	require('./GLOBALS.php');
	fud_use('err.inc');

	/* Before we go on, we need to do some very basic activation checks. */
	if (!($FUD_OPT_1 & 1)) {
		fud_use('errmsg.inc');
		exit('<?xml version="1.0" encoding="{TEMPLATE: forum_CHARSET}"?><error><message>'. $DISABLED_REASON .'</message></error>');
	}

	/* Control options. */
	$mode = (isset($_GET['mode']) && in_array($_GET['mode'], array('m', 't', 'u'))) ? $_GET['mode'] : 'm';
	$basic = isset($_GET['basic']);
	$format = 'rdf';	// Default syndication type.
	if (isset($_GET['format'])) {
		if (strtolower(substr($_GET['format'], 0, 4)) == 'atom') {
			$format = 'atom';
		} else if (strtolower(substr($_GET['format'], 0, 3)) == 'rss') {
			$format = 'rss';
		}
	}
	if (!isset($_GET['th'])) {
	   $_GET['l'] = 1;	// Unless thread is syndicated, we will always order entries from newest to oldest.
	}

/*{PRE_HTML_PHP}*/

	if (!($FUD_OPT_2 & 16777216) || (!($FUD_OPT_2 & 67108864) && $mode == 'u')) {
		fud_use('cookies.inc');
		fud_use('users.inc');
		std_error('disabled');
	}

	if ($FUD_OPT_2 & 16384) {
		ob_start(array('ob_gzhandler', $PHP_COMPRESSION_LEVEL));
	}

function sp($data)
{
	return '<![CDATA[' . str_replace(array('[', ']'), array('&#91;', '&#93;'), $data) . ']]>';
}

function email_format($data)
{
	return str_replace(array('.', '@'), array(' dot ', ' at '), $data);
}

function multi_id($data)
{
	$out = array();
	foreach (explode(',', (string)$data) as $v) {
		$out[] = (int) $v;
	}
	return implode(',', $out);
}

$enc_src = array('<br>', '&', "\r", '&nbsp;', '<', '>', chr(0));
$enc_dst = array('<br />', '&amp;', '&#13;', ' ', '&lt;', '&gt;', '&#0;');

function fud_xml_encode($str)
{
	return str_replace($GLOBALS['enc_src'], $GLOBALS['enc_dst'], $str);
}

function feed_cache_cleanup()
{
	foreach (glob($GLOBALS['FORUM_SETTINGS_PATH'].'feed_cache_*') as $v) {
		if (filemtime($v) + $GLOBALS['FEED_CACHE_AGE'] < __request_timestamp__) {
			unlink($v);
		}
	}
}

// change relative smiley URLs to full ones
function smiley_full(&$data)
{
	if (strpos($data, '<img src="images/smiley_icons/') !== false) {
		$data = str_replace('<img src="images/smiley_icons/', '<img src="'.$GLOBALS['WWW_ROOT'].'images/smiley_icons/', $data);
	}
}

/*{POST_HTML_PHP}*/

	/* supported modes of output
	 * m 		- messages
	 * t 		- threads
	 * u		- users
	 */

	if (@count($_GET) < 2) {
		$_GET['ds'] = __request_timestamp__ - 86400;
		$_GET['l'] = 1;
		$_GET['n'] = 10;
	}

	define('__ROOT__', $WWW_ROOT . 'index.php');

	$res = 0;
	$offset = isset($_GET['o']) ? (int)$_GET['o'] : 0;

	if ($FEED_CACHE_AGE) {
		register_shutdown_function('feed_cache_cleanup');

		$key = $_GET; 
		if ($FEED_AUTH_ID) {
			$key['auth_id'] = $FEED_AUTH_ID;
		}
		unset($key['S'], $key['rid'], $key['SQ']);	// Remove irrelavent components.
		$key = array_change_key_case($key, CASE_LOWER);	// Cleanup the key.
		$key = array_map('strtolower', $key);
		ksort($key);

		$file_name = $FORUM_SETTINGS_PATH.'feed_cache_'.md5(serialize($key));
		if (file_exists($file_name) && (($t = filemtime($file_name)) + $FEED_CACHE_AGE) > __request_timestamp__) {
			$mod = gmdate('D, d M Y H:i:s', $t) . ' GMT';
			if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && !isset($_SERVER['HTTP_RANGE']) && $_SERVER['HTTP_IF_MODIFIED_SINCE'] == $mod) {
				header('HTTP/1.1 304 Not Modified');
				header('Status: 304 Not Modified');
				return;
			}
			header('{TEMPLATE: xml_header}');
			header('Last-Modified: ' . $mod);
			readfile($file_name);
			return;
		}
		ob_start();
	}

	if ($FEED_MAX_N_RESULTS < 1) {	// Handler for events when the value is not set.
		$FEED_MAX_N_RESULTS = 20;
	}
	$limit  = (isset($_GET['n']) && $_GET['n'] <= $FEED_MAX_N_RESULTS) ? (int)$_GET['n'] : $FEED_MAX_N_RESULTS;

	$feed_data = $feed_header = $join = '';
	switch ($mode) {
		case 'm':
			$lmt = ' t.moved_to=0 AND m.apr=1';
			/* check for various supported limits
			 * cat		- category
			 * frm		- forum
			 * th		- thread
			 * id		- message id
			 * ds		- start date
			 * de		- date end
			 * o		- offset
			 * n		- number of rows to get
			 * l		- latest
			 * sf		- subcribed forums based on user id
			 * st		- subcribed topics based on user id
			 * basic	- output basic info parsable by all rdf parsers
			 */
			if (isset($_GET['sf'])) {
				$_GET['frm'] = db_all('SELECT forum_id FROM {SQL_TABLE_PREFIX}forum_notify WHERE user_id='.(int)$_GET['sf']);
			} else if (isset($_GET['st'])) {
				$_GET['th'] = db_all('SELECT thread_id FROM {SQL_TABLE_PREFIX}thread_notify WHERE user_id='.(int)$_GET['sf']);
			}
			if (isset($_GET['cat'])) {
			 	$lmt .= ' AND f.cat_id IN('.multi_id($_GET['cat']).')';
			}
			if (isset($_GET['frm'])) {
			 	$lmt .= ' AND t.forum_id IN('.multi_id($_GET['frm']).')';
			}
			if (isset($_GET['th'])) {
				$lmt .= ' AND m.thread_id IN('.multi_id($_GET['th']).')';
			}
			if (isset($_GET['id'])) {
			 	$lmt .= ' AND m.id IN('.multi_id($_GET['id']).')';
			}
			if (isset($_GET['ds'])) {
				$lmt .= ' AND m.post_stamp >='.(int)$_GET['ds'];
			}
			if (isset($_GET['de'])) {
				$lmt .= ' AND m.post_stamp <='.(int)$_GET['de'];
			}

			/* This is an optimization so that the forum does not need to
			 * go through the entire message db to fetch latest messages.
			 * So, instead we set an arbitrary search limit of 5 days.
			 */
			if (isset($_GET['l']) && $lmt == ' t.moved_to=0 AND m.apr=1') {
				$lmt .= ' AND t.last_post_date >=' . (__request_timestamp__ - 86400 * 5);
			}

			if ($FUD_OPT_2 & 33554432) {	// FEED_AUTH
				if ($FEED_AUTH_ID) {
					$join = '	INNER JOIN {SQL_TABLE_PREFIX}group_cache g1 ON g1.user_id=2147483647 AND g1.resource_id=f.id
							LEFT JOIN {SQL_TABLE_PREFIX}group_cache g2 ON g2.user_id='.$FEED_AUTH_ID.' AND g2.resource_id=f.id
							LEFT JOIN {SQL_TABLE_PREFIX}mod mm ON mm.forum_id=f.id AND mm.user_id='.$FEED_AUTH_ID.' ';
					$lmt .= ' AND (mm.id IS NOT NULL OR (COALESCE(g2.group_cache_opt, g1.group_cache_opt) & 2) > 0)';
				} else {
					$join = ' INNER JOIN {SQL_TABLE_PREFIX}group_cache g1 ON g1.user_id=0 AND g1.resource_id=f.id ';
					$lmt .= ' AND (g1.group_cache_opt & 2) > 0';
				}
			}

			$c = q('SELECT
					m.*,
					u.alias,
					t.forum_id,
					p.name AS poll_name, p.total_votes,
					m2.subject AS th_subject,
					m3.subject AS reply_subject,
					f.name AS frm_name,
					c.name AS cat_name
				FROM
					{SQL_TABLE_PREFIX}msg m
					INNER JOIN {SQL_TABLE_PREFIX}thread t ON m.thread_id=t.id
					INNER JOIN {SQL_TABLE_PREFIX}forum f ON t.forum_id=f.id
					INNER JOIN {SQL_TABLE_PREFIX}cat c ON c.id=f.cat_id
					INNER JOIN {SQL_TABLE_PREFIX}msg m2 ON t.root_msg_id=m2.id
					LEFT JOIN {SQL_TABLE_PREFIX}msg m3 ON m3.id=m.reply_to
					LEFT JOIN {SQL_TABLE_PREFIX}users u ON m.poster_id=u.id
					LEFT JOIN {SQL_TABLE_PREFIX}poll p ON m.poll_id=p.id
					'.$join.'
				WHERE
					' . $lmt  . ' ORDER BY m.post_stamp ' . (isset($_GET['l']) ? 'DESC LIMIT ' : 'ASC LIMIT ') . qry_limit($limit, $offset));
			while ($r = db_rowobj($c)) {
				if (!$res) {
					header('{TEMPLATE: xml_header}');
					$res = 1;
				}

				$body = read_msg_body($r->foff, $r->length, $r->file_id);
				smiley_full($body);

				if ($format == 'rdf') {
					$feed_header .= '{TEMPLATE: rdf_message_header}';
					$feed_data .= '{TEMPLATE: rdf_message_entry}';

					$rdf_message_attachments = '';
					if ($r->attach_cnt && $r->attach_cache) {
						if (($al = unserialize($r->attach_cache))) {
							foreach ($al as $a) {
								$rdf_message_attachments .= '{TEMPLATE: rdf_message_attachments}';
							}
						}
					}

					$rdf_message_polls = '';	
					if ($r->poll_name) {
						if ($r->poll_cache) {
							if (($pc = unserialize($r->poll_cache))) {
								foreach ($pc as $o) {
									$rdf_message_polls .= '{TEMPLATE: rdf_message_polls}';
								}
							}
						}
					}
				}
				if ($format == 'rss' ) $feed_data .= '{TEMPLATE: rss_message_entry}';
				if ($format == 'atom') $feed_data .= '{TEMPLATE: atom_message_entry}';
			}
			if ($res) {
				if ($format == 'rdf')  echo '{TEMPLATE: rdf_doc}';
				if ($format == 'rss')  echo '{TEMPLATE: rss_doc}';
				if ($format == 'atom') echo '{TEMPLATE: atom_doc}';
			}
			unset($c);
			break;

		case 't':
			/* check for various supported limits
			 * cat		- category
			 * frm		- forum
			 * id		- topic id
			 * ds		- start date
			 * de		- date end
			 * o		- offset
			 * n		- number of rows to get
			 * l		- latest
			 */
			$lmt = ' t.moved_to=0 AND m.apr=1';
			if (isset($_GET['cat'])) {
				$lmt .= ' AND f.cat_id IN('.multi_id($_GET['cat']).')';
			}
			if (isset($_GET['frm'])) {
				$lmt .= ' AND t.forum_id IN('.multi_id($_GET['frm']).')';
			}
			if (isset($_GET['id'])) {
			 	$lmt .= ' AND t.id IN ('.multi_id($_GET['id']).')';
			}
			if (isset($_GET['ds'])) {
				$lmt .= ' AND t.last_post_date >='.(int)$_GET['ds'];
			}
			if (isset($_GET['de'])) {
				$lmt .= ' AND t.last_post_date <='.(int)$_GET['de'];
			}

			/* This is an optimization so that the forum does not need to
			 * go through the entire message db to fetch latest messages.
			 * So, instead we set an arbitrary search limit if 5 days.
			 */
			if (isset($_GET['l']) && $lmt == ' t.moved_to=0 AND m.apr=1') {
				$lmt .= ' AND t.last_post_date >=' . (__request_timestamp__ - 86400 * 5);
			}

			if ($FUD_OPT_2 & 33554432) {	// FEED_AUTH
				if ($FEED_AUTH_ID) {
					$join = '	INNER JOIN {SQL_TABLE_PREFIX}group_cache g1 ON g1.user_id=2147483647 AND g1.resource_id=f.id
							LEFT JOIN {SQL_TABLE_PREFIX}group_cache g2 ON g2.user_id='.$FEED_AUTH_ID.' AND g2.resource_id=f.id
							LEFT JOIN {SQL_TABLE_PREFIX}mod mm ON mm.forum_id=f.id AND mm.user_id='.$FEED_AUTH_ID.' ';
					$lmt .= ' AND (mm.id IS NOT NULL OR (COALESCE(g2.group_cache_opt, g1.group_cache_opt) & 2) > 0)';
				} else {
					$join = ' INNER JOIN {SQL_TABLE_PREFIX}group_cache g1 ON g1.user_id=0 AND g1.resource_id=f.id ';
					$lmt .= ' AND (g1.group_cache_opt & 2) > 0';
				}
			}
			$c = q('SELECT
					t.*,
					f.name AS frm_name,
					c.name AS cat_name,
					m.subject, m.post_stamp, m.poster_id, m.foff, m.length, m.file_id,
					m2.subject AS lp_subject,
					u.alias
				FROM
					{SQL_TABLE_PREFIX}thread t
					INNER JOIN {SQL_TABLE_PREFIX}forum f ON t.forum_id=f.id
					INNER JOIN {SQL_TABLE_PREFIX}cat c ON c.id=f.cat_id
					INNER JOIN {SQL_TABLE_PREFIX}msg m ON t.root_msg_id=m.id
					INNER JOIN {SQL_TABLE_PREFIX}msg m2 ON t.last_post_id=m2.id
					LEFT JOIN {SQL_TABLE_PREFIX}users u ON m.poster_id=u.id
					'.$join.'
				WHERE
					' . $lmt  . (isset($_GET['l']) ? ' ORDER BY m.post_stamp DESC LIMIT ' : ' LIMIT ') . qry_limit($limit, $offset));

			$data = '';
			while ($r = db_rowobj($c)) {
				if (!$res) {
					header('{TEMPLATE: xml_header}');
					$res = 1;
				}
				if ($r->root_msg_id == $r->last_post_id) {
					$r->last_post_id = $r->lp_subject = $r->last_post_date = '';
				}

				$body = read_msg_body($r->foff, $r->length, $r->file_id);
				smiley_full($body);

				if ($format == 'rdf') {
					$feed_header .= '{TEMPLATE: rdf_thread_header}';
					$feed_data .= '{TEMPLATE: rdf_thread_entry}';
				}
				if ($format == 'rss' ) $feed_data .= '{TEMPLATE: rss_thread_entry}';
				if ($format == 'atom') $feed_data .= '{TEMPLATE: atom_thread_entry}';
			}
			if ($res) {
				if ($format == 'rdf')  echo '{TEMPLATE: rdf_doc}';
				if ($format == 'rss')  echo '{TEMPLATE: rss_doc}';
				if ($format == 'atom') echo '{TEMPLATE: atom_doc}';
			}
			unset($c);
			break;

		case 'u':
			/* check for various supported limits
			 * pc	-	order by post count
			 * rd	-	order by registration date
			 * cl	-	show only currently online users
			 * l	-	limit to 'l' rows
			 * o	- 	offset
			 * n	-	max rows to fetch
			 */
			$lmt .= ' u.id>1 ';
			if (isset($_GET['pc'])) {
				$order_by = 'u.posted_msg_count';
			} else if (isset($_GET['rd'])) {
				$order_by = 'u.join_date';
			} else {
				$order_by = 'u.alias';
			}
			if (isset($_GET['cl'])) {
				$lmt .= ' AND u.last_visit>='.(__request_timestamp__ - $LOGEDIN_TIMEOUT * 60);
			}
			if ($FUD_OPT_2 & 33554432) {	// FEED_AUTH
				if ($FEED_AUTH_ID) {
					$join = '	INNER JOIN {SQL_TABLE_PREFIX}group_cache g1 ON g1.user_id=2147483647 AND g1.resource_id=f.id
							LEFT JOIN {SQL_TABLE_PREFIX}group_cache g2 ON g2.user_id='.$FEED_AUTH_ID.' AND g2.resource_id=f.id
							LEFT JOIN {SQL_TABLE_PREFIX}mod mm ON mm.forum_id=f.id AND mm.user_id='.$FEED_AUTH_ID.' ';
					$perms = ', (CASE WHEN (mm.id IS NOT NULL OR (COALESCE(g2.group_cache_opt, g1.group_cache_opt) & 2) > 0) THEN 1 ELSE 0 END) AS can_show_msg';
				} else {
					$join = ' INNER JOIN {SQL_TABLE_PREFIX}group_cache g1 ON g1.user_id=0 AND g1.resource_id=f.id ';
					$perms = ', (g1.group_cache_opt & 2) > 0 AS can_show_msg';
				}
			} else {
				$perms = ', 1 AS can_show_msg';
			}
			$c = q('SELECT
						u.id, u.alias, u.join_date, u.posted_msg_count, u.avatar_loc, u.users_opt,
						u.home_page, u.bday, u.last_visit, u.icq, u.aim, u.yahoo, u.msnm, u.jabber, u.google, u.skype, u.twitter, u.affero,
						u.name, u.email,
						m.id AS msg_id, m.subject, m.thread_id,
						t.forum_id,
						f.name AS frm_name,
						c.name AS cat_name
						'.$perms.'

					FROM {SQL_TABLE_PREFIX}users u
					LEFT JOIN {SQL_TABLE_PREFIX}msg m ON m.id=u.u_last_post_id
					LEFT JOIN {SQL_TABLE_PREFIX}thread t ON m.thread_id=t.id
					LEFT JOIN {SQL_TABLE_PREFIX}forum f ON f.id=t.forum_id
					LEFT JOIN {SQL_TABLE_PREFIX}cat c ON c.id=f.cat_id
					'.$join.'
					WHERE
						' . $lmt . ' ORDER BY ' . $order_by . ' DESC LIMIT ' . qry_limit($limit, $offset));
			while ($r = db_rowobj($c)) {
				if (!$res) {
					header('{TEMPLATE: xml_header}');
					$res = 1;
				}

				if ($r->bday && $r->bday > 18500000) {
					$y = substr($r->bday, 0, 4);
					$m = substr($r->bday, 4, 2);
					$d = substr($r->bday, 6, 2);
					$r->bday = gmdate('r', gmmktime(1, 1, 1, $m, $d, $y));
				} else {
					$r->bday = '';
				}
				$r->last_visit = ($r->last_visit && $r->last_visit > 631155661) ? $r->last_visit : '';
				$r->join_date = ($r->join_date && $r->join_date > 631155661) ? $r->join_date : '';

				if ($r->users_opt >= 16777216) {
					$r->avatar_loc = '';
				}

				if ($format == 'rdf' ) $feed_data .= '{TEMPLATE: rdf_user_entry}';
				if ($format == 'rss' ) $feed_data .= '{TEMPLATE: rss_user_entry}';
				if ($format == 'atom') $feed_data .= '{TEMPLATE: atom_user_entry}';
			}
			if ($res) {
				if ($format == 'rdf')  echo '{TEMPLATE: rdf_doc}';
				if ($format == 'rss')  echo '{TEMPLATE: rss_doc}';				
				if ($format == 'atom') echo '{TEMPLATE: atom_doc}';
			}
			unset($c);
			break;
	} // switch ($mode)

	if ($res) {
		if ($FEED_CACHE_AGE) {
			echo ($out = ob_get_clean());
			$fp = fopen($file_name, 'w');
			fwrite($fp, $out);
			fclose($fp);
		}
	} else {
		exit('{TEMPLATE: xml_no_data}');
	}
?>
