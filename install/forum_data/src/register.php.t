<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: register.php.t,v 1.8 2002/07/09 23:48:20 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

/*#? Register Page */
	
	include_once "GLOBALS.php";	
	{PRE_HTML_PHP}
	$usr = fud_user_to_reg($usr);
	
	if( empty($coppa) ) $coppa = NULL;
	if( empty($reg_coppa) ) $reg_coppa = $coppa;
	if( empty($id) ) $id = NULL;

/*
 * REGISTRATION ERROR CHECK FUNCTIONS
 * This function checks the errors on the register form
 */

function create_theme_select($name, $def=NULL)
{ /* here because only used on register form */
	$theme_select_values = '';
	$r = q("SELECT * FROM {SQL_TABLE_PREFIX}themes WHERE enabled='Y' ORDER BY t_default");
	while ( $theme = db_rowobj($r) ) {
		$selected = $theme->id == $def ? ' selected' : '';
		$theme_select_values .= '{TEMPLATE: theme_select_value}';
	}
	qf($r);

	return '{TEMPLATE: theme_select}';
}

function fetch_img($url)
{
	$ub = parse_url($url);
	
	if( empty($ub['port']) ) $ub['port'] = 80;
	if( !empty($ub['query']) ) $ub['path'] .= '?'.$ub['query'];
	
	$fs = fsockopen($ub['host'], $ub['port'], $errno, $errstr, 10);
	if( !$fs ) return;
	
	fputs($fs, "GET ".$ub['path']." HTTP/1.0\r\nHost: ".$ub['host']."\r\n\r\n");
	
	$ret_code = fgets($fs, 255);
	
	if( !strstr($ret_code, '200') ) {
		fclose($fs);
		return;
	}
	
	$img_str = '';
	
	while( !feof($fs) && strlen($img_str)<$GLOBALS['CUSTOM_AVATAR_MAX_SIZE'] ) 
		$img_str .= fread($fs, $GLOBALS['CUSTOM_AVATAR_MAX_SIZE']);
	fclose($fs);
	
	$img_str = substr($img_str, strpos($img_str, "\r\n\r\n")+4);

	$fp = FALSE;
	do {
		if ( $fp ) fclose($fp);
		$fp = fopen(($path=tempnam($GLOBALS['TMP'],getmypid())), 'ab');
	} while ( ftell($fp) );
	
	fwrite($fp, $img_str);
	fclose($fp);
	
	if( function_exists("GetImageSize") && !@GetImageSize($path) ) { unlink($path); return; }
		
	return $path;
}

