<?php
/**
* copyright            : (C) 2001-2021 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id$
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; version 2 of the License.
**/

/** Get the PHP upload_max_filesize setting. */
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

/* main */
	require('./GLOBALS.php');
	fud_use('adm.inc', true);
	fud_use('glob.inc', true);
	fud_use('widgets.inc', true);
	fud_use('draw_select_opt.inc');
	fud_use('users_reg.inc');

	require($WWW_ROOT_DISK .'adm/header.php');

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
			if (isset($GLOBALS['FUD_OPT_'. $i])) {
				$GLOBALS['NEW_FUD_OPT_'. $i] = 0;
			} else {
				break;
			}
		}

		/* Make a list of the fields we need to change. */
		$sk = array('DBHOST_USER', 'DBHOST_PASSWORD', 'DBHOST_DBNAME');
		foreach ($_POST as $k => $v) {
			if (!strncmp($k, 'CF_', 3)) {
				$k = substr($k, 3);
				if (!isset($GLOBALS[$k]) || $GLOBALS[$k] != $v) {
					$ch_list[$k] = is_numeric($v) && !in_array($k, $sk) ? (int)$v : $v;
				}
			} else if (!strncmp($k, 'FUD_OPT_', 8)) {
				$GLOBALS['NEW_'. substr($k, 0, 9)] |= (int) $v;
			}
		}

		/* Restore PDF & XML Feed settings. */
		$GLOBALS['NEW_FUD_OPT_2'] |= $FUD_OPT_2 & (16777216|33554432|67108864|134217728|268435456|8388608);
		/* Restore plugin settings. */
		$GLOBALS['NEW_FUD_OPT_3'] |= $FUD_OPT_3 & (4194304);
		/* Restore calendar settings. */
		$GLOBALS['NEW_FUD_OPT_3'] |= $FUD_OPT_3 & (134217728);
		$GLOBALS['NEW_FUD_OPT_3'] |= $FUD_OPT_3 & (268435456);
		/* Restore GeoLocation settings. */
		$GLOBALS['NEW_FUD_OPT_3'] |= $FUD_OPT_3 & (524288|2097152);
		/* Restore page list settings. */
		$GLOBALS['NEW_FUD_OPT_4'] |= $FUD_OPT_4 & (8);
		/* Restore blog settings. */
		$GLOBALS['NEW_FUD_OPT_4'] |= $FUD_OPT_4 & (16);

		/* Disable apache_setenv() is no such function. */
		if ($GLOBALS['NEW_FUD_OPT_3'] & 512 && !function_exists('apache_setenv')) {
			$GLOBALS['NEW_FUD_OPT_3'] ^= 512;
		}
		
		/* Check if we can use the spell checker. */
		if ($NEW_FUD_OPT_1 & 2097152 && !extension_loaded('enchant')) {
			echo errorify('PHP\'s enchant module is currently disabled.');
			$NEW_FUD_OPT_1 ^= 2097152;
		}
		
		/* Check if we can use TEMP tables. */
		if ($NEW_FUD_OPT_3 & 4096) {
			try {
				q('CREATE TEMPORARY TABLE '. $DBHOST_TBL_PREFIX .'temp_test (val INT)');
			} catch(Exception $e) {
				pf(errorify('Unable to create temporary tables. Feature cannot be enabled on your installation.'));
				$GLOBALS['NEW_FUD_OPT_3'] ^= 4096;
			}
		}

		/* Further instructions if WWW_ROOT is changed. */
		if ($GLOBALS['WWW_ROOT'] != $_POST['CF_WWW_ROOT']) {
			pf('Remember to rebuild your <a href="admthemes.php?'. __adm_rsid .'">themes</a> if you change the Forum URL or direcories.');
		}

		/* Further instructions if PATH_INFO is enabled. */
		if (($FUD_OPT_2 & 32768) == 0 && ($NEW_FUD_OPT_2 & 32768) == 32768) {
			pf('PATH_INFO is now enabled. Next step is to enable a <a href="admthemes.php?'. __adm_rsid .'">PATH_INFO theme</a>.');
		}

		for ($i = 1; $i < 10; $i++) {
			if (!isset($GLOBALS['FUD_OPT_'. $i])) {
				break;
			}

			if ($GLOBALS['FUD_OPT_'. $i] != $GLOBALS['NEW_FUD_OPT_'. $i]) {
				$ch_list['FUD_OPT_'. $i] = $GLOBALS['NEW_FUD_OPT_'. $i];
			}
		}

		if (isset($ch_list)) {
			change_global_settings($ch_list);

			/* Some fields require us to make special changes. */
			if (isset($ch_list['SHOW_N_MODS'])) {
				$GLOBALS['SHOW_N_MODS'] = $ch_list['SHOW_N_MODS'];
				fud_use('users_reg.inc');
				rebuildmodlist();
			}

			/* Handle disabling of aliases. */
			if (($FUD_OPT_2 ^ $NEW_FUD_OPT_2) & 128 && !($NEW_FUD_OPT_2 & 128)) {
				q('UPDATE '. $DBHOST_TBL_PREFIX .'users SET alias=login');
				rebuildmodlist();
			}

			/* Topic/Message tree view disabling code. */
			$o = 0;
			if (!($NEW_FUD_OPT_2 & 512)) {
				$o |= 128;
			}
			if ($NEW_FUD_OPT_3 & 2) {
				$o |= 256;
			}

			if ($o) {
				q('UPDATE '. $DBHOST_TBL_PREFIX .'users SET users_opt='. q_bitor('users_opt', $o) .' WHERE '. q_bitand('users_opt', $o) .'=0');
			}

			/* Settings that must also be applied to the Anonymous user. */
			$q_data = array();
			if (isset($ch_list['POSTS_PER_PAGE'])) {
				$q_data[] = 'posts_ppg='. (int)$ch_list['POSTS_PER_PAGE'];
			}
			if (isset($ch_list['ANON_NICK'])) {
				$q_data[] = 'login='. _esc($ch_list['ANON_NICK']) .', alias='. _esc(htmlspecialchars($ch_list['ANON_NICK'])) .', name='. htmlspecialchars(_esc($ch_list['ANON_NICK']));
			}
			if (isset($ch_list['SERVER_TZ'])) {
				$q_data[] = 'time_zone='. _esc($ch_list['SERVER_TZ']);
			}
			if (!($NEW_FUD_OPT_2 & 12)) {
				/* Only allow threaded topic view if it is selected & it's enabled. */
				$opt  = $NEW_FUD_OPT_2 & 512 ? 0 : 128;
				$opt |= $NEW_FUD_OPT_3 & 2 ? 256 : 0;
				$q_data[] = 'users_opt=(users_opt | '. $opt .')';
			}
			if ($q_data) {
				q('UPDATE '. $DBHOST_TBL_PREFIX .'users SET '. implode(',', $q_data) .' WHERE id=1');
			}

			/* Put the settings 'live' so they can be seen on the form. */
			foreach ($ch_list as $k => $v) {
				$GLOBALS[$k] = $v;
			}
			pf(successify('Forum settings successfully updated.'));
		}
	}
