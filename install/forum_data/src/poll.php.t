<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: poll.php.t,v 1.14 2003/10/01 21:51:52 hackie Exp $
****************************************************************************

****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

	define('plain_form', 1);

/*{PRE_HTML_PHP}*/

	if (isset($_GET['frm_id'])) {
		$frm_id = (int) $_GET['frm_id'];
	} else if (isset($_POST['frm_id'])) {
		$frm_id = (int) $_POST['frm_id'];
	} else {
		invl_inp_err();
	}

	if (isset($_GET['pl_id'])) {
		$pl_id = (int) $_GET['pl_id'];
	} else if (isset($_POST['pl_id'])) {
		$pl_id = (int) $_POST['pl_id'];
	} else {
		$pl_id = 0;
	}

	make_perms_query($fields, $join, $frm_id);

	/* fetch forum, poll & moderator data */
	if (!$pl_id) { /* new poll */
		$frm = db_sab('SELECT f.id, f.forum_opt, m.id AS md, '.$fields.'
			FROM {SQL_TABLE_PREFIX}forum f
			LEFT JOIN {SQL_TABLE_PREFIX}mod m ON m.user_id='._uid.' AND m.forum_id=f.id
			'.$join.'
			WHERE f.id='.$frm_id);
	} else { /* editing a poll */
		$frm = db_sab('SELECT f.id, f.forum_opt, m.id AS is_mod, ms.id AS old_poll, p.id AS poll_id, p.*, '.$fields.'
			FROM {SQL_TABLE_PREFIX}forum f
			INNER JOIN {SQL_TABLE_PREFIX}poll p ON p.id='.$pl_id.'
			LEFT JOIN {SQL_TABLE_PREFIX}mod m ON m.user_id='._uid.' AND m.forum_id=f.id
			LEFT JOIN {SQL_TABLE_PREFIX}msg ms ON ms.poll_id=p.id
			'.$join.'
			WHERE f.id='.$frm_id);
	}

	$frm->group_cache_opt = (int) $frm->group_cache_opt;
	$frm->forum_opt = (int) $frm->forum_opt;

	if (!$frm || (empty($frm->md) && $usr->users_opt & 1048576 && (!($frm->group_cache_opt & 4096) || (!empty($frm->old_poll) && !($frm->group_cache_opt & 16) && $frm->owner != _uid) || (empty($frm->old_poll) && !($frm->group_cache_opt & 4))))) {
		std_error('access');
	}

	if (isset($_POST['pl_submit'])) {
		if ($pl_id) { /* update a poll */
			poll_sync($pl_id, $_POST['pl_name'], $_POST['pl_max_votes'], $_POST['pl_expiry_date']);
		} else { /* adding a new poll */
			$pl_id = poll_add($_POST['pl_name'], $_POST['pl_max_votes'], $_POST['pl_expiry_date']);
		}
		$pl_name = $_POST['pl_name'];
		$pl_max_votes = $_POST['pl_max_votes'];
		$pl_expiry_date = $_POST['pl_expiry_date'];
	} else if (!empty($frm->poll_id)) {
		$pl_name = $frm->name;
		reverse_fmt($pl_name);
		$pl_max_votes = $frm->max_votes;
		$pl_expiry_date = $frm->expiry_date;
	} else {
		$pl_name = $pl_max_votes = $pl_expiry_date = '';
	}

	/* remove a poll option */
	if (isset($_GET['del_id'])) {
		poll_del_opt((int)$_GET['del_id'], $pl_id);
	}

	/* Adding or Updating poll options */
	if(!empty($_POST['pl_upd']) || !empty($_POST['pl_add'])) {
		$pl_option = apply_custom_replace($_POST['pl_option']);

		if ($frm->forum_opt & 16) {
			$pl_option = tags_to_html($pl_option, $frm->group_cache_opt & 32768);
		} else if ($frm->forum_opt & 8) {
			$pl_option = nl2br(htmlspecialchars($pl_option));
		}

		if ($frm->group_cache_opt & 16384 && !isset($_POST['pl_smiley_disabled'])) {
			$pl_option = smiley_to_post($pl_option);
		}

		if (isset($_POST['pl_upd'], $_POST['pl_option_id'])) {
			poll_opt_sync((int)$_POST['pl_option_id'], $pl_option);
		} else {
			poll_opt_add($pl_option, $pl_id);
		}
		$pl_option = '';
	}

	/* if we have a poll, fetch poll options */
	if ($pl_id) {
		$poll_opts = poll_fetch_opts($pl_id);
	}

	/* edit a poll option */
	if (isset($_GET['pl_optedit'])) {
		$pl_option = @$poll_opts[$_GET['pl_optedit']];
		$pl_option_id = $_GET['pl_optedit'];
	}

	$TITLE_EXTRA = ': {TEMPLATE: poll_title}';

/*{POST_HTML_PHP}*/

	$pl_expiry_date_data = tmpl_draw_select_opt("0\n3600\n21600\n43200\n86400\n259200\n604800\n2635200\n31536000", "{TEMPLATE: poll_unlimited}\n1 {TEMPLATE: poll_hour}\n6 {TEMPLATE: poll_hours}\n12 {TEMPLATE: poll_hours}\n1 {TEMPLATE: poll_day}\n3 {TEMPLATE: poll_days}\n1 {TEMPLATE: poll_week}\n1 {TEMPLATE: poll_month}\n1 {TEMPLATE: poll_year}", $pl_expiry_date, '{TEMPLATE: sel_opt}', '{TEMPLATE: sel_opt_selected}');
	$pl_max_votes_data = tmpl_draw_select_opt("0\n10\n50\n100\n200\n500\n1000\n10000\n100000", "{TEMPLATE: poll_unlimited}\n10\n50\n100\n200\n500\n1000\n10000\n100000", $pl_max_votes, '{TEMPLATE: sel_opt}', '{TEMPLATE: sel_opt_selected}');

	if ($frm->group_cache_opt & 16384) {
		$checked = isset($_POST['pl_smiley_disabled']) ? ' checked' : '';
		$pl_smiley_disabled_chk = '{TEMPLATE: pl_smiley_disabled_chk}';
	} else {
		$pl_smiley_disabled_chk = '';
	}

	$pl_submit = !$pl_id ? '{TEMPLATE: pl_submit_create}' : '{TEMPLATE: pl_submit_update}';

	/* this is only available on a created poll */
	if ($pl_id) {
		if (isset($pl_option)) {
			$pl_option = post_to_smiley($pl_option);

			if ($frm->forum_opt & 16) {
				$pl_option = html_to_tags($pl_option);
			} else if ($frm->forum_opt & 8) {
				reverse_nl2br($pl_option);
			}

			$pl_option = apply_reverse_replace($pl_option);
		} else {
			$pl_option = '';
		}

		$pl_action = !isset($_GET['pl_optedit']) ? '{TEMPLATE: pl_add}' : '{TEMPLATE: pl_upd}';

		$poll_option_entry_data = '';
		if (!empty($poll_opts)) {
			foreach ($poll_opts as $k => $v) {
				$poll_option_entry_data .= '{TEMPLATE: poll_option_entry}';
			}
		}

		$poll_editor = '{TEMPLATE: poll_editor}';
	} else {
		$poll_editor = '';
	}

	$poll_submit_btn = !$pl_id ? '{TEMPLATE: btn_submit}' : '{TEMPLATE: btn_update}';

/*{POST_PAGE_PHP_CODE}*/
?>
{TEMPLATE: POLL_PAGE}