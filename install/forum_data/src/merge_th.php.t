<?php
/***************************************************************************
* copyright            : (C) 2001-2003 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: merge_th.php.t,v 1.12 2003/10/17 00:58:13 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it 
* under the terms of the GNU General Public License as published by the 
* Free Software Foundation; either version 2 of the License, or 
* (at your option) any later version.
***************************************************************************/

/*{PRE_HTML_PHP}*/
/*{POST_HTML_PHP}*/

	$frm = isset($_GET['frm']) ? (int)$_GET['frm'] : (isset($_POST['frm']) ? (int)$_POST['frm'] : 0);
	if (!$frm) {
		invl_inp_err();
	}

	/* permission check */
	if (!($usr->users_opt & 1048576)) {
		$perms = db_saq('SELECT mm.id, '.(_uid ? ' (CASE WHEN g2.id IS NOT NULL THEN g2.group_cache_opt ELSE g1.group_cache_opt END) AS gco ' : ' g1.group_cache_opt AS gco ').'
				FROM {SQL_TABLE_PREFIX}forum f
				LEFT JOIN {SQL_TABLE_PREFIX}mod mm ON mm.user_id='._uid.' AND mm.forum_id=f.id
				'.(_uid ? 'INNER JOIN {SQL_TABLE_PREFIX}group_cache g1 ON g1.user_id=2147483647 AND g1.resource_id=f.id LEFT JOIN {SQL_TABLE_PREFIX}group_cache g2 ON g2.user_id='._uid.' AND g2.resource_id=f.id' : 'INNER JOIN {SQL_TABLE_PREFIX}group_cache g1 ON g1.user_id=0 AND g1.resource_id=f.id').'
				WHERE f.id='.$frm);
		if (!$perms || !$perms[0] && !($perms[1] & 2048)) {
			std_error('access');
		}
	}

	$forum = isset($_POST['forum']) ? (int)$_POST['forum'] : 0;
	$error = '';
	$post = (isset($_POST['next']) || isset($_POST['prev'])) ? 0 : 1;

	if (isset($_POST['sel_th'])) {
		foreach ($_POST['sel_th'] as $k => $v) {
			if (!(int)$v) {
				unset($_POST['sel_th'][$k]);
			}
		}
		if (count($_POST['sel_th']) != q_singleval("SELECT count(*) FROM {SQL_TABLE_PREFIX}thread WHERE forum_id={$frm} AND id IN(".implode(',', $_POST['sel_th']).")")) {
			std_error('access');
		}
	}

	if ($frm && $post && !empty($_POST['new_title']) && !empty($_POST['sel_th']) && count($_POST['sel_th'])) {
		/* we need to make sure that the user has access to destination forum */
		if (!($usr->users_opt & 1048576) && !q_singleval('SELECT f.id FROM {SQL_TABLE_PREFIX}forum f LEFT JOIN {SQL_TABLE_PREFIX}mod mm ON mm.user_id='._uid.' AND mm.forum_id=f.id '.(_uid ? 'INNER JOIN {SQL_TABLE_PREFIX}group_cache g1 ON g1.user_id=2147483647 AND g1.resource_id=f.id LEFT JOIN {SQL_TABLE_PREFIX}group_cache g2 ON g2.user_id='._uid.' AND g2.resource_id=f.id' : 'INNER JOIN {SQL_TABLE_PREFIX}group_cache g1 ON g1.user_id=0 AND g1.resource_id=f.id').' WHERE f.id='.$forum.' AND (mm.id IS NOT NULL OR '.(_uid ? ' ((CASE WHEN g2.id IS NOT NULL THEN g2.group_cache_opt ELSE g1.group_cache_opt END)' : ' (g1.group_cache_opt').' & 4) > 0)')) {
			std_error('access');
		}

		/* sanity check */
		if (!count($_POST['sel_th'])) {
			if ($FUD_OPT_2 & 32768) {
				header('Location: {ROOT}/t/'.$th.'/'._rsidl);
			} else {
				header('Location: {ROOT}?t='.d_thread_view.'&th='.$th.'&'._rsidl);
			}
			exit;
		} else if (count($_POST['sel_th']) > 1) {
			apply_custom_replace($_POST['new_title']);

			db_lock('{SQL_TABLE_PREFIX}thread_view WRITE, {SQL_TABLE_PREFIX}thread WRITE, {SQL_TABLE_PREFIX}forum WRITE, {SQL_TABLE_PREFIX}msg WRITE, {SQL_TABLE_PREFIX}poll WRITE');

			$tl = implode(',', $_POST['sel_th']);

			list($start, $repl) = db_saq("SELECT MIN(root_msg_id), SUM(replies) FROM {SQL_TABLE_PREFIX}thread WHERE id IN({$tl})");
			$repl += count($_POST['sel_th']) - 1;
			list($lpi, $lpd) = db_saq("SELECT last_post_id, last_post_date FROM {SQL_TABLE_PREFIX}thread WHERE id IN({$tl}) ORDER BY last_post_date DESC LIMIT 1");

			$new_th = th_add($start, $forum, $lpd, 0, 0, $repl, $lpi);
			q("UPDATE {SQL_TABLE_PREFIX}msg SET reply_to=0, subject='".addslashes(htmlspecialchars($_POST['new_title']))."' WHERE id=".$start);
			q("UPDATE {SQL_TABLE_PREFIX}msg SET reply_to={$start} WHERE thread_id IN({$tl}) AND (reply_to=0 OR reply_to=id) AND id!={$start}");
			if ($forum != $frm) {
				$p = array();
				$c = q('SELECT poll_id FROM {SQL_TABLE_PREFIX}msg WHERE thread_id IN('.$tl.') AND apr=1 AND poll_id>0');
				while ($r = db_rowarr($c)) {
					$p[] = $r[0];
				}
				unset($c);
				if (count($p)) {
					q('UPDATE {SQL_TABLE_PREFIX}poll SET forum_id='.$forum.' WHERE id IN('.implode(',', $p).')');
				}
			}
			q("UPDATE {SQL_TABLE_PREFIX}msg SET thread_id={$new_th} WHERE thread_id IN({$tl})");
			q("DELETE FROM {SQL_TABLE_PREFIX}thread WHERE id IN({$tl})");

			rebuild_forum_view($forum);
			if ($forum != $frm) {
				rebuild_forum_view($frm);
				foreach (array($frm, $forum) as $v) {
					$r = db_saq("SELECT MAX(last_post_id), SUM(replies), COUNT(*) FROM {SQL_TABLE_PREFIX}thread INNER JOIN {SQL_TABLE_PREFIX}msg ON root_msg_id={SQL_TABLE_PREFIX}msg.id AND {SQL_TABLE_PREFIX}msg.apr=1 WHERE forum_id={$v}");
					if (empty($r[2])) {
						$r = array(0,0,0);
					}
					q("UPDATE {SQL_TABLE_PREFIX}forum SET thread_count={$r[2]}, post_count={$r[1]}, last_post_id={$r[0]} WHERE id={$v}");
				}
			} else {
				q("UPDATE {SQL_TABLE_PREFIX}forum SET thread_count=thread_count-".(count($_POST['sel_th']) - 1)." WHERE id={$frm}");
			}
			db_unlock();

			logaction(_uid, 'THRMERGE', $new_th, count($_POST['sel_th']));
			unset($_POST['sel_th']);
		}
	}

	/* fetch a list of accesible forums */
	$c = uq('SELECT f.id, f.name
			FROM {SQL_TABLE_PREFIX}forum f
			INNER JOIN {SQL_TABLE_PREFIX}fc_view v ON v.f=f.id
			INNER JOIN {SQL_TABLE_PREFIX}cat c ON c.id=f.cat_id
			LEFT JOIN {SQL_TABLE_PREFIX}mod mm ON mm.forum_id=f.id AND mm.user_id='._uid.'
			INNER JOIN {SQL_TABLE_PREFIX}group_cache g1 ON g1.resource_id=f.id AND g1.user_id='.(_uid ? '2147483647' : '0').'
			'.(_uid ? ' LEFT JOIN {SQL_TABLE_PREFIX}group_cache g2 ON g2.resource_id=f.id AND g2.user_id='._uid : '').'
			'.($usr->users_opt & 1048576 ? '' : ' WHERE mm.id IS NOT NULL OR ((CASE WHEN g2.id IS NULL THEN g1.group_cache_opt ELSE g2.group_cache_opt END) & 2) > 0').'
			ORDER BY v.id');
	$vl = $kl = '';
	while ($r = db_rowarr($c)) {
		$vl .= $r[0] . "\n";
		$kl .= $r[1] . "\n";
	}

	$forum_sel = tmpl_draw_select_opt(rtrim($vl), rtrim($kl), $frm, '', '');

	$page = !empty($_POST['page']) ? (int) $_POST['page'] : 1;
	if ($page > 1 && isset($_POST['prev'])) {
		--$page;
	} else if (isset($_POST['next'])) {
		++$page;
	}

	$thread_sel = '';
	if (isset($_POST['sel_th'])) {
		$c = uq("SELECT t.id, m.subject FROM {SQL_TABLE_PREFIX}thread t INNER JOIN {SQL_TABLE_PREFIX}msg m ON t.root_msg_id=m.id WHERE t.id IN(".implode(',', $_POST['sel_th']).")");
		while ($r = db_rowarr($c)) {
			$thread_sel .= '{TEMPLATE: sel_opt_selected}';
		}
		unset($_POST['sel_th']);
	}
	$c = uq("SELECT t.id, m.subject FROM {SQL_TABLE_PREFIX}thread_view tv INNER JOIN {SQL_TABLE_PREFIX}thread t ON t.id=tv.thread_id INNER JOIN {SQL_TABLE_PREFIX}msg m ON m.id=t.root_msg_id WHERE tv.forum_id={$frm} AND page={$page} ORDER BY pos");
	while ($r = db_rowarr($c)) {
		$thread_sel .= '{TEMPLATE: sel_opt}';
	}

/*{POST_PAGE_PHP_CODE}*/
?>
{TEMPLATE: MERGE_TH_PAGE}
