<?php
/**
* copyright            : (C) 2001-2010 Advanced Internet Designs Inc.
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
	$all_mods = -1000000000;
	$all_grp_lead = -1000000001;	

	/* Find groups with members. */
	$groups = array();
	$c = uq('select count(*), g.id, g.name from '. $DBHOST_TBL_PREFIX .'group_members gm INNER JOIN '. $DBHOST_TBL_PREFIX .'groups g ON g.id=gm.group_id WHERE gm.user_id NOT IN(0,2147483647) GROUP BY g.id, g.name');
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
			echo errorify('Invalid group id!');
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
				$c = uq('SELECT '. $fld .' FROM '. $DBHOST_TBL_PREFIX .'users u WHERE u.id > 1 AND level_id='.($gid * -1).(isset($_POST['ignore_override']) ? '' : ' AND id > 1 AND (users_opt & 8)=0'));
			}
		}

		$mails_sent = 0;
		$mails_failed = 0;
		if (!empty($_POST['pm'])) {
			define('no_inline', 1);
			fud_use('ssu.inc');
			$to = array();
			while ($r = db_rowarr($c)) {
				$to[] = (int)$r[0];
			}
			$mails_sent = count($to);
			if ($to) {
				send_status_update($to, '', '', $_POST['subject'], $_POST['body']);
			}
		} else {
			$email_block = 50;
			$email_block_stat = 0;
			$to = array();

			if (!($FUD_OPT_1 & 512)) {	// USE_SMTP
				$header = 'From: '. $ADMIN_EMAIL. "\r\nErrors-To: ". $ADMIN_EMAIL. "\r\nReply-To: ". $ADMIN_EMAIL. "\r\nX-Mailer: FUDforum v". $GLOBALS['FORUM_VERSION']. "\r\nMIME-Version: 1.0\r\nContent-Type: text/plain; charset=". $charset. "\r\nContent-Transfer-Encoding: 8bit";
			
				$_POST['body'] = str_replace("\n.", "\n..", $_POST['body']);

				while ($r = db_rowarr($c)) {
					$to[] = $r[0];
					if (!(++$email_block_stat % $email_block)) {
						$email_block_stat = 0;
						$bcc = implode(', ', $to) . "\r\n";
						$mail_success = @mail(' ', encode_subject($_POST['subject']), $_POST['body'], $header.'\nBcc: '.$bcc);
						if ($mail_success) {
							$mails_sent = $mails_sent + count($to);
						} else {
							$mails_failed = $mails_failed + count($to);
							fud_logerror('Mass Mail ['. $_POST['subject'] .'] to ['. $to[0] .', ...] not accepted for delivery.', 'fud_errors');
						}
						$to = array();
					}
				}
				unset($c);
				if ($to) {
					$bcc = implode(', ', $to) ."\r\n";
					$mail_success = @mail(' ', encode_subject($_POST['subject']), $_POST['body'], $header.'\nBcc: '.$bcc);
					if ($mail_success) {
						$mails_sent = $mails_sent + count($to);
					} else {
						$mails_failed = $mails_failed + count($to);
						fud_logerror('Mass Mail ['. $_POST['subject'] .'] to ['. $to[0] .', ...] not accepted for delivery.', 'fud_errors');
					}
				}
			} else {
				$header = 'Errors-To: '. $ADMIN_EMAIL. "\r\nReply-To: ". $ADMIN_EMAIL. "\r\nMIME-Version: 1.0\r\nContent-Type: text/plain; charset=". $charset. "\r\nContent-Transfer-Encoding: 8bit";
			
				$smtp = new fud_smtp;
				$smtp->msg = str_replace("\n.", "\n..", $_POST['body']);
				$smtp->subject = encode_subject($_POST['subject']);
				$smtp->from = $ADMIN_EMAIL;
				$smtp->headers = $header;

				while ($r = db_rowarr($c)) {
					$to[] = $r[0];
					if (!(++$email_block_stat % $email_block)) {
						$email_block_stat = 0;

						$smtp->to = $to;
						$mail_success = $smtp->send_smtp_email();
						
						if ($mail_success) {
							$mails_sent = $mails_sent + count($to);
						} else {
							$mails_failed = $mails_failed + count($to);
						}

						$to = array();
					}
				}
				if (count($to)) {
					$smtp->to = $to;
					$mail_success = $smtp->send_smtp_email();

					if ($mail_success) {
						$mails_sent = $mails_sent + count($to);
					} else {
						$mails_failed = $mails_failed + count($to);
					}
				}
			}
		}
		if ($mails_sent) {
			echo successify($mails_sent .' '. (empty($_POST['pm']) ? 'Mail(s)' : 'Private Message(s)') .' were sent.');
		}
		if ($mails_failed) {
			echo errorify('Sending of '. $mails_failed .' '. (empty($_POST['pm']) ? 'Mail(s)' : 'Private Message(s)') .' failed, please check the <a href="admerr.php?'. __adm_rsid .'#fud_errors">Error Log</a> for more information.');
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
	<td>Send Messages via PM</td>
	<td><input type="checkbox" name="pm" value="1" /></td>
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
			<label><input type="checkbox" name="ignore_override" value="1" /> Ignore User Override</label>
			<input tabindex="3" type="submit" value="Send" name="btn_submit" />
		</td>
	</tr>
</table>
</form>
<?php require($WWW_ROOT_DISK .'adm/footer.php'); ?>