function register_form_check($user_id)
{
	if( empty($GLOBALS['error']) ) $GLOBALS['error'] = 0;

	/* General clean up */
	if( isset($GLOBALS['HTTP_POST_VARS']['reg_plaintext_passwd']) ) 
		$GLOBALS['HTTP_POST_VARS']['reg_plaintext_passwd'] = trim($GLOBALS['HTTP_POST_VARS']['reg_plaintext_passwd']);
	if( isset($GLOBALS['HTTP_POST_VARS']['reg_plaintext_passwd']) )
		$GLOBALS['HTTP_POST_VARS']['reg_plaintext_passwd_conf'] = trim($GLOBALS['HTTP_POST_VARS']['reg_plaintext_passwd_conf']);
	$GLOBALS['HTTP_POST_VARS']['reg_name'] = trim($GLOBALS['HTTP_POST_VARS']['reg_name']);
	$GLOBALS['HTTP_POST_VARS']['reg_email'] = trim($GLOBALS['HTTP_POST_VARS']['reg_email']);
	$GLOBALS['HTTP_POST_VARS']['reg_login'] = trim($GLOBALS['HTTP_POST_VARS']['reg_login']);
	$GLOBALS['HTTP_POST_VARS']['reg_home_page'] = trim(url_check($GLOBALS['HTTP_POST_VARS']['reg_home_page']));
	$GLOBALS['HTTP_POST_VARS']['reg_icq'] = preg_replace('![^0-9]!', '', $GLOBALS['HTTP_POST_VARS']['reg_icq']);
	
	if ( strlen($GLOBALS['HTTP_POST_VARS']['reg_avatar_loc']) && !preg_match('!^http://!i', $GLOBALS['HTTP_POST_VARS']['reg_avatar_loc']) )
		$GLOBALS['HTTP_POST_VARS']['reg_avatar_loc'] = 'http://'.$GLOBALS['HTTP_POST_VARS']['reg_avatar_loc'];
	
	/* Check login length */
	if( !$user_id && strlen($GLOBALS['HTTP_POST_VARS']['reg_login']) < 4 ) {
		set_err('reg_login', '{TEMPLATE: register_err_short_login}');
	}
	
	/*
	 * IF we do not have an ID that means that this is a NEW user 
	 * registration
	 */
	if( !$user_id ) {
		if ( is_blocked_login($GLOBALS['HTTP_POST_VARS']['reg_login']) ) {
			set_err('reg_login', '{TEMPLATE: register_err_login_notallowed}');
		}
	
		/* Login uniqness check */
		if ( get_id_by_login($GLOBALS['HTTP_POST_VARS']['reg_login']) ) {
			set_err('reg_login', '{TEMPLATE: register_err_loginunique}');
		}
		
		if ( get_id_by_email($GLOBALS['HTTP_POST_VARS']['reg_email']) ) {
			set_err('reg_email', '{TEMPLATE: register_err_emailexists}');
		}
		
		if( strlen($GLOBALS['HTTP_POST_VARS']["reg_plaintext_passwd"]) < 6 ) {
			set_err('reg_plaintext_passwd', '{TEMPLATE: register_err_shortpasswd}');
		}
	}
	
	/* Password check, needs to be done only if registering a new user */
	if( !$user_id && $GLOBALS['HTTP_POST_VARS']["reg_plaintext_passwd"] != $GLOBALS['HTTP_POST_VARS']["reg_plaintext_passwd_conf"] ) {
		set_err('reg_plaintext_passwd', '{TEMPLATE: register_err_passwdnomatch}');
	}
	
	/* E-mail validity check */
	if( validate_email($GLOBALS['HTTP_POST_VARS']["reg_email"]) ) {
		set_err('reg_email', '{TEMPLATE: register_err_invalidemail}');
	}
	
	/* User's name or nick name */
	if( strlen($GLOBALS['HTTP_POST_VARS']["reg_name"]) < 2 ) {
		set_err('reg_name', '{TEMPLATE: register_err_needname}');
	}
	
	if ( $user_id ) {
		if ( empty($GLOBALS['mod_id']) ) {
			if ( !check_passwd($user_id, stripslashes($GLOBALS['HTTP_POST_VARS']['reg_confirm_passwd'])) ) {
				set_err('reg_confirm_passwd', '{TEMPLATE: register_err_enterpasswd}');
			}
		}
		else {
			if ( !check_passwd($GLOBALS['MOD_usr']->id, stripslashes($GLOBALS['HTTP_POST_VARS']['reg_confirm_passwd'])) ) {
				set_err('reg_confirm_passwd', '{TEMPLATE: register_err_adminpasswd}');
			}
		}
		
		if ( ($email_id=get_id_by_email($GLOBALS['HTTP_POST_VARS']['reg_email'])) && $email_id != $user_id ) {
			set_err('reg_email', '{TEMPLATE: register_err_notyouremail}');
		}
	}
	
	/* Image count check */
	if( $GLOBALS['FORUM_IMG_CNT_SIG'] && $GLOBALS['FORUM_IMG_CNT_SIG'] < substr_count(strtolower($GLOBALS['HTTP_POST_VARS']['reg_sig']), '[img]') ) {
		set_err('reg_sig', '{TEMPLATE: register_err_toomanyimages}');
	}
			
	/* Url Avatar check */
	if( $GLOBALS['HTTP_POST_VARS']['reg_avatar_loc'] ) {		
		if( !($GLOBALS['reg_avatar_loc_file']=fetch_img($GLOBALS['HTTP_POST_VARS']['reg_avatar_loc'])) ) {
			set_err('reg_avatar_loc', '{TEMPLATE: register_err_not_valid_img}');
		}		
	}
	
	/* Alias Check */
	if( $GLOBALS['USE_ALIASES'] == 'Y' && $GLOBALS['HTTP_POST_VARS']['reg_alias'] ) {
		if( ($val=get_id_by_alias($GLOBALS['HTTP_POST_VARS']['reg_alias'])) && $val != $GLOBALS['usr']->id )
			set_err('reg_alias', '{TEMPLATE: register_err_taken_alias}');	
	}
		
	return $GLOBALS['error'];
}

