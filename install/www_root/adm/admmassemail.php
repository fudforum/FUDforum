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

	require('./GLOBALS.php');
	fud_use('adm.inc', true);
	fud_use('widgets.inc', true);
	fud_use('smtp.inc');
	fud_use('iemail.inc');

	require($WWW_ROOT_DISK .'adm/header.php');

	/* Special user groups. */
	$all_mods     = -1000000000;
	$all_grp_lead = -1000000001;	

	/* Find groups with members. */
	$groups = array();
	$c = uq('SELECT count(*), g.id, g.name FROM '. $DBHOST_TBL_PREFIX .'group_members gm INNER JOIN '. $DBHOST_TBL_PREFIX .'groups g ON g.id=gm.group_id WHERE gm.user_id NOT IN(0,2147483647) GROUP BY g.id, g.name');
	while (list($cnt, $gid, $gname) = db_rowarr($c)) {
		$groups[$gid] = array($gname, $cnt);
	}
	unset($c);
	$err = 0;

	if (!empty($_POST['subject']) && !empty($_POST['body'])) {
		$fld = !empty($_POST['pm']) ? 'u.id' : 'email';
		if (!$_POST['group']) {
			$c = uq('SELECT '. $fld .' FROM '. $DBHOST_TBL_PREFIX .'users u WHERE u.id > 1'. (isset($_POST['ignore_override']) ? '' : ' AND '. q_bitand('users_opt', 8) .'=0'));
		} else if (!isset($groups[$_POST['group']]) && $_POST['group'] != $all_mods && $_POST['group'] != $all_grp_lead && $_POST['group'] > 0) {
			pf(errorify('Invalid group id!'));
			$err = 1;
			$c = uq('SELECT '. $fld .' FROM '. $DBHOST_TBL_PREFIX .'users u WHERE id=-1');
		} else {
			$gid = (int) $_POST['group'];
			if ($gid > 0) {
				$c = uq('SELECT '. $fld .' FROM '. $DBHOST_TBL_PREFIX .'group_members gm INNER JOIN '. $DBHOST_TBL_PREFIX .'users u ON u.id=gm.user_id WHERE u.id > 1 AND gm.group_id='.$gid.(isset($_POST['ignore_override']) ? '' : ' AND '. q_bitand('users_opt', 8) .'=0'));
			} else if ($gid == $all_mods) {
				$c = uq('SELECT DISTINCT('. $fld .') FROM '. $DBHOST_TBL_PREFIX .'mod m INNER JOIN '. $DBHOST_TBL_PREFIX .'users u ON u.id=m.user_id WHERE u.id > 1'.(isset($_POST['ignore_override']) ? '' : ' AND '. q_bitand('users_opt', 8) .'=0'));
			} else if ($gid == $all_grp_lead) {
				$c = uq('SELECT '. $fld .' FROM '. $DBHOST_TBL_PREFIX .'group_members gm INNER JOIN '. $DBHOST_TBL_PREFIX .'users u ON u.id=gm.user_id WHERE u.id > 1 AND (gm.group_members_opt & 131072) '.(isset($_POST['ignore_override']) ? '' : ' AND '. q_bitand('users_opt', 8) .'=0'));
			} else {
				$c = uq('SELECT '. $fld .' FROM '. $DBHOST_TBL_PREFIX .'users u WHERE u.id > 1 AND level_id='. ($gid * -1).(isset($_POST['ignore_override']) ? '' : ' AND id > 1 AND '. q_bitand('users_opt', 8) .'=0'));
			}
		}

		$mails_sent   = 0;
		$mails_failed = 0;
		if (!empty($_POST['pm'])) {	// Send private messages.
			define('no_inline', 1);
			fud_use('ssu.inc');
			$to = array();
			while ($r = db_rowarr($c)) {
				$to[] = (int)$r[0];
			}
			$mails_sent = count($to);
			if ($to && !isset($_POST['dryrun'])) {
				send_status_update($to, '', '', $_POST['subject'], $_POST['body']);
			}
		} else {	// Send via E-mail.
			$email_batch     = 50;
			$email_batch_cnt = 0;
			$to = array();
			
			$addronly = preg_replace('/.*</', '<', $ADMIN_EMAIL);	// RFC 2822 Return-Path: <...>
			$header   = 'From: '. $ADMIN_EMAIL ."\r\nReply-To: ". $addronly ."\r\nX-Mailer: FUDforum v". $GLOBALS['FORUM_VERSION'] ."\r\nMIME-Version: 1.0\r\nContent-Type: text/plain; charset=". $charset ."\r\nContent-Transfer-Encoding: 8bit";
			$subject  = encode_subject($_POST['subject']);
			$body     = str_replace("\n.", "\n..", $_POST['body']);

			if (!($FUD_OPT_1 & 512)) {	// Not USE_SMTP.
				while ($r = db_rowarr($c)) {
					$to[] = $r[0];
					if (!(++$email_batch_cnt % $email_batch)) {
						$email_batch_cnt = 0;
						$bcc = implode(', ', $to) . "\r\n";
						if (!isset($_POST['dryrun'])) {
							$mail_success = @mail(' ', $subject, $body, $header ."\nBcc: ". $bcc);
						} else {
							$mail_success = 1;
							pf('DRYRUN: Send e-mail to '. $bcc);
						}
						if ($mail_success) {
							$mails_sent = $mails_sent + count($to);
						} else {
							$mails_failed = $mails_failed + count($to);
							fud_logerror('Mass Mail ['. $subject .'] to ['. $to[0] .', ...] not accepted for delivery.', 'fud_errors');
						}
						$to = array();
					}
				}
				unset($c);
				if ($to) {
					$bcc = implode(', ', $to) ."\r\n";
					if (!isset($_POST['dryrun'])) {
						$mail_success = @mail(' ', $subject, $body, $header ."\nBcc: ". $bcc);
					} else {
						$mail_success = 1;
						pf('DRYRUN: Send e-mail to '. $bcc);
					}
					if ($mail_success) {
						$mails_sent = $mails_sent + count($to);
					} else {
						$mails_failed = $mails_failed + count($to);
						fud_logerror('Mass Mail ['. $subject .'] to ['. $to[0] .', ...] not accepted for delivery.', 'fud_errors');
					}
				}
			} else {
				$smtp = new fud_smtp;
				$smtp->msg = $body;
				$smtp->subject = $subject;
				$smtp->from = $ADMIN_EMAIL;
				$smtp->headers = $header;

				while ($r = db_rowarr($c)) {
					$to[] = $r[0];
					if (!(++$email_batch_cnt % $email_batch)) {
						$email_batch_cnt = 0;

						if (!isset($_POST['dryrun'])) {
							$smtp->to = $to;
							$mail_success = $smtp->send_smtp_email();
						} else {
							$mail_success = 1;
							pf('DRYRUN: Send e-mail via SMTP to '. $to);
						}
						
						if ($mail_success) {
							$mails_sent = $mails_sent + count($to);
						} else {
							$mails_failed = $mails_failed + count($to);
						}

						$to = array();
					}
				}
				if (count($to)) {
					if (!isset($_POST['dryrun'])) {
						$smtp->to = $to;
						$mail_success = $smtp->send_smtp_email();
					} else {
						$mail_success = 1;
						pf('DRYRUN: Send e-mail via SMTP to '. $to);
					}

					if ($mail_success) {
						$mails_sent = $mails_sent + count($to);
					} else {
						$mails_failed = $mails_failed + count($to);
					}
				}
			}
		}
		if ($mails_sent) {
			pf(successify($mails_sent .' '. (empty($_POST['pm']) ? 'Mail(s)' : 'Private Message(s)') .' were sent.'));
		}
		if ($mails_failed) {
			pf(errorify('Sending of '. $mails_failed .' '. (empty($_POST['pm']) ? 'Mail(s)' : 'Private Message(s)') .' failed, please check the <a href="admerr.php?'. __adm_rsid .'#fud_errors">Error Log</a> for more information.'));
		}
	}

