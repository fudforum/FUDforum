<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: admglobal.php,v 1.28 2003/05/16 06:36:13 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

	require('GLOBALS.php');
	fud_use('adm.inc', true);
	fud_use('glob.inc', true);
	fud_use('widgets.inc', true);
	fud_use('tz.inc');
	fud_use('cfg.inc', true);
	fud_use('draw_select_opt.inc');
	
function draw_help($about)
{
	if (isset($GLOBALS['help_ar'][$about])) {
		return '<br><font size="-1">'.$GLOBALS['help_ar'][$about].'</font>';
	}
}

function print_yn_field($descr, $field)
{
	$str = !isset($GLOBALS[$field]) ? 'Y' : $GLOBALS[$field];
	echo '<tr bgcolor="#bff8ff"><td>'.$descr.': '.draw_help($field).'</td><td valign="top">'.create_select('CF_'.$field, "Yes\nNo", "Y\nN", $str).'</td></tr>';
}
	
function print_string_field($descr, $field, $is_int=0)
{
	if (!isset($GLOBALS[$field])) {
		$str = !$is_int ? '' : '0';
	} else {
		$str = !$is_int ? htmlspecialchars($GLOBALS[$field]) : (int)$GLOBALS[$field];
	}
	echo '<tr bgcolor="#bff8ff"><td>'.$descr.': '.draw_help($field).'</td><td valign="top"><input type="text" name="CF_'.$field.'" value="'.$str.'"></td></tr>';
}

function print_tag_style($descr, $field)
{
	$str = !isset($GLOBALS[$field]) ? 'FUD ML' : $GLOBALS[$field];
	echo '<tr bgcolor="#bff8ff"><td>Tag Style: '.draw_help($field).'</td><td>'.create_select('CF_'.$field, "FUD ML\nHTML\nNone", "ML\nHTML\nNONE", $str).'</td></tr>';
}