function fmt_year($val)
{
	if( empty($val) ) return;
	if ( $val > 1000 ) return $val;
	else if ( $val < 100 && $val > 5 ) return '19'.$val;
	else if ( $val < 5 ) return '200'.$val;
	
	return;
}

function set_err($err_name, $err_msg)
{
	$GLOBALS['error'] = 1;
	$GLOBALS['err_msg'][$err_name] = $err_msg;
}

function draw_err($err_name)
{
	if ( !isset($err_name) || !isset($GLOBALS['err_msg'][$err_name]) || !strlen($GLOBALS['err_msg'][$err_name])) return;
	return '{TEMPLATE: register_error}';
}

function clean_variables()
{
	$vars = array('reg_avatar_loc','reg_login','reg_alias','reg_email','reg_name','reg_location','reg_occupation','reg_interests','reg_user_image','reg_icq','reg_aim','reg_yahoo','reg_home_page','reg_msnm','reg_avatar','b_month','b_day','b_year','reg_gender','reg_bio','reg_sig','reg_invisible_mode','reg_notify','reg_notify_method','reg_posts_ppg','reg_theme', 'reg_jabber');
	while( list(,$v) = each($vars) ) {
		if( !isset($GLOBALS["HTTP_POST_VARS"][$v]) ) $GLOBALS[$v]=$GLOBALS["HTTP_POST_VARS"][$v]=NULL;		
	}
}

function is_avatar_upload_allowed()
{
	switch( $GLOBALS['CUSTOM_AVATARS'] )
	{
		case 'ALL':
		case 'UPLOAD':
		case 'BUILT_UPLOAD':
		case 'URL_UPLOAD':
			return 1;
	}
	return 0;
}