?>
<h2>Mass Mail System</h2>
<p>Send a bulk message or newsletter to your forum's members:</p>
<form method="post" action="" id="a_frm">
<?php echo _hs; ?>
<table class="datatable solidtable">
<?php if($FUD_OPT_1 & 1024) { ?>
<tr class="field">
	<td><label for="pm">Send Messages via PM</label></td>
	<td><input type="checkbox" id="pm" name="pm" value="1" /></td>
</tr>
<?php
}
	echo '<tr class="field"><td valign="top">Send Messages To</td><td><select name="group">';
	echo '<option value="0">All Forum Members</option>';
	echo '<option value="'.$all_mods.'">All Forum Moderators</option>';
	echo '<option value="'.$all_grp_lead.'">All Group Leaders</option>';
	if ($groups) {	
		foreach ($groups as $k => $v) {
			echo '<option value="'. $k .'">'. $v[1] .' member(s) of group '. htmlspecialchars($v[0]) .'</option>';
		}
	}
	$r = uq('SELECT id, name FROM '. $DBHOST_TBL_PREFIX .'level');
	while (($v = db_rowarr($r))) {
		echo '<option value="-'. $v[0] .'">User Rank: '. $v[1] .'</option>';
	}
	unset($r);
	echo '</select></td></tr>';
?>
	<tr class="field">
		<td valign="top">Subject</td>
		<td><input tabindex="1" type="text" name="subject" size="40" value="" /></td>
	</tr>
	<tr class="field">
		<td colspan="2" valign="top">
			<b>Body</b><br />
			<textarea tabindex="2" name="body" cols="80" rows="20"></textarea>
		</td>
	</tr>
	<tr class="fieldaction">
		<td colspan="2" align="right">
			<label><input type="checkbox" name="dryrun" value="1" /> Dry run</label>
			<label><input type="checkbox" name="ignore_override" value="1" /> Ignore User Override</label>
			<input tabindex="4" type="submit" value="Send" name="btn_submit" />
		</td>
	</tr>
</table>
</form>
<?php require($WWW_ROOT_DISK .'adm/footer.php'); ?>
