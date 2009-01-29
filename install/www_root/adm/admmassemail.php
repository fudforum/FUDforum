<?php
/**
* copyright            : (C) 2001-2009 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: admmassemail.php,v 1.58 2009/01/29 18:37:40 frank Exp $
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

	/* special user groups */
	$all_mods = -1000000000;
	$all_grp_lead = -1000000001;	

	/* find groups with members */
	$groups = array();
	$c = uq('select count(*), g.id, g.name from '.$DBHOST_TBL_PREFIX.'group_members gm INNER JOIN '.$DBHOST_TBL_PREFIX.'groups g ON g.id=gm.group_id WHERE gm.user_id NOT IN(0,2147483647) GROUP BY g.id, g.name');
	while (list($cnt, $gid, $gname) = db_rowarr($c)) {
		$groups[$gid] = array($gname, $cnt);
	}
	unset($c);
	$err = 0;

	if (!empty($_POST['subject']) && !empty($_POST['body'])) {
		if (!$_POST['group']) {
			$c = uq('SELECT email FROM '.$DBHOST_TBL_PREFIX.'users '.(isset($_POST['ignore_override']) ? '' : 'WHERE id > 1 AND (users_opt & 8)=0'));
		} else if (!isset($groups[$_POST['group']]) && $_POST['group'] != $all_mods && $_POST['group'] != $all_grp_lead && $_POST['group'] > 0) {
			echo '<font color="+1" color="red">Invalid group id</font><br />';
			$err = 1;
			$c = uq('SELECT id FROM '.$DBHOST_TBL_PREFIX.'users WHERE id=-1');
		} else {
			$gid = (int) $_POST['group'];
			$fld = !empty($_POST['pm']) ? 'u.id' : 'email';

			if ($gid > 0) {
				$c = uq('SELECT '.$fld.' FROM '.$DBHOST_TBL_PREFIX.'group_members gm INNER JOIN '.$DBHOST_TBL_PREFIX.'users u ON u.id=gm.user_id WHERE gm.group_id='.$gid.(isset($_POST['ignore_override']) ? '' : ' AND (users_opt & 8)=0'));
			} else if ($gid == $all_mods) {
				$c = uq('SELECT DISTINCT('.$fld.') FROM '.$DBHOST_TBL_PREFIX.'mod m INNER JOIN '.$DBHOST_TBL_PREFIX.'users u ON u.id=m.user_id '.(isset($_POST['ignore_override']) ? '' : ' WHERE (users_opt & 8)=0'));
			} else if ($gid == $all_grp_lead) {
				$c = uq('SELECT '.$fld.' FROM '.$DBHOST_TBL_PREFIX.'group_members gm INNER JOIN '.$DBHOST_TBL_PREFIX.'users u ON u.id=gm.user_id WHERE (gm.group_members_opt & 131072) '.(isset($_POST['ignore_override']) ? '' : ' AND (users_opt & 8)=0'));
			} else {
				$c = uq('SELECT '.$fld.' FROM '.$DBHOST_TBL_PREFIX.'users u WHERE level_id='.($gid * -1).(isset($_POST['ignore_override']) ? '' : ' AND id > 1 AND (users_opt & 8)=0'));
			}
		}

		if (!empty($_POST['pm'])) {
			define('no_inline', 1);
			fud_use('ssu.inc');
			$to = array();
			while ($r = db_rowarr($c)) {
				$to[] = (int)$r[0];
			}
			$total = count($to);
			if ($to) {
				send_status_update($to, '', '', $_POST['subject'], $_POST['body']);
			}
		} else {
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
						$bcc = implode(', ', $to) . "\r\n";
						mail(' ', encode_subject($_POST['subject']), $_POST['body'], "From: ".$ADMIN_EMAIL."\r\nReply-To: ".$ADMIN_EMAIL."\r\nErrors-To: ".$ADMIN_EMAIL."\r\nX-Mailer: FUDforum v".$GLOBALS['FORUM_VERSION']."\r\nBcc: ".$bcc);
						$to = array();
					}
					++$total;
				}
				unset($c);
				if ($to) {
					$bcc = implode(', ', $to) . "\r\n";
					mail(' ', encode_subject($_POST['subject']), $_POST['body'], "From: ".$ADMIN_EMAIL."\r\nReply-To: ".$ADMIN_EMAIL."\r\nErrors-To: ".$ADMIN_EMAIL."\r\nX-Mailer: FUDforum v".$GLOBALS['FORUM_VERSION']."\r\nBcc: ".$bcc);
				}
			} else {
				$smtp = new fud_smtp;
				$smtp->msg = str_replace("\n.", "\n..", $_POST['body']);
				$smtp->subject = encode_subject($_POST['subject']);
				$smtp->from = $ADMIN_EMAIL;
				$smtp->headers = "Reply-To: ".$ADMIN_EMAIL."\r\nErrors-To: ".$ADMIN_EMAIL."\r\n";

				while ($r = db_rowarr($c)) {
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
		}
		if (!$err) {
			echo '<font size="+1" color="green">'.$total.' '.(empty($_POST['pm']) ? 'E-mails' : 'Private Mesages').' were sent</font><br />';
		}
	}

	require($WWW_ROOT_DISK . 'adm/admpanel.php');
?>
<h2>Mass Mail System</h2>
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
			echo '<option value="'.$k.'">'.$v[1].' member(s) of group '.htmlspecialchars($v[0]).'</option>';
		}
	}
	$r = uq("SELECT id, name FROM ".$DBHOST_TBL_PREFIX."level");
	while (($v = db_rowarr($r))) {
		echo '<option value="-'.$v[0].'">User Rank: '.$v[1].'</option>';
	}
	unset($r);
	echo '</select></td></tr>';
?>
	<tr class="field">
		<td valign="top">Subject</td>
		<td><input tabindex="1" type="text" name="subject" value="" /></td>
	</tr>
	<tr class="field">
		<td colspan="2" valign="top">
			<b>Body</b><br />
			<textarea tabindex="2" name="body" cols="80" rows="25"></textarea>
		</td>
	</tr>
	<tr class="fieldaction">
		<td colspan="2" align="right">
			<input type="checkbox" name="ignore_override" value="1" /> Ignore User Override <input tabindex="3" type="submit" value="Send" name="btn_submit" />
		</td>
	</tr>
</table>
</form>
<script type="text/javascript">
/* <![CDATA[ */
document.forms['a_frm'].subject.focus();
/* ]]> */
</script>
<?php require($WWW_ROOT_DISK . 'adm/admclose.html'); ?>
