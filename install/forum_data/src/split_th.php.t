<?php
/***************************************************************************
* copyright            : (C) 2001-2003 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: split_th.php.t,v 1.30 2003/11/01 19:11:34 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it 
* under the terms of the GNU General Public License as published by the 
* Free Software Foundation; either version 2 of the License, or 
* (at your option) any later version.
***************************************************************************/

/*{PRE_HTML_PHP}*/
/*{POST_HTML_PHP}*/

	$th = isset($_GET['th']) ? (int)$_GET['th'] : (isset($_POST['th']) ? (int)$_POST['th'] : 0);
	if (!$th) {
		invl_inp_err();
	}

	/* permission check */
	if (!($usr->users_opt & 1048576)) {
		$perms = db_saq('SELECT mm.id, '.(_uid ? ' (CASE WHEN g2.id IS NOT NULL THEN g2.group_cache_opt ELSE g1.group_cache_opt END) AS gco ' : ' g1.group_cache_opt AS gco ').'
				FROM {SQL_TABLE_PREFIX}thread t
				LEFT JOIN {SQL_TABLE_PREFIX}mod mm ON mm.user_id='._uid.' AND mm.forum_id=t.forum_id
				'.(_uid ? 'INNER JOIN {SQL_TABLE_PREFIX}group_cache g1 ON g1.user_id=2147483647 AND g1.resource_id=t.forum_id LEFT JOIN {SQL_TABLE_PREFIX}group_cache g2 ON g2.user_id='._uid.' AND g2.resource_id=t.forum_id' : 'INNER JOIN {SQL_TABLE_PREFIX}group_cache g1 ON g1.user_id=0 AND g1.resource_id=t.forum_id').'
				WHERE t.id='.$th);
		if (!$perms || !$perms[0] && !($perms[1] & 2048)) {
			std_error('access');
		}
	}

	$forum = isset($_POST['forum']) ? (int)$_POST['forum'] : 0;

	if ($forum && !empty($_POST['new_title']) && isset($_POST['sel_th']) && ($mc = count($_POST['sel_th']))) {
		/* we need to make sure that the user has access to destination forum */
		if (!($usr->users_opt & 1048576) && !q_singleval('SELECT f.id FROM {SQL_TABLE_PREFIX}forum f LEFT JOIN {SQL_TABLE_PREFIX}mod mm ON mm.user_id='._uid.' AND mm.forum_id=f.id '.(_uid ? 'INNER JOIN {SQL_TABLE_PREFIX}group_cache g1 ON g1.user_id=2147483647 AND g1.resource_id=f.id LEFT JOIN {SQL_TABLE_PREFIX}group_cache g2 ON g2.user_id='._uid.' AND g2.resource_id=f.id' : 'INNER JOIN {SQL_TABLE_PREFIX}group_cache g1 ON g1.user_id=0 AND g1.resource_id=f.id').' WHERE f.id='.$forum.' AND (mm.id IS NOT NULL OR '.(_uid ? ' ((CASE WHEN g2.id IS NOT NULL THEN g2.group_cache_opt ELSE g1.group_cache_opt END)' : ' (g1.group_cache_opt').' & 4) > 0)')) {
			std_error('access');
		}

		foreach ($_POST['sel_th'] as $k => $v) {
			if (!(int)$v) {
				unset($_POST['sel_th'][$k]);
			}
			$_POST['sel_th'][$k] = (int) $v;
		}
		/* sanity check */
		if (!count($_POST['sel_th'])) {
			if ($FUD_OPT_2 & 32768) {
				header('Location: {ROOT}/t/'.$th.'/'._rsidl);
			} else {
				header('Location: {ROOT}?t='.d_thread_view.'&th='.$th.'&'._rsidl);
			}
			exit;
		}

		if (isset($_POST['btn_selected'])) {
			sort($_POST['sel_th']);
			$mids = implode(',', $_POST['sel_th']);
			$start = $_POST['sel_th'][0];
			$end = $_POST['sel_th'][($mc - 1)];
		} else {
			$c = uq('SELECT id FROM {SQL_TABLE_PREFIX}msg WHERE thread_id='.$th.' AND id NOT IN('.implode(',', $_POST['sel_th']).') AND apr=1 ORDER BY post_stamp ASC');
			while ($r = db_rowarr($c)) {
				$a[] = $r[0];
			}
			/* sanity check */
			if (!isset($a)) {
				if ($FUD_OPT_2 & 32768) {
					header('Location: {ROOT}/t/'.$th_id.'/'._rsidl);
				} else {
					header('Location: {ROOT}?t='.d_thread_view.'&th='.$th_id.'&'._rsidl);
				}
				exit;
			}
			$mids = implode(',', $a);
			$mc = count($a);
			$start = $a[0];
			$end = $a[($mc - 1)];
		}

		/* fetch all relevant information */
		$data = db_sab('SELECT
				t.id, t.forum_id, t.replies, t.root_msg_id, t.last_post_id, t.last_post_date,
				m1.post_stamp AS new_th_lps, m1.id AS new_th_lpi,
				m2.post_stamp AS old_fm_lpd,
				f1.last_post_id AS src_lpi,
				f2.last_post_id AS dst_lpi
				FROM {SQL_TABLE_PREFIX}thread t
				INNER JOIN {SQL_TABLE_PREFIX}forum f1 ON t.forum_id=f1.id
				INNER JOIN {SQL_TABLE_PREFIX}forum f2 ON f2.id='.$forum.'
				INNER JOIN {SQL_TABLE_PREFIX}msg m1 ON m1.id='.$end.'
				INNER JOIN {SQL_TABLE_PREFIX}msg m2 ON m2.id=f2.last_post_id

		WHERE t.id='.$th);

		/* sanity check */
		if (!$data->replies) {
			if ($FUD_OPT_2 & 32768) {
				header('Location: {ROOT}/t/'.$th_id.'/'._rsidl);
			} else {
				header('Location: {ROOT}?t='.d_thread_view.'&th='.$th_id.'&'._rsidl);
			}
			exit;
		}

		apply_custom_replace($_POST['new_title']);

		if ($mc != ($data->replies + 1)) { /* check that we need to move the entire thread */
			db_lock('{SQL_TABLE_PREFIX}thread_view WRITE, {SQL_TABLE_PREFIX}thread WRITE, {SQL_TABLE_PREFIX}forum WRITE, {SQL_TABLE_PREFIX}msg WRITE, {SQL_TABLE_PREFIX}poll WRITE');

			$new_th = th_add($start, $forum, $data->new_th_lps, 0, 0, ($mc - 1), $data->new_th_lpi);

			/* Deal with the new thread */
			q('UPDATE {SQL_TABLE_PREFIX}msg SET thread_id='.$new_th.' WHERE id IN ('.$mids.')');
			q('UPDATE {SQL_TABLE_PREFIX}msg SET reply_to='.$start.' WHERE thread_id='.$new_th.' AND reply_to NOT IN ('.$mids.')');
			q("UPDATE {SQL_TABLE_PREFIX}msg SET reply_to=0, subject='".addslashes(htmlspecialchars($_POST['new_title']))."' WHERE id=".$start);

			/* Deal with the old thread */
			list($lpi, $lpd) = db_saq("SELECT id, post_stamp FROM {SQL_TABLE_PREFIX}msg WHERE thread_id=".$data->id." AND apr=1 ORDER BY post_stamp DESC LIMIT 1");$old_root_msg_id = q_singleval("SELECT id FROM {SQL_TABLE_PREFIX}msg WHERE thread_id=".$data->id." AND apr=1 ORDER BY post_stamp ASC LIMIT 1");
			q("UPDATE {SQL_TABLE_PREFIX}msg SET reply_to=".$old_root_msg_id." WHERE thread_id=".$data->id." AND reply_to IN(".$mids.")");
			q('UPDATE {SQL_TABLE_PREFIX}msg SET reply_to=0 WHERE id='.$old_root_msg_id);
			q('UPDATE {SQL_TABLE_PREFIX}thread SET root_msg_id='.$old_root_msg_id.', replies=replies-'.$mc.', last_post_date='.$lpd.', last_post_id='.$lpi.' WHERE id='.$data->id);

			if ($forum != $data->forum_id) {
				$c = q('SELECT poll_id FROM {SQL_TABLE_PREFIX}msg WHERE thread_id='.$new_th.' AND apr=1 AND poll_id>0');
				while ($r = db_rowarr($c)) {
					$p[] = $r[0];
				}
				unset($c);
				if (isset($p)) {
					q('UPDATE {SQL_TABLE_PREFIX}poll SET forum_id='.$data->forum_id.' WHERE id IN('.implode(',', $p).')');
				}

				/* deal with the source forum */
				if ($data->src_lpi != $data->last_post_id || $data->last_post_date <= $lpd) {
					q('UPDATE {SQL_TABLE_PREFIX}forum SET post_count=post_count-'.$mc.' WHERE id='.$data->forum_id);
				} else {
					q('UPDATE {SQL_TABLE_PREFIX}forum SET post_count=post_count-'.$mc.', last_post_id='.th_frm_last_post_id($data->forum_id, $data->id).' WHERE id='.$data->forum_id);
				}

				/* deal with destination forum */
				if ($data->old_fm_lpd > $data->new_th_lps) {
					q('UPDATE {SQL_TABLE_PREFIX}forum SET post_count=post_count+'.$mc.', thread_count=thread_count+1 WHERE id='.$forum);
				} else {
					q('UPDATE {SQL_TABLE_PREFIX}forum SET post_count=post_count+'.$mc.', thread_count=thread_count+1, last_post_id='.$data->new_th_lpi.' WHERE id='.$forum);
				}

				rebuild_forum_view($forum);
			} else {
				if ($data->src_lpi == $data->last_post_id && $data->last_post_date >= $lpd) {
					q('UPDATE {SQL_TABLE_PREFIX}forum SET thread_count=thread_count+1 WHERE id='.$data->forum_id);
				} else {
					q('UPDATE {SQL_TABLE_PREFIX}forum SET thread_count=thread_count+1, last_post_id='.$data->new_th_lpi.' WHERE id='.$data->forum_id);
				}
			}
			rebuild_forum_view($data->forum_id);
			db_unlock();
			logaction(_uid, 'THRSPLIT', $new_th);
			$th_id = $new_th;
		} else { /* moving entire thread */
			q("UPDATE {SQL_TABLE_PREFIX}msg SET subject='".addslashes(htmlspecialchars($_POST['new_title']))."' WHERE id=".$data->root_msg_id);
			if ($forum != $data->forum_id) {
				th_move($data->id, $forum, $data->root_msg_id, $thr->forum_id, $data->last_post_date, $data->last_post_id);

				if ($data->src_lpi == $data->last_post_id) {
					q('UPDATE {SQL_TABLE_PREFIX}forum SET last_post_id='.th_frm_last_post_id($data->forum_id, $data->id).' WHERE id='.$data->forum_id);
				}
				if ($data->old_fm_lpd < $data->last_post_date) {
					q('UPDATE {SQL_TABLE_PREFIX}forum SET last_post_id='.$data->last_post_id.' WHERE id='.$forum);
				}

				logaction(_uid, 'THRMOVE', $th);
			}
			$th_id = $data->id;
		}
		if ($FUD_OPT_2 & 32768) {
			header('Location: {ROOT}/t/'.$th_id.'/'._rsidl);
		} else {
			header('Location: {ROOT}?t='.d_thread_view.'&th='.$th_id.'&'._rsidl);
		}
		exit;
	}
	/* fetch a list of accesible forums */
	$c = uq('SELECT f.id, f.name
			FROM {SQL_TABLE_PREFIX}forum f
			INNER JOIN {SQL_TABLE_PREFIX}fc_view v ON v.f=f.id
			INNER JOIN {SQL_TABLE_PREFIX}cat c ON c.id=f.cat_id
			LEFT JOIN {SQL_TABLE_PREFIX}mod mm ON mm.forum_id=f.id AND mm.user_id='._uid.'
			INNER JOIN {SQL_TABLE_PREFIX}group_cache g1 ON g1.resource_id=f.id AND g1.user_id='.(_uid ? '2147483647' : '0').'
			'.(_uid ? ' LEFT JOIN {SQL_TABLE_PREFIX}group_cache g2 ON g2.resource_id=f.id AND g2.user_id='._uid : '').'
			'.($usr->users_opt & 1048576 ? '' : ' WHERE mm.id IS NOT NULL OR ((CASE WHEN g2.id IS NULL THEN g1.group_cache_opt ELSE g2.group_cache_opt END) & 4) > 0').'
			ORDER BY v.id');
	$vl = $kl = '';
	while ($r = db_rowarr($c)) {
		$vl .= $r[0] . "\n";
		$kl .= $r[1] . "\n";
	}

	if (!$forum) {
		$forum = q_singleval('SELECT forum_id FROM {SQL_TABLE_PREFIX}thread WHERE id='.$th);
	}

	$forum_sel = tmpl_draw_select_opt(rtrim($vl), rtrim($kl), $forum, '{TEMPLATE: sel_opt}', '{TEMPLATE: sel_opt_selected}');

	$c = uq("SELECT m.id, m.foff, m.length, m.file_id, m.subject, m.post_stamp, u.alias FROM {SQL_TABLE_PREFIX}msg m LEFT JOIN {SQL_TABLE_PREFIX}users u ON m.poster_id=u.id WHERE m.thread_id=".$th." AND m.apr=1 ORDER BY m.post_stamp ASC");

	$anon_alias = htmlspecialchars($ANON_NICK);

	$msg_entry = '';
	while ($r = db_rowobj($c)) {
		if (!$r->alias) {
			$r->alias = $anon_alias;
		}
		$msg_body = read_msg_body($r->foff, $r->length, $r->file_id);
		$msg_entry .= '{TEMPLATE: msg_entry}';
	}
	un_register_fps();

/*{POST_PAGE_PHP_CODE}*/
?>
{TEMPLATE: SPLIT_TH_PAGE}