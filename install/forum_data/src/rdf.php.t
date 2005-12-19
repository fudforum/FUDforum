<?php
/**
* copyright            : (C) 2001-2006 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: rdf.php.t,v 1.60 2005/12/19 17:20:17 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; either version 2 of the License, or
* (at your option) any later version.
**/

	require('./GLOBALS.php');
	fud_use('err.inc');

	/* before we go on, we need to do some very basic activation checks */
	if (!($FUD_OPT_1 & 1)) {
		fud_use('errmsg.inc');
		exit($DISABLED_REASON . __fud_ecore_adm_login_msg);
	}
	if (!$FORUM_TITLE && @file_exists($WWW_ROOT_DISK.'install.php')) {
		fud_use('errmsg.inc');
	        exit(__fud_e_install_script_present_error);
	}

	$mode = (isset($_GET['mode']) && in_array($_GET['mode'], array('m', 't', 'u'))) ? $_GET['mode'] : 'm';
	$basic = isset($_GET['basic']);

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

function rss_cache_cleanup()
{
	foreach (glob($GLOBALS['FORUM_SETTINGS_PATH'].'rss_cache_*') as $v) {
		if (filemtime($v) + $GLOBALS['RDF_CACHE_AGE'] < __request_timestamp__) {
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

	$charset = '{TEMPLATE: rdf_CHARSET}';

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

	if ($RDF_CACHE_AGE) {
		register_shutdown_function('rss_cache_cleanup');

		$key = $_GET; 
		if ($RDF_AUTH_ID) {
			$key['auth_id'] = $RDF_AUTH_ID;
		}
		unset($key['S'], $key['rid'], $key['SQ']); // remove not relavent components

		$file_name = $FORUM_SETTINGS_PATH.'rss_cache_'.md5(serialize($key));
		if (file_exists($file_name) && (($t = filemtime($file_name)) + $RDF_CACHE_AGE) > __request_timestamp__) {
			$mod = gmdate('D, d M Y H:i:s', $t) . ' GMT';
			if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && !isset($_SERVER['HTTP_RANGE']) && $_SERVER['HTTP_IF_MODIFIED_SINCE'] == $mod) {
				header('HTTP/1.1 304 Not Modified');
				header('Status: 304 Not Modified');
				return;
			}
			header('Content-Type: text/xml');
			header('Last-Modified: ' . $mod);
			readfile($file_name);
			return;
		}
		ob_start();
	}

	if ($RDF_MAX_N_RESULTS < 1) { // handler for events when the value is not set
		$RDF_MAX_N_RESULTS = 100;
	}
	$limit  = (isset($_GET['n']) && $_GET['n'] <= $RDF_MAX_N_RESULTS) ? (int)$_GET['n'] : $RDF_MAX_N_RESULTS;

	$basic_rss_data = $basic_rss_header = $join = '';
	switch ($mode) {
		case 'm':
			$lmt = " t.moved_to=0 AND m.apr=1";
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
				$_GET['frm'] = db_all("SELECT forum_id FROM {SQL_TABLE_PREFIX}forum_notify WHERE user_id=".(int)$_GET['sf']);
			} else if (isset($_GET['st'])) {
				$_GET['th'] = db_all("SELECT thread_id FROM {SQL_TABLE_PREFIX}thread_notify WHERE user_id=".(int)$_GET['sf']);
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
			 * So, instead we set an arbitrary search limit if 5 days.
			 */
			if (isset($_GET['l']) && $lmt == " t.moved_to=0 AND m.apr=1") {
				$lmt .= ' AND t.last_post_date >=' . (__request_timestamp__ - 86400 * 5);
			}

			if ($FUD_OPT_2 & 33554432) {
				if ($RDF_AUTH_ID) {
					$join = '	INNER JOIN {SQL_TABLE_PREFIX}group_cache g1 ON g1.user_id=2147483647 AND g1.resource_id=f.id
							LEFT JOIN {SQL_TABLE_PREFIX}group_cache g2 ON g2.user_id='.$RDF_AUTH_ID.' AND g2.resource_id=f.id
							LEFT JOIN {SQL_TABLE_PREFIX}mod mm ON mm.forum_id=f.id AND mm.user_id='.$RDF_AUTH_ID.' ';
					$lmt .= " AND (mm.id IS NOT NULL OR (COALESCE(g2.group_cache_opt, g1.group_cache_opt) & 2) > 0)";
				} else {
					$join = ' INNER JOIN {SQL_TABLE_PREFIX}group_cache g1 ON g1.user_id=0 AND g1.resource_id=f.id ';
					$lmt .= " AND (g1.group_cache_opt & 2) > 0";
				}
			}

			$c = uq('SELECT
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
					header('Content-Type: text/xml');
					echo '<?xml version="1.0" encoding="'.$charset.'"?>' . "\n";
					if ($basic) {
						echo '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:sy="http://purl.org/rss/1.0/modules/syndication/" xmlns:admin="http://webns.net/mvcb/" xmlns="http://purl.org/rss/1.0/">';

						echo '
<channel rdf:about="'.__ROOT__.'">
	<title>'.$FORUM_TITLE.' RDF feed</title>
	<link>'.__ROOT__.'</link>
	<description>'.$FORUM_TITLE.' RDF feed</description>
	<items>
		<rdf:Seq>
';
					} else {
						echo '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" xmlns:content="http://purl.org/rss/1.0/modules/content/" xmlns="http://purl.org/rss/1.0/">';
						echo '
<channel rdf:about="'.__ROOT__.'">
	<title>'.$FORUM_TITLE.' RDF feed</title>
	<link>'.__ROOT__.'</link>
	<description>'.$FORUM_TITLE.' RDF feed</description>
</channel>';
					}

					$res = 1;
				}

				$body = read_msg_body($r->foff, $r->length, $r->file_id);
				smiley_full($body);
				if ($basic) {
$basic_rss_header .= "\t\t\t<rdf:li rdf:resource=\"".$WWW_ROOT."index.php?t=rview&amp;goto=".$r->id."&amp;th=".$r->thread_id."#msg_".$r->id."\" />\n";

$basic_rss_data .= '
<item rdf:about="'.$WWW_ROOT.'index.php?t=rview&amp;goto='.$r->id.'&amp;th='.$r->thread_id.'#msg_'.$r->id.'">
	<title>'.htmlspecialchars($r->subject).'</title>
	<link>'.$WWW_ROOT.'index.php?t=rview&amp;goto='.$r->id.'&amp;th='.$r->thread_id.'#msg_'.$r->id.'</link>
	<description>'.fud_xml_encode($body).'</description>
	<dc:subject></dc:subject>
	<dc:creator>'.$r->alias.'</dc:creator>
	<dc:date>'.gmdate('Y-m-d\TH:i:s', $r->post_stamp).'-00:00</dc:date>
</item>
';
				} else {
					echo '
<item>
	<title>'.sp($r->subject).'</title>
	<topic_id>'.$r->thread_id.'</topic_id>
	<topic_title>'.sp($r->th_subject).'</topic_title>
	<message_id>'.$r->id.'</message_id>
	<reply_to_id>'.$r->reply_to.'</reply_to_id>
	<reply_to_title>'.$r->reply_subject.'</reply_to_title>
	<forum_id>'.$r->forum_id.'</forum_id>
	<forum_title>'.sp($r->frm_name).'</forum_title>
	<category_title>'.sp($r->cat_name).'</category_title>
	<author>'.sp($r->alias).'</author>
	<author_id>'.$r->poster_id.'</author_id>
	<body>'.str_replace("\n", '', sp($body)).'</body>
';
					if ($r->attach_cnt && $r->attach_cache) {
						if (($al = unserialize($r->attach_cache))) {
							echo '<content:items><rdf:Bag>';
							foreach ($al as $a) {
								echo '<rdf:li>
									<content:item rdf:about="attachments">
										<a_title>'.sp($a[1]).'</a_title>
										<a_id>'.$a[0].'</a_id>
										<a_size>'.$a[2].'</a_size>
										<a_nd>'.$a[3].'</a_nd>
									</content:item>
								</rdf:li>';
							}
							echo '</rdf:Bag></content:items>';
						}
					}
					if ($r->poll_name) {
						echo '<content:items><rdf:Bag><poll_name>'.sp($r->poll_name).'</poll_name><total_votes>'.$r->total_votes.'</total_votes>';
						if ($r->poll_cache) {
							if (($pc = unserialize($r->poll_cache))) {
								foreach ($pc as $o) {
									echo '<rdf:li>
										<content:item rdf:about="poll_opt">
											<opt_title>'.sp($o[0]).'</opt_title>
											<opt_votes>'.$o[1].'</opt_votes>
										</content:item></rdf:li>';
								}
							}
						}
						echo '</rdf:Bag></content:items>';
					}
					echo '</item>';
				}
			}
			unset($c);
			if ($basic && $res) {
				echo $basic_rss_header . "\t\t</rdf:Seq>\n\t</items>\n</channel>\n" . $basic_rss_data;
			}
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
			$lmt = " t.moved_to=0 AND m.apr=1";
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
			if (isset($_GET['l']) && $lmt == " t.moved_to=0 AND m.apr=1") {
				$lmt .= ' AND t.last_post_date >=' . (__request_timestamp__ - 86400 * 5);
			}

			if ($FUD_OPT_2 & 33554432) {
				if ($RDF_AUTH_ID) {
					$join = '	INNER JOIN {SQL_TABLE_PREFIX}group_cache g1 ON g1.user_id=2147483647 AND g1.resource_id=f.id
							LEFT JOIN {SQL_TABLE_PREFIX}group_cache g2 ON g2.user_id='.$RDF_AUTH_ID.' AND g2.resource_id=f.id
							LEFT JOIN {SQL_TABLE_PREFIX}mod mm ON mm.forum_id=f.id AND mm.user_id='.$RDF_AUTH_ID.' ';
					$lmt .= " AND (mm.id IS NOT NULL OR (COALESCE(g2.group_cache_opt, g1.group_cache_opt) & 2) > 0)";
				} else {
					$join = ' INNER JOIN {SQL_TABLE_PREFIX}group_cache g1 ON g1.user_id=0 AND g1.resource_id=f.id ';
					$lmt .= " AND (g1.group_cache_opt & 2) > 0";
				}
			}
			$c = uq('SELECT
					t.*,
					f.name AS frm_name,
					c.name AS cat_name,
					m.subject, m.post_stamp, m.poster_id,
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
			while ($r = db_rowobj($c)) {
				if (!$res) {
					header('Content-Type: text/xml');
					echo '<?xml version="1.0" encoding="'.$charset.'"?>
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" xmlns:content="http://purl.org/rss/1.0/modules/content/" xmlns="http://purl.org/rss/1.0/">
<channel rdf:about="'.__ROOT__.'">
	<title>'.$FORUM_TITLE.' RDF feed</title>
	<link>'.__ROOT__.'</link>
	<description>'.$FORUM_TITLE.' RDF feed</description>
</channel>';
					$res = 1;
				}
				if ($r->root_msg_id == $r->last_post_id) {
					$r->last_post_id = $r->lp_subject = $r->last_post_date = '';
				} else {
					$r->last_post_date = gmdate('r', $r->last_post_date);
				}

				echo '
<item>
	<topic_id>'.$r->id.'</topic_id>
	<topic_title>'.sp($r->subject).'</topic_title>
	<topic_creation_date>'.date('r', $r->post_stamp).'</topic_creation_date>
	<forum_id>'.$r->forum_id.'</forum_id>
	<forum_title>'.sp($r->frm_name).'</forum_title>
	<category_title>'.sp($r->cat_name).'</category_title>
	<author>'.sp($r->alias).'</author>
	<author_id>'.$r->poster_id.'</author_id>
	<replies>'.(int)$r->replies.'</replies>
	<views>'.(int)$r->views.'</views>
	<last_post_id>'.$r->last_post_id.'</last_post_id>
	<last_post_subj>'.sp($r->lp_subject).'</last_post_subj>
	<last_post_date>'.$r->last_post_date.'</last_post_date>
</item>';
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
			if ($FUD_OPT_2 & 33554432) {
				if ($RDF_AUTH_ID) {
					$join = '	INNER JOIN {SQL_TABLE_PREFIX}group_cache g1 ON g1.user_id=2147483647 AND g1.resource_id=f.id
							LEFT JOIN {SQL_TABLE_PREFIX}group_cache g2 ON g2.user_id='.$RDF_AUTH_ID.' AND g2.resource_id=f.id
							LEFT JOIN {SQL_TABLE_PREFIX}mod mm ON mm.forum_id=f.id AND mm.user_id='.$RDF_AUTH_ID.' ';
					$perms = ", (CASE WHEN (mm.id IS NOT NULL OR (COALESCE(g2.group_cache_opt, g1.group_cache_opt) & 2) > 0) THEN 1 ELSE 0 END) AS can_show_msg";
				} else {
					$join = ' INNER JOIN {SQL_TABLE_PREFIX}group_cache g1 ON g1.user_id=0 AND g1.resource_id=f.id ';
					$perms = ", (g1.group_cache_opt & 2) > 0 AS can_show_msg";
				}
			} else {
				$perms = ', 1 AS can_show_msg';
			}
			$c = uq('SELECT
						u.id, u.alias, u.join_date, u.posted_msg_count, u.avatar_loc, u.users_opt,
						u.home_page, u.bday, u.last_visit, u.icq, u.aim, u.yahoo, u.msnm, u.jabber, u.affero,
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
					header('Content-Type: text/xml');
					echo '<?xml version="1.0" encoding="'.$charset.'"?>
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" xmlns:content="http://purl.org/rss/1.0/modules/content/" xmlns="http://purl.org/rss/1.0/">
<channel rdf:about="'.__ROOT__.'">
	<title>'.$FORUM_TITLE.' RDF feed</title>
	<link>'.__ROOT__.'</link>
	<description>'.$FORUM_TITLE.' RDF feed</description>
</channel>';
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
					$r->last_visit = ($r->last_visit && $r->last_visit > 631155661) ? gmdate('r', $r->last_visit) : '';
					$r->join_date = ($r->join_date && $r->join_date > 631155661) ? gmdate('r', $r->join_date) : '';

					if ($r->users_opt >= 16777216) {
						$r->avatar_loc = '';
					}

				echo '
<item>
	<user_id>'.$r->id.'</user_id>
	<user_login>'.sp($r->alias).'</user_login>
	<user_name>'.sp($r->name).'</user_name>
	<user_email>'.sp(email_format($r->email)).'</user_email>
	<post_count>'.(int)$r->posted_msg_count.'</post_count>
	<avatar_img>'.sp($r->avatar_loc).'</avatar_img>
	<homepage>'.sp(htmlspecialchars($r->homepage)).'</homepage>
	<bday>'.$r->bday.'</bday>
	<last_visit>'.$r->last_visit.'</last_visit>
	<reg_date>'.$r->join_date.'</reg_date>
	<im_icq>'.$r->icq.'</im_icq>
	<im_aim>'.sp($r->aim).'</im_aim>
	<im_yahoo>'.sp($r->yahoo).'</im_yahoo>
	<im_msnm>'.sp($r->msnm).'</im_msnm>
	<im_jabber>'.sp($r->msnm).'</im_jabber>
	<im_affero>'.sp($r->affero).'</im_affero>
';

				if ($r->subject && $r->can_show_msg) {
					echo '
<m_subject>'.sp($r->subject).'</m_subject>
<m_id>'.$r->msg_id.'</m_id>
<m_thread_id>'.$r->thread_id.'</m_thread_id>
<m_forum_id>'.$r->forum_id.'</m_forum_id>
<m_forum_title>'.sp($r->frm_name).'</m_forum_title>
<m_cat_title>'.sp($r->cat_name).'</m_cat_title>
';
				}
				echo '</item>';
			}
			unset($c);

			break;
	}
	if ($res) {
		echo '</rdf:RDF>';
		if ($RDF_CACHE_AGE) {
			echo ($out = ob_get_clean());
			$fp = fopen($file_name, "w");
			fwrite($fp, $out);
			fclose($fp);
		}
	} else {
		exit('<xml><error>no data matching data</error></xml>');
	}
?>