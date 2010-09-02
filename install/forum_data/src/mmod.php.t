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

	if (isset($_GET['del'])) {
		$del = (int) $_GET['del'];
	} else if (isset($_POST['del'])) {
		$del = (int) $_POST['del'];
	} else {
		$del = 0;
	}
	if (isset($_GET['th'])) {
		$th = (int) $_GET['th'];
	} else if (isset($_POST['th'])) {
		$th = (int) $_POST['th'];
	} else {
		$th = 0;
	}

	if (isset($_POST['NO'])) {
		check_return($usr->returnto);
	}

	if ($del) {
		if (!($data = db_saq('SELECT t.forum_id, m.thread_id, m.id, m.subject, t.root_msg_id, m.reply_to, t.replies, mm.id,
			COALESCE(g2.group_cache_opt, g1.group_cache_opt) AS gco,
			m.foff, m.length, m.file_id, m.poster_id, u.alias, u.email, m.subject
			FROM {SQL_TABLE_PREFIX}msg m
			INNER JOIN {SQL_TABLE_PREFIX}thread t ON t.id=m.thread_id
			LEFT JOIN {SQL_TABLE_PREFIX}mod mm ON mm.forum_id=t.forum_id AND mm.user_id='. _uid .'
			INNER JOIN {SQL_TABLE_PREFIX}group_cache g1 ON g1.user_id='. (_uid ? '2147483647': '0') .' AND g1.resource_id=t.forum_id
			LEFT JOIN {SQL_TABLE_PREFIX}group_cache g2 ON g2.user_id='. _uid .' AND g2.resource_id=t.forum_id
			LEFT JOIN {SQL_TABLE_PREFIX}users u ON u.id=m.poster_id
			WHERE m.id='. $del))) {
			check_return($usr->returnto);
		}

		if ($del && !($data[8] & 32) && !$is_a && !$data[7]) {
			check_return($usr->returnto);
		}

		if (empty($_POST['confirm'])) {
			if ($data[2] != $data[4]) {
				$delete_msg = '{TEMPLATE: single_msg_delete}';
			} else {
				$delete_msg = '{TEMPLATE: thread_delete}';
			}
			?> {TEMPLATE: delete_confirm_pg} <?php
			exit;
		}

		if (isset($_POST['YES'])) {
			$if_not_pm = (!empty($_POST['del_reason']) && $data[12]);

			if ($if_not_pm && !empty($_POST['del_inc_body'])) {
				$body = read_msg_body($data[9], $data[10], $data[11]);
			} else {
				$body = '';
			}
			if ($if_not_pm) {
				fud_use('ssu.inc');
				fud_use('private.inc');
				if ($body) {
					/* PM disabled, notification will be sent via e-mail. */
					if ($FUD_OPT_1 & 1024) {
						/* Will be done by send_status_update(). */
						$body = str_replace('<br />', '', $body);
					} else {
						$body = strip_tags($body);
					}
					$body = '{TEMPLATE: delete_msg_pm_body}';
				} else {
					$body = $FUD_OPT_1 & 1024 ? htmlspecialchars($_POST['del_reason']) : $_POST['del_reason'];
				}
				send_status_update($data[12], $data[13], $data[14], '{TEMPLATE: delete_msg_removed_ttl}', $body);
			}

			if ($data[2] == $data[4]) {
				logaction(_uid, 'DELTHR', 0, '"'. $data[3] .'" w/'. $data[6] .' replies');

				fud_msg_edit::delete(true, $data[2], 1);

				if (strpos($usr->returnto, 'selmsg') === false) {
					if ($FUD_OPT_2 & 32768) {
						header('Location: {FULL_ROOT}{ROOT}/f/'. $data[0] .'/'. _rsidl);
					} else {
						header('Location: {FULL_ROOT}{ROOT}?t='. t_thread_view .'&'. _rsidl .'&frm_id='. $data[0]);
					}
					exit;
				} else {
					check_return($usr->returnto);
				}
			} else {
				logaction(_uid, 'DELMSG', 0, $data[3]);
				fud_msg_edit::delete(true, $data[2], 0);
			}
		}

		if (strpos($usr->returnto, 'selmsg') !== false) {
			check_return($usr->returnto);
		}

		if (d_thread_view == 'tree') {
			if (!$data[5]) {
				if ($FUD_OPT_2 & 32768) {
					header('Location: {FULL_ROOT}{ROOT}/mv/tree/'. $data[1] .'/'. _rsidl);
				} else {
					header('Location: {FULL_ROOT}{ROOT}?t=tree&'. _rsidl .'&th='. $data[1]);
				}
			} else {
				if ($FUD_OPT_2 & 32768) {
					header('Location: {FULL_ROOT}{ROOT}/mv/tree/'. $data[1] .'/'. $data[5] .'/'. _rsidl);
				} else {
					header('Location: {FULL_ROOT}{ROOT}?t=tree&'. _rsidl .'&th='. $data[1] .'&mid='. $data[5]);
				}
			}
		} else {
			$prev_id = q_singleval('SELECT id FROM {SQL_TABLE_PREFIX}msg WHERE thread_id='. $data[1] .' AND id < '. $data[2] .' ORDER BY id DESC LIMIT 1');
		
			if ($FUD_OPT_2 & 32768) {
				header('Location: {FULL_ROOT}{ROOT}/mv/msg/'. $data[1] .'/'. $prev_id .'/'. _rsidl);
			} else {
				header('Location: {FULL_ROOT}{ROOT}?t=msg&th='. $data[1] .'&'. _rsidl .'&goto='. $prev_id);
			}
		}
		exit;
	} else if ($th && (!isset($_GET['th']) || sq_check(0, $usr->sq))) {
		if (!($data = db_saq('SELECT mm.id, COALESCE(g2.group_cache_opt, g1.group_cache_opt) AS gco
			FROM {SQL_TABLE_PREFIX}thread t
			LEFT JOIN {SQL_TABLE_PREFIX}mod mm ON mm.forum_id=t.forum_id AND mm.user_id='. _uid .'
			INNER JOIN {SQL_TABLE_PREFIX}group_cache g1 ON g1.user_id='. (_uid ? '2147483647': '0') .' AND g1.resource_id=t.forum_id
			LEFT JOIN {SQL_TABLE_PREFIX}group_cache g2 ON g2.user_id='. _uid .' AND g2.resource_id=t.forum_id
			WHERE t.id='. $th))) {
			check_return($usr->returnto);
		}
		if (!$data[0] && !($data[1] & 4096) && !$is_a) {
			check_return($usr->returnto);
		}

		if (isset($_GET['lock'])) {
			logaction(_uid, 'THRLOCK', $th);
			th_lock($th, 1);
		} else {
			logaction(_uid, 'THRUNLOCK', $th);
			th_lock($th, 0);
		}
	}
	check_return($usr->returnto);
?>
