<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: scripts_common.inc,v 1.6 2003/09/30 03:49:26 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

function match_user_to_post($from_email, $from_name, $create_users, &$user_id)
{
	/* Try to identify user by email */
	$user_id = q_singleval("SELECT id FROM ".sql_p."users WHERE email='".addslashes($from_email)."'");

	/* If user was not found via email, try to look the user up by login */
	if (empty($user_id) && !empty($from_name)) {
		$user_id = q_singleval("SELECT id FROM ".sql_p."users WHERE login='".addslashes($from_name)."'");
	}	

	if (empty($user_id)) {
		$user_id = $create_users ? create_new_user($from_name, $from_email) : 0;
	} 

	return $user_id;	
}

function create_new_user($from_name, $from_email)
{
	/* Since we assume every user created from a mailing list is already 'confirmed' 
	 * this disables sending of the email confirmation
	 */

	$login = empty($from_name) ? $from_email : $from_name;
	$i = 1;

	$user = new fud_user_reg;
	$user->login = $login;
		
	db_lock(sql_p.'users WRITE, '.sql_p.'themes WRITE');

	/* 
	 * This code ensures that creation of user does not fail in the event another user on the forum 
	 * is already signed up under the same login name and/or alias
	 */	
	while (q_singleval("SELECT id FROM ".sql_p."users WHERE login='".addslashes($user->login)."'")) {
		$user->login = $login.'['.$i++.']';
	}

	$alias = $user->alias = htmlspecialchars($user->login);

	if ($GLOBALS['FUD_OPT_2'] & 128) {
		while (q_singleval("SELECT id FROM ".sql_p."users WHERE alias='".addslashes($user->alias)."'")) {
			$user->alias = $alias.'['.$i++.']';
		}
	}

	$user->email = $from_email;
	$user->plaintext_passwd = substr(md5(get_random_value()), 0, 8);
	$user->name = $from_name;

	$id = $user->add_user();
	q("UPDATE ".sql_p."users SET users_opt=users_opt|131072 WHERE id=".$id);

	db_unlock();
		
	return $id;
}

function get_fud_reply_id($complex, $forum_id, $subject, $data)
{
	if (!empty($data)) {
		if (is_string($data)) {
			$data = array($data);	
		}

		foreach ($data as $reply_id) {
			if (($r = db_saq("SELECT id, thread_id FROM ".sql_p."msg WHERE mlist_msg_id='".addslashes($reply_id)."'"))) {
				break;
			}
		}
	}
	if (empty($r) && $complex) {
		// This is slow, but only way to match 'rouge' replies in the event no reference fields are avaliable
		if (preg_match('!(Re|Wa)\s*:(.*)$!i', $subject, $matches)) {
			$r = db_saq('SELECT m.id, m.thread_id FROM '.sql_p.'msg m INNER JOIN '.sql_p.'thread t ON m.thread_id=t.id WHERE t.forum_id='.$forum_id.' AND m.subject='.strnull(addslashes(trim($matches[2]))));
		}
	}

	return !empty($r) ? array((int)$r[0], (int)$r[1]) : array(0, 0);
}

function parse_ip($str)
{
	if (preg_match('!([0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3})!', $str, $m)) {
		return $m[1];
	} else {
		return;	
	}
}

function decode_string($str, $encoding)
{
	switch ($encoding) {
		case 'quoted-printable':
			// Remove soft line breaks & decode
		        return quoted_printable_decode(preg_replace("!=\r?\n!", '', $str));
			break;
		case 'base64':
			return base64_decode($str);
			break;
		default:
			return $str;	
			break;
	}
}

function decode_header_value($val)
{
	// check if string needs to be decoded
	if (strpos($val, '?') === false) {
		return trim($val);	
	}

	// Decode String
	if (preg_match_all('!(.*?)(=\?([^?]+)\?(Q|B)\?([^?]*)\?=)[[:space:]]*(.*)!i', $val, $m)) {
		$newval = '';
			
		$c = count($m[4]);
		for ($i = 0; $i < $c; $i++) {
			$ec_type = strtolower($m[4][$i]);
			
			if ($ec_type == 'q') {
				$newval .= decode_string(str_replace('_', ' ', $m[5][$i]), 'quoted-printable');	
			} else if ($ec_type == 'b') {
				$newval .= decode_string($m[5][$i], 'base64');
			}

			if (!empty($m[5][$i])) {
				$newval .= ' '.$m[6][$i];
			}
			if (!empty($m[1][$i])) {
				$newval = $m[1][$i].$newval;
			}
		}
		$val = trim($newval);
	}
	return trim($val);
}
?>