?>
<h2>Global Settings Manager</h2>
<a name="top"></a>
<div class="tutor" style="font-size: small;">[
<a href="#1" class="seclink">Primary Forum Options</a> |
<a href="#2" class="seclink">URL &amp; directories</a> |
<a href="#3" class="seclink">Database</a> |
<a href="#4" class="seclink">Interface Look &amp; Feel</a> |
<a href="#5" class="seclink">Front Page</a> |
<a href="#6" class="seclink">Topics</a> |
<a href="#7" class="seclink">Messages</a> |
<a href="#8" class="seclink">PM</a> |
<a href="#9" class="seclink">User Accounts</a> |
<a href="#10" class="seclink">Cookie &amp; Session</a> |
<a href="#11" class="seclink">Avatar</a> |
<a href="#12" class="seclink">Signature</a> |
<a href="#13" class="seclink">Search</a> |
<a href="#14" class="seclink">Spell Checker</a> |
<a href="#15" class="seclink">E-mail</a> |
<a href="#16" class="seclink">General</a>
]</div>
<br />

<script>
function show_section(sec) {
	jQuery("fieldset.section").hide();
	jQuery("."+sec).slideDown("slow").find(":text:visible:enabled:first").focus();
	jQuery("#settings-form").attr("action", "admglobal.php#"+sec);
}

