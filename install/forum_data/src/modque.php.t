<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: modque.php.t,v 1.14 2003/04/15 14:43:05 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

	define('msg_edit', 1); define('_imsg_edit_inc_', 1);

/*{PRE_HTML_PHP}*/
	
	/* only admins & moderators have access to this control panel */
	if (!_uid || ($usr->is_mod != 'A' && $usr->is_mod != 'Y')) { 
		error_dialog('{TEMPLATE: permission_denied_title}', '{TEMPLATE: permission_denied_msg}');
	}

	$appr = isset($_GET['appr']) ? (int) $_GET['appr'] : 0;
	$del = isset($_GET['del']) ? (int) $_GET['del'] : 0;
	
	/* we need to determine wether or not the message exists & if the user has access to approve/delete it */
	if ($appr || $del) {
		if (!q_singleval('SELECT CASE WHEN \''.$usr->is_mod.'\'!=\'A\' THEN mm.id ELSE 1 END FROM {SQL_TABLE_PREFIX}msg m INNER JOIN {SQL_TABLE_PREFIX}thread t ON m.thread_id=t.id LEFT JOIN {SQL_TABLE_PREFIX}mod mm ON t.forum_id=mm.forum_id AND mm.user_id='._uid.' WHERE m.id='.($appr ? $appr : $del))) {
			if (db_affected()) {
				error_dialog('{TEMPLATE: permission_denied_title}', '{TEMPLATE: permission_denied_msg}');
			} else {
				$del = $appr = 0;
			}
		}
	}

	if ($appr) {
		fud_msg_edit::approve($appr, TRUE);
	} else if ($del) {
		fud_msg_edit::delete(FALSE, $del);
	}

	ses_update_status($usr->sid, '', 0);
	
	/* for sanity sake, we only select up to POSTS_PER_PAGE messages, simply because otherwise the form will 
	 * become unmanageable.
	 */
	
	$r = uq("SELECT 
		m.*, 
		t.locked, t.root_msg_id, t.last_post_id, t.forum_id,
		f.message_threshold, f.name AS frm_name,
		c.name AS cat_name,
		u.id AS user_id, u.alias AS login, u.display_email, u.avatar_approved,
		u.avatar_loc, u.email, u.posted_msg_count, u.join_date,  u.location, 
		u.sig, u.custom_status, u.icq, u.jabber, u.affero, u.aim, u.msnm, 
		u.yahoo, u.invisible_mode, u.email_messages, u.is_mod, u.last_visit AS time_sec,
		l.name AS level_name, l.pri AS level_pri, l.img AS level_img,
		p.max_votes, p.expiry_date, p.creation_date, p.name AS poll_name, p.total_votes
	FROM
		{SQL_TABLE_PREFIX}msg m
	INNER JOIN {SQL_TABLE_PREFIX}thread t ON m.thread_id=t.id 
	INNER JOIN {SQL_TABLE_PREFIX}forum f ON t.forum_id=f.id 
	".($usr->is_mod != 'A' ? ' INNER JOIN {SQL_TABLE_PREFIX}mod mm ON f.id=mm.forum_id AND mm.user_id='._uid.' ' : '')."
	INNER JOIN {SQL_TABLE_PREFIX}cat c ON f.cat_id=c.id	
	LEFT JOIN {SQL_TABLE_PREFIX}users u ON m.poster_id=u.id
	LEFT JOIN {SQL_TABLE_PREFIX}level l ON u.level_id=l.id
	LEFT JOIN {SQL_TABLE_PREFIX}poll p ON m.poll_id=p.id
	WHERE 
		f.moderated='Y' AND m.approved='N'
	ORDER BY f.view_order, m.post_stamp DESC LIMIT ".$POSTS_PER_PAGE);
	
/*{POST_HTML_PHP}*/
	
	$prev_thread_id = $modque_message = '';
	$m_num = 0;

	/* quick cheat to give us full access to the messages ;) */
	$perms = perms_from_obj($a, 'A');
	$GLOBALS['MOD'] = 1;
	$_GET['start'] = 0;

	while ($obj = db_rowobj($r)) {
		if (!$prev_thread_id || $prev_thread_id != $obj->thread_id) {
			$prev_thread_id = $obj->thread_id;
		}

		$message = tmpl_drawmsg($obj, $usr, $perms, FALSE, $m_num, NULL);
		$modque_message .= '{TEMPLATE: modque_message}';
	}
	qf($r);

	if (empty($modque_message)) {
		$modque_message = '{TEMPLATE: no_modque_msg}';
	}
	
	un_register_fps();
	
/*{POST_PAGE_PHP_CODE}*/
?>
{TEMPLATE: MODQUE_PAGE}