<?php
/**
* copyright            : (C) 2001-2009 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: usrinfo.php.t,v 1.54 2009/01/29 18:37:17 frank Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; version 2 of the License.
**/

/*{PRE_HTML_PHP}*/

function convert_bdate($val, $month_fmt)
{
	$ret['year'] = substr($val, 0, 4);
	if (!(int)$ret['year']) {
		$ret['year'] = '';
	}

	$ret['day'] = substr($val, 6, 2);
	if (!(int)$ret['day']) {
		$ret['day'] = '';
	}

	if (!($month = (int)substr($val, 4, 2))) {
		$ret['month'] = '';
	} else {
		$ret['month'] = strftime($month_fmt, mktime(1, 1, 1, $month, 11, 2000));
	}

	return $ret;
}

	if (!isset($_GET['id']) || !(int)$_GET['id']) {
		invl_inp_err();
	}
	if ($FUD_OPT_3 & 32 && !_uid) {
		if (__fud_real_user__) {
			is_allowed_user($usr);
		} else {
			std_error('login');
		}
	}

	if (!($u = db_sab('SELECT s.time_sec, u.*, u.alias AS login, l.name AS level_name, l.level_opt, l.img AS level_img FROM {SQL_TABLE_PREFIX}users u LEFT JOIN {SQL_TABLE_PREFIX}ses s ON u.id=s.user_id LEFT JOIN {SQL_TABLE_PREFIX}level l ON l.id=u.level_id WHERE u.id='.(int)$_GET['id']))) {
		std_error('user');
	}

	if (!_uid && __fud_cache($u->last_visit)) {
		return;
	}

	$obj = $u; // a little hack for online status, so we don't need to add more messages

	if ($FUD_OPT_1 & 28 && $u->users_opt & 8388608 && $u->level_opt & (2|1) == 1) {
		$level_name = $level_image = '';
	} else {
		$level_name = $u->level_name ? '{TEMPLATE: level_name}' : '';
		$level_image = $u->level_img ? '{TEMPLATE: level_image}' : '';
	}

	if (!$is_a) {
		$frm_perms = get_all_read_perms(_uid, ($usr->users_opt & 524288));
		$forum_list = implode(',', array_keys($frm_perms, 2));
	} else {
		$forum_list = 1;
	}

	$moderation = '';
	if ($u->users_opt & 524288 && $forum_list) {
		$c = uq('SELECT f.id, f.name FROM {SQL_TABLE_PREFIX}mod mm INNER JOIN {SQL_TABLE_PREFIX}forum f ON mm.forum_id=f.id INNER JOIN {SQL_TABLE_PREFIX}cat c ON f.cat_id=c.id WHERE '.($is_a ? '' : 'f.id IN('.$forum_list.') AND ').'mm.user_id='.$u->id);
		while ($r = db_rowarr($c)) {
			$moderation .= '{TEMPLATE: moderation_entry}';
		}
		unset($c);
		if ($moderation) {
			$moderation = '{TEMPLATE: moderation}';
		}
	}

/*{POST_HTML_PHP}*/

	$TITLE_EXTRA = ': {TEMPLATE: user_info_l}';

	ses_update_status($usr->sid, '{TEMPLATE: userinfo_update}');

	$avg = round($u->posted_msg_count / ((__request_timestamp__ - $u->join_date) / 86400), 2);
	if ($avg > $u->posted_msg_count) {
		$avg = $u->posted_msg_count;
	}

	$last_post = '';
	if ($u->u_last_post_id) {
		$r = db_saq('SELECT m.subject, m.id, m.post_stamp, t.forum_id FROM {SQL_TABLE_PREFIX}msg m INNER JOIN {SQL_TABLE_PREFIX}thread t ON m.thread_id=t.id WHERE m.id='.$u->u_last_post_id);
		if ($is_a || !empty($frm_perms[$r[3]])) {
			$last_post = '{TEMPLATE: last_post}';
		}
	}

	if ($u->users_opt & 1) {
		$email_link = '{TEMPLATE: email_link}';
	} else if ($FUD_OPT_2 & 1073741824) {
		$email_link = '{TEMPLATE: email_form_link}';
	} else {
		$email_link = '';
	}

	if ($FUD_OPT_2 & 8192 && ($referals = q_singleval('SELECT count(*) FROM {SQL_TABLE_PREFIX}users WHERE referer_id='.$u->id))) {
		$referals = '{TEMPLATE: referals}';
	} else {
		$referals = '';
	}

	if (_uid && _uid != $u->id && !q_singleval("SELECT id FROM {SQL_TABLE_PREFIX}buddy WHERE user_id="._uid." AND bud_id=".$u->id)) {
		$buddy = '{TEMPLATE: ui_buddy}';
	} else {
		$buddy = '';
	}

	if ($forum_list && ($polls = q_singleval('SELECT count(*) FROM {SQL_TABLE_PREFIX}poll p INNER JOIN {SQL_TABLE_PREFIX}forum f ON p.forum_id=f.id WHERE p.owner='.$u->id.' AND f.cat_id>0 '.($is_a ? '' : ' AND f.id IN('.$forum_list.')')))) {
		$polls = '{TEMPLATE: polls}';
	} else {
		$polls = '';
	}

	if ($u->users_opt & 1024) {
		$gender = '{TEMPLATE: male}';
	} else if (!($u->users_opt & 512)) {
		$gender = '{TEMPLATE: female}';
	} else {
		$gender = '';
	}

	if ($u->bday) {
		$bday = convert_bdate($u->bday, '%B');
		$birth_date = '{TEMPLATE: birth_date}';
	} else {
		$birth_date = '';
	}
/*{POST_PAGE_PHP_CODE}*/
?>
{TEMPLATE: USERINFO_PAGE}