function fmt_post_vars(&$arr, $who, $leave_arr=NULL)
{
	if ( isset($leave_arr) ) {
		reset($leave_arr);
		while ( list(,$v) = each($leave_arr) ) {
			$leave[$v] = 1;
		}
	}
	
	reset($arr);
	while( list($k,) = each($arr) ) {
		if ( isset($leave[$k]) ) $GLOBALS['_BK_'][$k] = $arr[$k];
		if ( $who == 'DB' ) 
			$GLOBALS['MYSQL_DATA'][$k] = htmlspecialchars($arr[$k]);
		else 
			$GLOBALS[$k] = $arr[$k] = FMT($arr[$k]);
	}
}
/*
 * END OF ERROR CHECK FUNCTIONS 
 */


	/*----------------- END FORM FUNCTIONS --------------------*/
	if( empty($usr->id) && empty($reg_coppa) ) {
		if ( $GLOBALS['COPPA'] == 'Y' ) {
			header("Location: {ROOT}?t=coppa&"._rsid);
		}
		else if ( $GLOBALS['COPPA'] != 'Y' ) {
			header("Location: {ROOT}?t=pre_reg");
		}
		exit;
	}	
	
	if( empty($prev_loaded) ) clean_variables();

	/* allow the root to moderate other lusers */
	if ( !empty($usr) && $usr->is_mod == 'A' && $mod_id ) {
		$MOD_usr = new fud_user;
		$MOD_usr->get_user_by_id($usr->id);
		$usr = new fud_user_reg;
		$usr->get_user_by_id($mod_id);
	}
	else $mod_id=NULL;
	
	if ( empty($usr) && $ALLOW_REGISTRATION != 'Y' ) {
		std_error('registration_disabled');
		exit();
	}
	
	/*
	 * deal with attached files
	 */		
	 if ( is_avatar_upload_allowed() ) {
		$avatar_arr=NULL;
		
		if ( isset($avatar_upload_size) && $avatar_upload_size > 0 ) {
			if( $avatar_upload_size>=$GLOBALS['CUSTOM_AVATAR_MAX_SIZE'] ) { 
				set_err('avatar', '{TEMPLATE: register_err_avatartobig}');
			}
			else if( preg_match('!\.(jpg|jpeg|gif|png)$!i', $avatar_upload_name) ) {
				if ( strlen($avatar_arr['file']) ) @unlink($GLOBALS['TMP'].$avatar_arr['file']);
				$avatar_arr['file'] = safe_tmp_copy($avatar_upload);
				$avarar_arr['del'] = 0;
				$avatar_arr['leave'] = 0;
			}
			else {
				set_err('avatar', '{TEMPLATE: register_err_avatarnotallowed}');
			}	
		}
		else if( isset($avatar_tmp) ) {
			$avatar_tmp = base64_decode(stripslashes($avatar_tmp));
			if ( strlen($avatar_tmp) ) {
				list($avatar_arr['file'], $avatar_arr['local'], $avatar_arr['del'], $avatar_arr['leave']) = explode("\n", $avatar_tmp);
			}
		}	
	
		if ( !empty($btn_detach) ) {
			if ( strlen($avatar_arr['file']) ) @unlink($GLOBALS['TMP'].$avatar_arr['file']);
			$avatar_arr['file'] = '';
			$avatar_arr['del'] = 1;
			$avatar_arr['leave'] = 0;
		}
	}
	
	/*
	 * SUBMITTION CODE
	 *
	 * Here we submit the form, if it passes the error check
	 * then we actually do it, what is done depends
	 * wether we are registering as a new user
	 * or updating a profile
	 */
	
	if ( !empty($fud_submit) && !register_form_check($usr->id) ) {
		$HTTP_POST_VARS['reg_bday'] = fmt_year($b_year).prepad($b_month, 2, '0').prepad($b_day, 2, '0');
		fmt_post_vars($HTTP_POST_VARS, 'DB', array('reg_sig'));
	
		reverse_FMT($GLOBALS['MYSQL_DATA']['reg_sig']);
		$reg_sig = apply_custom_replace($GLOBALS['MYSQL_DATA']['reg_sig']);
		
		switch ( strtolower($GLOBALS['FORUM_CODE_SIG']) )
		{
			case 'ml':
				$reg_sig = tags_to_html($reg_sig, $GLOBALS['FORUM_IMG_SIG']);
				break;
			case 'html':
				break;
			default:
				$reg_sig = nl2br(htmlspecialchars($reg_sig));				       
		}
		
		if ( strtolower($GLOBALS['FORUM_SML_SIG']) == 'y' ) $reg_sig = smiley_to_post($reg_sig);
		
		$reg_sig = stripslashes($reg_sig);
		fud_wordwrap($reg_sig);
		$reg_sig = addslashes($reg_sig);
		
		if( !isset($usr) ) $usr = new fud_user_reg;

		$old_avatar_loc = $usr->avatar_loc;
		fetch_vars("reg_", $usr, $GLOBALS['MYSQL_DATA']);
		$usr->sig = $reg_sig;		
		$usr->bio = stripslashes($usr->bio);
		$usr->home_page = stripslashes($usr->home_page);
	
		if( !$usr->icq && $usr->notify_method == 'ICQ' ) $usr->notify_method = 'EMAIL';
	
		$usr->alias = stripslashes($usr->alias);
		reverse_FMT($usr->alias);
		$usr->alias = addslashes($usr->alias);
	
		if( empty($usr->id) ) {

			$usr->login = stripslashes($usr->login);
			reverse_FMT($usr->login);
			$usr->login = addslashes($usr->login);
			
			$usr->plaintext_passwd = stripslashes($usr->plaintext_passwd);
			reverse_FMT($usr->plaintext_passwd);

			$usr->id = $usr->add_user();
			
			if ( !isset($ses) ) $ses = new fud_session;
			$ses->save_session($usr->id);
			
			if ( is_avatar_upload_allowed() ) {
				if ( strlen($avatar_arr['file']) ) {
					copy($GLOBALS['TMP'].$avatar_arr['file'], 'images/custom_avatars/'.$usr->id);
					@chmod('images/custom_avatars/'.$usr->id,0600);
				}	
			}
			
			if ( $GLOBALS['EMAIL_CONFIRMATION'] == 'Y' ) {
				send_email($GLOBALS['NOTIFY_FROM'], $usr->email, '{TEMPLATE: register_conf_subject}', '{TEMPLATE: register_conf_msg}', "");
			}
			else {
				send_email($GLOBALS['NOTIFY_FROM'], $usr->email, '{TEMPLATE: register_welcome_subject}', '{TEMPLATE: register_welcome_msg}', "");
			}
			
			if ( $GLOBALS['COPPA'] == 'Y' && strtolower($reg_coppa) == 'y' ) {
				header("Location: {ROOT}?t=coppa_fax&"._rsid);
				exit();
			}
			check_return();
		}
		else if ( !empty($usr->id) ) {
			$user_avatar_file = 'images/custom_avatars/'.$usr->id;
			
			if ( $avatar_type == 'b' ) {
				if ( $usr->avatar_loc )
					$usr->avatar_loc = '';
				else if ( $usr->avatar_approved == 'Y' )
					@unlink($user_avatar_file);
				$usr->avatar_approved = 'NO';	
			}
			else if( $avatar_type == 'c' ) {
				if ( $usr->avatar_loc != $old_avatar_loc ) {
					if( $reg_avatar_loc && $reg_avatar_loc_file ) {
                                        	copy($reg_avatar_loc_file, $user_avatar_file);
                                                @chmod($user_avatar_file,0600);
                                                $usr->avatar_approved = 'N';
					}
                                        else {
						$usr->avatar_approved = 'NO';
                                                if ( file_exists($user_avatar_file) ) @unlink($user_avatar_file);
					}
					$usr->avatar_loc = '';
					$usr->avatar = 0;
				}				
			}
			else if ( strlen($avatar_arr['file']) && $avatar_arr['leave'] < 1 && $avatar_arr['del'] < 1 ) {
				copy($GLOBALS['TMP'].$avatar_arr['file'], $user_avatar_file);
				@chmod($user_avatar_file,0600);
				$usr->avatar_approved='N';
				$usr->avatar_loc = '';
				$usr->avatar = 0;
			}
			else if ( $avatar_arr['del'] > 0 ) {
				if ( file_exists($user_avatar_file) ) @unlink($user_avatar_file);
				$usr->avatar_loc = '';
				$usr->avatar = 0;
				$usr->avatar_approved = 'NO';
			}
			else if( $avatar_arr['leave'] > 0 ) {
				$avatar_approved = q_singleval("SELECT avatar_approved FROM {SQL_TABLE_PREFIX}users WHERE id=".$usr->id);
			}	
			
			if ( $usr->avatar_approved == 'N' && $GLOBALS['CUSTOM_AVATAR_APPOVAL'] == 'N' ) $usr->avatar_approved = 'Y';
			
			$usr->sync_user();
			
			if( $avatar_arr['file'] && file_exists($GLOBALS['TMP'].$avatar_arr['file']) && is_file($GLOBALS['TMP'].$avatar_arr['file']) ) 
				unlink($GLOBALS['TMP'].$avatar_arr['file']);
			
			/* restore admin for the redirect */
			$usr = $MOD_usr;
			check_return();
		}
		else {
			error_dialog('{TEMPLATE: register_err_cantreg_title}', '{TEMPLATE: regsiter_err_cantreg_msg}', '', 'FATAL');
			exit();
		}
	}
	
	fmt_post_vars($HTTP_POST_VARS, 'FORM', array('reg_sig','avatar_tmp'));
