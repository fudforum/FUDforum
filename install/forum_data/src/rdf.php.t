<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: rdf.php.t,v 1.6 2003/05/15 18:37:20 hackie Exp $
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
	require ($DATA_DIR . 'include/RDF.php');
	fud_use('err.inc');

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

/*{PRE_HTML_PHP}*/

	if ($RDF_ENABLED == 'N') {
		fud_use('cookies.inc');
		fud_use('users.inc');
		std_error('disabled');
	}

	if ($PHP_COMPRESSION_ENABLE == 'Y') {
		ob_start(array('ob_gzhandler', $PHP_COMPRESSION_LEVEL));
	}

	$GLOBALS['clean'] = array('[' => '&#91;', ']' => '&#93;');
	$GLOBALS['email'] = array('.' => ' dot ', '@' => ' at ');

function sp($data)
{
	return '<![CDATA[' . strtr($data, $GLOBALS['clean']) . ']]>';
}

function email_format($data)
{
	return strtr($data, $GLOBALS['email']);
}

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

	
	$offset = isset($_GET['o']) ? (int)$_GET['o'] : 0;
	$limit  = (isset($_GET['n']) && $_GET['n'] <= $MAX_N_RESULTS) ? (int)$_GET['n'] : $MAX_N_RESULTS; 

	switch ($mode) {
		case 'm':
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
					$lmt .= " AND (mm.id IS NOT NULL OR (CASE WHEN g2.id IS NOT NULL THEN g2.p_READ ELSE g1.p_READ END)='Y')";
				} else {
					$join = ' INNER JOIN {SQL_TABLE_PREFIX}group_cache g1 ON g1.user_id=0 AND g1.resource_id=f.id ';
					$lmt .= " AND g1.p_READ='Y'";
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
			$lmt = " m.approved='Y'";
			if (isset($_GET['cat'])) {
			 	$lmt .= ' AND f.cat_id='.(int)$_GET['cat'];
			}
			if (isset($_GET['frm'])) {
			 	$lmt .= ' AND t.forum_id='.(int)$_GET['frm'];
			}
			if (isset($_GET['id'])) {
			 	$lmt .= ' AND t.id='.(int)$_GET['id'];
			}
			if (isset($_GET['ds'])) {
				$lmt .= ' AND t.last_post_date >='.(int)$_GET['ds'];
			}
			if (isset($_GET['de'])) {
				$lmt .= ' AND t.last_post_date <='.(int)$_GET['de'];
			}
			if ($AUTH == 'Y') {
				if ($AUTH_ID) {
					$join = '	INNER JOIN {SQL_TABLE_PREFIX}group_cache g1 ON g1.user_id=2147483647 AND g1.resource_id=f.id
							LEFT JOIN {SQL_TABLE_PREFIX}group_cache g2 ON g2.user_id='.$AUTH_ID.' AND g2.resource_id=f.id
							LEFT JOIN {SQL_TABLE_PREFIX}mod mm ON mm.forum_id=f.id AND mm.user_id='.$AUTH_ID.' ';
					$lmt .= " AND (mm.id IS NOT NULL OR (CASE WHEN g2.id IS NOT NULL THEN g2.p_READ ELSE g1.p_READ END)='Y')";
				} else {
					$join = ' INNER JOIN {SQL_TABLE_PREFIX}group_cache g1 ON g1.user_id=0 AND g1.resource_id=f.id ';
					$lmt .= " AND g1.p_READ='Y'";
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
			break;

		case 'u':
			/* check for various supported limits
			 * pc	-	order by post count
			 * rd	-	order by registration date
			 * cl	-	show only currently online users
			 * l	-	limit to 'l' rows
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
				$lmt .= ' AND u.last_visit>='.(time() - $LOGEDIN_TIMEOUT * 60);
			}
			if ($AUTH == 'Y') {
				if ($AUTH_ID) {
					$join = '	INNER JOIN {SQL_TABLE_PREFIX}group_cache g1 ON g1.user_id=2147483647 AND g1.resource_id=f.id
							LEFT JOIN {SQL_TABLE_PREFIX}group_cache g2 ON g2.user_id='.$AUTH_ID.' AND g2.resource_id=f.id
							LEFT JOIN {SQL_TABLE_PREFIX}mod mm ON mm.forum_id=f.id AND mm.user_id='.$AUTH_ID.' ';
					$perms = ", (CASE WHEN (mm.id IS NOT NULL OR (CASE WHEN g2.id IS NOT NULL THEN g2.p_READ ELSE g1.p_READ END)='Y') THEN 1 ELSE 0 END) AS can_show_msg";
				} else {
					$join = ' INNER JOIN {SQL_TABLE_PREFIX}group_cache g1 ON g1.user_id=0 AND g1.resource_id=f.id ';
					$perms = ", (CASE WHEN g1.p_READ='Y' THEN 1 ELSE 0 END) AS can_show_msg";
				}
			} else {
				$perms = ', 1 AS can_show_msg';
			}
			$c = uq('SELECT 
						u.id, u.alias, u.join_date, u.posted_msg_count, u.avatar_loc, u.avatar_approved,
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
					if ($r->bday && $r->bday > 18500000) {
						$y = substr($r->bday, 0, 4);
						$m = substr($r->bday, 4, 2);
						$d = substr($r->bday, 6, 2);
						$r->bday = gmdate('r', gmmktime(1, 1, 1, $m, $d, y));
					} else {
						$r->bday = '';
					}
					$r->last_visit = ($r->last_visit && $r->last_visit > 631155661) ? gmdate('r', $r->last_visit) : '';
					$r->join_date = ($r->join_date && $r->join_date > 631155661) ? gmdate('r', $r->join_date) : '';

					if ($r->avatar_approved == 'N') {
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
			qf($c);

			break;
	}
	if (!empty($res)) {
		echo '</rdf:RDF>';
	} else {
		exit('no data');
	}
?>
