<?php
/***************************************************************************
* copyright            : (C) 2001-2004 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: thr_exch.php.t,v 1.21 2004/01/04 16:38:27 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; either version 2 of the License, or
* (at your option) any later version.
***************************************************************************/

/*{PRE_HTML_PHP}*/

	/* only admins & moderators have access to this control panel */
	if (!_uid || !($usr->users_opt & (1048576|524288))) {
		std_error('access');
	}

	if (isset($_GET['appr']) || isset($_GET['decl']) || isset($_POST['decl'])) {
		fud_use('thrx_adm.inc', true);
	}

	/* verify that we got a valid thread-x-change approval */
	if (isset($_GET['appr']) && ($thrx = thx_get((int)$_GET['appr']))) {
		$data = db_sab('SELECT
					t.forum_id, t.last_post_id, t.root_msg_id, t.last_post_date, t.last_post_id,
					f1.id, f1.last_post_id as f1_lpi, f2.last_post_id AS f2_lpi,
					'.($usr->users_opt & 1048576 ? ' 1 ' : ' mm.id ').' AS md
				FROM {SQL_TABLE_PREFIX}thread t
				INNER JOIN {SQL_TABLE_PREFIX}forum f1 ON t.forum_id=f1.id
				INNER JOIN {SQL_TABLE_PREFIX}forum f2 ON f2.id='.$thrx->frm.'
				LEFT JOIN {SQL_TABLE_PREFIX}mod mm ON mm.forum_id=f2.id AND mm.user_id='._uid.'
				WHERE t.id='.$thrx->th);
		if (!$data) {
			invl_inp_err();
		}
		if (!$data->md) {
			std_error('access');
		}

		th_move($thrx->th, $thrx->frm, $data->root_msg_id, $data->forum_id, $data->last_post_date, $data->last_post_id);

		if ($data->f1_lpi == $data->last_post_id) {
			$mid = (int) q_singleval('SELECT MAX(last_post_id) FROM {SQL_TABLE_PREFIX}thread t INNER JOIN {SQL_TABLE_PREFIX}msg m ON t.root_msg_id=m.id WHERE t.forum_id='.$data->forum_id.' AND t.moved_to=0 AND m.apr=1');
			q('UPDATE {SQL_TABLE_PREFIX}forum SET last_post_id='.$mid.' WHERE id='.$data->forum_id);
		}

		if ($data->f2_lpi < $data->last_post_id) {
			q('UPDATE {SQL_TABLE_PREFIX}forum SET last_post_id='.$data->last_post_id.' WHERE id='.$thrx->frm);
		}

		thx_delete($thrx->id);
		logaction($usr->id, 'THRXAPPROVE', $thrx->th);
	} else if ((isset($_GET['decl']) || isset($_POST['decl'])) && ($thrx = thx_get(($decl = (int)(isset($_GET['decl']) ? $_GET['decl'] : $_POST['decl']))))) {
		$data = db_sab('SELECT u.email, u.login, u.id, m.subject, f1.name AS f1_name, f2.name AS f2_name, '.($usr->users_opt & 1048576 ? ' 1 ' : ' mm.id ').' AS md
				FROM {SQL_TABLE_PREFIX}thread t
				INNER JOIN {SQL_TABLE_PREFIX}forum f1 ON t.forum_id=f1.id
				INNER JOIN {SQL_TABLE_PREFIX}forum f2 ON f2.id='.$thrx->frm.'
				INNER JOIN {SQL_TABLE_PREFIX}msg m ON m.id=t.root_msg_id
				INNER JOIN {SQL_TABLE_PREFIX}users u ON u.id='.$thrx->req_by.'
				LEFT JOIN {SQL_TABLE_PREFIX}mod mm ON mm.forum_id='.$thrx->frm.' AND mm.user_id='._uid.'
				WHERE t.id='.$thrx->th);
		if (!$data) {
			invl_inp_err();
		}
		if (!$data->md) {
			std_error('access');
		}

		if (!empty($_POST['reason'])) {
			send_status_update($data->id, $data->login, $data->email, '{TEMPLATE: exch_decline_ttl}', htmlspecialchars($_POST['reason']));
			thx_delete($thrx->id);
			$decl = null;
		} else {
			$thr_exch_data = '{TEMPLATE: thr_move_decline}';
		}

		logaction($usr->id, 'THRXDECLINE', $thrx->th);
	}

/*{POST_HTML_PHP}*/

	if (!isset($decl)) {
		$thr_exch_data = '';

		$r = uq('SELECT
				thx.*, m.subject, f1.name AS sf_name, f2.name AS df_name, u.alias
			 FROM {SQL_TABLE_PREFIX}thr_exchange thx
			 LEFT JOIN {SQL_TABLE_PREFIX}mod mm ON mm.forum_id=f2.id AND mm.user_id='._uid.'
			 INNER JOIN {SQL_TABLE_PREFIX}thread t ON thx.th=t.id
			 INNER JOIN {SQL_TABLE_PREFIX}msg m ON t.root_msg_id=m.id
			 INNER JOIN {SQL_TABLE_PREFIX}forum f1 ON t.forum_id=f1.id
			 INNER JOIN {SQL_TABLE_PREFIX}forum f2 ON thx.frm=f2.id
			 INNER JOIN {SQL_TABLE_PREFIX}users u ON thx.req_by=u.id
			 WHERE '.($usr->users_opt & 1048576 ? '1=1' : ' mm.id IS NOT NULL'));

		while ($obj = db_rowobj($r)) {
			$thr_exch_data .= '{TEMPLATE: thr_exch_entry}';
		}

		if (!$thr_exch_data) {
			$thr_exch_data = '{TEMPLATE: no_thr_exch}';
		}
	}

/*{POST_PAGE_PHP_CODE}*/
?>
{TEMPLATE: THR_EXCH_PAGE}