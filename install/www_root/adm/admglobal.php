<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: admglobal.php,v 1.2 2002/06/18 17:23:42 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

	define('admin_form', 1);

	include_once "GLOBALS.php";
	
	fud_use('static/glob.inc');
	fud_use('static/widgets.inc');
	fud_use('util.inc');
	fud_use('static/adm.inc');
	fud_use('tz.inc');
	fud_use('static/cfg.inc');
	fud_use('draw_select_opt.inc');
	
	list($ses, $usr) = initadm();
	
	$global_config = read_global_config();
	$global_config_ar = global_config_ar($global_config);
	reset($global_config_ar);
	
	if ( !empty($form_posted) ) {
		$change = 0;
		while( list($k,$v) = each($global_config_ar) ) {
			if( !isset($HTTP_POST_VARS['CF_'.$k]) ) continue;
			$HTTP_POST_VARS['CF_'.$k] = addcslashes(stripslashes($HTTP_POST_VARS['CF_'.$k]), '"$');
			if( $HTTP_POST_VARS['CF_'.$k] == $v ) continue;
			
			change_global_val($k, $HTTP_POST_VARS['CF_'.$k], $global_config);
			$change = 1;
		}
		
		if( $change ) write_global_config($global_config);
		
		/* specific actions that need taking */
		if ( $GLOBALS['SHOW_N_MODS'] != $HTTP_POST_VARS['CF_SHOW_N_MODS'] ) {
			$GLOBALS['SHOW_N_MODS'] = $HTTP_POST_VARS['CF_SHOW_N_MODS'];
			rebuildmodlist();
		}
		
		header("Location: admglobal.php?"._rsid."&rnd=".get_random_value(128));
		exit();
	}
	
	$help_ar = read_help();
	
	while ( list($key, $val) = each($global_config_ar) ) $GLOBALS['CF_'.$key] = stripslashes($val);
	
	$CF_DISABLED_REASON = cfg_dec($CF_DISABLED_REASON);

function draw_help($about)
{
	if( !empty($GLOBALS['help_ar'][$about]) ) 
		echo '<br><font size="-1">'.$GLOBALS['help_ar'][$about].'</font>';
}
	
include('admpanel.php'); 
?>
<h2>Global Configuration</h2>
<table border=0 cellspacing=1 cellpadding=3>
<form method="post">
<?php echo _hs; ?>
<tr bgcolor="#bff8ff"><td>Forum Title:<?php draw_help('FORUM_TITLE'); ?></td><td><input type="text" name="CF_FORUM_TITLE" value="<?php echo htmlspecialchars($CF_FORUM_TITLE); ?>"></td></tr>
<tr bgcolor="#bff8ff"><td>Forum Enabled:<?php draw_help('FORUM_ENABLED'); ?></td><td><?php draw_select('CF_FORUM_ENABLED', "Yes\nNo", "Y\nN", $CF_FORUM_ENABLED); ?></td></tr>
<tr bgcolor="#bff8ff"><td valign=top>Reason for Disabling:<?php draw_help('DISABLED_REASON'); ?></td><td><textarea name="CF_DISABLED_REASON" cols=40 rows=5><?php echo htmlspecialchars($CF_DISABLED_REASON); ?></textarea></td></tr>
<tr bgcolor="#bff8ff"><td>Allow Registration:<?php draw_help('ALLOW_REGISTRATION'); ?></td><td><?php draw_select('CF_ALLOW_REGISTRATION', "Yes\nNo", "Y\nN", $CF_ALLOW_REGISTRATION); ?></td></tr>
<tr bgcolor="#bff8ff"><td colspan=2 align=left><input type="submit" name="btn_submit" value="Set"></td></tr>

