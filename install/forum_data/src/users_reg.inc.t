<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: users_reg.inc.t,v 1.16 2003/04/02 12:19:22 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/
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
				'".addslashes($this->name)."',
				'".addslashes($this->email)."',
				'".YN($this->display_email)."',
				'".YN($this->notify)."',
				'".$this->notify_method."',
				'".YN($this->ignore_admin)."',
				'".YN($this->email_messages)."',
				'".YN($this->pm_messages)."',
				'".$this->gender."',
				".in($this->icq).",
				".ssn($this->aim).",
				".ssn($this->yahoo).",
				".ssn($this->msnm).",
				".ssn($this->jabber).",
				".ssn($this->affero).",
				'".YN($this->append_sig)."',
				".iz($this->posts_ppg).",
				".ssn($this->time_zone).",
				".iz($this->bday).",
				'".YN($this->invisible_mode)."',
				".__request_timestamp__.",
				'".$this->conf_key."',
				".ssn($this->user_image).",
				".__request_timestamp__.",
				".ssn($this->location).",
				0,
				".iz($this->theme).",
				'".YN($this->coppa)."',
				".ssn($this->occupation).",
				".ssn($this->interests).",
				".iz($ref_id).",
				'".YN($this->show_sigs)."',
				'".YN($this->show_avatars)."',
				'".YN($this->show_im)."',
				".__request_timestamp__.",
				'',
				'NO',
				".ssn($this->sig).",
				'".$this->default_view."',
				".ssn($this->home_page).",
				".ssn($this->bio).",
				'".$acc_status."',
				'".$this->email_conf."'
			)
		");

		if (isset($ll)) {
			db_unlock();
		}
		return $this->id;
	}
	
	function get_user_by_login($login)
	{
		qobj("SELECT * FROM {SQL_TABLE_PREFIX}users WHERE login='".$login."'", $this);
		if( empty($this->id) ) return;
		return $this;
	}
	
	function sync_user()
	{
		$passwd = $this->plaintext_passwd ? "'".md5($this->plaintext_passwd)."'," : '';
		
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
			$passwd name='".addslashes($this->name)."',
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
			aim=".ssn($this->aim).",
			yahoo=".ssn($this->yahoo).",
			msnm=".ssn($this->msnm).",
			jabber=".ssn($this->jabber).",
			affero=".ssn($this->affero).",
			append_sig='".YN($this->append_sig)."',
			show_sigs='".YN($this->show_sigs)."',
			show_avatars='".YN($this->show_avatars)."',
			show_im='".YN($this->show_im)."',
			posts_ppg='".iz($this->posts_ppg)."',
			time_zone=".ssn($this->time_zone).",
			invisible_mode='".YN($this->invisible_mode)."',
			bday=".iz($this->bday).",
			user_image=".ssn($this->user_image).",
			location=".ssn($this->location).",
			occupation=".ssn($this->occupation).",
			interests=".ssn($this->interests).",
			avatar=".iz($this->avatar).",
			theme=".iz($this->theme).",
			avatar_loc=".ssn($this->avatar_loc).",
			avatar_approved='".$this->avatar_approved."',
			sig=".ssn($this->sig).",
			default_view='".$this->default_view."',
			home_page=".ssn($this->home_page).",
			bio=".ssn($this->bio)."
		WHERE id=".$this->id);
	}
	
	function ch_passwd($pass)
	{
		q("UPDATE {SQL_TABLE_PREFIX}users SET passwd='".md5($pass)."' WHERE id=".$this->id);
	}

	function reset_passwd()
	{
		$randval = dechex(get_random_value(32));
		q("UPDATE {SQL_TABLE_PREFIX}users SET passwd='".md5($randval)."', reset_key='0' WHERE id=".$this->id);
		return $randval;
	}
	
	function reset_key()
	{
		db_lock('{SQL_TABLE_PREFIX}users WRITE');
		do {
			$reset_key = md5(get_random_value(128));
		} while ( bq("SELECT id FROM {SQL_TABLE_PREFIX}users WHERE reset_key='".$reset_key."'") );
		q("UPDATE {SQL_TABLE_PREFIX}users SET reset_key='".$reset_key."' WHERE id=".$this->id);
		db_unlock();
		return $reset_key;
	}

	function email_confirm()
	{
		q("UPDATE {SQL_TABLE_PREFIX}users SET email_conf='Y', conf_key='0' WHERE id=".$this->id);
	}
	
	function email_unconfirm()
	{
		db_lock('{SQL_TABLE_PREFIX}users WRITE');
		do {
			$this->conf_key = md5(get_random_value(128));
		} while ( bq("SELECT id FROM {SQL_TABLE_PREFIX}users WHERE conf_key='".$this->conf_key."'") );
	
		q("UPDATE {SQL_TABLE_PREFIX}users SET email_conf='N', conf_key='".$this->conf_key."' WHERE id=".$this->id);
		db_unlock();
		
		return $this->conf_key;
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

function get_id_by_alias($alias)
{
	return q_singleval("SELECT id FROM {SQL_TABLE_PREFIX}users WHERE alias='".addslashes(htmlspecialchars($alias))."'");
}

function get_id_by_radius($login, $passwd)
{
	return q_singleval("SELECT id FROM {SQL_TABLE_PREFIX}users WHERE login='".addslashes($login)."' AND passwd='".md5($passwd)."'");
}

function check_user($id)
{
	return q_singleval('SELECT login FROM {SQL_TABLE_PREFIX}users WHERE id='.$id);
}

function check_passwd($id, $passwd)
{
	return q_singleval("SELECT login FROM {SQL_TABLE_PREFIX}users WHERE id=".$id." AND passwd='".md5($passwd)."'");
}

function reset_user_passwd_by_key($key)
{
	db_lock('{SQL_TABLE_PREFIX}users WRITE');
	if (($id = q_singleval("SELECT id FROM {SQL_TABLE_PREFIX}users WHERE reset_key='".$key."'"))) {
		$u = new fud_user_reg;
		$u->get_user_by_id($id);
		$pass['passwd'] &= $u->reset_passwd();
		$pass['usr'] &= $u;
	}
	db_unlock();
	
	return isset($pass) ? $pass : NULL;
}

function fud_user_to_reg(&$obj)
{
	if (function_exists('aggregate_methods')) {
		aggregate_methods($obj, 'fud_user_reg');
	} else {
		$u = new fud_user_reg;
		user_copy_object($obj, $u);
		$obj &= $u;
	}
}
?>