/*
 * If we have an id we're trying to update the users profile
 * this bit of code will export the varibles for the form
 */	
	if( !empty($usr->id) && empty($prev_loaded) && empty($forced_new_reg) ) {
		export_vars("reg_",$usr);
		
		reverse_FMT($GLOBALS['reg_sig']);
		
		$GLOBALS['reg_sig'] = apply_reverse_replace($GLOBALS['reg_sig']);
		
		if ( strtolower($GLOBALS['FORUM_SML_SIG']) == 'y' ) 
			$GLOBALS['reg_sig'] = post_to_smiley($GLOBALS['reg_sig']);
		
		switch ( strtolower($GLOBALS['FORUM_CODE_SIG']) )
		{
			case 'ml':
				$GLOBALS['reg_sig'] = html_to_tags($GLOBALS['reg_sig']);
				break;
			case 'html':
				break;
			default:
				reverse_nl2br($GLOBALS['reg_sig']);
		}
		
		if( !empty($reg_bday) ) {
			$b_year = substr($reg_bday, 0, 4);
			$b_month = substr($reg_bday, 4, 2);
			$b_day = substr($reg_bday, 6, 8);
		}	
		if ( file_exists('images/custom_avatars/'.$usr->id) ) {
			$avatar_type = 'u';
			$avatar_arr['leave'] = 1;
		}
		else if ( $usr->avatar_loc ) {
			$reg_avatar_loc = $usr->avatar_loc;
			$avatar_type = 'c';
		}
		
		if ( strlen($usr->avatar) ) {
			$reg_avatar_img = 'images/avatars/'.q_singleval("SELECT img FROM {SQL_TABLE_PREFIX}avatar WHERE id=".$usr->avatar);
		}
	}

