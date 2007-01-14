<?php
/**
* copyright            : (C) 2001-2007 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: movemsg.php.t,v 1.6 2007/01/14 17:06:13 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; version 2 of the License.
**/

/*{PRE_HTML_PHP}*/
/*{POST_HTML_PHP}*/

	if (isset($_GET['th'])) {
		$th = (int) $_GET['th'];
	} else if (isset($_POST['th'])) {
		$th = (int) $_POST['th'];
	} else {
		$th = 0;
	}

	// permissions checks for non-admins
	if (!$is_a) {
		// source thread
		$perms = db_saq('SELECT mm.id, '.(_uid ? ' COALESCE(g2.group_cache_opt, g1.group_cache_opt) AS gco ' : ' g1.group_cache_opt AS gco ').'
				FROM {SQL_TABLE_PREFIX}thread t
				LEFT JOIN {SQL_TABLE_PREFIX}mod mm ON mm.user_id='._uid.' AND mm.forum_id=t.forum_id
				'.(_uid ? 'INNER JOIN {SQL_TABLE_PREFIX}group_cache g1 ON g1.user_id=2147483647 AND g1.resource_id=t.forum_id LEFT JOIN {SQL_TABLE_PREFIX}group_cache g2 ON g2.user_id='._uid.' AND g2.resource_id=t.forum_id' : 'INNER JOIN {SQL_TABLE_PREFIX}group_cache g1 ON g1.user_id=0 AND g1.resource_id=t.forum_id').'
				WHERE t.id='.$th);

		if (!$perms) {
			invl_inp_err();
		}

		if ((!$perms[0] && !($perms[1] & 8192))) {
			std_error('access');
		}
	}

	if (!($sth_info = db_arr_assoc("SELECT forum_id, root_msg_id, replies, last_post_id, last_post_date FROM {SQL_TABLE_PREFIX}thread WHERE id=".$th))) {
		invl_inp_err();
	}	

	/* do the work */
	if (!empty($_POST['dest_th']) && !empty($_POST['msg_ids'])) {
		$dth = (int)$_POST['dest_th'];

		// destination
		$perms = db_saq('SELECT mm.id, '.(_uid ? ' COALESCE(g2.group_cache_opt, g1.group_cache_opt) AS gco ' : ' g1.group_cache_opt AS gco ').'
				FROM {SQL_TABLE_PREFIX}thread t
				LEFT JOIN {SQL_TABLE_PREFIX}mod mm ON mm.user_id='._uid.' AND mm.forum_id=t.forum_id
				'.(_uid ? 'INNER JOIN {SQL_TABLE_PREFIX}group_cache g1 ON g1.user_id=2147483647 AND g1.resource_id=t.forum_id LEFT JOIN {SQL_TABLE_PREFIX}group_cache g2 ON g2.user_id='._uid.' AND g2.resource_id=t.forum_id' : 'INNER JOIN {SQL_TABLE_PREFIX}group_cache g1 ON g1.user_id=0 AND g1.resource_id=t.forum_id').'
				WHERE t.id='.$dth);

		if (!$perms) {
			invl_inp_err();
		}

		if ((!$perms[0] && !($perms[1] & 8))) {
			std_error('access');
		}

		$mids = array();
		foreach ($_POST['msg_ids'] as $m) {
			if (($m = (int)$m) > 0) {
				$mids[] = $m;
			}
		}
		if ($mids && $dth > 0) {
			$mids = db_all("SELECT id FROM {SQL_TABLE_PREFIX}msg WHERE id IN(".implode(',', $mids).") AND thread_id=".$th);
		}
		if ($mids && $dth > 0) {
			if (!($th_info = db_arr_assoc("SELECT forum_id, root_msg_id, replies, last_post_id, last_post_date FROM {SQL_TABLE_PREFIX}thread WHERE id=".$dth))) {
				check_return($usr->returnto);
			}

			$mstr = implode(',', $mids);
			$c_mids = count($mids);
			// move thread
			q("UPDATE {SQL_TABLE_PREFIX}msg SET thread_id=".$dth." WHERE id IN(".$mstr.") AND thread_id=".$th);
			// fix up reply_to
			q("UPDATE {SQL_TABLE_PREFIX}msg SET reply_to=".$th_info['root_msg_id']." WHERE id IN(".$mstr.") AND reply_to NOT IN(".$mstr.")");

			// determine if we need to update last_post_* in destination thread
			$minfo = db_saq("SELECT id, post_stamp FROM {SQL_TABLE_PREFIX}msg WHERE id IN(".$mstr.") ORDER BY post_stamp DESC LIMIT 1");
			if ($minfo[1] > $th_info['last_post_date']) {
				$pfx .= ', last_post_date='.$minfo[1].', last_post_id='.$minfo[0];
				rebuild_forum_view_ttl($th_info['forum_id']);
			} else {
				$pfx = '';
			}
			
			q("UPDATE {SQL_TABLE_PREFIX}thread SET replies=replies+".$c_mids.$pfx." WHERE id=".$dth);
			if (q_singleval("SELECT last_post_id FROM {SQL_TABLE_PREFIX}forum WHERE id=".$dth) == $th_info['last_post_id']) {
				$pfx .= ', last_post_id='.$minfo[0];
			} else {
				$pfx = '';
			}
			q("UPDATE {SQL_TABLE_PREFIX}forum SET post_count=post_count+".$c_mids.$pfx." WHERE id=".$th_info['forum_id']);

			// update source thread
			if ($sth_info['replies']+1 == $c_mids) { // complete thread move
				q("DELETE FROM {SQL_TABLE_PREFIX}thread WHERE id=".$th);
				rebuild_forum_view_ttl($sth_info['forum_id']);
				$lp = q_singleval("SELECT t.last_post_id FROM {SQL_TABLE_PREFIX}_tv_".$sth_info['forum_id']." v 
								INNER JOIN {SQL_TABLE_PREFIX}thread t ON t.id=v.thread_id
								WHERE tv_seq=1");
				$pfx = ', thread_count=MAX(thread_count-1, 0), last_post_id='.$lp;
			} else {
				if (in_array($mids, $sth_info['last_post_id'])) {
					$sinfo = db_saq("SELECT id, post_stamp FROM {SQL_TABLE_PREFIX}msg WHERE thread_id=".$th." ORDER BY post_stamp DESC LIMIT 1");
					q("UPDATE {SQL_TABLE_PREFIX}thread SET replies=replies-".$c_mids.", last_post_date=".$sinfo[1].", last_post_id=".$sinfo[0]." WHERE id=".$th);
					rebuild_forum_view_ttl($sth_info['forum_id']);
					$lp = q_singleval("SELECT t.last_post_id FROM {SQL_TABLE_PREFIX}_tv_".$sth_info['forum_id']." v 
								INNER JOIN {SQL_TABLE_PREFIX}thread t ON t.id=v.thread_id
								WHERE tv_seq=1");
					$pfx = ', last_post_id='.$lp;
				} else {
					q("UPDATE {SQL_TABLE_PREFIX}thread SET replies=replies-".$c_mids." WHERE id=".$th);
					$pfx = '';
				}
			}
			q("UPDATE {SQL_TABLE_PREFIX}forum SET post_count=post_count-".$c_mids.$pfx." WHERE id=".$th_info['forum_id']);
		}
	}

	$anon_alias = htmlspecialchars($ANON_NICK);
	$msg_entry = '';

	$c = uq("SELECT m.id, m.foff, m.length, m.file_id, m.subject, m.post_stamp, u.alias FROM {SQL_TABLE_PREFIX}msg m LEFT JOIN {SQL_TABLE_PREFIX}users u ON m.poster_id=u.id WHERE m.thread_id=".$th." AND m.apr=1 ORDER BY m.post_stamp ASC");
	while ($r = db_rowobj($c)) {
		$msg_entry .= '{TEMPLATE: move_msg_entry}';
	}
	unset($c);

/*{POST_PAGE_PHP_CODE}*/
?>
{TEMPLATE: MOVE_MSG_PAGE}