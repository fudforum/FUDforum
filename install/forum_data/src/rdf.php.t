<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: rdf.php.t,v 1.2 2003/05/15 06:13:29 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

	require('GLOBALS.php');

	/* before we go on, we need to do some very basic activation checks */
	if ($FORUM_ENABLED != 'Y') {
		fud_use('cfg.inc', TRUE);
		fud_use('errmsg.inc');
		exit(cfg_dec($DISABLED_REASON) . __fud_ecore_adm_login_msg);
	}
	if (!$FORUM_TITLE && @file_exists($WWW_ROOT_DISK.'install.php')) {
		fud_use('errmsg.inc');
	        exit(__fud_e_install_script_present_error);
	}

	if ($PHP_COMPRESSION_ENABLE == 'Y') {
		ob_start(array('ob_gzhandler', $PHP_COMPRESSION_LEVEL));
	}

	$GLOBALS['clean'] = array('[' => '&#91;', ']' => '&#93;');

function sp($data)
{
	return '<![CDATA[' . strtr($data, $GLOBALS['clean']) . ']]>';
}

	require ($DATA_DIR . 'include/RDF.php');

/*{PRE_HTML_PHP}*/
/*{POST_HTML_PHP}*/

	/* supported modes of output
	 * m 		- messages
	 * t 		- threads
	 * u		- users
	 */
	$mode = (isset($_GET['mode']) && in_array($_GET['mode'], array('m', 't', 'u'))) ? $_GET['mode'] : 'm';
	if (@count($_GET) < 2) {
		$_GET['ds'] = time() - 86400;
		$_GET['l'] = 1;
		$_GET['n'] = 10;
	}

	define('__ROOT__', $WWW_ROOT . 'index.php');

	$lmt = " m.approved='Y'";
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
	 */
	if (isset($_GET['cat'])) {
	 	$lmt .= ' AND f.cat_id='.(int)$_GET['cat'];
	}
	if (isset($_GET['frm'])) {
	 	$lmt .= ' AND t.forum_id='.(int)$_GET['frm'];
	}
	if (isset($_GET['th'])) {
		$lmt .= ' AND m.thread_id='.(int)$_GET['th'];
	}
	if (isset($_GET['id'])) {
	 	$lmt .= ' AND m.id='.(int)$_GET['id'];
	}
	if (isset($_GET['ds'])) {
		$lmt .= ' AND m.post_stamp >='.(int)$_GET['ds'];
	}
	if (isset($_GET['de'])) {
		$lmt .= ' AND m.post_stamp <='.(int)$_GET['de'];
	}
	if ($AUTH == 'Y') {
		if ($AUTH_ID) {
			$join = '	INNER JOIN {SQL_TABLE_PREFIX}group_cache g1 ON g1.user_id=2147483647 AND g1.resource_id=f.id
					LEFT JOIN {SQL_TABLE_PREFIX}group_cache g2 ON g2.user_id='.$AUTH_ID.' AND g2.resource_id=f.id
					LEFT JOIN {SQL_TABLE_PREFIX}mod mm ON mm.forum_id=f.id AND mm.user_id='.$AUTH_ID.' ';
			$lmt .= " AND (mm.id IS NOT NULL OR (CASE WHEN g2.id IS NOT NULL THEN g2.p_READ ELSE g1.p_READ END)='Y'";
		} else {
			$join = ' INNER JOIN {SQL_TABLE_PREFIX}group_cache g1 ON g1.user_id=0 AND g1.resource_id=f.id ';
			$lmt .= " AND g1.p_READ='Y'";
		}
	}
	$offset = isset($_GET['o']) ? (int)$_GET['o'] : 0;
	$limit  = isset($_GET['n']) ? (int)$_GET['n'] : $MAX_N_RESULTS; 

	switch ($mode) {
		case 'm':
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
					' . $lmt  . (isset($GET['l']) ? ' ORDER BY m.post_stamp DESC LIMIT ' : ' LIMIT ') . qry_limit($limit, $offset));
			$res = 0;
			while ($r = db_rowobj($c)) {
				if (!$res) {
					echo '<?xml version="1.0" encoding="utf-8"?> 
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" xmlns:content="http://purl.org/rss/1.0/modules/content/" xmlns="http://purl.org/rss/1.0/"> 
<channel rdf:about="'.__ROOT__.'">
	<title>'.$FORUM_TITLE.' RDF feed</title>
	<link>'.__ROOT__.'</link>
	<description>'.$FORUM_TITLE.' RDF feed</description>
</channel>';
					$res = 1;
				}

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
	<body>'.str_replace("\n", '', sp(read_msg_body($r->foff, $r->length, $r->file_id))).'</body>
';
				if ($r->attach_cnt && $r->attach_cache) {
					$al = @unserialize($r->attach_cache);
					if (is_array($al) && @count($al)) {
						echo '<content:items><rdf:Bag>';
						foreach ($al as $a) {
							echo '<rdf:li>
								<content:item rdf:about="attachments">
									<a_title>'.sp($r[1]).'</a_title>
									<a_id>'.$r[0].'</a_id>
									<a_size>'.$r[2].'</a_size>
									<a_nd>'.$r[3].'</a_nd>
								</content:item>
							</rdf:li>';
						}
						echo '</rdf:Bag></content:items>';
					}
				}
				if ($r->poll_name) {
					echo '<content:items><rdf:Bag><poll_name>'.sp($r->poll_name).'</poll_name><total_votes>'.$r->total_votes.'</total_votes>';
					if ($r->poll_cache) {
						$pc = @unserialize($r->poll_cache);
						if (is_array($pc) && count($pc)) {
							foreach ($pc as $o) {
								echo '<rdf:li>
									<content:item rdf:about="poll_opt">
										<opt_title><'.sp($o[0]).'></opt_title>
										<opt_votes>'.$o[1].'</opt_votes>
									</content:item></rdf:li>';
							}
						}
					}
					echo '</rdf:Bag></content:items>';
				}
				echo '</item>';
			}
			qf($c);
			if ($res) {
				echo '</rdf:RDF>';
			} else {
				exit('no data');
			}
			break;
	}
?>
