<html>
<head>
<link rel="StyleSheet" href="adm.css" type="text/css">
</head>
<body>
<table class="maintable">
<tr>
<td class="linkdata">
<table class="linktable">
<tr>
<td>
<a href="../<?php echo __fud_index_name__.'?'._rsidl; ?>">Return To Forum</a>
    </td></tr>
<tr><td>
<span class="linkhead">Admin Control Panel</span></td></tr>
<tr>
	<td nowrap>
	<span class="linkgroup">Checks/Consistency</span><br>
	<a href="consist.php?<?php echo _rsidl; ?>">Forum Consistency</a><br>
	<a href="indexdb.php?<?php echo _rsidl; ?>">Rebuild Search Index</a><br>
	<a href="compact.php?<?php echo _rsidl; ?>">Compact Messages</a><br><br>

	<span class="linkgroup">General Management</span><br>
	<a href="admglobal.php?<?php echo _rsidl; ?>">Global Settings Manager</a><br>
	<a href="admreplace.php?<?php echo _rsidl; ?>">Replacement & Censorship System</a><br>
	<a href="admmime.php?<?php echo _rsidl; ?>">MIME Managment System</a><br>
	<a href="admrdf.php?<?php echo _rsidl; ?>">RDF Feed Managment</a><br>
<?php
	if (extension_loaded('pdf')) {
		echo '<a href="admpdf.php?'._rsidl.'">PDF Generation Managment</a><br>';
	}
	if (extension_loaded('pspell')) {
		echo '<a href="admspell.php?'._rsidl.'">Custom Dictionary Spell Checker</a><br>';
	}

	if (strncasecmp('win', PHP_OS, 3)) {
		echo '<a href="admbrowse.php?'._rsidl.'">File Manager</a><br>';
		echo '<a href="admlock.php?'._rsidl.'">Lock/Unlock Forum\'s Files</a><br>';
	}
?>
	<a href="admstats.php?<?php echo _rsidl; ?>">Forum Statistics</a><br>
	<a href="admlog.php?<?php echo _rsidl; ?>">Action Log Viewer</a><br>
	<a href="admerr.php?<?php echo _rsidl; ?>">Error Log Viewer</a><br>
	<a href="admsysinfo.php?<?php echo _rsidl; ?>">System Info</a><br><br>

	<span class="linkgroup">Forum Management</span><br>
	<a href="admcat.php?<?php echo _rsidl; ?>">Category & Forum Management</a><br>
	<a href="admdelfrm.php?<?php echo _rsidl; ?>">Deleted Forums</a><br>
	<a href="admannounce.php?<?php echo _rsidl; ?>">Announcement Manager</a><br>
	<a href="admprune.php?<?php echo _rsidl; ?>">Topic Pruning</a><br>
	<a href="admaprune.php?<?php echo _rsidl; ?>">Attachment Pruning</a><br>
	<a href="admmlist.php?<?php echo _rsidl; ?>">Mailing List Manager</a><br>
	<a href="admnntp.php?<?php echo _rsidl; ?>">Newsgroup Manager</a><br><br>

	<span class="linkgroup">User Management</span><br>
	<a href="admuser.php?<?php echo _rsidl; ?>">Moderator/User Manager</a><br>
	<a href="admadduser.php?<?php echo _rsidl; ?>">Add User</a><br>
	<a href="admaccapr.php?<?php echo _rsidl; ?>">Account Approval</a><br>
	<a href="admgroups.php?<?php echo _rsidl; ?>">Groups Manager</a><br>
	<a href="admmassemail.php?<?php echo _rsidl; ?>">Mass Email</a><br>
	<a href="admlevel.php?<?php echo _rsidl; ?>">Rank Manager</a><br><br>

	<span class="linkgroup">Template Management</span><br>
	<a href="admthemes.php?<?php echo _rsidl; ?>">Theme Manager</a><br>
	<a href="tmpllist.php?<?php echo _rsidl; ?>">Template Editor</a><br>
	<a href="msglist.php?<?php echo _rsidl; ?>">Message Editor</a><br><br>

	<span class="linkgroup">Icon Management</span><br>
	<a href="admsmiley.php?<?php echo _rsidl; ?>">Smiley Manager</a><br>
	<a href="admforumicons.php?<?php echo _rsidl; ?>">Forum Icon Manager</a><br>
	<a href="admforumicons.php?<?php echo _rsidl; ?>&which_dir=1">Message Icon Manager</a><br><br>

	<span class="linkgroup">Avatar Management</span><br>
	<a href="admapprove_avatar.php?<?php echo _rsidl; ?>">Avatar Approval</a><br>
	<a href="admavatar.php?<?php echo _rsidl; ?>">Avatar Manager</a><br><br>

	<span class="linkgroup">Filters</span><br>
	<a href="admemail.php?<?php echo _rsidl; ?>">Email filter</a><br>
	<a href="admipfilter.php?<?php echo _rsidl; ?>">IP filter</a><br>
	<a href="admlogin.php?<?php echo _rsidl; ?>">Login filter</a><br>
	<a href="admext.php?<?php echo _rsidl; ?>">File filter</a><br><br>

	<span class="linkgroup">Forum Data Management</span><br>
	<a href="admdump.php?<?php echo _rsidl; ?>">Make forum datadump</a><br>
	<a href="admimport.php?<?php echo _rsidl; ?>">Import forum data</a><br>
</td></tr>
</table>
</td>
<td class="maindata">