<tr bgcolor="#bff8ff"><td colspan=2><br><b>Global</b></td></tr>
<tr bgcolor="#bff8ff"><td>WWW Root:<?php draw_help('WWW_ROOT'); ?></td><td><input type="text" name="CF_WWW_ROOT" value="<?php echo htmlspecialchars($CF_WWW_ROOT); ?>"></td></tr>
<tr bgcolor="#bff8ff"><td>Error File:<?php draw_help('ERROR_PATH'); ?></td><td><input type="text" name="CF_ERROR_PATH" value="<?php echo htmlspecialchars($CF_ERROR_PATH); ?>"></td></tr>
<tr bgcolor="#bff8ff"><td>Message Storage Dir:<?php draw_help('MSG_STORE_DIR'); ?></td><td><input type="text" name="CF_MSG_STORE_DIR" value="<?php echo htmlspecialchars($CF_MSG_STORE_DIR); ?>"></td></tr>
<tr bgcolor="#bff8ff"><td>Temporary Dir:<?php draw_help('TMP'); ?></td><td><input type="text" name="CF_TMP" value="<?php echo htmlspecialchars($CF_TMP); ?>"></td></tr>
<tr bgcolor="#bff8ff"><td>File Storage Dir:<?php draw_help('FILE_STORE'); ?></td><td><input type="text" name="CF_FILE_STORE" value="<?php echo htmlspecialchars($CF_FILE_STORE); ?>"></td></tr>
<tr bgcolor="#bff8ff"><td>Forum Settings Dir:<?php draw_help('FORUM_SETTINGS_PATH'); ?></td><td><input type="text" name="CF_FORUM_SETTINGS_PATH" value="<?php echo htmlspecialchars($CF_FORUM_SETTINGS_PATH); ?>"></td></tr>
<tr bgcolor="#bff8ff"><td>Mogrify Path:<?php draw_help('MOGRIFY_BIN'); ?><br><font size="-1">ImageMagick utility for manipulating images, used to allow the admin to scale/convert custom avatars.</font></td><td><input type="text" name="CF_MOGRIFY_BIN" value="<?php echo htmlspecialchars($CF_MOGRIFY_BIN); ?>"></td></tr>
<tr bgcolor="#bff8ff"><td colspan=2 align=left><input type="submit" name="btn_submit" value="Set"></td></tr>

<tr bgcolor="#bff8ff"><td colspan=2><br><b>MySQL</b> </td></tr>
<tr bgcolor="#bff8ff"><td>MySQL Server:<?php draw_help('MYSQL_SERVER'); ?></td><td><input type="text" name="CF_MYSQL_SERVER" value="<?php echo htmlspecialchars($CF_MYSQL_SERVER); ?>"></td></tr>
<tr bgcolor="#bff8ff"><td>MySQL Login:<?php draw_help('MYSQL_LOGIN'); ?></td><td><input type="text" name="CF_MYSQL_LOGIN" value="<?php echo htmlspecialchars($CF_MYSQL_LOGIN); ?>"></td></tr>
<tr bgcolor="#bff8ff"><td>MySQL Password:<?php draw_help('MYSQL_PASSWORD'); ?></td><td><input type="text" name="CF_MYSQL_PASSWORD" value="<?php echo htmlspecialchars($CF_MYSQL_PASSWORD); ?>"></td></tr>
<tr bgcolor="#bff8ff"><td>MySQL DB:<?php draw_help('MYSQL_DB'); ?></td><td><input type="text" name="CF_MYSQL_DB" value="<?php echo htmlspecialchars($CF_MYSQL_DB); ?>"></td></tr>
<tr bgcolor="#bff8ff"><td>User Persistent Connections:<?php draw_help('MYSQL_PERSIST'); ?></td><td><?php draw_select('CF_MYSQL_PERSIST', "Yes\nNo", "Y\nN", $MYSQL_PERSIST); ?></td></tr>
<tr bgcolor="#bff8ff"><td colspan=2 align=left><input type="submit" name="btn_submit" value="Set"></td></tr>

