<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: users_reg.inc.t,v 1.2 2002/06/18 18:26:09 hackie Exp $
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
		db_lock('{SQL_TABLE_PREFIX}users+');
		
		do {
			$this->conf_key = md5(get_random_value(128));
		} while ( bq("SELECT id FROM {SQL_TABLE_PREFIX}users WHERE conf_key='".$this->conf_key."'") );

		$ref_id = !empty($GLOBALS["HTTP_COOKIE_VARS"]["frm_referer_id"]) ? $GLOBALS["HTTP_COOKIE_VARS"]["frm_referer_id"] : 0;
		if( empty($this->avatar_loc) ) $this->avatar_loc = NULL;
		
		$md5pass = md5($this->plaintext_passwd);
		$tm = __request_timestamp__;		
		q("INSERT INTO 
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
				'".yn($this->display_email)."',
				'".yn($this->notify)."',
				'".$this->notify_method."',
				'".yn($this->ignore_admin)."',
				'".yn($this->email_messages)."',
				'".$this->gender."',
				".intnull($this->icq).",
				".strnull($this->aim).",
				".strnull($this->yahoo).",
				".strnull($this->msnm).",
				".strnull($this->jabber).",
				'".yn($this->append_sig)."',
				'".$this->posts_ppg."',
				'".$this->time_zone."',
				".intzero($this->bday).",
				'".yn($this->invisible_mode)."',
				".$tm.",
				'".$this->conf_key."',
				".strnull($this->user_image).",
				".$tm.",
				'".$this->location."',
				".intzero($this->avatar).",
				".intzero($this->theme).",
				'".yn($this->coppa)."',
				".strnull($this->occupation).",
				".strnull($this->interests).",
				".intzero($ref_id).",
				'".yn($this->show_sigs)."',
				'".yn($this->show_avatars)."',
				".$tm.",
				".strnull($this->avatar_loc).",
				'NO',
				".strnull($this->sig).",
				'".$this->default_view."',
				".strnull(addslashes($this->home_page)).",
				".strnull(addslashes($this->bio))."
			)
		");
		$this->id = db_lastid();
		if( $GLOBALS['EMAIL_CONFIRMATION'] == 'N' ) $this->email_confirm();
		db_unlock();
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
		if ( $plaintext_passwd ) $passwd = "'".md5($plaintext_passwd)."',";
		q("UPDATE 
				{SQL_TABLE_PREFIX}users 
			SET 
				$passwd name='".$this->name."',
				email='".$this->email."',
				display_email='".yn($this->display_email)."',
				notify='".yn($this->notify)."',
				notify_method='".$this->notify_method."',
				ignore_admin='".yn($this->ignore_admin)."',
				email_messages='".yn($this->email_messages)."',
				gender='".$this->gender."',
				icq=".intnull($this->icq).",
				aim=".strnull($this->aim).",
				yahoo=".strnull($this->yahoo).",
				msnm=".strnull($this->msnm).",
				jabber=".strnull($this->jabber).",
				append_sig='".yn($this->append_sig)."',
				show_sigs='".yn($this->show_sigs)."',
				show_avatars='".yn($this->show_avatars)."',
				posts_ppg='".$this->posts_ppg."',
				time_zone='".$this->time_zone."',
				invisible_mode='".yn($this->invisible_mode)."',
				bday=".intzero($this->bday).",
				user_image=".strnull($this->user_image).",
				location='".$this->location."',
				occupation='".$this->occupation."',
				interests='".$this->interests."',
				avatar=".intzero($this->avatar).",
				theme=".intzero($this->theme).",
				avatar_loc=".strnull($this->avatar_loc).",
				avatar_approved='".$this->avatar_approved."',
				sig=".strnull($this->sig).",
				default_view='".$this->default_view."',
				home_page=".strnull(addslashes($this->home_page)).",
				bio=".strnull(addslashes($this->bio))."
			WHERE id=".$this->id
		);
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
		db_lock('{SQL_TABLE_PREFIX}users+');
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
		db_lock('{SQL_TABLE_PREFIX}users+');
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
	return q_singleval("SELECT id FROM {SQL_TABLE_PREFIX}users WHERE email='".$email."'");
}

function get_id_by_login($login)
{
	return q_singleval("SELECT id FROM {SQL_TABLE_PREFIX}users WHERE login='".$login."'");
}

function get_id_by_radius($login, $passwd)
{
	return q_singleval("SELECT id FROM {SQL_TABLE_PREFIX}users WHERE login='".$login."' AND passwd='".md5($passwd)."'");
}

function check_user($id)
{
	return q_singleval("SELECT login FROM {SQL_TABLE_PREFIX}users WHERE id=".$id);
}

function check_passwd($id, $passwd)
{
	return q_singleval("SELECT login FROM {SQL_TABLE_PREFIX}users WHERE id=".$id." AND passwd='".md5($passwd)."'");
}

function reset_user_passwd_by_key($key)
{
	if ( empty($key) ) return;
	db_lock('{SQL_TABLE_PREFIX}users+');
	$pass=NULL;
	$id = q_singleval("SELECT id FROM {SQL_TABLE_PREFIX}users WHERE reset_key='".$key."'");
	if ( $id ) {
		$u = new fud_user_reg;
		$u->get_user_by_id($id);
		$pass['passwd'] = $u->reset_passwd();
		$pass['usr'] = $u;
		
	}
	db_unlock();
	
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