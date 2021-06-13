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

	if (!isset($FORUM_TITLE, $DATA_DIR)) die('Go away!');	// Hacking attempt? GLOBALS not included.

	if (file_exists($DATA_DIR .'thm/'. $usr->theme_name .'/i18n/'. $usr->lang .'/charset')) {
		$charset = trim(file_get_contents($DATA_DIR .'thm/'. $usr->theme_name .'/i18n/'. $usr->lang .'/charset'));
	} else if (file_exists($DATA_DIR .'thm/default/i18n/'. $usr->lang .'/charset')) {
		$charset = trim(file_get_contents($DATA_DIR .'thm/default/i18n/'. $usr->lang .'/charset'));
	} else {
		$charset = trim(file_get_contents($DATA_DIR .'thm/default/i18n/en/charset'));
	}

	if (defined('shell_script') ) return;	// Command line execution.
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="<?php echo $charset; ?>">
	<title><?php echo $FORUM_TITLE; ?>: Admin Control Panel</title>
	<link rel="styleSheet" href="../js/ui/jquery-ui.css" />
	<link rel="styleSheet" href="style/adm.css" />
	<script src="../js/jquery.js"></script>
	<script src="../js/ui/jquery-ui.js"></script>
	<script src="../js/lib.js"></script>
	<script src="style/jquery.tablesorter.min.js"></script>
</head>
<?php

if (defined('popup') ) {	/* Special header for popup pages. */
	echo '<body class="popup">';
	echo '<table class="maintable"><tr><td>';
	return;
}

?>
<body>
<div style="display: none;" id="dialogHolder"><p id="dialogContent"><!-- Placeholder for jQuery UI Dialog --></p></div>
<table class="headtable"><tr>
  <td><a href="index.php<?php if (defined('__adm_rsid')) echo '?'. __adm_rsid; ?>" title="Return to the Admin Control Panel Dashboard"><img src="../images/fudlogo.gif" width="200px" alt="" style="float:left;" border="0" /></a>
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
	[ <a title="Schedule and run ad hoc tasks" href="admjobs.php?<?php echo __adm_rsid; ?>">Jobs</a> ]
	</center><br />

<style>
ul {list-style-type:none; padding: 0px; margin: 0px;}
li {position: relative; padding: 2px 0; }
span.ui-icon {float: left; margin: 0 -2px;}
</style>
<script>
jQuery(document).ready(function() {
	jQuery(".menu li a").each(function() {
		var url = this.href.substring(0, this.href.lastIndexOf("?"));
		if( url == document.location.protocol + "//" + window.location.hostname + window.location.pathname ) {
			jQuery(this).before('<span class="ui-icon ui-icon-triangle-1-e"></span>');
			//jQuery(this).before('<img src="../blank.gif" class="ui-icon ui-icon-triangle-1-w" />');
		}
	});
});
</script>

	<span class="linkgroup">General Management</span>
	<ul class="menu">
	<li><a title="Change forum settings" href="admglobal.php?<?php echo __adm_rsid; ?>">Global Settings Manager</a></li>
	<li><a title="Enable and disable plugins" href="admplugins.php?<?php echo __adm_rsid; ?>">Plugin Manager</a></li>
	<li><a title="Review forum actions" href="admlog.php?<?php echo __adm_rsid; ?>">Action Log Viewer</a></li>
	<li><a title="Review your forum's error logs" href="admerr.php?<?php echo __adm_rsid; ?>">Error Log Viewer</a></li>
	<li><a title="Visualize forum statistics" href="admstats.php?<?php echo __adm_rsid; ?>">Forum Statistics</a></li>
	<li><a title="View system information" href="admsysinfo.php?<?php echo __adm_rsid; ?>">System Info</a></li>
	</ul>
	<br />

	<span class="linkgroup">Categories & Forums</span>
	<ul class="menu">
	<li><a title="Setup forum categories" href="admcat.php?<?php echo __adm_rsid; ?>">Category Manager</a></li>
	<li><a title="Setup forums and subforums" href="admforum.php?<?php echo __adm_rsid; ?>">Forum Manager</a></li>
	<li><a title="Manage and remove forum topics" href="admtopic.php?<?php echo __adm_rsid; ?>">Topic Manager</a></li>
	<li><a title="Remove old file attachements" href="admpruneattch.php?<?php echo __adm_rsid; ?>">Attachment Pruning</a></li>
	<li><a title="Import Mailing Lists messages into your forum" href="admmlist.php?<?php echo __adm_rsid; ?>">Mailing List Manager</a></li>
	<li><a title="Import Usenet posts into your forum" href="admnntp.php?<?php echo __adm_rsid; ?>">Newsgroup Manager</a></li>
