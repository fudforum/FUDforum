<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: users_reg.inc.t,v 1.31 2003/06/05 20:16:02 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

class fud_user
{
	var $id, $login, $alias, $passwd, $plaintext_passwd, $name, $email, $location, $occupation, $interests, $display_email,
	    $notify, $notify_method, $email_messages, $pm_messages, $gender, $icq, $aim, $yahoo, $msnm, $jabber, $affero, $avatar,
	    $avatar_loc, $avatar_approved, $append_sig, $show_sigs, $show_avatars, $show_im, $posts_ppg, $time_zone, $invisible_mode,
	    $ignore_admin, $bday, $blocked, $home_page, $sig, $bio, $posted_msg_count, $last_visit, $last_event, $email_conf, $conf_key,
	    $coppa, $user_image, $join_date, $theme, $last_read, $default_view, $mod_list, $mod_cur, $is_mod, $level_id, $u_last_post_id,
	    $cat_collapse_status, $acc_status, $ignore_list, $buddy_list;
}

class fud_user_reg extends fud_user
{
	function add_user()
	{	
		if (!db_locked()) {
			$ll = 1;
			db_lock('{SQL_TABLE_PREFIX}users WRITE');
		}	
		
		if ($GLOBALS['EMAIL_CONFIRMATION'] == 'Y') {
			do {
				$this->conf_key = md5(get_random_value(128));
			} while (q_singleval("SELECT id FROM {SQL_TABLE_PREFIX}users WHERE conf_key='".$this->conf_key."'"));
			$this->email_conf = 'N';
		} else {
			$this->conf_key = '';
			$this->email_conf = 'Y';
		}

		if (isset($_COOKIES['frm_referer_id']) && (int)$_COOKIES['frm_referer_id']) {
			$ref_id = $_COOKIES['frm_referer_id'];
		} else {
			$ref_id = 0;
		}
		
		$md5pass = md5($this->plaintext_passwd);
		
		if ($GLOBALS['USE_ALIASES'] != 'Y' || !$this->alias) {
			$this->alias = (strlen($this->login) < $GLOBALS['MAX_LOGIN_SHOW']) ? $this->login : substr($this->login, 0,  $GLOBALS['MAX_LOGIN_SHOW']);
		}
		$this->alias = htmlspecialchars($this->alias);
		
		$acc_status = ($GLOBALS['MODERATE_USER_REGS'] == 'N') ? 'A' : 'P';
		if ($this->gender != 'MALE' && $this->gender != 'FEMALE') {
			$this->gender = 'UNSPECIFIED';
		}
		if ($this->notify_method != 'ICQ') {
			$this->notify_method == 'EMAIL';
		}
				
		$this->id = db_qid("INSERT INTO 
			{SQL_TABLE_PREFIX}users (
				login,
				alias,
				passwd, 
				name, 
				email, 
				display_email, 
				notify, 
				notify_method, 
				ignore_admin, 
				email_messages,
				pm_messages, 
				gender, 
				icq, 
				aim,
				yahoo,
				msnm,
				jabber,
				affero,
				append_sig, 
				posts_ppg, 
				time_zone, 
				bday, 
				invisible_mode, 
				last_visit, 
				conf_key, 
				user_image, 
				join_date, 
				location, 
				avatar, 
				theme, 
				coppa, 
				occupation, 
				interests,
				referer_id,
				show_sigs,
				show_avatars,
				show_im,
				last_read,
				avatar_loc,
				avatar_approved,
				sig,
				default_view,
				home_page,
				bio,
				acc_status,
				email_conf
			) VALUES (
				'".addslashes($this->login)."',
				'".addslashes($this->alias)."',
				'".$md5pass."',
				'".addslashes(htmlspecialchars($this->name))."',
				'".addslashes($this->email)."',
				'".YN($this->display_email)."',
				'".YN($this->notify)."',
				'".$this->notify_method."',
				'".YN($this->ignore_admin)."',
				'".YN($this->email_messages)."',
				'".YN($this->pm_messages)."',
				'".$this->gender."',
				".in($this->icq).",
				".ssn(urlencode($this->aim)).",
				".ssn(urlencode($this->yahoo)).",
				".ssn(urlencode($this->msnm)).",
				".ssn(urlencode($this->jabber)).",
				".ssn(urlencode($this->affero)).",
				'".YN($this->append_sig)."',
				".iz($this->posts_ppg).",
				".ssn($this->time_zone).",
				".iz($this->bday).",
				'".YN($this->invisible_mode)."',
				".__request_timestamp__.",
				'".$this->conf_key."',
				".ssn(htmlspecialchars($this->user_image)).",
				".__request_timestamp__.",
				".ssn(htmlspecialchars($this->location)).",
				0,
				".iz($this->theme).",
				'".YN($this->coppa)."',
				".ssn(htmlspecialchars($this->occupation)).",
				".ssn(htmlspecialchars($this->interests)).",
				".iz($ref_id).",
				'".YN($this->show_sigs)."',
				'".YN($this->show_avatars)."',
				'".YN($this->show_im)."',
				".__request_timestamp__.",
				'',
				'NO',
				".ssn($this->sig).",
				'".$this->default_view."',
				".ssn(htmlspecialchars($this->home_page)).",
				".ssn(htmlspecialchars($this->bio)).",
				'".$acc_status."',
				'".$this->email_conf."'
			)
		");

		if (isset($ll)) {
			db_unlock();
		}
		return $this->id;
	}

	function sync_user()
	{
		$passwd = !empty($this->plaintext_passwd) ? "'".md5($this->plaintext_passwd)."'," : '';
		
		if ($GLOBALS['USE_ALIASES'] != 'Y' || !$this->alias) {
			$this->alias = htmlspecialchars((strlen($this->login) < $GLOBALS['MAX_LOGIN_SHOW']) ? $this->login : substr($this->login, 0,  $GLOBALS['MAX_LOGIN_SHOW']));
		} else if ($GLOBALS['USE_ALIASES'] == 'Y' && $this->alias) {
			$this->alias = htmlspecialchars($this->alias);
		}
		if ($this->gender != 'MALE' && $this->gender != 'FEMALE') {
			$this->gender = 'UNSPECIFIED';
		}
		if ($this->notify_method != 'ICQ') {
			$this->notify_method == 'EMAIL';
		}
		$this->avatar_approved = empty($this->avatar_loc) ? 'NO' : YN($this->avatar_approved);
		
		q("UPDATE {SQL_TABLE_PREFIX}users SET 
			$passwd name='".addslashes(htmlspecialchars($this->name))."',
			alias='".addslashes($this->alias)."',
			email='".addslashes($this->email)."',
			display_email='".YN($this->display_email)."',
			notify='".YN($this->notify)."',
			notify_method='".$this->notify_method."',
			ignore_admin='".YN($this->ignore_admin)."',
			email_messages='".YN($this->email_messages)."',
			pm_messages='".YN($this->pm_messages)."',
			gender='".$this->gender."',
			icq=".in($this->icq).",
			aim=".ssn(urlencode($this->aim)).",
			yahoo=".ssn(urlencode($this->yahoo)).",
			msnm=".ssn(urlencode($this->msnm)).",
			jabber=".ssn(urlencode($this->jabber)).",
			affero=".ssn(urlencode($this->affero)).",
			append_sig='".YN($this->append_sig)."',
			show_sigs='".YN($this->show_sigs)."',
			show_avatars='".YN($this->show_avatars)."',
			show_im='".YN($this->show_im)."',
			posts_ppg='".iz($this->posts_ppg)."',
			time_zone=".ssn($this->time_zone).",
			invisible_mode='".YN($this->invisible_mode)."',
			bday=".iz($this->bday).",
			user_image=".ssn(htmlspecialchars($this->user_image)).",
			location=".ssn(htmlspecialchars($this->location)).",
			occupation=".ssn(htmlspecialchars($this->occupation)).",
			interests=".ssn(htmlspecialchars($this->interests)).",
			avatar=".iz($this->avatar).",
			theme=".iz($this->theme).",
			avatar_loc=".ssn($this->avatar_loc).",
			avatar_approved='".$this->avatar_approved."',
			sig=".ssn($this->sig).",
			default_view='".$this->default_view."',
			home_page=".ssn(htmlspecialchars($this->home_page)).",
			bio=".ssn(htmlspecialchars($this->bio))."
		WHERE id=".$this->id);
	}
}

function get_id_by_email($email)
{
	return q_singleval("SELECT id FROM {SQL_TABLE_PREFIX}users WHERE email='".addslashes($email)."'");
}

function get_id_by_login($login)
{
	return q_singleval("SELECT id FROM {SQL_TABLE_PREFIX}users WHERE login='".addslashes($login)."'");
}

function usr_email_unconfirm($id)
{
	db_lock('{SQL_TABLE_PREFIX}users WRITE');
	do {
		$conf_key = md5(get_random_value(128));
	} while (q_singleval("SELECT id FROM {SQL_TABLE_PREFIX}users WHERE conf_key='".$conf_key."'"));
	
	q("UPDATE {SQL_TABLE_PREFIX}users SET email_conf='N', conf_key='".$conf_key."' WHERE id=".$id);
	db_unlock();
		
	return $conf_key;
}

if (!function_exists('aggregate_methods'))
{
	function aggregate_methods(&$obj, $class_name)
	{
		$o = new $class_name;
		foreach ($obj as $k => $v) {
			$o->{$k} = $v;
		}
		$obj = $o;
	}
}

function &usr_reg_get_full($id)
{
	if (($r = db_sab('SELECT * FROM {SQL_TABLE_PREFIX}users WHERE id='.$id))) {
		aggregate_methods($r, 'fud_user_reg');
		return $r;
	}
	return;
}

function user_login($id, $cur_ses_id, $use_cookies)
{
	if (!$use_cookies && isset($_COOKIE[$GLOBALS['COOKIE_NAME']])) {
		/* remove cookie so it does not confuse us */
		setcookie($GLOBALS['COOKIE_NAME'], '', __request_timestamp__-100000, $GLOBALS['COOKIE_PATH'], $GLOBALS['COOKIE_DOMAIN']);	
	}
	if ($GLOBALS['MULTI_HOST_LOGIN'] == 'Y' && $use_cookies && ($ses_id = q_singleval('SELECT ses_id FROM {SQL_TABLE_PREFIX}ses WHERE user_id='.$id))) {
		setcookie($GLOBALS['COOKIE_NAME'], $ses_id, __request_timestamp__+$GLOBALS['COOKIE_TIMEOUT'], $GLOBALS['COOKIE_PATH'], $GLOBALS['COOKIE_DOMAIN']);
		q('UPDATE {SQL_TABLE_PREFIX}ses SET sys_id=0 WHERE ses_id=\''.$ses_id.'\'');
		return $ses_id;
	} else {
		/* if we can only have 1 login per account, 'remove' all other logins */
		q('DELETE FROM {SQL_TABLE_PREFIX}ses WHERE user_id='.$id.' AND ses_id!=\''.$cur_ses_id.'\'');
		q('UPDATE {SQL_TABLE_PREFIX}ses SET user_id='.$id.($use_cookies ? ', sys_id=0' : '').' WHERE ses_id=\''.$cur_ses_id.'\'');

		return $cur_ses_id;
	}
}
?>