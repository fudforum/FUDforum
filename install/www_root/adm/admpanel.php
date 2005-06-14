<html>
<head>
<link rel="StyleSheet" href="adm.css" type="text/css">
<meta http-equiv="Content-Type" content="text/html; charset=<?php readfile($DATA_DIR . 'thm/' . $usr->theme_name . '/i18n/' . $usr->lang . '/charset'); ?>">
</head>
<body>
<table class="maintable">
<tr>
<td class="linkdata">
<table class="linktable">
<tr>
<td>
<a href="../<?php echo __fud_index_name__.'?'.__adm_rsidl; ?>">Return To Forum</a>
    </td></tr>
<tr><td>
<span class="linkhead">Admin Control Panel</span></td></tr>
<tr>
	<td nowrap>
	<span class="linkgroup">Checks/Consistency</span><br>
	<a href="consist.php?<?php echo __adm_rsidl; ?>">Forum Consistency</a><br>
	<a href="indexdb.php?<?php echo __adm_rsidl; ?>">Rebuild Search Index</a><br>
	<a href="compact.php?<?php echo __adm_rsidl; ?>">Compact Messages</a><br><br>

	<span class="linkgroup">General Management</span><br>
	<a href="admglobal.php?<?php echo __adm_rsidl; ?>">Global Settings Manager</a><br>
	<a href="admreplace.php?<?php echo __adm_rsidl; ?>">Replacement & Censorship System</a><br>
	<a href="admmime.php?<?php echo __adm_rsidl; ?>">MIME Managment System</a><br>
	<a href="admrdf.php?<?php echo __adm_rsidl; ?>">RDF Feed Managment</a><br>
	<a href="admpdf.php?<?php echo __adm_rsidl; ?>">PDF Generation Managment</a><br>
<?php
	if (extension_loaded('pspell')) {
		echo '<a href="admspell.php?'.__adm_rsidl.'">Custom Dictionary Spell Checker</a><br>';
	}

	if (strncasecmp('win', PHP_OS, 3)) {
		echo '<a href="admbrowse.php?'.__adm_rsidl.'">File Manager</a><br>';
		echo '<a href="admlock.php?'.__adm_rsidl.'">Lock/Unlock Forum\'s Files</a><br>';
	}
?>
	<a href="admstats.php?<?php echo __adm_rsidl; ?>">Forum Statistics</a><br>
	<a href="admlog.php?<?php echo __adm_rsidl; ?>">Action Log Viewer</a><br>
	<a href="admerr.php?<?php echo __adm_rsidl; ?>">Error Log Viewer</a><br>
	<a href="admsysinfo.php?<?php echo __adm_rsidl; ?>">System Info</a><br><br>

	<span class="linkgroup">Forum Management</span><br>
	<a href="admcat.php?<?php echo __adm_rsidl; ?>">Category & Forum Management</a><br>
	<a href="admdelfrm.php?<?php echo __adm_rsidl; ?>">Deleted Forums</a><br>
	<a href="admannounce.php?<?php echo __adm_rsidl; ?>">Announcement Manager</a><br>
	<a href="admprune.php?<?php echo __adm_rsidl; ?>">Topic Pruning</a><br>
	<a href="admaprune.php?<?php echo __adm_rsidl; ?>">Attachment Pruning</a><br>
	<a href="admmlist.php?<?php echo __adm_rsidl; ?>">Mailing List Manager</a><br>
	<a href="admnntp.php?<?php echo __adm_rsidl; ?>">Newsgroup Manager</a><br><br>

	<span class="linkgroup">User Management</span><br>
	<a href="admuser.php?<?php echo __adm_rsidl; ?>">Moderator/User Manager</a><br>
	<a href="admadduser.php?<?php echo __adm_rsidl; ?>">Add User</a><br>
	<a href="admaccapr.php?<?php echo __adm_rsidl; ?>">Account Approval</a><br>
	<a href="admgroups.php?<?php echo __adm_rsidl; ?>">Groups Manager</a><br>
	<a href="admmassemail.php?<?php echo __adm_rsidl; ?>">Mass Email</a><br>
	<a href="admlevel.php?<?php echo __adm_rsidl; ?>">Rank Manager</a><br>
	<a href="admslist.php?<?php echo __adm_rsidl; ?>">Privileged User List</a><br>
	<a href="admbanlist.php?<?php echo __adm_rsidl; ?>">Banned User List</a><br><br>

	<span class="linkgroup">Template Management</span><br>
	<a href="admthemes.php?<?php echo __adm_rsidl; ?>">Theme Manager</a><br>
	<a href="tmpllist.php?<?php echo __adm_rsidl; ?>">Template Editor</a><br>
	<a href="msglist.php?<?php echo __adm_rsidl; ?>">Message Editor</a><br><br>

	<span class="linkgroup">Icon Management</span><br>
	<a href="admsmiley.php?<?php echo __adm_rsidl; ?>">Smiley Manager</a><br>
	<a href="admforumicons.php?<?php echo __adm_rsidl; ?>">Forum Icon Manager</a><br>
	<a href="admforumicons.php?<?php echo __adm_rsidl; ?>&which_dir=1">Message Icon Manager</a><br><br>

	<span class="linkgroup">Avatar Management</span><br>
	<a href="admapprove_avatar.php?<?php echo __adm_rsidl; ?>">Avatar Approval</a><br>
	<a href="admavatar.php?<?php echo __adm_rsidl; ?>">Avatar Manager</a><br><br>

	<span class="linkgroup">Filters</span><br>
	<a href="admemail.php?<?php echo __adm_rsidl; ?>">Email filter</a><br>
	<a href="admipfilter.php?<?php echo __adm_rsidl; ?>">IP filter</a><br>
	<a href="admlogin.php?<?php echo __adm_rsidl; ?>">Login filter</a><br>
	<a href="admext.php?<?php echo __adm_rsidl; ?>">File filter</a><br><br>

	<span class="linkgroup">Forum Data Management</span><br>
	<a href="admdump.php?<?php echo __adm_rsidl; ?>">Make forum datadump</a><br>
	<a href="admimport.php?<?php echo __adm_rsidl; ?>">Import forum data</a><br>
</td></tr>
</table>
</td>
<td class="maindata">
