<?php define('__fud_index_name__', 'index.php'); ?>
<html>
<body bgcolor="#ffffff">
<style type="text/css">
.admin_fixed {
	font-family: courier;
	text-decoration: none;
	font-size: 12px;
}
</style>
				
<table bgcolor="#000000" cellspacing=1 cellpadding=3><tr><td valign=top bgcolor="#ffffff">
<table cellspacing=1 cellpadding=2 border=0 bgcolor="#000000" align="center" style="font-size: small; background: white;">
<tr><td><a href="../index.php?<?php echo _rsid; ?>">Return To Forum</a></td></tr>
<tr><td><font style="text-decoration: underline;"><b>Admin Control Panel</b></font></td></tr>
<tr>
	<td nowrap>
	<b>Checks/Consistency</b><br>
	<a style="font-size: x-small;" href="consist.php?<?php echo _rsid; ?>">Forum Consistency</a><br>
	<a style="font-size: x-small;" href="indexdb.php?<?php echo _rsid; ?>">Rebuild Search Index</a><br>
	<a style="font-size: x-small;" href="compact.php?<?php echo _rsid; ?>">Compact Messages</a><br><br>
	
	<b>General Management</b><br>
	<a style="font-size: x-small;" href="admglobal.php?<?php echo _rsid; ?>">Global Settings Manager</a><br>
	<a style="font-size: x-small;" href="admreplace.php?<?php echo _rsid; ?>">Replacement & Censorship System</a><br>
	<a style="font-size: x-small;" href="admmime.php?<?php echo _rsid; ?>">MIME Managment System</a><br>
	
<?php	
	if( substr(getcwd(), 0, 1) == '/' ) {
		echo '<a style="font-size: x-small;" href="admbrowse.php?'._rsid.'">File Manager</a><br>';
		echo '<a style="font-size: x-small;" href="admlock.php?'._rsid.'">Lock/Unlock Forum\'s Files</a><br>';
	}
?>	
	<a style="font-size: x-small;" href="admstats.php?<?php echo _rsid; ?>">Forum Statistics</a><br>
	<a style="font-size: x-small;" href="admlog.php?<?php echo _rsid; ?>">Action Log Viewer</a><br>
	<a style="font-size: x-small;" href="admsysinfo.php?<?php echo _rsid; ?>">System Info</a><br><br>

	<b>Forum Management</b><br>
	<a style="font-size: x-small;" href="admcat.php?<?php echo _rsid; ?>">Category & Forum Management</a><br>
	<a style="font-size: x-small;" href="admdelfrm.php?<?php echo _rsid; ?>">Deleted Forums</a><br>
	<a style="font-size: x-small;" href="admannounce.php?<?php echo _rsid; ?>">Announcement Manager</a><br>
	<a style="font-size: x-small;" href="admprune.php?<?php echo _rsid; ?>">Thread Pruning</a><br>
	<a style="font-size: x-small;" href="admmlist.php?<?php echo _rsid; ?>">Mailing List Manager</a><br>
	<a style="font-size: x-small;" href="admnntp.php?<?php echo _rsid; ?>">Newsgroup Manager</a><br><br>
	
	<b>User Management</b><br>
	<a style="font-size: x-small;" href="admuser.php?<?php echo _rsid; ?>">Moderator/User Manager</a><br>
	<a style="font-size: x-small;" href="admgroups.php?<?php echo _rsid; ?>">Groups Manager</a><br>
	<a style="font-size: x-small;" href="admmassemail.php?<?php echo _rsid; ?>">Mass Email</a><br>
	<a style="font-size: x-small;" href="admlevel.php?<?php echo _rsid; ?>">Rank Manager</a><br><br>
	
	<b>Template Management</b><br>
	<a style="font-size: x-small;" href="admthemes.php?<?php echo _rsid; ?>">Theme Manager</a><br>
	<a style="font-size: x-small;" href="tmpllist.php?<?php echo _rsid; ?>">Template Editor</a><br>
	<a style="font-size: x-small;" href="msglist.php?<?php echo _rsid; ?>">Message Editor</a><br><br>

	<b>Icon Management</b><br>
	<a style="font-size: x-small;" href="admsmiley.php?<?php echo _rsid; ?>">Smiley Manager</a><br>
	<a style="font-size: x-small;" href="admforumicons.php?<?php echo _rsid; ?>">Forum Icon Manager</a><br>
	<a style="font-size: x-small;" href="admforumicons.php?<?php echo _rsid; ?>&which_dir=1">Message Icon Manager</a><br><br>
	
	<b>Avatar Management</b><br>
	<a style="font-size: x-small;" href="admapprove_avatar.php?<?php echo _rsid; ?>">Avatar Approval</a><br>
	<a style="font-size: x-small;" href="admavatar.php?<?php echo _rsid; ?>">Avatar Manager</a><br><br>
	
	<b>Filters</b><br>
	<a style="font-size: x-small;" href="admemail.php?<?php echo _rsid; ?>">Email filter</a><br>
	<a style="font-size: x-small;" href="admipfilter.php?<?php echo _rsid; ?>">IP filter</a><br>
	<a style="font-size: x-small;" href="admlogin.php?<?php echo _rsid; ?>">Login filter</a><br>
	<a style="font-size: x-small;" href="admext.php?<?php echo _rsid; ?>">File filter</a><br><br>

	<b>Forum Data Management</b><br>
	<a style="font-size: x-small;" href="admdump.php?<?php echo _rsid; ?>">Make forum datadump</a><br>
	<a style="font-size: x-small;" href="admimport.php?<?php echo _rsid; ?>">Import forum data</a><br>
</td></tr>
</table>
</td>
<td valign=top bgcolor="#ffffff" width="100%">