jQuery(document).ready(function() {
	jQuery(".seclink").click(function () {	// Click on section link.
		sec = jQuery(this).attr('href').substr(1);
		show_section(sec);
	}); 
	if (window.location.hash) {	// Settings changed.
		sec = window.location.hash.substr(1);
		show_section(sec);
	}
});
</script>

<form id="settings-form" method="post" action="admglobal.php" autocomplete="off">
<?php echo _hs ?>

<fieldset class="section 1"><legend>Primary Forum Options</legend>
<table class="datatable solidtable"><col width="50%" /><col width="50%" />
<?php
	print_reg_field('Forum Title', 'FORUM_TITLE');
	print_txt_field('Forum Description', 'FORUM_DESCR');
	$msg = ($GLOBALS['FUD_OPT_1'] & 1) ? '' : '<font color="red">NOTE: </font>';
	print_bit_field($msg .'Forum Enabled', 'FORUM_ENABLED');
	print_txt_field('Reason for Disabling', 'DISABLED_REASON');
	$msg = ($GLOBALS['FUD_OPT_3'] & 1073741824) ? '<font color="red">NOTE: </font>' : '';
	print_bit_field($msg .'Debug Forum', 'FORUM_DEBUG');
?>
<tr class="fieldaction"><td align="left"><input type="submit" name="btn_submit" value="Set" /></td><td align="right">[ <a href="#top">top</a> ]</td></tr>
</table>
</fieldset>

<fieldset class="section 2"><legend>URL &amp; directories</legend>
<table class="datatable solidtable"><col width="50%" /><col width="50%" />
<?php
	print_reg_field('WWW Root', 'WWW_ROOT');
	print_reg_field('WWW Root (disk path)', 'WWW_ROOT_DISK');
	print_reg_field('Data Root', 'DATA_DIR');
	print_bit_field('Use PATH_INFO style URLs<br /><small><a href="'. $WWW_ROOT .'index.php/a/b/c" target="_blank">Test Link</a></small>', 'USE_PATH_INFO');
?>
<tr class="fieldaction"><td align="left"><input type="submit" name="btn_submit" value="Set" /></td><td align="right">[ <a href="#top">top</a> ]</td></tr>
</table>
</fieldset>

<fieldset class="section 3"><legend>Database Settings</legend>
<table class="datatable solidtable"><col width="50%" /><col width="50%" />
<?php
	print_reg_field('Database Server', 'DBHOST');
	if (__dbtype__ == 'mysql') {
		// Slave support is only implemented for MySQL drivers.
		print_reg_field('Database Slave Server', 'DBHOST_SLAVE_HOST');
	}
	print_reg_field('Database Login', 'DBHOST_USER');
	print_reg_field('Database Password', 'DBHOST_PASSWORD', 0, 1);
	print_reg_field('Database Name', 'DBHOST_DBNAME');
	print_bit_field('Use Persistent Connections', 'DBHOST_PERSIST');
	print_bit_field('Use Temporary Tables', 'USE_TEMP_TABLES');
	print_bit_field('Use Database for message storage', 'DB_MESSAGE_STORAGE');
	?>
<tr class="fieldaction"><td align="left"><input type="submit" name="btn_submit" value="Set" /></td><td align="right">[ <a href="#top">top</a> ]</td></tr>
</table>
</fieldset>

<fieldset class="section 4"><legend>Interface Look &amp; Feel</legend>
<table class="datatable solidtable"><col width="50%" /><col width="50%" />
<?php
	print_reg_field('General Pager Link Count', 'GENERAL_PAGER_COUNT', 1);
	print_reg_field('Quick Pager Link Count', 'THREAD_MSG_PAGER', 1);
	print_bit_field('Public Stats', 'PUBLIC_STATS');
	print_bit_field('Show PDF Generation Link', 'SHOW_PDF_LINK');
	print_bit_field('Show Syndication Link', 'SHOW_XML_LINK');
	print_bit_field('Online/Offline Status Indicator', 'ONLINE_OFFLINE_STATUS');
	print_bit_field('Disable AutoComplete', 'DISABLE_AUTOCOMPLETE');
