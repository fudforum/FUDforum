<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: admmassemail.php,v 1.11 2003/05/12 16:49:55 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/
	
	require('GLOBALS.php');
	fud_use('adm.inc', true);
	fud_use('widgets.inc', true);
	fud_use('smtp.inc');
	
	if (isset($_POST['btn_submit']) && !empty($_POST['subject']) && !empty($_POST['body'])) {
		$c = uq('SELECT email FROM '.$GLOBALS['DBHOST_TBL_PREFIX'].'users '.(isset($POST['ignore_override']) ? '' : 'WHERE ignore_admin=\'N\''));
		$email_block = 50;
		$email_block_stat = 0;
		$send_to = $GLOBALS['ADMIN_EMAIL'];
		$to = array();
		
		if ($GLOBALS['USE_SMTP'] == 'N') {
			while ($r = db_rowarr($c)) {
				$to[] = $r[0];
				if (!(++$email_block_stat % $email_block)) {
					$email_block_stat = 0;
					$send_to = array_pop($to);
					$bcc = implode(', ', $to) . "\r\n";
					mail($send_to, $_POST['subject'], $_POST['body'], "From: ".$ADMIN_EMAIL."\r\nReply-To: ".$ADMIN_EMAIL."\r\nErrors-To: ".$ADMIN_EMAIL."\r\nX-Mailer: FUDforum v".$GLOBALS['FORUM_VERSION']."\r\nBcc: ".$bcc);
					$to = array();
				}
			}
			if (count($to)) {
				$send_to = array_pop($to);
				$bcc = implode(', ', $to) . "\r\n";
				mail($send_to, $_POST['subject'], $_POST['body'], "From: ".$ADMIN_EMAIL."\r\nReply-To: ".$ADMIN_EMAIL."\r\nErrors-To: ".$ADMIN_EMAIL."\r\nX-Mailer: FUDforum v".$GLOBALS['FORUM_VERSION']."\r\nBcc: ".$bcc);
			}
		} else {
			$smtp = new fud_smtp;
			$smtp->msg = $_POST['body'];
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
			}	
			if (count($to)) {
				$smtp->to = $to;
				$smtp->send_smtp_email();
			}
		}	
		
		qf($r);
	}
	
	require($WWW_ROOT_DISK . 'adm/admpanel.php'); 
?>
<h2>Mass Mail System</h2>
<form method="post" name="a_frm">
<?php echo _hs; ?>
<table border=0 cellspacing=1 cellpadding=3>
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