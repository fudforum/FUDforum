<?php
/***************************************************************************
* copyright            : (C) 2001-2003 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: admrdf.php,v 1.11 2003/12/08 15:27:43 hackie Exp $
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

	$help_ar = read_help();

	if (isset($_POST['form_posted'])) {
		$NEW_FUD_OPT_2 = 0;

		foreach ($_POST as $k => $v) {
			if (!strncmp($k, 'CF_', 3)) {
				$k = substr($k, 3);
				if (!isset($GLOBALS[$k]) || $GLOBALS[$k] != $v) {
					$ch_list[$k] = is_numeric($v) ? (int) $v : $v;
				}
			} else if (!strncmp($k, 'FUD_OPT_2', 9)) {
				$NEW_FUD_OPT_2 |= (int) $v;
			}
		}

		if (($NEW_FUD_OPT_2 ^ $FUD_OPT_2) & (33554432|16777216|67108864)) {
			if (!($NEW_FUD_OPT_2 & 33554432)) {
				$FUD_OPT_2 &= ~33554432;
			} else {
				$FUD_OPT_2 |= 33554432;
			}
			if (!($NEW_FUD_OPT_2 & 16777216)) {
				$FUD_OPT_2 &= ~16777216;
			} else {
				$FUD_OPT_2 |= 16777216;
			}
			if (!($NEW_FUD_OPT_2 & 67108864)) {
				$FUD_OPT_2 &= ~67108864;
			} else {
				$FUD_OPT_2 |= 67108864;
			}
			$ch_list['FUD_OPT_2'] = $FUD_OPT_2;
		}

		if (isset($ch_list)) {
			change_global_settings($ch_list);
			/* put the settings 'live' so they can be seen on the form */
			foreach ($ch_list as $k => $v) {
				$GLOBALS[$k] = $v;
			}
		}
	}

	require($WWW_ROOT_DISK . 'adm/admpanel.php');

	$rdf_url = $WWW_ROOT . 'rdf.php';
?>
<h2>RDF Feed Configuration</h2>
<form method="post" action="admrdf.php"><?php echo _hs; ?>
<table class="datatable solidtable">
<?php
	print_bit_field('RDF Feed Enabled', 'RDF_ENABLED');
	print_bit_field('RDF Authentication', 'RDF_AUTH');
	print_reg_field('User id', 'RDF_AUTH_ID');
	print_reg_field('Maximum number of result', 'RDF_MAX_N_RESULTS');
	print_bit_field('Allow user data retrieval', 'RDF_ALLOW_USER_DATA');
?>
<tr class="fieldaction"><td colspan=2 align=right><input type="submit" name="btn_submit" value="Change Settings"></td></tr>
</table>
<input type="hidden" name="form_posted" value="1">
</form>
<br>
<table class="datatable">
<tr><th><b>Quick RDF Tutorial</b></th></tr>
<tr><td class="tutor">
If enabled, the RDF stream for your forum can be found at: <a href="<?php echo $rdf_url; ?>" target="_blank"><?php echo $rdf_url; ?></a><br />
The streams has three modes of operation, which are tailored for a specific data that you wish to fetch. You
can specify the mode by passing the 'mode' parameter via GET to the rdf script. The support modes are 'u', for
user information retrieval, 't' for topic retrieval and 'm' for message retrieval. Each mode has a number of
options that allow you to specify the exact nature of the data you wish to fetch. Below is the explanation of
those flags, it should be noted that flags are not exclusive and you can use as many as you like in a single
request.<br />
A fully functional parser of the FUDforum RDF can be found at: <b><?php echo $GLOBALS['DATA_DIR']; ?>scripts/rdf_parser.php</b>
<br /><br />
<h4><u><b>'m' mode (messages)</b></u></h4>
<blockquote>
	<table border=0 cellspacing=1 cellpadding=3>
		<tr><td><i>cat<i></td><td>Only retrieve messages from category with this id.</td></tr>
		<tr><td><i>frm<i></td><td>Only retrieve messages from forum with this id.</td></tr>
		<tr><td><i>th<i></td><td>Only retrieve messages from topic with this id.</td></tr>
		<tr><td><i>id<i></td><td>Retrieve a single message with the specified id.</td></tr>
		<tr><td><i>ds<i></td><td>Only retrieve messages posted after the specified date (unix timestamp).</td></tr>
		<tr><td><i>de<i></td><td>Only retrieve messages posted before the specified date (unix timestamp).</td></tr>
		<tr><td><i>n</i></td><td>Fetch no more then <i>n</i> messages (cannot be higher then overall maximum).</td></tr>
		<tr><td><i>o</i></td><td>Starting offset from which to begin fetching messages.</td></tr>
		<tr><td><i>l</i></td><td>Order messages from newest to oldest.</td></tr>
		<tr><td><i>basic</i></td><td>Output basic data parse-able by most RDF parsers.</td></tr>
	</table>
</blockquote>
<h4><u><b>'t' mode (topics)</b></u></h4>
<blockquote>
	<table border=0 cellspacing=1 cellpadding=3>
		<tr><td><i>cat<i></td><td>Only retrieve topics from category with this id.</td></tr>
		<tr><td><i>frm<i></td><td>Only retrieve topics from forum with this id.</td></tr>
		<tr><td><i>id<i></td><td>Retrieve a single topic with the specified id.</td></tr>
		<tr><td><i>ds<i></td><td>Only retrieve topics where the last message was posted after the specified date (unix timestamp).</td></tr>
		<tr><td><i>de<i></td><td>Only retrieve topics where the last message was posted before the specified date (unix timestamp).</td></tr>
		<tr><td><i>n</i></td><td>Fetch no more then <i>n</i> topics (cannot be higher then overall maximum).</td></tr>
		<tr><td><i>o</i></td><td>Starting offset from which to begin fetching topics.</td></tr>
		<tr><td><i>l</i></td><td>Order topics from newest to oldest.</td></tr>
	</table>
</blockquote>
<h4><u><b>'u' mode (users)</b></u></h4>
<blockquote>
	<table border=0 cellspacing=1 cellpadding=3>
		<tr><td><i>pc</i></td><td>Order users by number of messages posted, from largest to smallest.</td></tr>
		<tr><td><i>rd</i></td><td>Order users by registration, from most recent to oldest.</td></tr>
		<tr><td><i>cl</i></td><td>Only show users who are currently online.</td></tr>
		<tr><td><i>n</i></td><td>Fetch no more then <i>n</i> users (cannot be higher then overall maximum).</td></tr>
		<tr><td><i>o</i></td><td>Starting offset from which to begin fetching users.</td></tr>
	</table>
</blockquote>
<b>Example:</b><br />
The following link will fetch 10 most recent messages from your forum:<br />
<a href="<?php echo $rdf_url; ?>?mode=m&l=1&n=10&basic=1" target="_blank"><?php echo $rdf_url; ?>?mode=m&l=1&n=10</a>
</td></tr>
</table>
<?php require($WWW_ROOT_DISK . 'adm/admclose.html'); ?>