<tr bgcolor="#bff8ff"><td colspan=2><br><b>Private Messaging</b> </td></tr>
<tr bgcolor="#bff8ff"><td>Allow Private Messaging:<?php draw_help('PM_ENABLED'); ?></td><td><?php draw_select('CF_PM_ENABLED', "Yes\nNo", "Y\nN", $PM_ENABLED); ?></td></tr>
<tr bgcolor="#bff8ff"><td>File Attachments in Private Messages:<?php draw_help('PRIVATE_ATTACHMENTS'); ?></td><td><input type="text" name="CF_PRIVATE_ATTACHMENTS" value="<?php echo $CF_PRIVATE_ATTACHMENTS; ?>"></td></tr>
<tr bgcolor="#bff8ff"><td>Maximum Attachment Size (bytes):<?php draw_help('PRIVATE_ATTACH_SIZE'); ?></td><td><input type="text" name="CF_PRIVATE_ATTACH_SIZE" value="<?php echo $CF_PRIVATE_ATTACH_SIZE; ?>"></td></tr>
<tr bgcolor="#bff8ff"><td>Allow Smilies:<?php draw_help('PRIVATE_MSG_SMILEY'); ?></td><td><?php draw_select('CF_PRIVATE_MSG_SMILEY', "Yes\nNo", "Y\nN", $CF_PRIVATE_MSG_SMILEY); ?></td></tr>
<tr bgcolor="#bff8ff"><td>Allow Images (fudcode only):<?php draw_help('PRIVATE_IMAGES'); ?></td><td><?php draw_select('CF_PRIVATE_IMAGES', "Yes\nNo", "Y\nN", $CF_PRIVATE_IMAGES); ?></td></tr>
<tr bgcolor="#bff8ff"><td>Tag Style:<?php draw_help('PRIVATE_TAGS'); ?></td><td><?php draw_select('CF_PRIVATE_TAGS', "FUD ML\nHTML\nNone", "ML\nHTML\nNONE", $CF_PRIVATE_TAGS); ?></td></tr>
<tr bgcolor="#bff8ff"><td>Maximum Private Messages Folder Size:<?php draw_help('MAX_PMSG_FLDR_SIZE'); ?></td><td><input type="text" name="CF_MAX_PMSG_FLDR_SIZE" value="<?php echo $CF_MAX_PMSG_FLDR_SIZE; ?>"></td></tr>
<tr bgcolor="#bff8ff"><td colspan=2 align=left><input type="submit" name="btn_submit" value="Set"></td></tr>

<tr bgcolor="#bff8ff"><td colspan=2><br><b>Cookie & Session Settings</b> </td></tr>
<tr bgcolor="#bff8ff"><td>Cookie Path:<?php draw_help('COOKIE_PATH'); ?></td><td><input type="text" name="CF_COOKIE_PATH" value="<?php echo htmlspecialchars($CF_COOKIE_PATH); ?>"></td></tr>
<tr bgcolor="#bff8ff"><td>Cookie Domain:<?php draw_help('COOKIE_DOMAIN'); ?></td><td><input type="text" name="CF_COOKIE_DOMAIN" value="<?php echo htmlspecialchars($CF_COOKIE_DOMAIN); ?>"></td></tr>
<tr bgcolor="#bff8ff"><td>Cookie Name:<?php draw_help('COOKIE_NAME'); ?></td><td><input type="text" name="CF_COOKIE_NAME" value="<?php echo htmlspecialchars($CF_COOKIE_NAME); ?>"></td></tr>
<tr bgcolor="#bff8ff"><td>Cookie Timeout:<?php draw_help('COOKIE_TIMEOUT'); ?></td><td><input type="text" name="CF_COOKIE_TIMEOUT" value="<?php echo $CF_COOKIE_TIMEOUT; ?>"></td></tr>
<tr bgcolor="#bff8ff"><td>Session Timeout:<?php draw_help('SESSION_TIMEOUT'); ?></td><td><input type="text" name="CF_SESSION_TIMEOUT" value="<?php echo $CF_SESSION_TIMEOUT; ?>"></td></tr>
<tr bgcolor="#bff8ff"><td colspan=2 align=left><input type="submit" name="btn_submit" value="Set"></td></tr>

