<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: usrinfo.php.t,v 1.12 2003/04/02 20:58:55 hackie Exp $
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

	if (!($u = db_sab('SELECT {SQL_TABLE_PREFIX}users.*, {SQL_TABLE_PREFIX}level.name AS level_name, {SQL_TABLE_PREFIX}level.pri AS level_pri, {SQL_TABLE_PREFIX}level.img AS level_img FROM {SQL_TABLE_PREFIX}users LEFT JOIN {SQL_TABLE_PREFIX}level ON {SQL_TABLE_PREFIX}level.id={SQL_TABLE_PREFIX}users.level_id WHERE {SQL_TABLE_PREFIX}users.id='.(int)$_GET['id']))) {
		std_error('user');
	}
	if ($u->level_pri) {
		$level_name = $u->level_name ? '{TEMPLATE: level_name}' : '';
		$level_image = ($u->level_img && $obj->level_pri != 'A') ? '{TEMPLATE: level_image}' : '';
	} else {
		$level_name = $level_image = '';
	}

	$custom_tags = $u->custom_status ? '{TEMPLATE: no_custom_tags}' : '{TEMPLATE: custom_tags}';
	
	if ($usr->is_mod != 'A') {
		$lmt = get_all_perms(_uid);
		$qry_limit = '{SQL_TABLE_PREFIX}forum.id IN ('.$lmt.') AND ';
		$forum_limit = ' AND {SQL_TABLE_PREFIX}thread.forum_id IN ('.$lmt.') ';
	} else {
		$lmt = $forum_limit = '';
	}
	
	$c = uq('SELECT {SQL_TABLE_PREFIX}forum.id, {SQL_TABLE_PREFIX}forum.name FROM {SQL_TABLE_PREFIX}mod LEFT JOIN {SQL_TABLE_PREFIX}forum ON {SQL_TABLE_PREFIX}mod.forum_id={SQL_TABLE_PREFIX}forum.id LEFT JOIN {SQL_TABLE_PREFIX}cat ON {SQL_TABLE_PREFIX}forum.cat_id={SQL_TABLE_PREFIX}cat.id WHERE '.$qry_limit.' {SQL_TABLE_PREFIX}mod.user_id='.$u->id);
	$moderation = '';
	if (($r = @db_rowarr($c))) {
		do {
			$moderation .= '{TEMPLATE: moderation_entry}';
		} while (($r = @db_rowarr($c)));
		$moderation = '{TEMPLATE: moderation}';
	}
	qf($r);
	
/*{POST_HTML_PHP}*/

	$TITLE_EXTRA = ': {TEMPLATE: user_info_l}';

	$ses->update('{TEMPLATE: userinfo_update}');

	if (!empty($level_name) || !empty($moderation) || !empty($level_image) || !empty($custom_tags)) {
		$status = '{TEMPLATE: status}';
	} else {
		$status = '';
	}
	
	$avg = sprintf('%.2f', $u->posted_msg_count/((__request_timestamp__-$u->join_date)/86400));
	if ($avg > $u->posted_msg_count) {
		$avg = $u->posted_msg_count;
	}
	
	if ($u->u_last_post_id) {
		$r = db_saq('SELECT {SQL_TABLE_PREFIX}msg.subject, {SQL_TABLE_PREFIX}msg.id, {SQL_TABLE_PREFIX}msg.post_stamp FROM {SQL_TABLE_PREFIX}msg INNER JOIN {SQL_TABLE_PREFIX}thread ON {SQL_TABLE_PREFIX}msg.thread_id={SQL_TABLE_PREFIX}thread.id WHERE {SQL_TABLE_PREFIX}msg.id='.$u->u_last_post_id);
		
		/* for permission checks we do a little cheat and run a 
		 * regex on $lmt, which is faster then running a new query
		 */
		if ($usr->is_mod == 'A' || preg_match('!^|,'.$r[2].',|$!', $lmt)) {
			$last_post = '{TEMPLATE: last_post}';
		} else {
			$last_post = '{TEMPLATE: no_view_perm}';
		}
	} else {
		$last_post = '';
	}
	
	$user_image = ($u->user_image && strpos($u->user_image, '://')) ? '{TEMPLATE: user_image}' : '';
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
	
	if (($polls = q_singleval("SELECT count(*) FROM {SQL_TABLE_PREFIX}poll 
				INNER JOIN {SQL_TABLE_PREFIX}msg ON 
					{SQL_TABLE_PREFIX}poll.id={SQL_TABLE_PREFIX}msg.poll_id 
				INNER JOIN {SQL_TABLE_PREFIX}thread ON
					{SQL_TABLE_PREFIX}thread.id={SQL_TABLE_PREFIX}msg.thread_id	
				INNER JOIN {SQL_TABLE_PREFIX}forum ON	
					{SQL_TABLE_PREFIX}thread.forum_id={SQL_TABLE_PREFIX}forum.id	
				WHERE {SQL_TABLE_PREFIX}poll.owner=".$u->id." ".$forum_limit." AND {SQL_TABLE_PREFIX}thread.locked='N' AND {SQL_TABLE_PREFIX}msg.approved='Y' AND {SQL_TABLE_PREFIX}forum.cat_id!=0"))) {
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