<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: usrinfo.php.t,v 1.18 2003/09/26 18:49:03 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

/*{PRE_HTML_PHP}*/

function convert_bdate($val, $month_fmt)
{
	$ret['year']	= substr($val, 0, 4);
	$ret['day']	= substr($val, 6, 2);
	$ret['month']	= strftime($month_fmt, mktime(1, 1, 1, substr($val, 4, 2), 11, 2000));

	return $ret;
}

	if (!isset($_GET['id']) || !(int)$_GET['id']) {
		invl_inp_err();
	}

	if (!($u = db_sab('SELECT u.*, l.name AS level_name, l.level_opt, l.img AS level_img FROM {SQL_TABLE_PREFIX}users u LEFT JOIN {SQL_TABLE_PREFIX}level l ON l.id=u.level_id WHERE u.id='.(int)$_GET['id']))) {
		std_error('user');
	}

	if ($u->level_opt) {
		$level_name = $u->level_name ? '{TEMPLATE: level_name}' : '';
		$level_image = ($u->level_img && $u->level_opt != 1) ? '{TEMPLATE: level_image}' : '';
	} else {
		$level_name = $level_image = '';
	}

	$custom_tags = $u->custom_status ? '{TEMPLATE: custom_tags}' : '{TEMPLATE: no_custom_tags}';

	if ($usr->is_mod != 'A') {
		$frm_perms = get_all_read_perms(_uid);
	}

	$moderation = '';
	$c = uq('SELECT f.id, f.name FROM {SQL_TABLE_PREFIX}mod mm INNER JOIN {SQL_TABLE_PREFIX}forum f ON mm.forum_id=f.id INNER JOIN {SQL_TABLE_PREFIX}cat c ON f.cat_id=c.id WHERE mm.user_id='.$u->id);
	while ($r = db_rowarr($c)) {
		if (!empty($frm_perms[$r[0]])) {
			$moderation .= '{TEMPLATE: moderation_entry}';
		}
	}
	qf($r);
	if ($moderation) {
		$moderation = '{TEMPLATE: moderation}';
	}
	
/*{POST_HTML_PHP}*/

	$TITLE_EXTRA = ': {TEMPLATE: user_info_l}';

	ses_update_status($usr->sid, '{TEMPLATE: userinfo_update}');

	if (!empty($level_name) || !empty($moderation) || !empty($level_image) || !empty($custom_tags)) {
		$status = '{TEMPLATE: status}';
	} else {
		$status = '';
	}
	
	$avg = sprintf('%.2f', $u->posted_msg_count / ((__request_timestamp__ - $u->join_date) / 86400));
	if ($avg > $u->posted_msg_count) {
		$avg = $u->posted_msg_count;
	}

	$last_post = '';
	if ($u->u_last_post_id) {
		$r = db_saq('SELECT m.subject, m.id, m.post_stamp, t.forum_id FROM {SQL_TABLE_PREFIX}msg m INNER JOIN {SQL_TABLE_PREFIX}thread t ON m.thread_id=t.id WHERE m.id='.$u->u_last_post_id);
		if (!empty($frm_perms[$r[3]])) {
			$last_post = '{TEMPLATE: last_post}';
		}
	}

	$user_image = ($GLOBALS['ALLOW_PROFILE_IMAGE'] == 'Y' && $u->user_image && strpos($u->user_image, '://')) ? '{TEMPLATE: user_image}' : '';
	if ($CUSTOM_AVATARS != 'OFF' && $u->avatar_loc && $u->avatar_approved == 'Y') {
		$avatar = '{TEMPLATE: avatar}';
	} else {
		$avatar = '';
	}
	
	if ($u->display_email == 'Y') {
		$email_link = '{TEMPLATE: email_link}';
	} else if ($ALLOW_EMAIL == 'Y') {
		$encoded_login = urlencode($u->alias);
		$email_link = '{TEMPLATE: email_form_link}';
	} else {
		$email_link = '';
	}
	
	if (($referals = q_singleval('SELECT count(*) FROM {SQL_TABLE_PREFIX}users WHERE referer_id='.$u->id))) {
		$referals = '{TEMPLATE: referals}'; 		
	} else {
		$referals = '';	
	}
	
	if (($polls = q_singleval('SELECT count(*) 
			FROM {SQL_TABLE_PREFIX}poll p 
			INNER JOIN {SQL_TABLE_PREFIX}forum f ON p.forum_id=f.id
			INNER JOIN {SQL_TABLE_PREFIX}cat c ON c.id=f.cat_id
			LEFT JOIN {SQL_TABLE_PREFIX}mod mm ON mm.forum_id=p.forum_id AND mm.user_id='._uid.'
			INNER JOIN {SQL_TABLE_PREFIX}group_cache g1 ON g1.user_id='.(_uid ? '2147483647' : '0').' AND g1.resource_id=p.forum_id
			LEFT JOIN {SQL_TABLE_PREFIX}group_cache g2 ON g2.user_id='._uid.' AND g2.resource_id=p.forum_id
			WHERE p.owner='.$u->id.' '.($usr->is_mod != 'A' ? ' AND (mm.id IS NOT NULL OR (CASE WHEN g2.id IS NOT NULL THEN g2.p_READ ELSE g1.p_READ END)=\'Y\')' : '')))) {
		$polls = '{TEMPLATE: polls}';
	} else {
		$polls = '';
	}
	
	$usrinfo_private_msg = ($PM_ENABLED == 'Y' && _uid) ? '{TEMPLATE: usrinfo_private_msg}' : '';

	if ($u->gender == 'MALE') {
		$gender = '{TEMPLATE: male}';
	} else if ($u->gender == 'FEMALE') {
		$gender = '{TEMPLATE: female}';
	} else {
		$gender = '';
	}

	$location	= $u->location ? '{TEMPLATE: location}' : '';
	$occupation	= $u->occupation ? '{TEMPLATE: occupation}' : '';
	$interests	= $u->interests ? '{TEMPLATE: interests}' : '';
	$bio		= $u->bio ? '{TEMPLATE: bio}' : '';
	$home_page	= $u->home_page ? '{TEMPLATE: home_page}' : '';
	$im_icq		= $u->icq ? '{TEMPLATE: im_icq}' : '';
	$im_jabber	= $u->jabber ? '{TEMPLATE: im_jabber}' : '';
	$im_aim		= $u->aim ? '{TEMPLATE: im_aim}' : '';
	$im_yahoo	= $u->yahoo ? '{TEMPLATE: im_yahoo}' : '';
	$im_msnm	= $u->msnm ? '{TEMPLATE: im_msnm}' : '';

	if ($u->bday) {
		$bday = convert_bdate($u->bday, '%B');
		$birth_date = '{TEMPLATE: birth_date}';
	} else {
		$birth_date = '';
	}

	if ($ENABLE_AFFERO == 'Y' && $u->affero) {
		$im_affero = '{TEMPLATE: usrinfo_affero}';
	} else {
		$im_affero = '';
	}
	
/*{POST_PAGE_PHP_CODE}*/
?>
{TEMPLATE: USERINFO_PAGE}