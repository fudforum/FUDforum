<?php
	if (!isset($FORUM_TITLE, $DATA_DIR)) die('Go away!');	// Hacking attempt?

	if (defined('shell_script') ) {	// Command line execution.
		$usr = new stdClass;
		$usr->lang = 'en';
		$usr->theme_name = 'default';
	}

	if (file_exists($DATA_DIR.'thm/'.$usr->theme_name .'/i18n/'. $usr->lang .'/charset')) {
		$charset = trim(file_get_contents($DATA_DIR .'thm/'. $usr->theme_name .'/i18n/'. $usr->lang .'/charset'));
	} else if (file_exists($DATA_DIR .'thm/default/i18n/'. $usr->lang .'/charset')) {
		$charset = trim(file_get_contents($DATA_DIR .'thm/default/i18n/'. $usr->lang .'/charset'));
	} else {
		$charset = trim(file_get_contents($DATA_DIR .'thm/default/i18n/en/charset'));
	}

	if (defined('shell_script') ) return;	// Command line execution.
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" xml:lang="en">
<head>
<?php echo '<title>'. $FORUM_TITLE .': Admin Control Panel</title>' ?>
<link rel="styleSheet" href="style/adm.css" type="text/css" />
<script type="text/javascript" src="../js/jquery.js"></script>
<script type="text/javascript" src="../js/lib.js"></script>
<script type="text/javascript" src="style/jquery.tablesorter.min.js"></script>
<meta http-equiv="Content-Type" content="text/html; charset=<?php echo $charset; ?>" />
</head>
<?php

if (defined('popup') ) {	/* Special header for popup pages. */
	echo '<body class="popup">';
	echo '<table class="maintable"><tr><td>';
	return;
}

?>
<body>
<table class="headtable"><tr>
  <td><a href="index.php<?php if (defined('__adm_rsid')) echo '?'. __adm_rsid; ?>" title="Return to the Admin Control Panel Dashboard"><img src="../images/fudlogo.gif" alt="" style="float:left;" border="0" /></a>
  </td>
  <td><span class="linkhead">Admin Control Panel</span></td>
  <td align="right"><?php if (defined('__fud_index_name__')) { ?>
      [ <a title="Go back to your forum" href="../<?php echo __fud_index_name__ .'?'. __adm_rsid; ?>">Return to forum &raquo;</a> ]
      <?php } ?>
  </td>
</tr></table>

