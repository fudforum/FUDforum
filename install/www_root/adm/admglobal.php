<?php
/***************************************************************************
* copyright            : (C) 2001-2004 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: admglobal.php,v 1.56 2004/01/20 22:08:56 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it 
* under the terms of the GNU General Public License as published by the 
* Free Software Foundation; either version 2 of the License, or 
* (at your option) any later version.
***************************************************************************/

	require('./GLOBALS.php');
	fud_use('adm.inc', true);
	fud_use('glob.inc', true);
	fud_use('widgets.inc', true);
	fud_use('draw_select_opt.inc');
	fud_use('users_reg.inc');
	fud_use('tz.inc');

function get_max_upload_size()
{
	$us = strtolower(ini_get('upload_max_filesize'));
	$size = (int) $us;
	if (strpos($us, 'm') !== false) {
		$size *= 1024 * 1024;
	} else if (strpos($us, 'k') !== false) {
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

	$help_ar = read_help();

	if (isset($_POST['form_posted'])) {
		for ($i = 1; $i < 10; $i++) {
			if (isset($GLOBALS['FUD_OPT_'.$i])) {
				$GLOBALS['NEW_FUD_OPT_'.$i] = 0;
			} else {
				break;
			}
		}

		/* make a list of the fields we need to change */
		foreach ($_POST as $k => $v) {
			if (!strncmp($k, 'CF_', 3)) {
				$k = substr($k, 3);
				if (!isset($GLOBALS[$k]) || $GLOBALS[$k] != $v) {
					$ch_list[$k] = is_numeric($v) ? (int) $v : $v;
				}
			} else if (!strncmp($k, 'FUD_OPT_', 8)) {
				$GLOBALS['NEW_' . substr($k, 0, 9)] |= (int) $v;
			}
		}

		/* restore PDF & RDF settings */
		$GLOBALS['NEW_FUD_OPT_2'] |= $FUD_OPT_2 & (16777216|33554432|67108864|134217728|268435456);

		for ($i = 1; $i < 10; $i++) {
			if (!isset($GLOBALS['FUD_OPT_'.$i])) {
				break;
			}

			if ($GLOBALS['FUD_OPT_'.$i] != $GLOBALS['NEW_FUD_OPT_'.$i]) {
				$ch_list['FUD_OPT_'.$i] = $GLOBALS['NEW_FUD_OPT_'.$i];
			}
		}

		if (isset($ch_list)) {
			change_global_settings($ch_list);

			/* some fields require us to make special changes */
			if (isset($ch_list['SHOW_N_MODS'])) {
				$GLOBALS['SHOW_N_MODS'] = $ch_list['SHOW_N_MODS'];
				fud_use('users_reg.inc');
				rebuildmodlist();
			}

			/* Handle disabling of aliases */
			if (($FUD_OPT_2 ^ $NEW_FUD_OPT_2) & 128 && !($NEW_FUD_OPT_2 & 128)) {
				q('UPDATE '.$DBHOST_TBL_PREFIX.'users SET alias=login');
				rebuildmodlist();
			}

			/* Topic/Message tree view disabling code */
			$o = 0;
			if (($FUD_OPT_2 ^ $NEW_FUD_OPT_2) & 512 && !($NEW_FUD_OPT_2 & 512)) {
				$o |= 128;
			}
			if (($FUD_OPT_3 ^ $NEW_FUD_OPT_3) & 2 && !($NEW_FUD_OPT_3 & 2)) {
				$o |= 256;
			}
			if ($o) {
				q('UPDATE '.$DBHOST_TBL_PREFIX.'users SET users_opt=users_opt|'.$o.' WHERE (users_opt & '.$o.')=0');
			}

			$q_data = array();
			if (isset($ch_list['POSTS_PER_PAGE'])) {
				$q_data[] = 'posts_ppg='.(int)$ch_list['POSTS_PER_PAGE'];
			}
			if (isset($ch_list['ANON_NICK'])) {
				$q_data[] = "login='".addslashes($ch_list['ANON_NICK'])."', alias='".addslashes(htmlspecialchars($ch_list['ANON_NICK']))."', name='".htmlspecialchars(addslashes($ch_list['ANON_NICK']))."'";
			}
			if (isset($ch_list['SERVER_TZ'])) {
				$q_data[] = "time_zone='".addslashes($ch_list['SERVER_TZ'])."'";
			}
			if (($FUD_OPT_2 ^ $NEW_FUD_OPT_2) & (4|8)) {
				/* only allow threaded topic view if it is selected & it's enabled */
				$opt  = $NEW_FUD_OPT_2 & 4 && $NEW_FUD_OPT_2 & 512 ? 128 : 0;
				$opt |= $NEW_FUD_OPT_2 & 8 || $NEW_FUD_OPT_3 & 2 ? 256 : 0;
				$q_data[] = 'users_opt=(users_opt & ~ 384) | '.$opt;
			}
			if ($q_data) {
				q('UPDATE '.$DBHOST_TBL_PREFIX.'users SET '.implode(',', $q_data).' WHERE id=1');
			}

			/* put the settings 'live' so they can be seen on the form */
			foreach ($ch_list as $k => $v) {
				$GLOBALS[$k] = $v;
			}
		}
	}
	require($WWW_ROOT_DISK . 'adm/admpanel.php');
?>
<h2>Global Configuration</h2>
<table class="datatable solidtable">
<form method="post" action="admglobal.php">
<?php
	echo _hs;
	print_reg_field('Forum Title', 'FORUM_TITLE');
	print_bit_field('Forum Enabled', 'FORUM_ENABLED');
	print_reg_field('Reason for Disabling', 'DISABLED_REASON');
	print_bit_field('Allow Registration', 'ALLOW_REGISTRATION');
?>
<tr class="fieldaction"><td colspan=2 align=left><input type="submit" name="btn_submit" value="Set"></td></tr>

<tr class="fieldtopic"><td colspan=2><br><b>Global</b></td></tr>
<?php
	print_reg_field('WWW Root', 'WWW_ROOT');
	print_reg_field('WWW Root (disk path)', 'WWW_ROOT_DISK');
	print_reg_field('Data Root', 'DATA_DIR');
?>
<tr class="fieldaction"><td colspan=2 align=left><input type="submit" name="btn_submit" value="Set"></td></tr>

<tr class="fieldtopic"><td colspan=2><br><b>Database Settings</b> </td></tr>
<?php
	print_reg_field('Database Server', 'DBHOST');
	print_reg_field('Database Login', 'DBHOST_USER');
	print_reg_field('Database Password', 'DBHOST_PASSWORD', 0, 1);
	print_reg_field('Database Name', 'DBHOST_DBNAME');
	print_bit_field('Use Persistent Connections', 'DBHOST_PERSIST');
?>
<tr class="fieldaction"><td colspan=2 align=left><input type="submit" name="btn_submit" value="Set"></td></tr>

<tr class="fieldtopic"><td colspan=2><br><b>Private Messaging</b> </td></tr>
<?php
	print_bit_field('Allow Private Messaging', 'PM_ENABLED');
	print_reg_field('File Attachments in Private Messages', 'PRIVATE_ATTACHMENTS', 1);
	print_reg_field('Maximum Attachment Size (bytes)', 'PRIVATE_ATTACH_SIZE', 1);
	print_bit_field('Allow Smilies', 'PRIVATE_MSG_SMILEY');
	print_bit_field('Allow Images (fudcode only)', 'PRIVATE_IMAGES');
	print_bit_field('Tag Style', 'PRIVATE_TAGS');
	print_reg_field('Maximum Private Messages Folder Size', 'MAX_PMSG_FLDR_SIZE', 1);
?>
<tr class="fieldaction"><td colspan=2 align=left><input type="submit" name="btn_submit" value="Set"></td></tr>

<tr class="fieldtopic"><td colspan=2><br><b>Cookie & Session Settings</b> </td></tr>
<?php
	print_reg_field('Cookie Path', 'COOKIE_PATH');
	print_reg_field('Cookie Domain', 'COOKIE_DOMAIN');
	print_reg_field('Cookie Name', 'COOKIE_NAME');
	print_reg_field('Cookie Timeout', 'COOKIE_TIMEOUT', 1);
	print_reg_field('Session Timeout', 'SESSION_TIMEOUT', 1);
	print_bit_field('Enable URL sessions', 'SESSION_USE_URL');
	print_bit_field('Use Session Cookies', 'SESSION_COOKIES');
	print_bit_field('Session Referrer Check', 'ENABLE_REFERRER_CHECK');
	print_bit_field('Session IP Validation', 'SESSION_IP_CHECK');
?>
<tr class="fieldaction"><td colspan=2 align=left><input type="submit" name="btn_submit" value="Set"></td></tr>

<tr class="fieldtopic"><td colspan=2><br><b>Custom Avatar Settings</b> </td></tr>
<?php
	print_bit_field('Avatar Approval', 'CUSTOM_AVATAR_APPOVAL');
	print_bit_field('Allow Flash (swf) avatars', 'CUSTOM_AVATAR_ALLOW_SWF');
	print_bit_field('Custom Avatars', 'CUSTOM_AVATARS');
	print_reg_field('Custom Avatar Max Size (bytes)', 'CUSTOM_AVATAR_MAX_SIZE', 1);
	print_reg_field('Custom Avatar Max Dimentions', 'CUSTOM_AVATAR_MAX_DIM');
?>
<tr class="fieldaction"><td colspan=2 align=left><input type="submit" name="btn_submit" value="Set"></td></tr>

<tr class="fieldtopic"><td colspan=2><br><b>Signature Settings</b> </td></tr>
<?php
	print_bit_field('Allow Signatures', 'ALLOW_SIGS');
	print_bit_field('Tag Style', 'FORUM_CODE_SIG');
	print_bit_field('Allow Smilies', 'FORUM_SML_SIG');
	print_bit_field('Allow Images (fudcode only)', 'FORUM_IMG_SIG');
	print_reg_field('Maximum number of images', 'FORUM_IMG_CNT_SIG', 1);
	print_reg_field('Maximum signature length', 'FORUM_SIG_ML', 1);
?>
<tr class="fieldaction"><td colspan=2 align=left><input type="submit" name="btn_submit" value="Set"></td></tr>

<tr class="fieldtopic"><td colspan=2><br><b>Spell Checker</b> </td></tr>
<?php
	if (function_exists('pspell_new_config')) {
		$pspell_support = '<font color="red">is enabled.</font>';
	} else {
		$pspell_support = '<font color="red">is disabled.<br>Please ask your administrator to enable pspell support.</font>';
		$GLOBALS['CF_SPELL_CHECK_ENABLED'] = 0;
	}
	print_bit_field('Enable Spell Checker', 'SPELL_CHECK_ENABLED');
?>
<tr class="fieldaction"><td colspan=2 align=left><input type="submit" name="btn_submit" value="Set"></td></tr>

<tr class="fieldtopic"><td colspan=2><br><b>Email Settings</b> </td></tr>
<?php
	print_bit_field('Allow Email', 'ALLOW_EMAIL');
	print_bit_field('Use SMTP To Send Email', 'USE_SMTP');
	print_reg_field('SMTP Server', 'FUD_SMTP_SERVER');
	print_reg_field('SMTP Server Timeout', 'FUD_SMTP_TIMEOUT', 1);
	print_reg_field('SMTP Server Login', 'FUD_SMTP_LOGIN');
	print_reg_field('SMTP Server Password', 'FUD_SMTP_PASS');
	print_bit_field('Email Confirmation', 'EMAIL_CONFIRMATION');
	print_reg_field('Administrator Email', 'ADMIN_EMAIL');
	print_reg_field('Notify From', 'NOTIFY_FROM');
	print_bit_field('Notify W/Body', 'NOTIFY_WITH_BODY');
?>
<tr class="fieldaction"><td colspan=2 align=left><input type="submit" name="btn_submit" value="Set"></td></tr>

<tr class="fieldtopic"><td colspan=2><br><b>General Settings</b> </td></tr>
<?php
	print_bit_field('New Account Moderation', 'MODERATE_USER_REGS');
	print_bit_field('New Account Notification', 'NEW_ACCOUNT_NOTIFY');
	print_bit_field('Public Host Resolving', 'PUBLIC_RESOLVE_HOST');

	print_bit_field('Logged In Users List Enabled', 'LOGEDIN_LIST');
	print_reg_field('Logged In Users List Timeout (minutes)', 'LOGEDIN_TIMEOUT', 1);
	print_reg_field('Maximum number of logged in users to show', 'MAX_LOGGEDIN_USERS', 1);
	print_bit_field('Allow Action List', 'ACTION_LIST_ENABLED');
	print_bit_field('Online/Offline Status Indicator', 'ONLINE_OFFLINE_STATUS');

	print_bit_field('COPPA', 'COPPA');
	print_reg_field('Max Smilies Shown', 'MAX_SMILIES_SHOWN', 1);

	print_reg_field('Maximum Shown Login Length', 'MAX_LOGIN_SHOW', 1);
	print_reg_field('Maximum Shown Location Length', 'MAX_LOCATION_SHOW', 1);

	print_reg_field('Posts Per Page', 'POSTS_PER_PAGE', 1);
	print_reg_field('Topics Per Page', 'THREADS_PER_PAGE', 1);
	print_reg_field('Message icons per row', 'POST_ICONS_PER_ROW', 1);

	print_bit_field('Allow Tree View of Thread Listing', 'TREE_THREADS_ENABLE');
	print_bit_field('Disable Tree View of Message Listing', 'DISABLE_TREE_MSG');
	print_bit_field('Default Topic View', 'DEFAULT_THREAD_VIEW');
	print_reg_field('Maximum Depth of Thread Listing (tree view)', 'TREE_THREADS_MAX_DEPTH', 1);
	print_reg_field('Maximum Shown Subject Length (tree view)', 'TREE_THREADS_MAX_SUBJ_LEN', 1);
	print_reg_field('Polls Per Page', 'POLLS_PER_PAGE', 1);

	print_reg_field('Word Wrap', 'WORD_WRAP', 1);
	print_reg_field('Unconfirmed User Expiry', 'UNCONF_USER_EXPIRY', 1);
	print_reg_field('Flood Trigger (seconds)', 'FLOOD_CHECK_TIME', 1);
	print_reg_field('Moved Topic Pointer Expiry', 'MOVED_THR_PTR_EXPIRY', 1);
	print_bit_field('Use Aliases', 'USE_ALIASES');
	print_bit_field('Multiple Host Login', 'MULTI_HOST_LOGIN');
	print_bit_field('Bust&#39;A&#39;Punk', 'BUST_A_PUNK');
?>
<tr class="field"><td colspan=2>Server Time Zone: <font size="-1"> <?php echo $help_ar['SERVER_TZ'][0]; ?></font><br /><select name="CF_SERVER_TZ" style="font-size: xx-small;"><?php echo tmpl_draw_select_opt($tz_values, $tz_names, $SERVER_TZ, '', ''); ?></select></td></tr>
<?php
	print_bit_field('Forum Search Engine', 'FORUM_SEARCH');
	print_reg_field('Search results cache', 'SEARCH_CACHE_EXPIRY', 1);
	print_bit_field('Member Search', 'MEMBER_SEARCH_ENABLED');
	print_reg_field('Members Per Page', 'MEMBERS_PER_PAGE', 1);
	print_reg_field('Anonymous Username', 'ANON_NICK');
	print_reg_field('Quick Pager Link Count', 'THREAD_MSG_PAGER', 1);
	print_reg_field('General Pager Link Count', 'GENERAL_PAGER_COUNT', 1);
	print_bit_field('Show Edited By', 'SHOW_EDITED_BY');
	print_bit_field('Show Edited By Moderator', 'EDITED_BY_MOD');
	print_reg_field('Edit Time Limit (minutes)', 'EDIT_TIME_LIMIT', 1);
	print_bit_field('Display IP Publicly', 'DISPLAY_IP');
	print_reg_field('Max Image Count', 'MAX_IMAGE_COUNT', 1);
	print_reg_field('Number Of Moderators To Show', 'SHOW_N_MODS', 1);
	print_bit_field('Public Stats', 'PUBLIC_STATS');
	print_bit_field('Forum Info', 'FORUM_INFO');
	print_reg_field('Forum Info Cache Age', 'STATS_CACHE_AGE', 1);
	print_reg_field('Registration Time Limit', 'REG_TIME_LIMIT', 1);
	print_bit_field('Enable Affero<br><a href="http://www.affero.net/bbsteps.html" target=_blank>Click here for details</a>', 'ENABLE_AFFERO');
	print_bit_field('Topic Rating', 'ENABLE_THREAD_RATING');
	print_bit_field('Track referrals', 'TRACK_REFERRALS');
	print_bit_field('Profile Image', 'ALLOW_PROFILE_IMAGE');
	print_bit_field('Moderator Notification', 'MODERATED_POST_NOTIFY');
	print_reg_field('Max History', 'MNAV_MAX_DATE', 1);
	print_reg_field('Max Message Preview Length', 'MNAV_MAX_LEN', 1);
	print_bit_field('Show PDF Generation Link', 'SHOW_PDF_LINK');
	print_bit_field('Show Syndication Link', 'SHOW_XML_LINK');
	print_bit_field('Attachment Referrer Check', 'DWLND_REF_CHK');
	print_bit_field('Show Reply Reference', 'SHOW_REPL_LNK');
	print_bit_field('Obfuscate e-mails in NNTP posts', 'NNTP_OBFUSCATE_EMAIL');

	if (function_exists('ob_gzhandler')) {
		print_bit_field('Use PHP compression', 'PHP_COMPRESSION_ENABLE');
		print_reg_field('PHP compression level', 'PHP_COMPRESSION_LEVEL', 1);
	}
	print_bit_field('Use PATH_INFO style URLs<br><a href="'.$WWW_ROOT.'index.php/a/b/c" target="_blank">Test Link</a>', 'USE_PATH_INFO');
?>
<tr class="fieldaction"><td colspan=2 align=left><input type="submit" name="btn_submit" value="Set"></td></tr>
</table>
<input type="hidden" name="form_posted" value="1">
</form>
<?php require($WWW_ROOT_DISK . 'adm/admclose.html'); ?>
