<?php
/**
* copyright            : (C) 2001-2006 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: threadt.php.t,v 1.46 2006/03/28 15:18:15 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; either version 2 of the License, or
* (at your option) any later version.
**/

/*{PRE_HTML_PHP}*/

	if (!($FUD_OPT_2 & 512)) {
		error_dialog('{TEMPLATE: threadt_disabled_ttl}', '{TEMPLATE: threadt_disabled_desc}');
	}

	ses_update_status($usr->sid, '{TEMPLATE: threadt_update}', $frm->id);

/*{POST_HTML_PHP}*/

	$TITLE_EXTRA = ': {TEMPLATE: thread_title}';

	$r = uq('SELECT
			t.moved_to, t.thread_opt, t.root_msg_id, r.last_view,
			m.subject, m.reply_to, m.poll_id, m.attach_cnt, m.icon, m.poster_id, m.post_stamp, m.thread_id, m.id,
			u.alias
		FROM {SQL_TABLE_PREFIX}tv_'.$frm->id.' tv
		INNER JOIN {SQL_TABLE_PREFIX}thread t ON tv.thread_id=t.id
		INNER JOIN {SQL_TABLE_PREFIX}msg m ON t.id=m.thread_id AND m.apr=1
		LEFT JOIN {SQL_TABLE_PREFIX}users u ON m.poster_id=u.id
		LEFT JOIN {SQL_TABLE_PREFIX}read r ON t.id=r.thread_id AND r.user_id='._uid.'
		WHERE tv.seq BETWEEN '.($lwi - ($cur_frm_page * $THREADS_PER_PAGE) + 1).' AND '.($lwi - (($cur_frm_page - 1) * $THREADS_PER_PAGE)).'
		ORDER BY tv.seq DESC, m.id');

	if (!($obj = db_rowobj($r))) {
		$thread_list_table_data = '{TEMPLATE: no_messages}';
	} else {
		$thread_list_table_data = '';
		$s = $cur_th_id = 0;
		error_reporting(0);

		unset($stack, $tree, $arr, $cur);
		while (1) {
			if ($s) { /* 1st run handler */
				$obj = db_rowobj($r);
			}
			$s = 1;

			if ($obj->thread_id != $cur_th_id) {
				if (is_array($tree->kiddies)) {
					reset($tree->kiddies);
					$stack[0] = &$tree;
					$stack_cnt = isset($tree->kiddie_count) ? $tree->kiddie_count : 0;
					$j = $lev = 0;

					$thread_list_table_data .= '{TEMPLATE: thread_sep_s}';

					while ($stack_cnt > 0) {
						$cur = &$stack[$stack_cnt-1];

						if (isset($cur->subject) && empty($cur->sub_shown)) {
							if ($TREE_THREADS_MAX_DEPTH > $lev) {
								if (isset($cur->subject[$TREE_THREADS_MAX_SUBJ_LEN])) {
									$cur->subject = substr($cur->subject, 0, $TREE_THREADS_MAX_SUBJ_LEN).'...';
								}
								if (_uid) {
									if ($usr->last_read < $cur->post_stamp && $cur->post_stamp>$cur->last_view) {
										$thread_read_status = $cur->thread_opt & 1 ? '{TEMPLATE: thread_unread_locked}'	: '{TEMPLATE: thread_unread}';
									} else {
										$thread_read_status = $cur->thread_opt & 1 ? '{TEMPLATE: thread_read_locked}' : '{TEMPLATE: thread_read}';
									}
								} else {
									$thread_read_status = $cur->thread_opt & 1 ? '{TEMPLATE: thread_read_locked}' : '{TEMPLATE: thread_read_unreg}';
								}

								$thread_list_table_data .= '{TEMPLATE: thread_row}';
							} else if ($TREE_THREADS_MAX_DEPTH == $lev) {
								$thread_list_table_data .= '{TEMPLATE: max_depth_reached}';
							}

							$cur->sub_shown = 1;
						}

						if (!isset($cur->kiddie_count)) {
							$cur->kiddie_count = 0;
						}

						if ($cur->kiddie_count && isset($cur->kiddie_pos)) {
							++$cur->kiddie_pos;
						} else {
							$cur->kiddie_pos = 0;
						}

						if ($cur->kiddie_pos < $cur->kiddie_count) {
							++$lev;
							$stack[$stack_cnt++] = &$cur->kiddies[$cur->kiddie_pos];
						} else { // unwind the stack if needed
							unset($stack[--$stack_cnt]);
							--$lev;
						}
					}
					$thread_list_table_data .= '{TEMPLATE: thread_sep_e}';
				}

				$cur_th_id = $obj->thread_id;
				unset($stack, $tree, $arr, $cur);
			}

			if (!$obj) {
				break;
			}

			$arr[$obj->id] = $obj;
			$arr[$obj->reply_to]->kiddie_count++;
			$arr[$obj->reply_to]->kiddies[] = &$arr[$obj->id];

			if (!$obj->reply_to) {
				$tree->kiddie_count++;
				$tree->kiddies[] = &$arr[$obj->id];
			}
		}
	}
	unset($r);

	if ($FUD_OPT_2 & 32768) {
		$page_pager = tmpl_create_pager($start, 1, ceil($frm->thread_count / $THREADS_PER_PAGE), '{ROOT}/sf/threadt/'.$frm->id.'/1/', '/' . _rsid);
	} else {
		$page_pager = tmpl_create_pager($start, 1, ceil($frm->thread_count / $THREADS_PER_PAGE), '{ROOT}?t=threadt&amp;frm_id='.$frm->id.'&amp;'._rsid);
	}

/*{POST_PAGE_PHP_CODE}*/
?>
{TEMPLATE: THREAD_PAGE}
<?php
	if (_uid) {
		while (@ob_end_flush());
		user_register_forum_view($frm->id);
	}
?>