/*
 * If this is users first vist to the registration form setup the
 * default values
 */
	if ( empty($usr->id) && empty($error) ) {
		$reg_display_email = 'Y';
		$reg_ignore_admin = 'N';
		$reg_email_messages = 'Y';	
		$reg_append_sig = 'Y';
		$reg_show_sigs = 'Y';
		$reg_show_avatars = 'Y';
		$reg_invisible_mode = 'N';
		$reg_notify = 'Y';
		$default_view = $GLOBALS['DEFAULT_THREAD_VIEW'];
	}
	
	if ( empty($reg_time_zone) ) $reg_time_zone = $GLOBALS['SERVER_TZ'];
	
	if ( isset($ses) && isset($usr) ) 
		$ses->update('{TEMPLATE: register_profile_update}');
	else 
		$ses->update('{TEMPLATE: register_register_update}');

	$TITLE_EXTRA = ': {TEMPLATE: register_title}';

	if( isset($MOD_usr) ) { $usr_b = $usr; $usr=$MOD_usr; }
	{POST_HTML_PHP}
	if( isset($usr_b) ) { $usr = $usr_b;};

	$reg_email_err = draw_err('reg_email');
	$reg_name_err = draw_err('reg_name');
	$reg_sig_err = draw_err('reg_sig');
	$reg_alias_err = draw_err('reg_alias');

	if( $HTTP_POST_VARS['reg_alias'] ) reverse_FMT($reg_alias);

	$reg_alias_t = ($GLOBALS['USE_ALIASES'] != 'Y' ? '' : '{TEMPLATE: reg_alias}');

