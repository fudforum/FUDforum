<?php
/***************************************************************************
* copyright            : (C) 2001-2003 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: mvthread.php.t,v 1.24 2003/11/14 10:50:19 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; either version 2 of the License, or
* (at your option) any later version.
***************************************************************************/

	define('plain_form', 1);

/*{PRE_HTML_PHP}*/

	$th = isset($_POST['th']) ? (int)$_POST['th'] : (isset($_GET['th']) ? (int)$_GET['th'] : 0);
	$thx = isset($_POST['thx']) ? (int)$_POST['thx'] : (isset($_GET['thx']) ? (int)$_GET['thx'] : 0);
	$to = isset($_GET['to']) ? (int)$_GET['to'] : 0;

	if (!count($_POST) && !sq_check(0, $usr->last_visit)) {
		return;
	}

	/* thread x-change */
	if ($th && $thx) {
		if (!($usr->users_opt & 1048576) && q_singleval('SELECT id FROM {SQL_TABLE_PREFIX}mod WHERE forum_id='.$thx.' AND user_id='._uid)) {
			std_error('access');
		}

		if (!empty($_POST['reason_msg'])) {
			fud_use('thrx_adm.inc', true);
			if (thx_add($_POST['reason_msg'], $th, $thx, _uid)) {
				logaction(_uid, 'THRXREQUEST', $th);
			}
			exit('<html><script>window.close();</script></html>');
		} else {
			$thr = db_sab('SELECT f.name AS frm_name, m.subject FROM {SQL_TABLE_PREFIX}forum f INNER JOIN {SQL_TABLE_PREFIX}thread t ON t.id='.$th.' INNER JOIN {SQL_TABLE_PREFIX}msg m ON t.root_msg_id=m.id WHERE f.id='.$thx);
			$table_data = '{TEMPLATE: move_thread_request}';
		}
	}

	/* moving a thread */
	if ($th && $to) {
		$thr = db_sab('SELECT
				t.id, t.forum_id, t.last_post_id, t.root_msg_id, t.last_post_date, t.last_post_id,
				f1.last_post_id AS f1_lpi, f2.last_post_id AS f2_lpi,
				'.($usr->users_opt & 1048576 ? ' 1 AS mod1, 1 AS mod2' : ' mm1.id AS mod1, mm2.id AS mod2').',
				(CASE WHEN gs2.id IS NOT NULL THEN gs2.group_cache_opt ELSE gs1.group_cache_opt END) AS sgco,
				(CASE WHEN gd2.id IS NOT NULL THEN gd2.group_cache_opt ELSE gd1.group_cache_opt END) AS dgco
			FROM {SQL_TABLE_PREFIX}thread t
			INNER JOIN {SQL_TABLE_PREFIX}forum f1 ON t.forum_id=f1.id
			INNER JOIN {SQL_TABLE_PREFIX}forum f2 ON f2.id='.$to.'
			LEFT JOIN {SQL_TABLE_PREFIX}mod mm1 ON mm1.forum_id=f1.id AND mm1.user_id='._uid.'
			LEFT JOIN {SQL_TABLE_PREFIX}mod mm2 ON mm2.forum_id=f2.id AND mm2.user_id='._uid.'
			INNER JOIN {SQL_TABLE_PREFIX}group_cache gs1 ON gs1.user_id=2147483647 AND gs1.resource_id=f1.id
			LEFT JOIN {SQL_TABLE_PREFIX}group_cache gs2 ON gs2.user_id='._uid.' AND gs2.resource_id=f1.id
			INNER JOIN {SQL_TABLE_PREFIX}group_cache gd1 ON gd1.user_id=2147483647 AND gd1.resource_id=f2.id
			LEFT JOIN {SQL_TABLE_PREFIX}group_cache gd2 ON gd2.user_id='._uid.' AND gd2.resource_id=f2.id
			WHERE t.id='.$th);

		if (!$thr) {
			invl_inp_err();
		}

		if ((!$thr->mod1 && !($thr->sgco & 8192)) || (!$thr->mod2 && !($thr->dgco & 8192))) {
			std_error('access');
		}

		/* fetch data about source thread & forum */
		$src_frm_lpi = (int) $thr->f1_lpi;
		/* fetch data about dest forum */
		$dst_frm_lpi = (int) $thr->f2_lpi;

		th_move($thr->id, $to, $thr->root_msg_id, $thr->forum_id, $thr->last_post_date, $thr->last_post_id);

		if ($src_frm_lpi == $thr->last_post_id) {
			$mid = (int) q_singleval('SELECT MAX(last_post_id) FROM {SQL_TABLE_PREFIX}thread t INNER JOIN {SQL_TABLE_PREFIX}msg m ON t.root_msg_id=m.id WHERE t.forum_id='.$thr->forum_id.' AND t.moved_to=0 AND m.apr=1');
			q('UPDATE {SQL_TABLE_PREFIX}forum SET last_post_id='.$mid.' WHERE id='.$thr->forum_id);
		}

		if ($dst_frm_lpi < $thr->last_post_id) {
			q('UPDATE {SQL_TABLE_PREFIX}forum SET last_post_id='.$thr->last_post_id.' WHERE id='.$to);
		}

		logaction(_uid, 'THRMOVE', $th);

		if ($FUD_OPT_2 & 32768 && !empty($_SERVER['PATH_INFO'])) {
			exit("<html><script>window.opener.location='{ROOT}/f/".$thr->forum_id."/"._rsid."'; window.close();</script></html>");
		} else {
			exit("<html><script>window.opener.location='{ROOT}?t=".t_thread_view."&"._rsid."&frm_id=".$thr->forum_id."'; window.close();</script></html>");
		}
	}

/*{POST_HTML_PHP}*/

	if (!$thx) {
		$thr = db_sab('SELECT f.name AS frm_name, m.subject, t.forum_id, t.id FROM {SQL_TABLE_PREFIX}thread t INNER JOIN {SQL_TABLE_PREFIX}forum f ON f.id=t.forum_id INNER JOIN {SQL_TABLE_PREFIX}msg m ON t.root_msg_id=m.id WHERE t.id='.$th);

		$r = uq('SELECT f.name, f.id, c.name, m.user_id, (CASE WHEN g2.id IS NOT NULL THEN g2.group_cache_opt ELSE g1.group_cache_opt END) AS gco
			FROM {SQL_TABLE_PREFIX}forum f
			INNER JOIN {SQL_TABLE_PREFIX}fc_view v ON v.f=f.id
			INNER JOIN {SQL_TABLE_PREFIX}cat c ON c.id=v.c
			LEFT JOIN {SQL_TABLE_PREFIX}mod m ON m.user_id='._uid.' AND m.forum_id=f.id
			INNER JOIN {SQL_TABLE_PREFIX}group_cache g1 ON g1.user_id=2147483647 AND g1.resource_id=f.id
			LEFT JOIN {SQL_TABLE_PREFIX}group_cache g2 ON g2.user_id='._uid.' AND g2.resource_id=f.id
			WHERE c.id!=0 AND f.id!='.$thr->forum_id.($usr->users_opt & 1048576 ? '' : ' AND (CASE WHEN m.user_id IS NOT NULL OR ((CASE WHEN g2.id IS NOT NULL THEN g2.group_cache_opt ELSE g1.group_cache_opt END) & 1) > 0 THEN 1 ELSE 0 END)=1').'
			ORDER BY v.id');

		$table_data = $prev_cat = '';

		while ($ent = db_rowarr($r)) {
			if ($ent[2] !== $prev_cat) {
				$table_data .= '{TEMPLATE: cat_entry}';
				$prev_cat = $ent[2];
			}

			if ($ent[3] || $usr->users_opt & 1048576 || $ent[4] & 8192) {
				$table_data .= '{TEMPLATE: forum_entry}';
			} else {
				$table_data .= '{TEMPLATE: txc_forum_entry}';
			}
		}
	}
/*{POST_PAGE_PHP_CODE}*/
?>
{TEMPLATE: MVTHREAD_PAGE}