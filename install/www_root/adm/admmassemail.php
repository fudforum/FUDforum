<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: admmassemail.php,v 1.2 2002/06/18 18:26:10 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/
	
	define('admin_form', 1);
	
	include_once "GLOBALS.php";
	
	fud_use('db.inc');
	fud_use('static/widgets.inc');
	fud_use('util.inc');
	fud_use('static/adm.inc');
	fud_use('users.inc');
	
	list($ses, $usr) = initadm();
	
	if ( $btn_submit ) {
		if ( !$ignore_override ) $ignore_override_q = " WHERE ignore_admin='N'";
		$r = q("SELECT email, login FROM ".$GLOBALS['MYSQL_TBL_PREFIX']."users".$ignore_override_q);
		$email_block = 50;
		$email_block_stat = 0;
		$send_to = $GLOBALS['ADMIN_EMAIL'];
		
		$subject = stripslashes($subject);
		$body = stripslashes($body);
		
		while ( $obj = db_rowobj($r) ) {
			$bcc .= $obj->email.', ';
			if ( !(++$email_block_stat%$email_block) ) {
				$email_block_stat=0;
				$bcc = substr($bcc, 0, -2)."\r\n";
				mail($send_to, $subject, $body, "From: ".$ADMIN_EMAIL."\r\nReply-To: ".$ADMIN_EMAIL."\r\nErrors-To: ".$ADMIN_EMAIL."\r\nX-Mailer: FUDforum v".$GLOBALS['FORUM_VERSION']."\r\nBcc: ".$bcc);
				$bcc = NULL;
			}
		}
		
		if( $bcc ) {
			$bcc = substr($bcc, 0, -2)."\r\n";
			mail($send_to, $subject, $body, "From: ".$ADMIN_EMAIL."\r\nReply-To: ".$ADMIN_EMAIL."\r\nErrors-To: ".$ADMIN_EMAIL."\r\nX-Mailer: FUDforum\r\nBcc: ".$bcc);
		}
		
		qf($r);
	}
	
	cache_buster();

include('admpanel.php'); 
?>
<h2>Mass Mail System</h2>
<form method="post" name="a_frm">
<?php echo _hs; ?>
<table border=0 cellspacing=1 cellpadding=3>
	<tr bgcolor="#bff8ff">
		<td valign=top>Subject</td>
		<td><input type="text" name="subject" value="<?php echo htmlspecialchars($subject); ?>"></td>
	</tr>
	<tr bgcolor="#bff8ff">
		<td colspan=2 valign=top>
			<b>Body</b><br>
			<textarea name="body" cols=80 rows=25><?php echo htmlspecialchars($body); ?></textarea>
		</td>
	</tr>
	<tr bgcolor="#bff8ff">
		<td colspan=2 align=right>
			<input type="checkbox" name="ignore_override" value="1"<?php echo (($ignore_override)?' checked':''); ?>> Ignore User Override <input type="submit" value="Send" name="btn_submit">
		</td>
	</tr>
</table>
</form>
<?php readfile('admclose.html'); ?>