?>
<tr class="fieldaction"><td align="left"><input type="submit" name="btn_submit" value="Set" /></td><td align="right">[ <a href="#top">top</a> ]</td></tr>
</table>
</fieldset>

<fieldset class="section 5"><legend>Front Page Settings</legend>
<table class="datatable solidtable"><col width="50%" /><col width="50%" />
<?php
	print_reg_field('Number Of Moderators To Show', 'SHOW_N_MODS', 1);
	print_bit_field('Forum Info', 'FORUM_INFO');
	print_reg_field('Forum Info Cache Age', 'STATS_CACHE_AGE', 1);
	print_bit_field('Logged In Users List Enabled', 'LOGEDIN_LIST');
	print_reg_field('Maximum number of logged in users to show', 'MAX_LOGGEDIN_USERS', 1);
	print_reg_field('Logged In Users List Timeout (minutes)', 'LOGEDIN_TIMEOUT', 1);
	print_bit_field('Allow Action List', 'ACTION_LIST_ENABLED');
?>
<tr class="fieldaction"><td align="left"><input type="submit" name="btn_submit" value="Set" /></td><td align="right">[ <a href="#top">top</a> ]</td></tr>
</table>
</fieldset>

<fieldset class="section 6"><legend>Topic Settings</legend>
<table class="datatable solidtable"><col width="50%" /><col width="50%" />
<?php
	print_reg_field('Topics Per Page', 'THREADS_PER_PAGE', 1);
	print_reg_field('Moved Topic Pointer Expiry', 'MOVED_THR_PTR_EXPIRY', 1);
	print_bit_field('Allow Tree View of Thread Listing', 'TREE_THREADS_ENABLE');
	print_bit_field('Default Topic View', 'DEFAULT_THREAD_VIEW');
	print_reg_field('Maximum Depth of Thread Listing (tree view)', 'TREE_THREADS_MAX_DEPTH', 1);
	print_reg_field('Maximum Shown Subject Length (tree view)', 'TREE_THREADS_MAX_SUBJ_LEN', 1);
	print_bit_field('Check for Duplicates', 'THREAD_DUP_CHECK');
	print_bit_field('Topic Rating', 'ENABLE_THREAD_RATING');
?>
<tr class="fieldaction"><td align="left"><input type="submit" name="btn_submit" value="Set" /></td><td align="right">[ <a href="#top">top</a> ]</td></tr>
</table>
</fieldset>

<fieldset class="section 7"><legend>Message Settings</legend>
<table class="datatable solidtable"><col width="50%" /><col width="50%" />
<?php
	print_reg_field('Messages Per Page', 'POSTS_PER_PAGE', 1);
	print_bit_field('Disable Tree View of Message Listing', 'DISABLE_TREE_MSG');
	print_bit_field('Show Reply Reference', 'SHOW_REPL_LNK');
	print_bit_field('Show Edited By', 'SHOW_EDITED_BY');
	print_bit_field('Show Edited By Moderator', 'EDITED_BY_MOD');
	print_bit_field('Allow editing after reply', 'EDIT_AFTER_REPLY');	
	print_reg_field('Edit Time Limit (minutes)', 'EDIT_TIME_LIMIT', 1);
	print_bit_field('Display IP Publicly', 'DISPLAY_IP');
	print_bit_field('Quick Reply', 'QUICK_REPLY');
	print_reg_field('Flood Trigger (seconds)', 'FLOOD_CHECK_TIME', 1);
	print_reg_field('Minimum Message Length', 'POST_MIN_LEN', 1);
	print_reg_field('Moderate user\'s first N messages', 'MOD_FIRST_N_POSTS', 1);
	print_reg_field('Messages Before Allowing Links', 'POSTS_BEFORE_LINKS', 1);
	print_reg_field('Word Wrap', 'WORD_WRAP', 1);
	print_reg_field('Max Image Count', 'MAX_IMAGE_COUNT', 1);
	print_reg_field('Max Smilies Shown', 'MAX_SMILIES_SHOWN', 1);
	print_reg_field('Message icons per row', 'POST_ICONS_PER_ROW', 1);
