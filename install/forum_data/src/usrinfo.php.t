<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: usrinfo.php.t,v 1.2 2002/06/18 16:12:36 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

	include_once "GLOBALS.php";
	{PRE_HTML_PHP}

function convert_bdate($val, $month_fmt)
{
	$ret['year'] = substr($val, 0, 4);
	$ret['month'] = substr($val, 4, 2);
	$ret['day'] = substr($val, 6, 2);

	$ret['month'] = strftime($month_fmt, mktime(1, 1, 1, $ret['month'], 11, 2000));
	return $ret;
}

	if( empty($id) || !is_numeric($id) ) invl_inp_err();
	
	$u = new fud_user;
	if ( !$u->get_user_by_id($id) ) {
		std_error('user');
	}

	$r = Q("SELECT name AS level_name, pri AS level_pri, img AS level_img FROM {SQL_TABLE_PREFIX}level WHERE id=".$u->level_id);
	$obj = DB_SINGLEOBJ($r);

	if ( $obj->level_pri ) {
		if( !empty($obj->level_name) ) $level_name = '{TEMPLATE: level_name}';
		if( !empty($obj->level_img) && strtolower($obj->level_pri)!='a' ) $level_image = '{TEMPLATE: level_image}';
	}
	
	$custom_tags = empty($u->custom_status) ? '{TEMPLATE: no_custom_tags}' : '{TEMPLATE: custom_tags}';
	
	if( $usr->is_mod != 'A' ) {
		$lmt = get_all_perms(_uid);
		if( !$lmt ) $lmt = 0;
		$qry_limit = "{SQL_TABLE_PREFIX}forum.id IN (".$lmt.") AND ";
	}	
	
	$r = Q("SELECT {SQL_TABLE_PREFIX}forum.id,{SQL_TABLE_PREFIX}forum.name FROM {SQL_TABLE_PREFIX}mod LEFT JOIN {SQL_TABLE_PREFIX}forum ON {SQL_TABLE_PREFIX}mod.forum_id={SQL_TABLE_PREFIX}forum.id LEFT JOIN {SQL_TABLE_PREFIX}cat ON {SQL_TABLE_PREFIX}forum.cat_id={SQL_TABLE_PREFIX}cat.id WHERE ".$qry_limit." {SQL_TABLE_PREFIX}mod.user_id=".$u->id);
	if( DB_COUNT($r) ) {
		$moderation_entry = '';
		while ( $ar = DB_ROWOBJ($r) ) $moderation_entry .= '{TEMPLATE: moderation_entry}';
		$moderation = '{TEMPLATE: moderation}';
	}
	QF($r);
	
	$user_info = htmlspecialchars($u->login);
	{POST_HTML_PHP}
	$TITLE_EXTRA = ': '.'{TEMPLATE: user_info_l}';
	if ( isset($ses) ) $ses->update('{TEMPLATE: userinfo_update}');

	if ( !empty($level_name) || !empty($moderation) || !empty($level_image) || !empty($custom_tags) ) $status = '{TEMPLATE: status}';
	
	$avg = sprintf("%.2f", $u->posted_msg_count/((__request_timestamp__-$u->join_date)/86400));
	if( $avg > $u->posted_msg_count ) $avg = $u->posted_msg_count;
	
	if( !empty($u->u_last_post_id) ) {
		$r = Q("SELECT {SQL_TABLE_PREFIX}msg.subject,{SQL_TABLE_PREFIX}msg.id,{SQL_TABLE_PREFIX}msg.post_stamp,{SQL_TABLE_PREFIX}thread.forum_id FROM {SQL_TABLE_PREFIX}msg INNER JOIN {SQL_TABLE_PREFIX}thread ON {SQL_TABLE_PREFIX}msg.thread_id={SQL_TABLE_PREFIX}thread.id WHERE {SQL_TABLE_PREFIX}msg.id=".$u->u_last_post_id);
		$m_obj = DB_SINGLEOBJ($r);
		if( $usr->is_mod == 'A' || is_perms(_uid, $m_obj->forum_id, 'READ') ) 
			$last_post = '{TEMPLATE: last_post}';
		else
			$last_post = '{TEMPLATE: no_view_perm}';
	}
	
	if ( $u->user_image && strstr($u->user_image, '://') ) $user_image = '{TEMPLATE: user_image}';
	
	if( $u->avatar ) 
		$avatar_img = 'images/avatars/'.Q_SINGLEVAL("SELECT img FROM {SQL_TABLE_PREFIX}avatar WHERE id=".$u->avatar);
	else if ( $u->avatar_approved == 'Y' ) 
		$avatar_img = ($u->avatar_loc) ? $u->avatar_loc : 'images/custom_avatars/'.$u->id;
		
	if( $avatar_img ) $avatar = '{TEMPLATE: avatar}';

	if ( $u->display_email == 'Y' ) 
		$email_link = '{TEMPLATE: email_link}';
	else if( $GLOBALS["ALLOW_EMAIL"] == 'Y' ) {
		$encoded_login = urlencode($u->login);
		$email_link = '{TEMPLATE: email_form_link}';
	}	
	
	if( ($referals = Q_SINGLEVAL("SELECT count(*) FROM {SQL_TABLE_PREFIX}users WHERE referer_id=".$u->id)) )
		$referals = '{TEMPLATE: referals}'; 		
	else
		$referals = '';	
	
	if( $GLOBALS['PM_ENABLED'] == 'Y' && isset($usr) ) $usrinfo_private_msg = '{TEMPLATE: usrinfo_private_msg}';
	if ( strlen($u->home_page) ) $home_page = '{TEMPLATE: home_page}'; 
	if ( strlen($u->gender) && $u->gender != 'UNSPECIFIED' ) { $gender_data = ($u->gender=='MALE')?'{TEMPLATE: male}':'{TEMPLATE: female}'; $gender = '{TEMPLATE: gender}'; }
	if ( strlen($u->location) ) $location = '{TEMPLATE: location}';
	if ( strlen($u->occupation) ) $occupation = '{TEMPLATE: occupation}';
	if ( strlen($u->interests) ) $interests = '{TEMPLATE: interests}';
	if ( strlen($u->bio ) ) $bio = '{TEMPLATE: bio}';
	
	$bday = convert_bdate($u->bday, '%B');
	if ( $bday['month'] && $bday['day'] && $bday['year'] ) $birth_date = '{TEMPLATE: birth_date}';
	if ( $u->icq ) $im_icq = '{TEMPLATE: im_icq}';
	if ( $u->jabber ) $im_jabber = '{TEMPLATE: im_jabber}';
	
	if ( strlen($u->aim) ) {
		$aim = $u->aim;
		reverse_FMT($aim);
		$im_aim = urlencode($aim);
		$im_aim = '{TEMPLATE: im_aim}';
	}		
	if ( strlen($u->yahoo) ) {
		$yahoo = $u->yahoo;
		reverse_FMT($yahoo);
		$im_yahoo = urlencode($yahoo);
		$im_yahoo = '{TEMPLATE: im_yahoo}';
	}	
	if ( strlen($u->msnm) ) $im_msnm = '{TEMPLATE: im_msnm}';
	
	{POST_PAGE_PHP_CODE}
?>
{TEMPLATE: USERINFO_PAGE}