<?php if ((bool)ini_get('allow_url_fopen') == TRUE) { ?>
	<li><a title="Import XML Feeds into your forum" href="admxmlagg.php?<?php echo __adm_rsid; ?>">XML Aggregation</a></li>
<?php } ?>
	</ul>
	<br />

	<span class="linkgroup">Users & Groups</span>
	<ul class="menu">
	<li><a title="Manage user accounts" href="admuser.php?<?php echo __adm_rsid; ?>">User Manager</a></li>
	<li><a title="Manage bots and spiders" href="admspiders.php?<?php echo __adm_rsid; ?>">Spiders / Bots</a></li>
	<li><a title="Setup groups and group permisions" href="admgroups.php?<?php echo __adm_rsid; ?>">Groups Manager</a></li>
	<li><a title="Manage user ranks" href="admlevel.php?<?php echo __adm_rsid; ?>">Rank Manager</a></li>
	<li><a title="Define custom profile fields" href="admcustomfields.php?<?php echo __adm_rsid; ?>">Profile fields</a></li>
	<li><a title="Send E-mail to your forum memebers" href="admmassemail.php?<?php echo __adm_rsid; ?>">Mass E-mail</a></li>
	<br />

	<span class="linkgroup">Templates & Messages</span>
	<ul class="menu">
	<li><a title="Manage themes" href="admthemes.php?<?php echo __adm_rsid; ?>">Theme Manager</a></li>
	<li><a title="Change your forum's look and feel" href="admtemplates.php?<?php echo __adm_rsid; ?>">Template Editor</a></li>
	<li><a title="Edit 118n language strings" href="admmessages.php?<?php echo __adm_rsid; ?>">Message Editor</a></li>
	<li><a title="Edit help pages" href="admhelp.php?<?php echo __adm_rsid; ?>">Help Editor</a></li>
	</ul>
	<br />

	<span class="linkgroup">Content</span>
	<ul class="menu">
	<li><a title="Manage the forum's blog/portal page" href="admblog.php?<?php echo __adm_rsid; ?>">Blog Manager</a></li>
	<li><a title="Add, edit and remove static pages" href="admpages.php?<?php echo __adm_rsid; ?>">Page Manager</a></li>
	<li><a title="Manage events and calendar settings" href="admcalendar.php?<?php echo __adm_rsid; ?>">Calendar Manager</a></li>
	<li><a title="Announce important news" href="admannounce.php?<?php echo __adm_rsid; ?>">Announcement Manager</a></li>
	<li><a title="Manage RDF, RSS and ATOM feeds" href="admfeed.php?<?php echo __adm_rsid; ?>">Syndication Manager</a></li>
	<li><a title="Define PDF page settings" href="admpdf.php?<?php echo __adm_rsid; ?>">PDF Generation Manager</a></li>
	<li><a title="Track the location of your users" href="admgeoip.php?<?php echo __adm_rsid; ?>">Geolocation Manager</a></li>
<?php if (extension_loaded('enchant')) { ?>
	<li><a title="Setup spell checker" href="admspell.php?<?php echo __adm_rsid; ?>">Spell Checker</a></li>
<?php } ?>
	</ul>
	<br />
	
	<span class="linkgroup">Avatars & Icons</span>
	<ul class="menu">
	<li><a title="Manage avatars" href="admavatar.php?<?php echo __adm_rsid; ?>">Avatar Manager</a></li>
	<li><a title="Approve avatars" href="admavatarapr.php?<?php echo __adm_rsid; ?>">Avatar Approval</a></li>
	<li><a title="Define MIME types andicons" href="admmime.php?<?php echo __adm_rsid; ?>">MIME Manager</a></li>
	<li><a title="Manage smilys" href="admsmiley.php?<?php echo __adm_rsid; ?>">Smiley Manager</a></li>
	<li><a title="Manage forum icons" href="admforumicons.php?<?php echo __adm_rsid; ?>">Forum Icon Manager</a></li>
	<li><a title="Manage messages icons" href="admforumicons.php?<?php echo __adm_rsid; ?>&amp;which_dir=1">Message Icon Manager</a></li>
	</ul>
	<br />

	<span class="linkgroup">Filters</span>
	<ul class="menu">
	<li><a title="Filter user entered data" href="admreplace.php?<?php echo __adm_rsid; ?>">Replacement &amp; Censorship</a></li>
	<li><a title="Block E-mail addresses" href="admemail.php?<?php echo __adm_rsid; ?>">E-mail filter</a></li>
	<li><a title="Block IP addresses" href="admipfilter.php?<?php echo __adm_rsid; ?>">IP Address filter</a></li>
	<li><a title="Block logins" href="admlogin.php?<?php echo __adm_rsid; ?>">Login filter</a></li>
	<li><a title="Define allowed file extentions" href="admext.php?<?php echo __adm_rsid; ?>">File filter</a></li>
	</ul>
	<br />

	<span class="linkgroup">Maintenance</span>
	<ul class="menu">
	<li><a title="Perform consistency check" href="consist.php?<?php echo __adm_rsid; ?>">Forum Consistency</a></li>
	<li><a title="Reindex your forum messages" href="indexdb.php?<?php echo __adm_rsid; ?>">Rebuild Search Index</a></li>
	<li><a title="Rebuild messages" href="compact.php?<?php echo __adm_rsid; ?>">Rebuild Messages</a></li>
<?php if (strncasecmp('win', PHP_OS, 3)) {	/* Not for Windows. */ ?>
		<li><a title="Secure your forum's files" href="admlock.php?<?php echo __adm_rsid; ?>">Lock/Unlock Forum Files</a></li>
<?php } ?>
<?php if (defined('fud_debug') && defined('__dbtype__') && __dbtype__ == 'mysql') { ?>
	<li><a title="Change the character set of your database tables" href="admmysql.php?<?php echo __adm_rsid; ?>">MySQL Charset Changer</a></li>
<?php } ?>
	</ul>
	<br />

	<span class="linkgroup">Backup/Restore</span>
	<ul class="menu">
	<li><a title="Backup your forum" href="admdump.php?<?php echo __adm_rsid; ?>">Make forum datadump</a></li>
	<li><a title="Restore a forum backup" href="admimport.php?<?php echo __adm_rsid; ?>">Import forum data</a></li>
	<!-- li><a title="Convert from another form type to FUDforum" href="convert.php?<?php echo __adm_rsid; ?>">Convert forum</a></li -->
	</ul>
	<br />
</td></tr>
</table>
</td>
<?php } ?>
<td class="maindata">