<tr bgcolor="#bff8ff"><td colspan=2><br><b>Custom Avatar Settings</b> </td></tr>
<tr bgcolor="#bff8ff"><td>Avatar Approval:<?php draw_help('CUSTOM_AVATAR_APPOVAL'); ?></td><td><?php draw_select('CF_CUSTOM_AVATAR_APPOVAL', "Yes\nNo", "Y\nN", $CF_CUSTOM_AVATAR_APPOVAL); ?></td></tr>
<tr bgcolor="#bff8ff"><td>Custom Avatars:<?php draw_help('CUSTOM_AVATARS'); ?></td><td><?php draw_select('CF_CUSTOM_AVATARS', "OFF\nBuilt In Only\nURL Only\nUploaded Only\nBuilt In & URL\nBuilt In & Uploaded\nURL & Uploaded\nALL", "OFF\nBUILT\nURL\nUPLOAD\nBUILT_URL\nBUILT_UPLOAD\nURL_UPLOAD\nALL", $CF_CUSTOM_AVATARS); ?></td></tr>
<tr bgcolor="#bff8ff"><td>Custom Avatar Max Size (bytes):<?php draw_help('CUSTOM_AVATAR_MAX_SIZE'); ?></td><td><input type="text" name="CF_CUSTOM_AVATAR_MAX_SIZE" value="<?php echo $CUSTOM_AVATAR_MAX_SIZE; ?>"></td></tr>
<tr bgcolor="#bff8ff"><td>Custom Avatar Max Dimentions:<?php draw_help('CUSTOM_AVATAR_MAX_DIM'); ?></td><td><input type="text" name="CF_CUSTOM_AVATAR_MAX_DIM" value="<?php echo htmlspecialchars($CUSTOM_AVATAR_MAX_DIM); ?>"></td></tr>
<tr bgcolor="#bff8ff"><td colspan=2 align=left><input type="submit" name="btn_submit" value="Set"></td></tr>

<tr bgcolor="#bff8ff"><td colspan=2><br><b>Signature Settings</b> </td></tr>
<tr bgcolor="#bff8ff"><td>Allow Signatures:<?php draw_help('ALLOW_SIGS'); ?></td><td><?php draw_select('CF_ALLOW_SIGS', "Yes\nNo", "Y\nN", $CF_ALLOW_SIGS); ?></td></tr>
<tr bgcolor="#bff8ff"><td>Tag Style:<?php draw_help('FORUM_CODE_SIG'); ?></td><td><?php draw_select('CF_FORUM_CODE_SIG', "FUD ML\nHTML\nNone", "ML\nHTML\nNONE", $CF_FORUM_CODE_SIG); ?></td></tr>
<tr bgcolor="#bff8ff"><td>Allow Smilies:<?php draw_help('FORUM_SML_SIG'); ?></td><td><?php draw_select('CF_FORUM_SML_SIG', "Yes\nNo", "Y\nN", $CF_FORUM_SML_SIG); ?></td></tr>
<tr bgcolor="#bff8ff"><td>Allow Images (fudcode only):<?php draw_help('FORUM_IMG_SIG'); ?></td><td><?php draw_select('CF_FORUM_IMG_SIG', "Yes\nNo", "Y\nN", $CF_FORUM_IMG_SIG); ?></td></tr>
<tr bgcolor="#bff8ff"><td>Maximum number of images:<?php draw_help('FORUM_IMG_CNT_SIG'); ?></td><td><input type="text" name="CF_FORUM_IMG_CNT_SIG" value="<?php echo $FORUM_IMG_CNT_SIG; ?>"></td></tr>
<tr bgcolor="#bff8ff"><td colspan=2 align=left><input type="submit" name="btn_submit" value="Set"></td></tr>

<?php
if ( function_exists('pspell_new_config') )
	$pspell_support = '<font color="red">is enabled.</font>';
else
	$pspell_support = '<font color="red">is disabled.<br>Please ask your administrator to enable pspell support.</font>';
