<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: users_reg.inc.t,v 1.1.1.1 2002/06/17 23:00:09 hackie Exp $
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
		DB_LOCK('{SQL_TABLE_PREFIX}users+');
		
		do {
			$this->conf_key = md5(get_random_value(128));
		} while ( BQ("SELECT id FROM {SQL_TABLE_PREFIX}users WHERE conf_key='".$this->conf_key."'") );

		$ref_id = !empty($GLOBALS["HTTP_COOKIE_VARS"]["frm_referer_id"]) ? $GLOBALS["HTTP_COOKIE_VARS"]["frm_referer_id"] : 0;
		if( empty($this->avatar_loc) ) $this->avatar_loc = NULL;
		
		$md5pass = md5($this->plaintext_passwd);
		$tm = __request_timestamp__;		
		Q("INSERT INTO 
			{SQL_TABLE_PREFIX}users (
				login, 
				passwd, 
				name, 
				email, 
				display_email, 
				notify, 
				notify_method, 
				ignore_admin, 
				email_messages, 
				gender, 
				icq, 
				aim,
				yahoo,
				msnm,
				jabber,
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
				last_read,
				avatar_loc,
				avatar_approved,
				sig,
				default_view,
				home_page,
				bio
			)
			VALUES (
				'".$this->login."',
				'".$md5pass."',
				'".$this->name."',
				'".$this->email."',
				'".YN($this->display_email)."',
				'".YN($this->notify)."',
				'".$this->notify_method."',
				'".YN($this->ignore_admin)."',
				'".YN($this->email_messages)."',
				'".$this->gender."',
				".INTNULL($this->icq).",
				".STRNULL($this->aim).",
				".STRNULL($this->yahoo).",
				".STRNULL($this->msnm).",
				".STRNULL($this->jabber).",
				'".YN($this->append_sig)."',
				'".$this->posts_ppg."',
				'".$this->time_zone."',
				".INTZERO($this->bday).",
				'".YN($this->invisible_mode)."',
				".$tm.",
				'".$this->conf_key."',
				".STRNULL($this->user_image).",
				".$tm.",
				'".$this->location."',
				".INTZERO($this->avatar).",
				".INTZERO($this->theme).",
				'".YN($this->coppa)."',
				".STRNULL($this->occupation).",
				".STRNULL($this->interests).",
				".INTZERO($ref_id).",
				'".YN($this->show_sigs)."',
				'".YN($this->show_avatars)."',
				".$tm.",
				".STRNULL($this->avatar_loc).",
				'NO',
				".STRNULL($this->sig).",
				'".$this->default_view."',
				".STRNULL(addslashes($this->home_page)).",
				".STRNULL(addslashes($this->bio))."
			)
		");
		$this->id = DB_LASTID();
		if( $GLOBALS['EMAIL_CONFIRMATION'] == 'N' ) $this->email_confirm();
		DB_UNLOCK();
		return $this->id;
	}
	
	function get_user_by_login($login)
	{
		QOBJ("SELECT * FROM {SQL_TABLE_PREFIX}users WHERE login='".$login."'", $this);
		if( empty($this->id) ) return;
		return $this;
	}
	
	function sync_user()
	{
		if ( $plaintext_passwd ) $passwd = "'".md5($plaintext_passwd)."',";
		Q("UPDATE 
				{SQL_TABLE_PREFIX}users 
			SET 
				$passwd name='".$this->name."',
				email='".$this->email."',
				display_email='".YN($this->display_email)."',
				notify='".YN($this->notify)."',
				notify_method='".$this->notify_method."',
				ignore_admin='".YN($this->ignore_admin)."',
				email_messages='".YN($this->email_messages)."',
				gender='".$this->gender."',
				icq=".INTNULL($this->icq).",
				aim=".STRNULL($this->aim).",
				yahoo=".STRNULL($this->yahoo).",
				msnm=".STRNULL($this->msnm).",
				jabber=".STRNULL($this->jabber).",
				append_sig='".YN($this->append_sig)."',
				show_sigs='".YN($this->show_sigs)."',
				show_avatars='".YN($this->show_avatars)."',
				posts_ppg='".$this->posts_ppg."',
				time_zone='".$this->time_zone."',
				invisible_mode='".YN($this->invisible_mode)."',
				bday=".INTZERO($this->bday).",
				user_image=".STRNULL($this->user_image).",
				location='".$this->location."',
				occupation='".$this->occupation."',
				interests='".$this->interests."',
				avatar=".INTZERO($this->avatar).",
				theme=".INTZERO($this->theme).",
				avatar_loc=".STRNULL($this->avatar_loc).",
				avatar_approved='".$this->avatar_approved."',
				sig=".STRNULL($this->sig).",
				default_view='".$this->default_view."',
				home_page=".STRNULL(addslashes($this->home_page)).",
				bio=".STRNULL(addslashes($this->bio))."
			WHERE id=".$this->id
		);
	}
	
	function ch_passwd($pass)
	{
		Q("UPDATE {SQL_TABLE_PREFIX}users SET passwd='".md5($pass)."' WHERE id=".$this->id);
	}

	function reset_passwd()
	{
		$randval = dechex(get_random_value(32));
		Q("UPDATE {SQL_TABLE_PREFIX}users SET passwd='".md5($randval)."', reset_key='0' WHERE id=".$this->id);
		return $randval;
	}
	
	function reset_key()
	{
		DB_LOCK('{SQL_TABLE_PREFIX}users+');
		do {
			$reset_key = md5(get_random_value(128));
		} while ( BQ("SELECT id FROM {SQL_TABLE_PREFIX}users WHERE reset_key='".$reset_key."'") );
		Q("UPDATE {SQL_TABLE_PREFIX}users SET reset_key='".$reset_key."' WHERE id=".$this->id);
		DB_UNLOCK();
		return $reset_key;
	}

	function email_confirm()
	{
		Q("UPDATE {SQL_TABLE_PREFIX}users SET email_conf='Y', conf_key='0' WHERE id=".$this->id);
	}
	
	function email_unconfirm()
	{
		DB_LOCK('{SQL_TABLE_PREFIX}users+');
		do {
			$this->conf_key = md5(get_random_value(128));
		} while ( BQ("SELECT id FROM {SQL_TABLE_PREFIX}users WHERE conf_key='".$this->conf_key."'") );
	
		Q("UPDATE {SQL_TABLE_PREFIX}users SET email_conf='N', conf_key='".$this->conf_key."' WHERE id=".$this->id);
		DB_UNLOCK();
		
		return $this->conf_key;
	}
}