?>
<tr class="fieldaction"><td align="left"><input type="submit" name="btn_submit" value="Set" /></td><td align="right">[ <a href="#top">top</a> ]</td></tr>
</table>
</fieldset>

<fieldset class="section 8"><legend>Private Messaging</legend>
<table class="datatable solidtable"><col width="50%" /><col width="50%" />
<?php
	print_bit_field('Allow Private Messaging', 'PM_ENABLED');
	print_reg_field('File Attachments in Private Messages', 'PRIVATE_ATTACHMENTS', 1);
	print_reg_field('Maximum Attachment Size (bytes)', 'PRIVATE_ATTACH_SIZE', 1);
	print_bit_field('Allow Smilies', 'PRIVATE_MSG_SMILEY');
	print_bit_field('Allow Images (BBcode only)', 'PRIVATE_IMAGES');
	print_bit_field('Tag Style', 'PRIVATE_TAGS');
	print_reg_field('Maximum Private Messages Folder Size', 'MAX_PMSG_FLDR_SIZE', 1);
	print_reg_field('Maximum Private Messages Folder Size (for moderators)', 'MAX_PMSG_FLDR_SIZE_PM', 1);
	print_reg_field('Maximum Private Messages Folder Size (for administrators)', 'MAX_PMSG_FLDR_SIZE_AD', 1);
?>
<tr class="fieldaction"><td align="left"><input type="submit" name="btn_submit" value="Set" /></td><td align="right">[ <a href="#top">top</a> ]</td></tr>
</table>
</fieldset>

<fieldset class="section 9"><legend>User Account Settings</legend>
<table class="datatable solidtable"><col width="50%" /><col width="50%" />
<?php
	print_bit_field('Allow Registration', 'ALLOW_REGISTRATION');
	print_bit_field('Allow Userid Changes', 'ALLOW_USERID_CHANGES');
	print_bit_field('Allow Password Resets', 'ALLOW_PASSWORD_RESET');
	print_bit_field('Use Aliases', 'USE_ALIASES');
	print_reg_field('Registration Time Limit', 'REG_TIME_LIMIT', 1);
	print_reg_field('Unconfirmed User Expiry', 'UNCONF_USER_EXPIRY', 1);
	print_bit_field('COPPA', 'COPPA');
	print_bit_field('Karma', 'KARMA');
	print_reg_field('Maximum Shown Login Length', 'MAX_LOGIN_SHOW', 1);
	print_reg_field('Maximum Shown Location Length', 'MAX_LOCATION_SHOW', 1);
	print_bit_field('Hide user profiles', 'HIDE_PROFILES_FROM_ANON');
	print_bit_field('Profile Image', 'ALLOW_PROFILE_IMAGE');
	print_bit_field('New Account Moderation', 'MODERATE_USER_REGS');
	print_bit_field('New Account Notification', 'NEW_ACCOUNT_NOTIFY');
	print_reg_field('Anonymous Username', 'ANON_NICK');
	print_bit_field('Disable caching for anonymous users', 'DISABLE_ANON_CACHE');
	print_bit_field('Disable actions list for anonymous users', 'NO_ANON_ACTION_LIST');
	print_bit_field('Disable who\'s online for anonymous users', 'NO_ANON_WHO_ONLINE');
?>
<tr class="fieldaction"><td align="left"><input type="submit" name="btn_submit" value="Set" /></td><td align="right">[ <a href="#top">top</a> ]</td></tr>
</table>
</fieldset>

<fieldset class="section 10"><legend>Cookie &amp; Session Settings</legend>
<table class="datatable solidtable"><col width="50%" /><col width="50%" />
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
	print_reg_field('Time between login attempts', 'MIN_TIME_BETWEEN_LOGIN', 1);
	print_bit_field('Multiple Host Login', 'MULTI_HOST_LOGIN');