function get_max_upload_size()
{
	$us = strtolower(ini_get('upload_max_filesize'));
	$size = (int) $us;
	if (strpos($us, 'm') !== FALSE) {
		$size *= 1024 * 1024;
	} else if (strpos($us, 'k') !== FALSE) {
		$size *= 1024;
	}
	return $size;
}

	$max_attach_size = get_max_upload_size();
	if (isset($_POST['CF_PRIVATE_ATTACH_SIZE'])) {
		if ($_POST['CF_PRIVATE_ATTACH_SIZE'] > $max_attach_size) {
			$_POST['CF_PRIVATE_ATTACH_SIZE'] = $max_attach_size;
		}
	} else if ($GLOBALS['PRIVATE_ATTACH_SIZE'] > $max_attach_size) {
		$GLOBALS['PRIVATE_ATTACH_SIZE'] = $max_attach_size;	
	}

	if (isset($_POST['form_posted'])) {
		/* make a list of the fields we need to change */
		foreach ($_POST as $k => $v) {
			if (strncmp($k, 'CF_', 3)) {
				continue;
			}
			$k = substr($k, 3);
			if (!isset($GLOBALS[$k]) || $GLOBALS[$k] != $v) {
				$ch_list[$k] = $v;
			}
		}
		if (isset($ch_list)) {
			change_global_settings($ch_list);

			/* some fields require us to make special changes */
			if (isset($ch_list['SHOW_N_MODS'])) {
				$GLOBALS['SHOW_N_MODS'] = $ch_list['SHOW_N_MODS'];
				rebuildmodlist();
			}
			if (isset($ch_list['USE_ALIASES']) && $ch_list['USE_ALIASES'] == 'N') {
				q('UPDATE '.$GLOBALS['DBHOST_TBL_PREFIX'].'users SET alias=login');
			}
			if (isset($ch_list['POSTS_PER_PAGE']) || isset($ch_list['DEFAULT_THREAD_VIEW']) || isset($ch_list['ANON_NICK']) || isset($ch_list['SERVER_TZ'])) {
				q('UPDATE '.$GLOBALS['DBHOST_TBL_PREFIX'].'users SET 
					posts_ppg='.(int)$ch_list['POSTS_PER_PAGE'].',
					default_view=\''.addslashes($ch_list['DEFAULT_THREAD_VIEW']).'\',
					login=\''.addslashes($ch_list['ANON_NICK']).'\',
					alias=\''.addslashes($ch_list['SERVER_TZ']).'\',
					time_zone=\''.addslashes($ch_list['SERVER_TZ']).'\'
					WHERE id=1');
			}

			/* put the settings 'live' so they can be seen on the form */
			foreach ($ch_list as $k => $v) {
				$GLOBALS[$k] = $v;
			}
		}
	}
	
	$help_ar = read_help();
	
	$DISABLED_REASON = cfg_dec($DISABLED_REASON);

	require($WWW_ROOT_DISK . 'adm/admpanel.php'); 
?>
<h2>Global Configuration</h2>
<table border=0 cellspacing=1 cellpadding=3>
<form method="post" action="admglobal.php">
<?php
	echo _hs;
	print_string_field('Forum Title', 'FORUM_TITLE');
	print_yn_field('Forum Enabled', 'FORUM_ENABLED');
?>
<tr bgcolor="#bff8ff"><td valign=top>Reason for Disabling:<?php echo draw_help('DISABLED_REASON'); ?></td><td><textarea name="CF_DISABLED_REASON" cols=40 rows=5><?php echo htmlspecialchars($DISABLED_REASON); ?></textarea></td></tr>
<?php
	print_yn_field('Allow Registration', 'ALLOW_REGISTRATION');
?>
<tr bgcolor="#bff8ff"><td colspan=2 align=left><input type="submit" name="btn_submit" value="Set"></td></tr>

<tr bgcolor="#bff8ff"><td colspan=2><br><b>Global</b></td></tr>
<?php
	print_string_field('WWW Root', 'WWW_ROOT');
	print_string_field('WWW Root (disk path)', 'WWW_ROOT_DISK');
	print_string_field('Data Root', 'DATA_DIR');
?>
<tr bgcolor="#bff8ff"><td colspan=2 align=left><input type="submit" name="btn_submit" value="Set"></td></tr>

<tr bgcolor="#bff8ff"><td colspan=2><br><b>Database Settings</b> </td></tr>
<?php
	print_string_field('Database Server', 'DBHOST');
	print_string_field('Database Login', 'DBHOST_USER');
	print_string_field('Database Password', 'DBHOST_PASSWORD');
	print_string_field('Database Name', 'DBHOST_DBNAME');
	print_yn_field('Use Persistent Connections', 'DBHOST_PERSIST');
?>
<tr bgcolor="#bff8ff"><td colspan=2 align=left><input type="submit" name="btn_submit" value="Set"></td></tr>

<tr bgcolor="#bff8ff"><td colspan=2><br><b>Private Messaging</b> </td></tr>
<?php
	print_yn_field('Allow Private Messaging', 'PM_ENABLED');
	print_string_field('File Attachments in Private Messages', 'PRIVATE_ATTACHMENTS', 1);
	print_string_field('Maximum Attachment Size (bytes)', 'PRIVATE_ATTACH_SIZE', 1);
	print_yn_field('Allow Smilies', 'PRIVATE_MSG_SMILEY');
	print_yn_field('Allow Images (fudcode only)', 'PRIVATE_IMAGES');
	print_tag_style('Tag Style', 'PRIVATE_TAGS');
	print_string_field('Maximum Private Messages Folder Size', 'MAX_PMSG_FLDR_SIZE', 1);
?>
<tr bgcolor="#bff8ff"><td colspan=2 align=left><input type="submit" name="btn_submit" value="Set"></td></tr>

<tr bgcolor="#bff8ff"><td colspan=2><br><b>Cookie & Session Settings</b> </td></tr>
<?php
	print_string_field('Cookie Path', 'COOKIE_PATH');
	print_string_field('Cookie Domain', 'COOKIE_DOMAIN');
	print_string_field('Cookie Name', 'COOKIE_NAME');
	print_string_field('Cookie Timeout', 'COOKIE_TIMEOUT', 1);
	print_string_field('Session Timeout', 'SESSION_TIMEOUT', 1);
	print_yn_field('Enable URL sessions', 'SESSION_USE_URL');
?>
<tr bgcolor="#bff8ff"><td colspan=2 align=left><input type="submit" name="btn_submit" value="Set"></td></tr>

<tr bgcolor="#bff8ff"><td colspan=2><br><b>Custom Avatar Settings</b> </td></tr>
<?php
	print_yn_field('Avatar Approval', 'CUSTOM_AVATAR_APPOVAL');
	print_yn_field('Allow Flash (swf) avatars', 'AVATAR_ALLOW_SWF');
?>
<tr bgcolor="#bff8ff"><td>Custom Avatars:<?php echo draw_help('CUSTOM_AVATARS'); ?></td><td><?php draw_select('CF_CUSTOM_AVATARS', "OFF\nBuilt In Only\nURL Only\nUploaded Only\nBuilt In & URL\nBuilt In & Uploaded\nURL & Uploaded\nALL", "OFF\nBUILT\nURL\nUPLOAD\nBUILT_URL\nBUILT_UPLOAD\nURL_UPLOAD\nALL", $CUSTOM_AVATARS); ?></td></tr>
<?php
	print_string_field('Custom Avatar Max Size (bytes)', 'CUSTOM_AVATAR_MAX_SIZE', 1);
	print_string_field('Custom Avatar Max Dimentions', 'CUSTOM_AVATAR_MAX_DIM');
?>
<tr bgcolor="#bff8ff"><td colspan=2 align=left><input type="submit" name="btn_submit" value="Set"></td></tr>

<tr bgcolor="#bff8ff"><td colspan=2><br><b>Signature Settings</b> </td></tr>
<?php
	print_yn_field('Allow Signatures', 'ALLOW_SIGS');
	print_tag_style('Tag Style', 'FORUM_CODE_SIG');
	print_yn_field('Allow Smilies', 'FORUM_SML_SIG');
	print_yn_field('Allow Images (fudcode only)', 'FORUM_IMG_SIG');
	print_string_field('Maximum number of images', 'FORUM_IMG_CNT_SIG', 1);
?>
<tr bgcolor="#bff8ff"><td colspan=2 align=left><input type="submit" name="btn_submit" value="Set"></td></tr>

<tr bgcolor="#bff8ff"><td colspan=2><br><b>Spell Checker</b> </td></tr>
<?php
	if (function_exists('pspell_new_config')) {
		$pspell_support = '<font color="red">is enabled.</font>';
	} else {
		$pspell_support = '<font color="red">is disabled.<br>Please ask your administrator to enable pspell support.</font>';
		$GLOBALS['SPELL_CHECK_ENABLED'] = 'N';
	}
	print_yn_field('Enable Spell Checker', 'SPELL_CHECK_ENABLED');
?>
<tr bgcolor="#bff8ff"><td colspan=2 align=left><input type="submit" name="btn_submit" value="Set"></td></tr>

<tr bgcolor="#bff8ff"><td colspan=2><br><b>Email Settings</b> </td></tr>
<?php
	print_yn_field('Allow Email', 'ALLOW_EMAIL');
	print_yn_field('Use SMTP To Send Email', 'USE_SMTP');
	print_string_field('SMTP Server', 'FUD_SMTP_SERVER');
	print_string_field('SMTP Server Timeout', 'FUD_SMTP_TIMEOUT', 1);
	print_string_field('SMTP Server Login', 'FUD_SMTP_LOGIN');
	print_string_field('SMTP Server Password', 'FUD_SMTP_PASS');
	print_yn_field('Email Confirmation', 'EMAIL_CONFIRMATION');
	print_string_field('Administrator Email', 'ADMIN_EMAIL');
	print_string_field('Notify From', 'NOTIFY_FROM');
	print_yn_field('Notify W/Body', 'NOTIFY_WITH_BODY');
?>
<tr bgcolor="#bff8ff"><td colspan=2 align=left><input type="submit" name="btn_submit" value="Set"></td></tr>

<tr bgcolor="#bff8ff"><td colspan=2><br><b>General Settings</b> </td></tr>
<?php
	print_yn_field('New Account Moderation', 'MODERATE_USER_REGS');
	print_yn_field('Public Host Resolving', 'PUBLIC_RESOLVE_HOST');

	print_yn_field('Logged In Users List Enabled', 'LOGEDIN_LIST');
	print_string_field('Logged In Users List Timeout (minutes)', 'LOGEDIN_TIMEOUT', 1);
	print_string_field('Maximum number of logged in users to show', 'MAX_LOGGEDIN_USERS', 1);
	print_yn_field('Allow Action List', 'ACTION_LIST_ENABLED');
	print_yn_field('Online/Offline Status Indicator', 'ONLINE_OFFLINE_STATUS');

	print_yn_field('COPPA', 'COPPA');
	print_string_field('Max Smilies Shown', 'MAX_SMILIES_SHOWN', 1);

	print_string_field('Maximum Shown Login Length', 'MAX_LOGIN_SHOW', 1);
	print_string_field('Maximum Shown Location Length', 'MAX_LOCATION_SHOW', 1);

	print_string_field('Posts Per Page', 'POSTS_PER_PAGE', 1);
	print_string_field('Topics Per Page', 'THREADS_PER_PAGE', 1);
	print_string_field('Message icons per row', 'POST_ICONS_PER_ROW', 1);

	print_yn_field('Allow Tree View of Thread Listing', 'TREE_THREADS_ENABLE');
?>
	<tr bgcolor="#bff8ff"><td>Default Topic View:<?php echo draw_help('DEFAULT_THREAD_VIEW'); ?></td><td><?php draw_select('CF_DEFAULT_THREAD_VIEW', "Flat View thread and message list\nTree View thread and message list".(($GLOBALS['TREE_THREADS_ENABLE']=='Y')?"\nFlat thread listing/Tree message listing\nTree thread listing/Flat message listing":''), "msg\ntree".(($GLOBALS['TREE_THREADS_ENABLE']=='Y')?"\nmsg_tree\ntree_msg":''), $DEFAULT_THREAD_VIEW); ?></td></tr>
<?php	
	print_string_field('Maximum Depth of Thread Listing (tree view)', 'TREE_THREADS_MAX_DEPTH', 1);
	print_string_field('Maximum Shown Subject Length (tree view)', 'TREE_THREADS_MAX_SUBJ_LEN', 1);
	print_string_field('Polls Per Page', 'POLLS_PER_PAGE', 1);
	
	print_string_field('Word Wrap', 'WORD_WRAP', 1);
	print_string_field('Unconfirmed User Expiry', 'UNCONF_USER_EXPIRY', 1);
	print_string_field('Flood Trigger (seconds)', 'FLOOD_CHECK_TIME', 1);
	print_string_field('Moved Topic Pointer Expiry', 'MOVED_THR_PTR_EXPIRY', 1);
	print_yn_field('Use Aliases', 'USE_ALIASES');
	print_yn_field('Multiple Host Login', 'MULTI_HOST_LOGIN');
?>
<tr bgcolor="#bff8ff"><td>Server Time Zone:<?php draw_help('SERVER_TZ'); ?></td><td><select name="CF_SERVER_TZ" style="font-size: xx-small;"><?php echo tmpl_draw_select_opt($tz_values, $tz_names, $SERVER_TZ, '', ''); ?></select></td></tr>
<?php
	print_yn_field('Forum Search Engine', 'FORUM_SEARCH');
	print_string_field('Search results cache', 'SEARCH_CACHE_EXPIRY', 1);
	print_yn_field('Member Search', 'MEMBER_SEARCH_ENABLED');
	print_string_field('Members Per Page', 'MEMBERS_PER_PAGE', 1);
	print_string_field('Maximum logged-in users', 'MAX_LOGGEDIN_USERS', 1);
	print_string_field('Anonymous Username', 'ANON_NICK');
	print_string_field('Quick Pager Link Count', 'THREAD_MSG_PAGER', 1);
	print_string_field('General Pager Link Count', 'GENERAL_PAGER_COUNT', 1);
	print_string_field('Message icons per row', 'POST_ICONS_PER_ROW', 1);
	print_yn_field('Show Edited By', 'SHOW_EDITED_BY');
	print_yn_field('Show Edited By Moderator', 'EDITED_BY_MOD');
	print_string_field('Edit Time Limit (minutes)', 'EDIT_TIME_LIMIT', 1);
	print_yn_field('Display IP Publicly', 'DISPLAY_IP');
	print_string_field('Max Image Count', 'MAX_IMAGE_COUNT', 1);
	print_string_field('Number Of Moderators To Show', 'SHOW_N_MODS', 1);
	print_yn_field('Public Stats', 'PUBLIC_STATS');
	print_yn_field('Forum Info', 'FORUM_INFO');
	print_string_field('Forum Info Cache Age', 'STATS_CACHE_AGE', 1);
	print_string_field('Registration Time Limit', 'REG_TIME_LIMIT', 1);
	print_yn_field('Enable Affero<br><a href="http://www.affero.net/bbsteps.html" target=_blank>Click here for details</a>', 'ENABLE_AFFERO');
	print_yn_field('Topic Rating', 'ENABLE_THREAD_RATING');
	print_yn_field('Track referrals', 'TRACK_REFERRALS');
	print_yn_field('Profile Image', 'ALLOW_PROFILE_IMAGE');

	if (function_exists('ob_gzhandler')) {
		print_yn_field('Use PHP compression', 'PHP_COMPRESSION_ENABLE');
		print_string_field('PHP compression level', 'PHP_COMPRESSION_LEVEL', 1);
	}		
	print_yn_field('Use PATH_INFO style URLs<br><a href="'.$WWW_ROOT.'index.php/a/b/c" target="_blank">Test Link</a>', 'USE_PATH_INFO');
?>
<tr bgcolor="#bff8ff"><td colspan=2 align=left><input type="submit" name="btn_submit" value="Set"></td></tr>
</table>
<input type="hidden" name="form_posted" value="1">
</form>
<?php require($WWW_ROOT_DISK . 'adm/admclose.html'); ?>