function get_id_by_email($email)
{
	return Q_SINGLEVAL("SELECT id FROM {SQL_TABLE_PREFIX}users WHERE email='".$email."'");
}

function get_id_by_login($login)
{
	return Q_SINGLEVAL("SELECT id FROM {SQL_TABLE_PREFIX}users WHERE login='".$login."'");
}

function get_id_by_radius($login, $passwd)
{
	return Q_SINGLEVAL("SELECT id FROM {SQL_TABLE_PREFIX}users WHERE login='".$login."' AND passwd='".md5($passwd)."'");
}

function check_user($id)
{
	return Q_SINGLEVAL("SELECT login FROM {SQL_TABLE_PREFIX}users WHERE id=".$id);
}

function check_passwd($id, $passwd)
{
	return Q_SINGLEVAL("SELECT login FROM {SQL_TABLE_PREFIX}users WHERE id=".$id." AND passwd='".md5($passwd)."'");
}

function reset_user_passwd_by_key($key)
{
	if ( empty($key) ) return;
	DB_LOCK('{SQL_TABLE_PREFIX}users+');
	$pass=NULL;
	$id = Q_SINGLEVAL("SELECT id FROM {SQL_TABLE_PREFIX}users WHERE reset_key='".$key."'");
	if ( $id ) {
		$u = new fud_user_reg;
		$u->get_user_by_id($id);
		$pass['passwd'] = $u->reset_passwd();
		$pass['usr'] = $u;
		
	}
	DB_UNLOCK();
	
	return $pass;
}

function fud_user_to_reg($obj)
{
	if ( !$obj ) return;
	$u = new fud_user_reg;
	user_copy_object($obj, $u);
	return $u;
}

?>