<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" xml:lang="en">
<head>
<?php echo '<title>'.$FORUM_TITLE.': '.'Admin Control Panel</title>' ?>
<link rel="StyleSheet" href="adm.css" type="text/css" />
<meta http-equiv="Content-Type" content="text/html; charset=<?php 
if (file_exists($DATA_DIR.'thm/'.$usr->theme_name.'/i18n/'.$usr->lang.'/charset')) {
	echo trim(file_get_contents($DATA_DIR.'thm/'.$usr->theme_name.'/i18n/'.$usr->lang.'/charset'));
} else if (file_exists($DATA_DIR.'thm/default/i18n/'.$usr->lang.'/charset')) {
	echo trim(file_get_contents($DATA_DIR.'thm/default/i18n/'.$usr->lang.'/charset'));
} else {
	echo 'utf-8';
}
?>" />
</head>
<body>
<table class="headtable"><tr>
  <td><a href="index.php?<?php echo __adm_rsid; ?>" title="Return to the Admin Control Panel Dashboard"><img src="../images/fudlogo.gif" alt="" style="float:left;" border="0" /></a></td>
  <td><span class="linkhead">Admin Control Panel</span></td>
  <td>[ <a title="Go back to your forum" href="../<?php echo __fud_index_name__.'?'.__adm_rsid; ?>">Return to forum &raquo;</a> ]</td>
</tr></table>

<table class="maintable">
<tr>
<?php if ($is_a) { ?>
<td class="linkdata">
<table class="linktable">
<tr>
	<td nowrap="nowrap">
	<span class="linkgroup">Checks/Consistency</span><br />
	<a href="consist.php?<?php echo __adm_rsid; ?>">Forum Consistency</a><br />
	<a href="indexdb.php?<?php echo __adm_rsid; ?>">Rebuild Search Index</a><br />
<?php if (!($FUD_OPT_3 & 32768)) { ?>
	<a href="compact.php?<?php echo __adm_rsid; ?>">Compact Messages</a><br />
<?php } ?><br />
	<span class="linkgroup">General Management</span><br />
	<a href="admglobal.php?<?php echo __adm_rsid; ?>">Global Settings Manager</a><br />
	<a href="admplugins.php?<?php echo __adm_rsid; ?>">Plugin Manager</a><br />
	<a href="admreplace.php?<?php echo __adm_rsid; ?>">Replacement &amp; Censorship System</a><br />
	<a href="admmime.php?<?php echo __adm_rsid; ?>">MIME Management System</a><br />
	<a href="admrdf.php?<?php echo __adm_rsid; ?>">RDF Feed Management</a><br />
	<a href="admpdf.php?<?php echo __adm_rsid; ?>">PDF Generation Management</a><br />
	<a href="admgeoip.php?<?php echo __adm_rsid; ?>">Geolocation Management</a><br />
	<a href="admsql.php?<?php echo __adm_rsid; ?>">SQL Manager</a><br />
	<a href="admbrowse.php?<?php echo __adm_rsid; ?>">File Manager</a><br />
<?php
	if (strncasecmp('win', PHP_OS, 3)) {
		echo '<a href="admlock.php?'.__adm_rsid.'">Lock/Unlock Forum Files</a><br />';
	}
	if (extension_loaded('pspell')) {
		echo '<a href="admspell.php?'.__adm_rsid.'">Custom Dictionary Spell Checker</a><br />';
	}
?>
	<a href="admstats.php?<?php echo __adm_rsid; ?>">Forum Statistics</a><br />
	<a href="admlog.php?<?php echo __adm_rsid; ?>">Action Log Viewer</a><br />
	<a href="admerr.php?<?php echo __adm_rsid; ?>">Error Log Viewer</a><br />
	<a href="admsysinfo.php?<?php echo __adm_rsid; ?>">System Info</a><br />
<?php if (__dbtype__ == 'mysql') { ?>
	<a href="admmysql.php?<?php echo __adm_rsid; ?>">MySQL Charset Changer</a><br />
<?php } ?><br />

	<span class="linkgroup">Forum Management</span><br />
	<a href="admcat.php?<?php echo __adm_rsid; ?>">Category &amp; Forum Management</a><br />
	<a href="admdelfrm.php?<?php echo __adm_rsid; ?>">Deleted Forums</a><br />
	<a href="admannounce.php?<?php echo __adm_rsid; ?>">Announcement Manager</a><br />
	<a href="admprune.php?<?php echo __adm_rsid; ?>">Topic Pruning</a><br />
	<a href="admaprune.php?<?php echo __adm_rsid; ?>">Attachment Pruning</a><br />
	<a href="admmlist.php?<?php echo __adm_rsid; ?>">Mailing List Manager</a><br />
	<a href="admnntp.php?<?php echo __adm_rsid; ?>">Newsgroup Manager</a><br /><br />

	<span class="linkgroup">User Management</span><br />
	<a href="admuser.php?<?php echo __adm_rsid; ?>">Moderator/User Manager</a><br />
	<a href="admadduser.php?<?php echo __adm_rsid; ?>">Add User</a><br />
	<a href="admaccapr.php?<?php echo __adm_rsid; ?>">Account Approval</a><br />
	<a href="admgroups.php?<?php echo __adm_rsid; ?>">Groups Manager</a><br />
	<a href="admmassemail.php?<?php echo __adm_rsid; ?>">Mass Email</a><br />
	<a href="admlevel.php?<?php echo __adm_rsid; ?>">Rank Manager</a><br />
	<a href="admslist.php?<?php echo __adm_rsid; ?>">Privileged User List</a><br />
	<a href="admbanlist.php?<?php echo __adm_rsid; ?>">Banned User List</a><br /><br />

	<span class="linkgroup">Template Management</span><br />
	<a href="admthemes.php?<?php echo __adm_rsid; ?>">Theme Manager</a><br />
	<a href="tmpllist.php?<?php echo __adm_rsid; ?>">Template Editor</a><br />
	<a href="msglist.php?<?php echo __adm_rsid; ?>">Message Editor</a><br /><br />

	<span class="linkgroup">Icon Management</span><br />
	<a href="admsmiley.php?<?php echo __adm_rsid; ?>">Smiley Manager</a><br />
	<a href="admforumicons.php?<?php echo __adm_rsid; ?>">Forum Icon Manager</a><br />
	<a href="admforumicons.php?<?php echo __adm_rsid; ?>&amp;which_dir=1">Message Icon Manager</a><br /><br />

	<span class="linkgroup">Avatar Management</span><br />
	<a href="admapprove_avatar.php?<?php echo __adm_rsid; ?>">Avatar Approval</a><br />
	<a href="admavatar.php?<?php echo __adm_rsid; ?>">Avatar Manager</a><br /><br />

	<span class="linkgroup">Filters</span><br />
	<a href="admemail.php?<?php echo __adm_rsid; ?>">Email filter</a><br />
	<a href="admipfilter.php?<?php echo __adm_rsid; ?>">IP filter</a><br />
	<a href="admlogin.php?<?php echo __adm_rsid; ?>">Login filter</a><br />
	<a href="admext.php?<?php echo __adm_rsid; ?>">File filter</a><br /><br />

	<span class="linkgroup">Forum Data Management</span><br />
	<a href="admdump.php?<?php echo __adm_rsid; ?>">Make forum datadump</a><br />
	<a href="admimport.php?<?php echo __adm_rsid; ?>">Import forum data</a><br />
	<br />
</td></tr>
</table>
</td>
<?php } ?>
<td class="maindata">