?>
<tr class="fieldaction"><td align="left"><input type="submit" name="btn_submit" value="Set" /></td><td align="right">[ <a href="#top">top</a> ]</td></tr>
</table>
</fieldset>

<fieldset class="section 11"><legend>Avatar Settings</legend>
<table class="datatable solidtable"><col width="50%" /><col width="50%" />
<?php
	print_bit_field('Avatar Approval', 'CUSTOM_AVATAR_APPROVAL');
	print_bit_field('Allow Flash (swf) avatars', 'CUSTOM_AVATAR_ALLOW_SWF');
	print_bit_field('Custom Avatars', 'CUSTOM_AVATARS');
	print_reg_field('Custom Avatar Max Size (bytes)', 'CUSTOM_AVATAR_MAX_SIZE', 1);
	print_reg_field('Custom Avatar Max Dimensions', 'CUSTOM_AVATAR_MAX_DIM');
?>
<tr class="fieldaction"><td align="left"><input type="submit" name="btn_submit" value="Set" /></td><td align="right">[ <a href="#top">top</a> ]</td></tr>
</table>
</fieldset>

<fieldset class="section 12"><legend>Signature Settings</legend>
<table class="datatable solidtable"><col width="50%" /><col width="50%" />
<?php
	print_bit_field('Allow Signatures', 'ALLOW_SIGS');
	print_bit_field('Tag Style', 'FORUM_CODE_SIG');
	print_bit_field('Allow Smilies', 'FORUM_SML_SIG');
	print_bit_field('Allow Images (BBcode only)', 'FORUM_IMG_SIG');
	print_reg_field('Maximum number of images', 'FORUM_IMG_CNT_SIG', 1);
	print_reg_field('Maximum signature length', 'FORUM_SIG_ML', 1);
?>
<tr class="fieldaction"><td align="left"><input type="submit" name="btn_submit" value="Set" /></td><td align="right">[ <a href="#top">top</a> ]</td></tr>
</table>
</fieldset>

<fieldset class="section 13"><legend>Search Settings</legend>
<table class="datatable solidtable"><col width="50%" /><col width="50%" />
<?php
	print_bit_field('Forum Search Engine', 'FORUM_SEARCH');
	print_reg_field('Search results cache', 'SEARCH_CACHE_EXPIRY', 1);
	print_bit_field('Member Search', 'MEMBER_SEARCH_ENABLED');
	print_reg_field('Members Per Page', 'MEMBERS_PER_PAGE', 1);
?>
<tr class="fieldaction"><td align="left"><input type="submit" name="btn_submit" value="Set" /></td><td align="right">[ <a href="#top">top</a> ]</td></tr>
</table>
</fieldset>

<fieldset class="section 14"><legend>Spell Checker</legend>
<table class="datatable solidtable"><col width="50%" /><col width="50%" />
<?php
	if (!extension_loaded('enchant')) {
		echo '<tr class="field small"><td colspan="2">You cannot use the spell checker as PHP\'s enchant module is currently <span style="color:red">disabled</span>. Please ask your administrator to enable "enchant" support.</td></tr>';
	} else {
		echo '<tr class="field small"><td colspan="2">For futher details, refer to the <a href="admspell.php?'. __adm_rsid .'#14">Forum Spell Checker Control Panel</a>.</td></tr>';
	}
	print_bit_field('Enable Spell Checker', 'SPELL_CHECK_ENABLED');
?>
</td></tr>
<tr class="fieldaction"><td align="left"><input type="submit" name="btn_submit" value="Set" /></td><td align="right">[ <a href="#top">top</a> ]</td></tr>
</table>
</fieldset>

