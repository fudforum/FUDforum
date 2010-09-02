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

/*{PRE_HTML_PHP}*/
/*{POST_HTML_PHP}*/

	$frm = isset($_GET['frm_id']) ? (int)$_GET['frm_id'] : (isset($_POST['frm_id']) ? (int)$_POST['frm_id'] : 0);
	if (!$frm) {
		invl_inp_err();
	}

	/* Permission check. */
	if (!$is_a) {
		$perms = db_saq('SELECT mm.id, '. (_uid ? ' COALESCE(g2.group_cache_opt, g1.group_cache_opt) AS gco ' : ' g1.group_cache_opt AS gco ') .'
				FROM {SQL_TABLE_PREFIX}forum f
				LEFT JOIN {SQL_TABLE_PREFIX}mod mm ON mm.user_id='. _uid .' AND mm.forum_id=f.id
				'. (_uid ? 'INNER JOIN {SQL_TABLE_PREFIX}group_cache g1 ON g1.user_id=2147483647 AND g1.resource_id=f.id LEFT JOIN {SQL_TABLE_PREFIX}group_cache g2 ON g2.user_id='. _uid .' AND g2.resource_id=f.id' : 'INNER JOIN {SQL_TABLE_PREFIX}group_cache g1 ON g1.user_id=0 AND g1.resource_id=f.id') .'
				WHERE f.id='. $frm);
		if (!$perms || !$perms[0] && !($perms[1] & 2048)) {
			std_error('access');
		}
	}

	$forum = isset($_POST['forum']) ? (int)$_POST['forum'] : 0;
	$error = '';
	$post = (isset($_POST['next']) || isset($_POST['prev'])) ? 0 : 1;

	if (isset($_GET['sel_th'])) {
		$_POST['sel_th'] = unserialize($_GET['sel_th']);
	}
	if (isset($_POST['sel_th'])) {
		foreach ($_POST['sel_th'] as $k => $v) {
			if (!(int)$v) {
				unset($_POST['sel_th'][$k]);
			}
			$_POST['sel_th'][$k] = (int) $v;
		}
		if (count($_POST['sel_th']) != q_singleval('SELECT count(*) FROM {SQL_TABLE_PREFIX}thread WHERE forum_id='. $frm .' AND id IN('. implode(',', $_POST['sel_th']) .')')) {
			std_error('access');
		}
	}

	$new_title = isset($_GET['new_title']) ? $_GET['new_title'] : (isset($_POST['new_title']) ? $_POST['new_title'] : '');

	if ($frm && $post && !empty($_POST['new_title']) && !empty($_POST['sel_th'])) {
		/* We need to make sure that the user has access to destination forum. */
		if (!$is_a && !q_singleval('SELECT f.id FROM {SQL_TABLE_PREFIX}forum f LEFT JOIN {SQL_TABLE_PREFIX}mod mm ON mm.user_id='. _uid .' AND mm.forum_id=f.id '. (_uid ? 'INNER JOIN {SQL_TABLE_PREFIX}group_cache g1 ON g1.user_id=2147483647 AND g1.resource_id=f.id LEFT JOIN {SQL_TABLE_PREFIX}group_cache g2 ON g2.user_id='. _uid .' AND g2.resource_id=f.id' : 'INNER JOIN {SQL_TABLE_PREFIX}group_cache g1 ON g1.user_id=0 AND g1.resource_id=f.id') .' WHERE f.id='. $forum .' AND (mm.id IS NOT NULL OR '. q_bitand(_uid ? 'COALESCE(g2.group_cache_opt, g1.group_cache_opt)' : 'g1.group_cache_opt', 4) .' > 0)')) {
			std_error('access');
		}

		/* Sanity check. */
		if (empty($_POST['sel_th'])) {
			if ($FUD_OPT_2 & 32768) {
				header('Location: {FULL_ROOT}{ROOT}/t/'. $th .'/'. _rsidl);
			} else {
				header('Location: {FULL_ROOT}{ROOT}?t='. d_thread_view .'&th='. $th .'&'. _rsidl);
			}
			exit;
		} else if (count($_POST['sel_th']) > 1) {
			apply_custom_replace($_POST['new_title']);

			if ($forum != $frm) {
				$lk_pfx = '{SQL_TABLE_PREFIX}tv_'. $frm .' WRITE,';
			} else {
				$lk_pfx = '';
			}
			db_lock($lk_pfx .'{SQL_TABLE_PREFIX}tv_'. $forum .' WRITE, {SQL_TABLE_PREFIX}thread WRITE, {SQL_TABLE_PREFIX}forum WRITE, {SQL_TABLE_PREFIX}msg WRITE, {SQL_TABLE_PREFIX}poll WRITE');

			$tl = implode(',', $_POST['sel_th']);

			list($start, $replies, $views) = db_saq('SELECT MIN(root_msg_id), SUM(replies), SUM(views) FROM {SQL_TABLE_PREFIX}thread WHERE id IN('. $tl .')');
			$replies += count($_POST['sel_th']) - 1;
			list($lpi, $lpd, $tdescr) = db_saq('SELECT last_post_id, last_post_date, tdescr FROM {SQL_TABLE_PREFIX}thread WHERE id IN('. $tl .') ORDER BY last_post_date DESC LIMIT 1');

			$new_th = th_add($start, $forum, $lpd, 0, 0, $replies, $views, $lpi, $tdescr);
			q('UPDATE {SQL_TABLE_PREFIX}msg SET reply_to=0, subject='. _esc(htmlspecialchars($_POST['new_title'])) .' WHERE id='. $start);
			q('UPDATE {SQL_TABLE_PREFIX}msg SET reply_to='. $start .' WHERE thread_id IN('. $tl .') AND (reply_to=0 OR reply_to=id) AND id!='. $start);
			if ($forum != $frm) {
				$p = db_all('SELECT poll_id FROM {SQL_TABLE_PREFIX}msg WHERE thread_id IN('. $tl .') AND apr=1 AND poll_id>0');
				if ($p) {
					q('UPDATE {SQL_TABLE_PREFIX}poll SET forum_id='. $forum .' WHERE id IN('. implode(',', $p) .')');
				}
			}
			q('UPDATE {SQL_TABLE_PREFIX}msg SET thread_id={$new_th} WHERE thread_id IN('. $tl .')');
			q('DELETE FROM {SQL_TABLE_PREFIX}thread WHERE id IN('. $tl .')');

			rebuild_forum_view_ttl($forum);
			if ($forum != $frm) {
				rebuild_forum_view_ttl($frm);
				foreach (array($frm, $forum) as $v) {
					$r = db_saq('SELECT MAX(last_post_id), SUM(replies), COUNT(*) FROM {SQL_TABLE_PREFIX}thread INNER JOIN {SQL_TABLE_PREFIX}msg ON root_msg_id={SQL_TABLE_PREFIX}msg.id AND {SQL_TABLE_PREFIX}msg.apr=1 WHERE forum_id='. $v);
					if (empty($r[2])) {
						$r = array(0,0,0);
					}
					q('UPDATE {SQL_TABLE_PREFIX}forum SET thread_count='. $r[2] .', post_count='. $r[1] .', last_post_id='. $r[0] .' WHERE id='. $v);
				}
			} else {
				q('UPDATE {SQL_TABLE_PREFIX}forum SET thread_count=thread_count-'. (count($_POST['sel_th']) - 1) .' WHERE id='. $frm);
			}
			db_unlock();

			/* Handle thread subscriptions and message read indicators. */
			if (__dbtype__ == 'mysql') {
				q('UPDATE IGNORE {SQL_TABLE_PREFIX}thread_notify SET thread_id='. $new_th .' WHERE thread_id IN('. $tl .')');
				q('UPDATE IGNORE {SQL_TABLE_PREFIX}bookmarks SET thread_id='. $new_th .' WHERE thread_id IN('. $tl .')');
				q('UPDATE IGNORE {SQL_TABLE_PREFIX}read SET thread_id='. $new_th .' WHERE thread_id IN('. $tl .')');
			} else if (__dbtype__ == 'sqlite') {
				q('UPDATE OR IGNORE {SQL_TABLE_PREFIX}thread_notify SET thread_id='. $new_th .' WHERE thread_id IN('. $tl .')');
				q('UPDATE OR IGNORE {SQL_TABLE_PREFIX}bookmarks SET thread_id='. $new_th .' WHERE thread_id IN('. $tl .')');
				q('UPDATE OR IGNORE {SQL_TABLE_PREFIX}read SET thread_id='. $new_th .' WHERE thread_id IN('. $tl .')');
			} else {
				foreach (db_all('SELECT user_id FROM {SQL_TABLE_PREFIX}thread_notify WHERE thread_id IN('. $tl .') AND thread_id!='. $new_th) as $v) {
					db_li('INSERT INTO {SQL_TABLE_PREFIX}thread_notify (user_id, thread_id) VALUES('. $v .','. $new_th .')', $tmp);
				}
				foreach (db_all('SELECT user_id FROM {SQL_TABLE_PREFIX}bookmarks WHERE thread_id IN('. $tl .') AND thread_id!='. $new_th) as $v) {
					db_li('INSERT INTO {SQL_TABLE_PREFIX}bookmarks (user_id, thread_id) VALUES('. $v .','. $new_th .')', $tmp);
				}
			}
			q('DELETE FROM {SQL_TABLE_PREFIX}thread_notify WHERE thread_id IN('. $tl .')');
			q('DELETE FROM {SQL_TABLE_PREFIX}bookmarks WHERE thread_id IN('. $tl .')');
			q('DELETE FROM {SQL_TABLE_PREFIX}read WHERE thread_id IN('. $tl .')');
	
			logaction(_uid, 'THRMERGE', $new_th, count($_POST['sel_th']));
			unset($_POST['sel_th']);
		}
	}

	/* Fetch a list of accesible forums. */
	$c = uq('SELECT f.id, f.name
			FROM {SQL_TABLE_PREFIX}forum f
			INNER JOIN {SQL_TABLE_PREFIX}fc_view v ON v.f=f.id
			INNER JOIN {SQL_TABLE_PREFIX}cat c ON c.id=f.cat_id
			LEFT JOIN {SQL_TABLE_PREFIX}mod mm ON mm.forum_id=f.id AND mm.user_id='. _uid .'
			INNER JOIN {SQL_TABLE_PREFIX}group_cache g1 ON g1.resource_id=f.id AND g1.user_id='. (_uid ? '2147483647' : '0') .'
			'. (_uid ? ' LEFT JOIN {SQL_TABLE_PREFIX}group_cache g2 ON g2.resource_id=f.id AND g2.user_id='. _uid : '') .'
			'. ($is_a ? '' : ' WHERE mm.id IS NOT NULL OR '. q_bitand(_uid ? 'COALESCE(g2.group_cache_opt, g1.group_cache_opt)' : 'g1.group_cache_opt', 2) .' > 0') .'
			ORDER BY v.id');
	$vl = $kl = '';
	while ($r = db_rowarr($c)) {
		$vl .= $r[0] . "\n";
		$kl .= $r[1] . "\n";
	}
	unset($c);

	$forum_sel = tmpl_draw_select_opt(rtrim($vl), rtrim($kl), $frm);

	$page = !empty($_POST['page']) ? (int) $_POST['page'] : 1;
	if ($page > 1 && isset($_POST['prev'])) {
		--$page;
	} else if (isset($_POST['next'])) {
		++$page;
	}

	$lwi = q_singleval('SELECT seq FROM {SQL_TABLE_PREFIX}tv_'. $frm .' ORDER BY seq DESC LIMIT 1');
	$max_p = ceil($lwi / $THREADS_PER_PAGE);
	if ($page > $max_p || $page < 1) {
		$page = 1;
	}

	$thread_sel = '';
	if (isset($_POST['sel_th'])) {
		$c = uq('SELECT t.id, m.subject FROM {SQL_TABLE_PREFIX}thread t INNER JOIN {SQL_TABLE_PREFIX}msg m ON t.root_msg_id=m.id WHERE t.id IN('. implode(',', $_POST['sel_th']) .')');
		while ($r = db_rowarr($c)) {
			$thread_sel .= '{TEMPLATE: m_sel_opt_selected}';
		}
		unset($c);
	}

	$c = uq('SELECT t.id, m.subject FROM {SQL_TABLE_PREFIX}tv_'. $frm .' tv 
			INNER JOIN {SQL_TABLE_PREFIX}thread t ON t.id=tv.thread_id 
			INNER JOIN {SQL_TABLE_PREFIX}msg m ON m.id=t.root_msg_id 
			WHERE tv.seq BETWEEN ' .($lwi - ($page * $THREADS_PER_PAGE) + 1) .' AND '. ($lwi - (($page - 1) * $THREADS_PER_PAGE)) .'
			'. (isset($_POST['sel_th']) ? 'AND t.id NOT IN('. implode(',', $_POST['sel_th']) .')' : '') .'
			ORDER BY tv.seq DESC');
	while ($r = db_rowarr($c)) {
		$thread_sel .= '{TEMPLATE: m_sel_opt}';
	}
	unset($c, $_POST['sel_th']);

	$pages = implode("\n", range(1, $max_p));

/*{POST_PAGE_PHP_CODE}*/
?>
{TEMPLATE: MERGE_TH_PAGE}
