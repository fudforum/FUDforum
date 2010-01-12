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

	require($WWW_ROOT_DISK . 'adm/header.php');
	
	$u1 = isset($_POST['u1']) ? $_POST['u1'] : '';
	$u2 = isset($_POST['u2']) ? $_POST['u2'] : '';

	if (isset($_POST['usr_merge'])) {
		if (empty($u1) || empty($u2)) {
			echo errorify('Please enter two user id\'s below.');
		} else if ($u1 == $u2) {
			echo errorify('Users cannot be the same.');
		} else if (!($id1 = q_singleval('SELECT id FROM '.$DBHOST_TBL_PREFIX.'users WHERE users_opt >= 1048576 AND (users_opt & 1048576) > 0 AND login='._esc($u1)))) {
				echo errorify('From user ('. $u1 .') not in DB or is an admin user.');
		} else if (!($id2 = q_singleval('SELECT id FROM '. $DBHOST_TBL_PREFIX .'users WHERE users_opt >= 1048576 AND (users_opt & 1048576) > 0 AND login='._esc($u2)))) {
				echo errorify('To user ('. $u2 .') not in DB or is an admin user.');
		} else {
			q('UPDATE '. $DBHOST_TBL_PREFIX .'msg SET poster_id = '. $id2 .' WHERE poster_id = '. $id1);
			q('UPDATE '. $DBHOST_TBL_PREFIX .'pmsg SET ouser_id = '. $id2 .' WHERE ouser_id = '. $id1);
			q('UPDATE '. $DBHOST_TBL_PREFIX .'pmsg SET duser_id = '. $id2 .' WHERE duser_id = '. $id1);
			q('UPDATE '. $DBHOST_TBL_PREFIX .'bookmarks SET	user_id = '. $id2 .' WHERE user_id = '. $id1);
			// DEL?  q('UPDATE '. $DBHOST_TBL_PREFIX .'read SET user_id = '. $id2 .' WHERE user_id = '. $id1);
			// DEL? q('UPDATE '. $DBHOST_TBL_PREFIX .'forum_read SET user_id = '. $id2 .' WHERE user_id = '. $id1);
			q('UPDATE '. $DBHOST_TBL_PREFIX .'user_ignore SET user_id = '. $id2 .' WHERE user_id = '. $id1);
			q('UPDATE '. $DBHOST_TBL_PREFIX .'thread_rate_track SET	user_id = '. $id2 .' WHERE 	user_id = '. $id1);
			// DEL? q('UPDATE '. $DBHOST_TBL_PREFIX .'thread_notify SET	user_id = '. $id2 .' WHERE user_id = '. $id1);
			q('DELETE FROM '. $DBHOST_TBL_PREFIX .'users WHERE id = '. $id1);

			echo successify('Users '. $u1 .' and '. $u2 .' were sucessfully merged.');
		}
	}
?>
<h2>Merge users</h2>
<p>This control panel will merge the posts from two seperate user accounts into a single account. This action cannot be undone.</p>

<form id="frm_usr" method="post" action="admusermerge.php">
<?php echo _hs; ?>
<table class="datatable solidtable">
	<tr class="field">
		<td>From user:<br /><font size=-1>(will be deleted)</font></td>
		<td><input tabindex="2" type="text" name="u1" value="<?php echo $u1; ?>" size="30" /></td>
	</tr>
	<tr class="field">
		<td>To user:<br /><font size=-1>(target for messages, PM's, etc.)</font></td>
		<td><input tabindex="1" type="text" name="u2" value="<?php echo $u2; ?>" size="30" /></td>
	</tr>
	<tr class="fieldaction">
		<td colspan="2" align="right"><input type="submit" value="Merge Users" tabindex="5" name="usr_merge" /></td>
	</tr>
</table>
</form>
<a href="admuser.php?<?php echo __adm_rsid; ?>">&laquo; Back to Moderator/User Manager</a>
<script type="text/javascript">
/* <![CDATA[ */
document.forms['frm_usr'].u1.focus();
/* ]]> */
</script>
<?php require($WWW_ROOT_DISK . 'adm/footer.php'); ?>