?>
<tr bgcolor="#bff8ff"><td colspan=2><br><b>Spell Checker</b> </td></tr>
<tr bgcolor="#bff8ff"><td>Enable Spell Checker:<?php draw_help('SPELL_CHECK_ENABLED'); ?><br><font size=-1>This option requires pspell support in PHP, which is currently <?php echo $pspell_support; ?></font></td><td><?php draw_select('CF_SPELL_CHECK_ENABLED', "Yes\nNo", "Y\nN", $CF_SPELL_CHECK_ENABLED); ?></td></tr>
<tr bgcolor="#bff8ff"><td colspan=2 align=left><input type="submit" name="btn_submit" value="Set"></td></tr>

<tr bgcolor="#bff8ff"><td colspan=2><br><b>General Settings</b> </td></tr>
<tr bgcolor="#bff8ff"><td>Max Smilies Shown:<?php draw_help('MAX_SMILIES_SHOWN'); ?></td><td><input type="text" name="CF_MAX_SMILIES_SHOWN" value="<?php echo $CF_MAX_SMILIES_SHOWN; ?>"></td></tr>
<tr bgcolor="#bff8ff"><td>Public Host Resolving:<?php draw_help('PUBLIC_RESOLVE_HOST'); ?></td><td><?php draw_select('CF_PUBLIC_RESOLVE_HOST', "Yes\nNo", "Y\nN", $CF_PUBLIC_RESOLVE_HOST); ?></td></tr>
<tr bgcolor="#bff8ff"><td>Maximum Shown Login Length:<?php draw_help('MAX_LOGIN_SHOW'); ?></td><td><input type="text" name="CF_MAX_LOGIN_SHOW" value="<?php echo $CF_MAX_LOGIN_SHOW; ?>"></td></tr>
<tr bgcolor="#bff8ff"><td>Maximum Shown Location Length:<?php draw_help('MAX_LOCATION_SHOW'); ?></td><td><input type="text" name="CF_MAX_LOCATION_SHOW" value="<?php echo $CF_MAX_LOCATION_SHOW; ?>"></td></tr>
<tr bgcolor="#bff8ff"><td>Logged In Users List Enabled:<?php draw_help('LOGEDIN_LIST'); ?></td><td><?php draw_select('CF_LOGEDIN_LIST', "Yes\nNo", "Y\nN", $CF_LOGEDIN_LIST); ?></td></tr>
<tr bgcolor="#bff8ff"><td>Logged In Users List Timeout (minutes):<?php draw_help('LOGEDIN_TIMEOUT'); ?></td><td><input type="text" name="CF_LOGEDIN_TIMEOUT" value="<?php echo $CF_LOGEDIN_TIMEOUT; ?>"></td></tr>
<tr bgcolor="#bff8ff"><td>Allow Action List:<?php draw_help('ACTION_LIST_ENABLED'); ?></td><td><?php draw_select('CF_ACTION_LIST_ENABLED', "Yes\nNo", "Y\nN", $CF_ACTION_LIST_ENABLED); ?></td></tr>
<tr bgcolor="#bff8ff"><td>Online/Offline Status Indicator:<?php draw_help('ONLINE_OFFLINE_STATUS'); ?></td><td><?php draw_select('CF_ONLINE_OFFLINE_STATUS', "Yes\nNo", "Y\nN", $CF_ONLINE_OFFLINE_STATUS); ?></td></tr>
<tr bgcolor="#bff8ff"><td>Administrator EMail:<?php draw_help('ADMIN_EMAIL'); ?></td><td><input type="text" name="CF_ADMIN_EMAIL" value="<?php echo htmlspecialchars($CF_ADMIN_EMAIL); ?>"></td></tr>
<tr bgcolor="#bff8ff"><td>Notify From:<?php draw_help('NOTIFY_FROM'); ?></td><td><input type="text" name="CF_NOTIFY_FROM" value="<?php echo htmlspecialchars($CF_NOTIFY_FROM); ?>"></td></tr>
<tr bgcolor="#bff8ff"><td>Notify W/Body:<?php draw_help('NOTIFY_WITH_BODY'); ?></td><td><?php draw_select('CF_NOTIFY_WITH_BODY', "Yes\nNo", "Y\nN", $CF_NOTIFY_WITH_BODY); ?></td></tr>
<tr bgcolor="#bff8ff"><td>COPPA:<?php draw_help('COPPA'); ?></td><td><?php draw_select('CF_COPPA', "Off\nOn", "N\nY", $CF_COPPA); ?></td></tr>
<tr bgcolor="#bff8ff"><td>Posts Per Page:<?php draw_help('POSTS_PER_PAGE'); ?></td><td><input type="text" name="CF_POSTS_PER_PAGE" value="<?php echo $CF_POSTS_PER_PAGE; ?>"></td></tr>
<tr bgcolor="#bff8ff"><td>Threads Per Page:<?php draw_help('THREADS_PER_PAGE'); ?></td><td><input type="text" name="CF_THREADS_PER_PAGE" value="<?php echo $CF_THREADS_PER_PAGE; ?>"></td></tr>
<tr bgcolor="#bff8ff"><td>Default Thread View:<?php draw_help('DEFAULT_THREAD_VIEW'); ?></td><td><?php draw_select('CF_DEFAULT_THREAD_VIEW', "Flat View\nTree View", "msg\ntree", $CF_DEFAULT_THREAD_VIEW); ?></td></tr>
<tr bgcolor="#bff8ff"><td>Word Wrap:<?php draw_help('WORD_WRAP'); ?></td><td><input type="text" name="CF_WORD_WRAP" value="<?php echo $CF_WORD_WRAP; ?>"></td></tr>
<tr bgcolor="#bff8ff"><td>Unconfirmed User Expiry:<?php draw_help('UNCONF_USER_EXPIRY'); ?></td><td><input type="text" name="CF_UNCONF_USER_EXPIRY" value="<?php echo $CF_UNCONF_USER_EXPIRY; ?>"></td></tr>
<tr bgcolor="#bff8ff"><td>Flood Trigger (seconds):<?php draw_help('FLOOD_CHECK_TIME'); ?></td><td><input type="text" name="CF_FLOOD_CHECK_TIME" value="<?php echo $CF_FLOOD_CHECK_TIME; ?>"></td></tr>
<tr bgcolor="#bff8ff"><td>Moved Thread Pointer Expiry:<?php draw_help('MOVED_THR_PTR_EXPIRY'); ?></td><td><input type="text" name="CF_MOVED_THR_PTR_EXPIRY" value="<?php echo $CF_MOVED_THR_PTR_EXPIRY; ?>"></td></tr>
<tr bgcolor="#bff8ff"><td>Allow Email:<?php draw_help('ALLOW_EMAIL'); ?></td><td><?php draw_select('CF_ALLOW_EMAIL', "Yes\nNo", "Y\nN", $CF_ALLOW_EMAIL); ?></td></tr>
<tr bgcolor="#bff8ff"><td>Server Time Zone:<?php draw_help('SERVER_TZ'); ?></td><td><select name="CF_SERVER_TZ" style="font-size: xx-small;"><?php echo tmpl_draw_select_opt($tz_values, $tz_names, $CF_SERVER_TZ, '', ''); ?></select></td></tr>
<tr bgcolor="#bff8ff"><td>Forum Search Engine:<?php draw_help('FORUM_SEARCH'); ?></td><td><?php draw_select('CF_FORUM_SEARCH', "Yes\nNo", "Y\nN", $CF_FORUM_SEARCH); ?></td></tr>
<tr bgcolor="#bff8ff"><td>Member Search:<?php draw_help('MEMBER_SEARCH_ENABLED'); ?></td><td><?php draw_select('CF_MEMBER_SEARCH_ENABLED', "Yes\nNo", "Y\nN", $CF_MEMBER_SEARCH_ENABLED); ?></td></tr>
<tr bgcolor="#bff8ff"><td>Members Per Page:<?php draw_help('MEMBERS_PER_PAGE'); ?></td><td><input type="text" name="CF_MEMBERS_PER_PAGE" value="<?php echo $CF_MEMBERS_PER_PAGE; ?>"></td></tr>
<tr bgcolor="#bff8ff"><td>Anonymous Username:<?php draw_help('ANON_NICK'); ?></td><td><input type="text" name="CF_ANON_NICK" value="<?php echo $CF_ANON_NICK; ?>"></td></tr>
<tr bgcolor="#bff8ff"><td>Quick Pager Link Count:<?php draw_help('THREAD_MSG_PAGER'); ?></td><td><input type="text" name="CF_THREAD_MSG_PAGER" value="<?php echo $CF_THREAD_MSG_PAGER; ?>"></td></tr>
<tr bgcolor="#bff8ff"><td>General Pager Link Count:<?php draw_help('GENERAL_PAGER_COUNT'); ?></td><td><input type="text" name="CF_GENERAL_PAGER_COUNT" value="<?php echo $CF_GENERAL_PAGER_COUNT; ?>"></td></tr>
<tr bgcolor="#bff8ff"><td>Show Edited By:<?php draw_help('SHOW_EDITED_BY'); ?></td><td><?php draw_select('CF_SHOW_EDITED_BY', "Yes\nNo", "Y\nN", $CF_SHOW_EDITED_BY); ?></td></tr>
<tr bgcolor="#bff8ff"><td>Show Edited By Moderator:<?php draw_help('EDITED_BY_MOD'); ?></td><td><?php draw_select('CF_EDITED_BY_MOD', "Yes\nNo", "Y\nN", $CF_EDITED_BY_MOD); ?></td></tr>
<tr bgcolor="#bff8ff"><td>Edit Time Limit (minutes):<?php draw_help('EDIT_TIME_LIMIT'); ?></td><td><input type="text" name="CF_EDIT_TIME_LIMIT" value="<?php echo $CF_EDIT_TIME_LIMIT; ?>"></td></tr>
<tr bgcolor="#bff8ff"><td>Display IP Publicly:<?php draw_help('DISPLAY_IP'); ?></td><td><?php draw_select('CF_DISPLAY_IP', "Yes\nNo", "Y\nN", $CF_DISPLAY_IP); ?></td></tr>
<tr bgcolor="#bff8ff"><td>Max Image Count:<?php draw_help('MAX_IMAGE_COUNT'); ?></td><td><input type="text" name="CF_MAX_IMAGE_COUNT" value="<?php echo $CF_MAX_IMAGE_COUNT; ?>"></td></tr>
<tr bgcolor="#bff8ff"><td># Of Moderators To Show: <?php draw_help('SHOW_N_MODS'); ?></td><td><input type="text" name="CF_SHOW_N_MODS" value="<?php echo $CF_SHOW_N_MODS; ?>"></td></tr>
<tr bgcolor="#bff8ff"><td>Email Confirmation:<?php draw_help('EMAIL_CONFIRMATION'); ?></td><td><?php draw_select('CF_EMAIL_CONFIRMATION', "Yes\nNo", "Y\nN", $CF_EMAIL_CONFIRMATION); ?></td></tr>
<tr bgcolor="#bff8ff"><td>Public Stats:<?php draw_help('PUBLIC_STATS'); ?></td><td><?php draw_select('CF_PUBLIC_STATS', "Yes\nNo", "Y\nN", $CF_PUBLIC_STATS); ?></td></tr>
<tr bgcolor="#bff8ff"><td>Forum Info:<?php draw_help('FORUM_INFO'); ?></td><td><?php draw_select('CF_FORUM_INFO', "Yes\nNo", "Y\nN", $CF_FORUM_INFO); ?></td></tr>
<tr bgcolor="#bff8ff"><td colspan=2 align=left><input type="submit" name="btn_submit" value="Set"></td></tr>
</table>
<input type="hidden" name="form_posted" value="1">
</form>
<?php require('admclose.html'); ?>