if( empty($usr->id) ) {
	$reg_login_err = draw_err('reg_login');
	$reg_plaintext_passwd_err = draw_err('reg_plaintext_passwd');
	$user_info_heading = '{TEMPLATE: new_user}';
	$submit_button = '{TEMPLATE: register_button}';
}
else { 
	$reg_confirm_passwd_err = draw_err('reg_confirm_passwd');
	$user_login = htmlspecialchars($usr->login);
	if( empty($mod_id) ) $change_passwd_link = '{TEMPLATE: change_passwd_link}';
	$user_info_heading = '{TEMPLATE: update_user}';
	$submit_button = '{TEMPLATE: update_button}';
}

	$avt = new fud_avatar;
	if( ($avtc=$avt->avt_count()) || $GLOBALS['CUSTOM_AVATARS']!='OFF' ) {
		if ( $reg_avatar ) {
			$avt->get($reg_avatar);
			$reg_avatar_img = 'images/avatars/'.$avt->img;
		}
		else $reg_avatar_img = 'blank.gif';
		
		if( isset($usr) ) {
			switch( $GLOBALS['CUSTOM_AVATARS'] ) 
			{
				case 'BUILT':
					$sel_opt = "{TEMPLATE: register_builtin}";
					$sel_val = "b";
					$c_a = 1;
					$a_type='b';
					break;
				case 'URL':
					$sel_opt = "{TEMPLATE: register_specify_url}";
					$sel_val = "c";
					$c_a = 1;
					$a_type='c';
					break;
				case 'UPLOAD':
					$sel_opt = "{TEMPLATE: register_uploaded}";
					$sel_val = "u";
					$c_a = 1;
					$a_type='u';
					break;
				case 'BUILT_URL':
					$sel_opt = "{TEMPLATE: register_builtin}\n{TEMPLATE: register_specify_url}";
					$sel_val = "b\nc";
					$a_type='b';
					$c_a = 1;
					break;
				case 'BUILT_UPLOAD':
					$sel_opt = "{TEMPLATE: register_builtin}\n{TEMPLATE: register_uploaded}";
					$a_type='b';
					$sel_val = "b\nu";
					$c_a = 1;
					break;
				case 'URL_UPLOAD':
					$sel_opt = " URL\n{TEMPLATE: register_uploaded}";
					$sel_val = "c\nu";
					$c_a = 1;
					$a_type='u';
					break;
				case 'ALL':
					$sel_opt = "{TEMPLATE: register_builtin}\n{TEMPLATE: register_specify_url}\n{TEMPLATE: register_uploaded}";
					$a_type='b';
					$sel_val = "b\nc\nu";
					$c_a = 1;
					break;
			}
		}
		
		if ( empty($avatar_type) ) $avatar_type = $a_type;
		if ( isset($c_a) ) {
			$avatar_type_sel_options = tmpl_draw_select_opt($sel_val, $sel_opt, $avatar_type, '{TEMPLATE: sel_opt}', '{TEMPLATE: sel_opt_selected}');
			$avatar_type_sel = '{TEMPLATE: avatar_type_sel}';
		}	

		if ( $avatar_type == 'b' ) {		
			if ( $reg_avatar ) $del_built_in_avatar = '{TEMPLATE: del_built_in_avatar}';
			$avatar = '{TEMPLATE: built_in_avatar}';
		}
		else if ( $avatar_type == 'c' ) {
			$avatar = '{TEMPLATE: custom_url_avatar}';			
		}
		else if ( $avatar_type == 'u' ) {
			$avatar_err = draw_err('avatar');
			if ( $avatar_arr['leave'] > 0 ) {
				if ( @file_exists('images/custom_avatars/'.$usr->id) ) 
					$avatar_img = 'images/custom_avatars/'.$usr->id;
				else 
					$avatar_img = 'blank.gif';
			}
			else if( $avatar_arr['file'] ) 
				$avatar_img = '{ROOT}?t=tmp_view&img='.$avatar_arr['file'];
			else 
				$avatar_img = 'blank.gif';	
			
			if ( strlen($avatar_arr['file']) || $avatar_arr['leave'] > 0 ) 
				$buttons = '{TEMPLATE: delete_uploaded_avatar}';
			else 
				$buttons = '{TEMPLATE: upload_avatar}';
			$avatar_tmp = base64_encode($avatar_arr['file']."\n".$avatar_arr['local']."\n".$avatar_arr['del']."\n".$avatar_arr['leave']);
			$avatar='{TEMPLATE: custom_upload_avatar}';
		}
	}		
	
	$post_options = tmpl_post_options('sig');
	$day_select = tmpl_draw_select_opt("\n1\n2\n3\n4\n5\n6\n7\n8\n9\n10\n11\n12\n13\n14\n15\n16\n17\n18\n19\n20\n21\n22\n23\n24\n25\n26\n27\n28\n29\n30\n31", "\n1\n2\n3\n4\n5\n6\n7\n8\n9\n10\n11\n12\n13\n14\n15\n16\n17\n18\n19\n20\n21\n22\n23\n24\n25\n26\n27\n28\n29\n30\n31", $b_day, '{TEMPLATE: sel_opt}', '{TEMPLATE: sel_opt_selected}');
	$month_select = tmpl_draw_select_opt("\n1\n2\n3\n4\n5\n6\n7\n8\n9\n10\n11\n12", "\n{TEMPLATE: month_1}\n{TEMPLATE: month_2}\n{TEMPLATE: month_3}\n{TEMPLATE: month_4}\n{TEMPLATE: month_5}\n{TEMPLATE: month_6}\n{TEMPLATE: month_7}\n{TEMPLATE: month_8}\n{TEMPLATE: month_9}\n{TEMPLATE: month_10}\n{TEMPLATE: month_11}\n{TEMPLATE: month_12}", $b_month, '{TEMPLATE: sel_opt}', '{TEMPLATE: sel_opt_selected}');
	$gender_select = tmpl_draw_select_opt("UNSPECIFIED\nMALE\nFEMALE","{TEMPLATE: unspecified}\n{TEMPLATE: male}\n{TEMPLATE: female}", $reg_gender, '{TEMPLATE: sel_opt}', '{TEMPLATE: sel_opt_selected}');
	$view_select = tmpl_draw_select_opt("msg\ntree", "{TEMPLATE: register_flat_view}\n{TEMPLATE: register_tree_view}", $reg_default_view, '{TEMPLATE: sel_opt}', '{TEMPLATE: sel_opt_selected}');
	$mppg_select = tmpl_draw_select_opt("0\n5\n10\n20\n30\n40", "{TEMPLATE: use_forum_default}\n5\n10\n20\n30\n40", $reg_posts_ppg, '{TEMPLATE: sel_opt}', '{TEMPLATE: sel_opt_selected}');

	$theme_select = create_theme_select('reg_theme', $reg_theme);
	$style_select = tmpl_draw_select_opt($style_opts, $style_names, $reg_style, '{TEMPLATE: sel_opt}', '{TEMPLATE: sel_opt_selected}');
	$timezone_select = tmpl_draw_select_opt($tz_values, $tz_names, $reg_time_zone, '{TEMPLATE: sel_opt}', '{TEMPLATE: sel_opt_selected}');
	$ignore_admin_radio = tmpl_draw_radio_opt('reg_ignore_admin', "Y\nN", "{TEMPLATE: yes}\n{TEMPLATE: no}", $reg_ignore_admin, '{TEMPLATE: radio_button}', '{TEMPLATE: radio_button_selected}', '{TEMPLATE: radio_button_separator}');
	$invisible_mode_radio = tmpl_draw_radio_opt('reg_invisible_mode', "Y\nN", "{TEMPLATE: yes}\n{TEMPLATE: no}", $reg_invisible_mode, '{TEMPLATE: radio_button}', '{TEMPLATE: radio_button_selected}', '{TEMPLATE: radio_button_separator}');
	$show_email_radio = tmpl_draw_radio_opt('reg_display_email', "Y\nN", "{TEMPLATE: yes}\n{TEMPLATE: no}", $reg_display_email, '{TEMPLATE: radio_button}', '{TEMPLATE: radio_button_selected}', '{TEMPLATE: radio_button_separator}');
	$notify_default_radio = tmpl_draw_radio_opt('reg_notify', "Y\nN", "{TEMPLATE: yes}\n{TEMPLATE: no}", $reg_notify, '{TEMPLATE: radio_button}', '{TEMPLATE: radio_button_selected}', '{TEMPLATE: radio_button_separator}');
	$notification_select = tmpl_draw_select_opt("EMAIL\nICQ", "{TEMPLATE: register_email}\n{TEMPLATE: register_icq}", $reg_notify_method, '{TEMPLATE: sel_opt}', '{TEMPLATE: sel_opt_selected}');
	$accept_user_email = tmpl_draw_radio_opt('reg_email_messages', "Y\nN", "{TEMPLATE: yes}\n{TEMPLATE: no}", $reg_email_messages, '{TEMPLATE: radio_button}', '{TEMPLATE: radio_button_selected}', '{TEMPLATE: radio_button_separator}');
	$show_sig_radio = tmpl_draw_radio_opt('reg_show_sigs', "Y\nN", "{TEMPLATE: yes}\n{TEMPLATE: no}", $reg_show_sigs, '{TEMPLATE: radio_button}', '{TEMPLATE: radio_button_selected}', '{TEMPLATE: radio_button_separator}');
	$show_avatar_radio = tmpl_draw_radio_opt('reg_show_avatars', "Y\nN", "{TEMPLATE: yes}\n{TEMPLATE: no}", $reg_show_avatars, '{TEMPLATE: radio_button}', '{TEMPLATE: radio_button_selected}', '{TEMPLATE: radio_button_separator}');
	$append_sig_radio = tmpl_draw_radio_opt('reg_append_sig', "Y\nN", "{TEMPLATE: yes}\n{TEMPLATE: no}", $reg_append_sig, '{TEMPLATE: radio_button}', '{TEMPLATE: radio_button_selected}', '{TEMPLATE: radio_button_separator}');
	$return = create_return();

	{POST_PAGE_PHP_CODE}
?>
{TEMPLATE: REGISTER_PAGE}