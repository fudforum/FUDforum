<?php
/***************************************************************************
* copyright            : (C) 2001-2003 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: admmassemail.php,v 1.21 2003/10/09 14:34:32 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it 
* under the terms of the GNU General Public License as published by the 
* Free Software Foundation; either version 2 of the License, or 
* (at your option) any later version.
***************************************************************************/

	require('./GLOBALS.php');
	fud_use('adm.inc', true);
	fud_use('widgets.inc', true);
	fud_use('smtp.inc');

	/* find groups with members */
	$groups = array();
	$c = uq('select count(*), g.id, g.name from '.$DBHOST_TBL_PREFIX.'group_members gm INNER JOIN '.$DBHOST_TBL_PREFIX.'groups g ON g.id=gm.group_id WHERE gm.user_id NOT IN(0,2147483647) GROUP BY g.id, g.name');
	while (list($cnt, $gid, $gname) = db_rowarr($c)) {
		$groups[$gid] = array($gname, $cnt);
	}
	qf($c);

	$err = 0;

	if (!empty($_POST['subject']) && !empty($_POST['body'])) {
		if (!$_POST['group']) {
			$c = uq('SELECT email FROM '.$DBHOST_TBL_PREFIX.'users '.(isset($POST['ignore_override']) ? '' : 'WHERE (users_opt & 8)=0'));
		} else if (!isset($groups[$_POST['group']])) {
			echo '<font color="+1" color="red">Invalid group id</font><br />';
			$err = 1;
			$c = uq('SELECT id FROM '.$DBHOST_TBL_PREFIX.'users WHERE id=-1');
		} else {
			$c = uq('SELECT email FROM '.$DBHOST_TBL_PREFIX.'group_members gm INNER JOIN '.$DBHOST_TBL_PREFIX.'users u ON u.id=gm.user_id WHERE gm.group_id='.$_POST['group'].(isset($POST['ignore_override']) ? '' : ' AND (users_opt & 8)=0'));
		}

		$email_block = 50;
		$total = $email_block_stat = 0;
		$send_to = $ADMIN_EMAIL;
		$to = array();

		if (!($FUD_OPT_1 & 512)) {
			if (version_compare("4.3.3RC2", PHP_VERSION, ">")) {
				$_POST['body'] = str_replace("\n.", "\n..", $_POST['body']);
			}

			while ($r = db_rowarr($c)) {
				$to[] = $r[0];
				if (!(++$email_block_stat % $email_block)) {
					$email_block_stat = 0;
					$send_to = array_pop($to);
					$bcc = implode(', ', $to) . "\r\n";
					mail($send_to, $_POST['subject'], $_POST['body'], "From: ".$ADMIN_EMAIL."\r\nReply-To: ".$ADMIN_EMAIL."\r\nErrors-To: ".$ADMIN_EMAIL."\r\nX-Mailer: FUDforum v".$GLOBALS['FORUM_VERSION']."\r\nBcc: ".$bcc);
					$to = array();
				}
				++$total;
			}
			if (count($to)) {
				$send_to = array_pop($to);
				$bcc = implode(', ', $to) . "\r\n";
				mail($send_to, $_POST['subject'], $_POST['body'], "From: ".$ADMIN_EMAIL."\r\nReply-To: ".$ADMIN_EMAIL."\r\nErrors-To: ".$ADMIN_EMAIL."\r\nX-Mailer: FUDforum v".$GLOBALS['FORUM_VERSION']."\r\nBcc: ".$bcc);
			}
		} else {
			$smtp = new fud_smtp;
			$smtp->msg = str_replace("\n.", "\n..", $_POST['body']);
			$smtp->subject = $_POST['subject'];
			$smtp->from = $ADMIN_EMAIL;
			$smtp->headers = "Reply-To: ".$ADMIN_EMAIL."\r\nErrors-To: ".$ADMIN_EMAIL."\r\n";

			while ($r = db_rowobj($c)) {
				$to[] = $r[0];
				if (!(++$email_block_stat % $email_block)) {
					$email_block_stat = 0;

					$smtp->to = $to;
					$smtp->send_smtp_email();

					$to = array();
				}
				++$total;
			}
			if (count($to)) {
				$smtp->to = $to;
				$smtp->send_smtp_email();
			}
		}

		qf($r);

		if (!$err) {
			echo '<font size="+1" color="green">'.$total.' E-mails were sent</font><br />';
		}
	}

	require($WWW_ROOT_DISK . 'adm/admpanel.php');
?>
<h2>Mass Mail System</h2>
<form method="post" name="a_frm">
<?php echo _hs; ?>
<table border=0 cellspacing=1 cellpadding=3>
<?php
	if ($groups) {
		echo '<tr bgcolor="#bff8ff"><td valign=top>Send E-mails To</td><td><select name="group">';
		echo '<option value="0">All Forum Members</option>';
		foreach ($groups as $k => $v) {
			echo '<option value="'.$k.'">'.$v[1].' member(s) of group '.htmlspecialchars($v[0]).'</option>';
		}
		echo '</select></td></tr>';
	} else {
		echo '<input type="hidden" name="group" value="0">';
	}
?>
	<tr bgcolor="#bff8ff">
		<td valign=top>Subject</td>
		<td><input type="text" name="subject" value=""></td>
	</tr>
	<tr bgcolor="#bff8ff">
		<td colspan=2 valign=top>
			<b>Body</b><br>
			<textarea name="body" cols=80 rows=25></textarea>
		</td>
	</tr>
	<tr bgcolor="#bff8ff">
		<td colspan=2 align=right>
			<input type="checkbox" name="ignore_override" value="1"> Ignore User Override <input type="submit" value="Send" name="btn_submit">
		</td>
	</tr>
</table>
</form>
<?php require($WWW_ROOT_DISK . 'adm/admclose.html'); ?>