<table class="maintable">
<tr>
<?php if (defined('__adm_rsid') && $is_a) { ?>
<td class="linkdata">
<table class="linktable">
<tr>
	<td nowrap="nowrap">
	<center>
	[ <a title="Browse your forum's files" href="admbrowse.php?<?php echo __adm_rsid; ?>">Files</a> ]
	[ <a title="Query database tables" href="admsql.php?<?php echo __adm_rsid; ?>">SQL</a> ]
	[ <a title="Define and run job" href="admbatch.php?<?php echo __adm_rsid; ?>">Jobs</a> ]
	</center><br />

	<span class="linkgroup">General Management</span><br />
	<a title="Change forum settings" href="admglobal.php?<?php echo __adm_rsid; ?>">Global Settings Manager</a><br />
	<a title="Enable and disable plugins" href="admplugins.php?<?php echo __adm_rsid; ?>">Plugin Manager</a><br />
	<!-- a title="Add, edit and remove static pages" href="admpages.php?<?php echo __adm_rsid; ?>">Page Manager</a><br / -->
	<a title="Manage events and calendar settings" href="admcalendar.php?<?php echo __adm_rsid; ?>">Calendar Manager</a><br />
	<a title="Manage RDF, RSS and ATOM feeds" href="admfeed.php?<?php echo __adm_rsid; ?>">Syndication Manager</a><br />
	<a title="Define PDF page settings" href="admpdf.php?<?php echo __adm_rsid; ?>">PDF Generation Manager</a><br />
	<a title="Track the location of your users" href="admgeoip.php?<?php echo __adm_rsid; ?>">Geolocation Manager</a><br />
<?php if (extension_loaded('pspell')) { ?>
	<a title="Setup spell checker" href="admspell.php?<?php echo __adm_rsid; ?>">Custom Dictionary Spell Checker</a><br />
<?php } ?>
	<a title="Review forum actions" href="admlog.php?<?php echo __adm_rsid; ?>">Action Log Viewer</a><br />
	<a title="Review your forum's error logs" href="admerr.php?<?php echo __adm_rsid; ?>">Error Log Viewer</a><br />
	<a title="Visualize forum statistics" href="admstats.php?<?php echo __adm_rsid; ?>">Forum Statistics</a><br />
	<a title="View system information" href="admsysinfo.php?<?php echo __adm_rsid; ?>">System Info</a><br />
	<br />

	<span class="linkgroup">Forum Management</span><br />
	<a title="Setup categories and forums" href="admcat.php?<?php echo __adm_rsid; ?>">Category &amp; Forum Manager</a><br />
	<a title="List forums in the recycle bin" href="admdelfrm.php?<?php echo __adm_rsid; ?>">Deleted Forums</a><br />
	<a title="Announce important news" href="admannounce.php?<?php echo __adm_rsid; ?>">Announcement Manager</a><br />
	<a title="Remove old forum topics" href="admprune.php?<?php echo __adm_rsid; ?>">Topic Pruning</a><br />
	<a title="Remove old file attachements" href="admpruneattch.php?<?php echo __adm_rsid; ?>">Attachment Pruning</a><br />
	<a title="Import Mailing Lists messages into your forum" href="admmlist.php?<?php echo __adm_rsid; ?>">Mailing List Manager</a><br />
	<a title="Import Usenet posts into your forum" href="admnntp.php?<?php echo __adm_rsid; ?>">Newsgroup Manager</a><br />
<?php if ((bool)ini_get('allow_url_fopen') == TRUE) { ?>
	<a title="Import XML Feeds into your forum" href="admxmlagg.php?<?php echo __adm_rsid; ?>">XML Aggregation</a><br />
<?php } ?>
	<br />

	<span class="linkgroup">User Management</span><br />
	<a title="Manage user accounts" href="admuser.php?<?php echo __adm_rsid; ?>">User Manager</a><br />
	<a title="Setup groups and group permisions" href="admgroups.php?<?php echo __adm_rsid; ?>">Groups Manager</a><br />
	<a title="Manage user ranks" href="admlevel.php?<?php echo __adm_rsid; ?>">Rank Manager</a><br />
	<a title="Delete old users without messages" href="admpruneusers.php?<?php echo __adm_rsid; ?>">Prune users</a><br />
	<a title="Define custom porfile fields" href="admcustomfields.php?<?php echo __adm_rsid; ?>">Profile fields</a><br />
	<a title="Send E-mail to your forum memebers" href="admmassemail.php?<?php echo __adm_rsid; ?>">Mass E-mail</a><br />
	<br />

	<span class="linkgroup">Template Management</span><br />
	<a title="Manage themes" href="admthemes.php?<?php echo __adm_rsid; ?>">Theme Manager</a><br />
	<a title="Change your forum's look and feel" href="admtemplates.php?<?php echo __adm_rsid; ?>">Template Editor</a><br />
	<a title="Edit 118n language strings" href="admmessages.php?<?php echo __adm_rsid; ?>">Message Editor</a><br />
	<a title="Edit help pages" href="admhelp.php?<?php echo __adm_rsid; ?>">Help Editor</a><br />
	<br />

	<span class="linkgroup">Icon Management</span><br />
	<a title="Define MIME types andicons" href="admmime.php?<?php echo __adm_rsid; ?>">MIME Manager</a><br />
	<a title="Manage smilys" href="admsmiley.php?<?php echo __adm_rsid; ?>">Smiley Manager</a><br />
	<a title="Manage forum icons" href="admforumicons.php?<?php echo __adm_rsid; ?>">Forum Icon Manager</a><br />
	<a title="Manage messages icons" href="admforumicons.php?<?php echo __adm_rsid; ?>&amp;which_dir=1">Message Icon Manager</a><br />
	<br />

	<span class="linkgroup">Avatar Management</span><br />
	<a title="Approve avatars" href="admapprove_avatar.php?<?php echo __adm_rsid; ?>">Avatar Approval</a><br />
	<a title="Manage avatars" href="admavatar.php?<?php echo __adm_rsid; ?>">Avatar Manager</a><br />
	<br />

	<span class="linkgroup">Filters</span><br />
	<a title="Filter user entered data" href="admreplace.php?<?php echo __adm_rsid; ?>">Replacement &amp; Censorship</a><br />
	<a title="Block E-mail addresses" href="admemail.php?<?php echo __adm_rsid; ?>">E-mail filter</a><br />
	<a title="Block IP addresses" href="admipfilter.php?<?php echo __adm_rsid; ?>">IP filter</a><br />
	<a title="Block logins" href="admlogin.php?<?php echo __adm_rsid; ?>">Login filter</a><br />
	<a title="Define allowed file extentions" href="admext.php?<?php echo __adm_rsid; ?>">File filter</a><br />
	<br />

	<span class="linkgroup">Checks/Consistency</span><br />
	<a title="Perform consistency check" href="consist.php?<?php echo __adm_rsid; ?>">Forum Consistency</a><br />
	<a title="Reindex your forum" href="indexdb.php?<?php echo __adm_rsid; ?>">Rebuild Search Index</a><br />
<?php if (!($FUD_OPT_3 & 32768)) {	/* Not using DB_MESSAGE_STORAGE. */ ?>
	<a title="Compact messages" href="compact.php?<?php echo __adm_rsid; ?>">Compact Messages</a><br />
<?php } ?>
<?php if (strncasecmp('win', PHP_OS, 3)) {	/* Not for Windows. */ ?>
		<a title="Secure your forum's files" href="admlock.php?<?php echo __adm_rsid; ?>">Lock/Unlock Forum Files</a><br />
<?php } ?>
<?php if (__dbtype__ == 'mysql') { ?>
	<a title="Change the character set of your database tables" href="admmysql.php?<?php echo __adm_rsid; ?>">MySQL Charset Changer</a><br />
<?php } ?>
	<br />

	<span class="linkgroup">Backup/Restore</span><br />
	<a title="Backup your forum" href="admdump.php?<?php echo __adm_rsid; ?>">Make forum datadump</a><br />
	<a title="Restore a forum backup" href="admimport.php?<?php echo __adm_rsid; ?>">Import forum data</a><br />
	<br />
</td></tr>
</table>
</td>
<?php } ?>
<td class="maindata">