<fieldset class="section 15"><legend>E-mail Settings</legend>
<table class="datatable solidtable"><col width="50%" /><col width="50%" />
<?php
	print_bit_field('Allow E-mail', 'ALLOW_EMAIL');
	print_reg_field('Administrator E-mail', 'ADMIN_EMAIL');
	print_reg_field('Notify From', 'NOTIFY_FROM');
	print_bit_field('Notify W/Body', 'NOTIFY_WITH_BODY');
	print_bit_field('Smart Notification', 'SMART_EMAIL_NOTIFICATION');
	print_bit_field('All Message Forum Notification', 'FORUM_NOTIFY_ALL');
	print_bit_field('Disable Welcome E-mail', 'DISABLE_WELCOME_EMAIL');
	print_bit_field('Disable E-mail notifications', 'DISABLE_NOTIFICATION_EMAIL');
	print_bit_field('Moderator Notification', 'MODERATED_POST_NOTIFY');
	print_bit_field('E-mail Confirmation', 'EMAIL_CONFIRMATION');
	print_bit_field('Use SMTP To Send E-mail', 'USE_SMTP');
	print_reg_field('SMTP Server', 'FUD_SMTP_SERVER');
	print_reg_field('SMTP Server Port', 'FUD_SMTP_PORT', 1);
	print_reg_field('SMTP Server Timeout', 'FUD_SMTP_TIMEOUT', 1);
	print_reg_field('SMTP Server Login', 'FUD_SMTP_LOGIN');
	print_reg_field('SMTP Server Password', 'FUD_SMTP_PASS');
?>
<script>
jQuery(document).ready(function() {
	/* Hide SMTP fields if SMTP is not active. */
	jQuery('#FUD_OPT_1_USE_SMTP').change(function() {
		if ( jQuery('#FUD_OPT_1_USE_SMTP option:selected').val() > 0 ) {
			jQuery('input[name^="CF_FUD_SMTP_"]').parent().parent().show('slow');
		} else {
			jQuery('input[name^="CF_FUD_SMTP_"]').parent().parent().hide('slow');
		}
	});
	jQuery('#FUD_OPT_1_USE_SMTP').change();
});
</script>
<tr class="fieldaction"><td align="left"><input type="submit" name="btn_submit" value="Set" /></td><td align="right">[ <a href="#top">top</a> ]</td></tr>
</table>
</fieldset>

<fieldset class="section 16"><legend>General Settings</legend>
<table class="datatable solidtable"><col width="50%" /><col width="50%" />
<?php
	// Get list of timezones to display.
	$tz_names = implode("\n", timezone_identifiers_list());
?>
<tr class="field"><td>Server Time Zone:<br /><font size="-1"> <?php echo $help_ar['SERVER_TZ'][0]; ?></font></td><td><select name="CF_SERVER_TZ"><?php echo tmpl_draw_select_opt($tz_names, $tz_names, $SERVER_TZ, '', ''); ?></select></td></tr>
<?php
	print_bit_field('Track referrals', 'TRACK_REFERRALS');
	print_reg_field('Max History', 'MNAV_MAX_DATE', 1);
	print_reg_field('Max Message Preview Length', 'MNAV_MAX_LEN', 1);
	print_bit_field('Attachment Referrer Check', 'DWLND_REF_CHK');
	print_bit_field('Bust&#39;A&#39;Punk', 'BUST_A_PUNK');
	print_bit_field('Disable Captcha Test', 'DISABLE_TURING_TEST');
	print_bit_field('Anonymous User Captcha Test', 'USE_ANON_TURING');
	print_bit_field('Use Captcha images', 'GRAPHICAL_TURING');
	print_bit_field('Obfuscate E-mails in NNTP posts', 'NNTP_OBFUSCATE_EMAIL');
	print_bit_field('MIME encode NNTP posts', 'NNTP_MIME_POSTS');
	if (extension_loaded('zlib')) {
		print_bit_field('Use PHP compression', 'PHP_COMPRESSION_ENABLE');
		print_reg_field('PHP compression level', 'PHP_COMPRESSION_LEVEL', 1);
	}
	print_bit_field('Public Host Resolving', 'PUBLIC_RESOLVE_HOST');
	print_reg_field('Whois Server Address', 'FUD_WHOIS_SERVER');
	print_reg_field('Polls Per Page', 'POLLS_PER_PAGE', 1);
?>
<tr class="fieldaction"><td align="left"><input type="submit" name="btn_submit" value="Set" /></td><td align="right">[ <a href="#top">top</a> ]</td></tr>
</table>
</fieldset>

<input type="hidden" name="form_posted" value="1" />
</form>
<?php require($WWW_ROOT_DISK .'adm/footer.php'); ?>
