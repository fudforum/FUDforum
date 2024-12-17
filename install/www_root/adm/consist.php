<?php
/**
* copyright            : (C) 2001-2024 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id$
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; version 2 of the License.
**/

function draw_stat($text)
{
	pf(htmlspecialchars($text));
}

function draw_info($cnt)
{
	draw_stat(($cnt < 1 ? 'OK' : $cnt .' entries unmatched, deleted.'));
}

function delete_zero($tbl, $q)
{
	if (__dbtype__ == 'mysql') {	// MySQL is full of crap (can't specify target table for update in FROM).
		q('DELETE '. substr($q, 7, strpos($q, '.') - 7) .' '. strstr($q, 'FROM'));
		draw_info(db_affected());
	} else {	// All other databases.
		q('DELETE FROM '. $tbl .' WHERE id IN ('. $q .')');
		draw_info(db_affected());
	}
}

/* main */
	require('./GLOBALS.php');

	// Run from command line.
	if (php_sapi_name() == 'cli') {
		if (isset($_SERVER['argv'][1]) && $_SERVER['argv'][1] == 'optimize') {
			$_GET['opt'] = 1;	// Run SQL optimizer.
		} elseif (isset($_SERVER['argv'][1]) && $_SERVER['argv'][1] == 'check') {
			$_POST['conf'] = 1;	// Run checks.
		} else {
			echo "Usage: php consist.php check|optimize\n";
			echo " - specify 'check' to run the consistency checker.\n";
			echo " - specify 'optimize' to run the SQL optimizer.\n";
			die();
		}

		fud_use('adm_cli.inc', 1);
	}

	fud_use('adm.inc', true);
	fud_use('glob.inc', true);
	fud_use('widgets.inc', true);
	fud_use('ext.inc', true);
	fud_use('ipfilter.inc', true);
	fud_use('login_filter.inc', true);
	fud_use('email_filter.inc', true);
	fud_use('customtags.inc', true);
	fud_use('groups_adm.inc', true);
	fud_use('sml_rcache.inc', true);
	fud_use('msg_icon_cache.inc', true);
	fud_use('cat.inc', true);
	fud_use('forum_adm.inc', true);
	fud_use('dbadmin.inc', true);
	fud_use('imsg_edt.inc');
	fud_use('err.inc');
	fud_use('private.inc');
	fud_use('th.inc');
	fud_use('ipoll.inc');
	fud_use('attach.inc');
	fud_use('groups.inc');
	fud_use('th_adm.inc');
	fud_use('users_reg.inc');
	fud_use('custom_field_adm.inc', true);
	fud_use('announce_adm.inc', true);
	fud_use('plugin_adm.inc', true);
	fud_use('spider_adm.inc', true);

	if (isset($_POST['btn_cancel'])) {
		header('Location: '. $WWW_ROOT .'adm/index.php?'. __adm_rsidl);
		exit;
	}

	include($WWW_ROOT_DISK .'adm/header.php');

	if (!isset($_POST['conf']) && !isset($_GET['enable_forum']) && !isset($_GET['opt'])) {
?>
<h2>Forum Consistency</h2>

<div class="alert">
Consistency check is a complex process which may take several minutes to run.
While it is running, your forum will be disabled!
</div><br />
<form method="post" action="consist.php">

<fieldset>
	<legend>Thoroughness:</legend>
	<label><p>
		Perform a quick check:
		<input name="mode" value="1" type="checkbox">
	</p></label>
</fieldset>

<p>Do you wish to proceed?</p>
<input type="submit" name="btn_cancel" value="No" />&nbsp;&nbsp;&nbsp;<input type="submit" name="conf" value="Yes" />
<?php echo _hs; ?>
</form>
<?php
		require($WWW_ROOT_DISK .'adm/footer.php');
		exit;
	}
	
	if (isset($_GET['opt'])) {
		pf('<h3>Database Optimizer progress</h3>'); 
	} else {
		pf('<h3>Consisteny Checker progress</h3>'); 
		$thorough = empty($_POST['mode']) ? 1 : 0;
	}

	if ($FUD_OPT_1 & 1) {
		draw_stat('Disabling the forum for the duration of maintenance run.');
		maintenance_status(1);
	}

	if (isset($_GET['opt'])) {
		draw_stat('Optimizing forum\'s SQL tables.');
		optimize_fud_tables();
		draw_stat('Done: Optimizing forum\'s SQL tables.');

		if ($FUD_OPT_1 & 1 || isset($_GET['enable_forum'])) {
			draw_stat('Re-enabling the forum.');
			maintenance_status(0);
			pf('<br /><div class="tutor">Database tables were successfully optimized.</div>');
		} else {
			pf('<br/><div class="tutor">Your forum is currently disabled, to re-enable it go to the <a href="admglobal.php?'. __adm_rsid .'">Global Settings Manager</a> and re-enable it.</div>');
		}

		require($WWW_ROOT_DISK .'adm/footer.php');
		exit;
	}

	$tbl = $DBHOST_TBL_PREFIX;
	$tbls = get_fud_table_list();

	// Drop unused Thread View and Forum Lock tables.
	draw_stat('Validating lock and view tables');
	$forums = db_all('SELECT id FROM '. $tbl .'forum');
	foreach ($tbls as $k => $v) {
		if (preg_match('/^'. $tbl .'tv_(\d+)+$/', $v, $m) && !in_array($m[1], $forums)) {
			pf('Drop unused thread view table: '. $v);
			drop_table($v);
			unset($tbls[$k]);
		}
		if (preg_match('/^'. $tbl .'fl_(\d+)+$/', $v, $m) && !in_array($m[1], $forums)) {
			pf('Drop unused forum lock table: '. $v);
			drop_table($v);
			unset($tbls[$k]);
		}
	}

	// Drop and re-create Thread View and Forum Lock tables.
	foreach ($forums as $v) {
		$tv_tbl = $tbl .'tv_'. $v;
		if (!in_array($tv_tbl, $tbls)) {
			$tbls[] = $tv_tbl;
		}
		$fl_tbl = $tbl .'fl_'. $v;		
		if (!in_array($fl_tbl, $tbls)) {
			$tbls[] = $fl_tbl;
		}
		if ($thorough) {
			frm_add_view_tbl($tv_tbl);
			frm_add_lock_tbl($fl_tbl);
		}
	}

	/* Add private message lock table. */
	if (!in_array($tbl .'fl_pm', $tbls)) {
		frm_add_lock_tbl($tbl .'fl_pm');
	}
	unset($tmp);
	/* Add page lock table. */
	if (!in_array($tbl .'fl_pg', $tbls)) {
		frm_add_lock_tbl($tbl .'fl_pg');
	}
	unset($tmp);
	draw_stat('Done: Validating lock and view tables');

	// Add the various table aliases.
	array_push($tbls, $tbl .'users u', $tbl .'forum f', $tbl .'thread t', $tbl .'poll p', $tbl .'poll_opt po', $tbl .'poll_opt_track pot',
				$tbl .'msg m', $tbl .'pmsg pm', $tbl .'mod mm', $tbl .'thread_rate_track trt', $tbl .'msg_report mr', $tbl .'cat c',
				$tbl .'forum_notify fn', $tbl .'thread_notify tn', $tbl .'bookmarks bm', $tbl .'buddy b', $tbl .'user_ignore i', $tbl .'msg m1', $tbl .'msg m2',
				$tbl .'users u1', $tbl .'users u2', $tbl .'attach a', $tbl .'thr_exchange te', $tbl .'read r', $tbl .'mime mi',
				$tbl .'group_members gm', $tbl .'group_resources gr', $tbl .'groups g', $tbl .'group_members gm1', $tbl .'group_members gm2', 
				$tbl .'themes thm', $tbl .'index si', $tbl .'title_index ti');

	draw_stat('Locking the database for checking');
	db_lock(implode(' WRITE, ', $tbls) .' WRITE');
	draw_stat('Locked!');

	draw_stat('Validating category order');
	$oldp = -1; $tmp = array();
	$c = uq('SELECT id, view_order, parent FROM '. $tbl .'cat ORDER BY parent, view_order, id');
	while ($r = db_rowarr($c)) {
		if ($oldp != $r[2]) {
			$i = 1;
			$oldp = $r[2];
		}
		if ($r[1] != $i) {
			$tmp[(int)$r[0]] = $i;
		}
		++$i;
	}
	unset($c);
	foreach ($tmp as $k => $v) {
		q('UPDATE '. $tbl .'cat SET view_order='. $v .' WHERE id='. $k);
	}
	unset($r, $tmp);
	draw_stat('Done: Validating category order');

	draw_stat('Checking if moderator and users table match');
	delete_zero($tbl .'mod', 'SELECT mm.id FROM '. $tbl .'mod mm LEFT JOIN '. $tbl .'users u ON mm.user_id=u.id LEFT JOIN '. $tbl .'forum f ON f.id=mm.forum_id WHERE u.id IS NULL OR f.id IS NULL');

	draw_stat('Rebuilding moderators');
	rebuildmodlist();
	draw_stat('Done: Rebuilding moderators');

	draw_stat('Checking if all private messages have users');
	delete_zero($tbl .'pmsg', 'SELECT pm.id FROM '. $tbl .'pmsg pm LEFT JOIN '. $tbl .'users u ON u.id=pm.ouser_id LEFT JOIN '. $tbl .'users u2 ON u2.id=pm.duser_id WHERE '. q_bitand('pm.pmsg_opt', 16) .' > 0 AND (u.id IS NULL OR u2.id IS NULL)');

	draw_stat('Checking messages against users & threads');
	delete_zero($tbl .'msg', 'SELECT m.id FROM '. $tbl .'msg m LEFT JOIN '. $tbl .'users u ON u.id=m.poster_id LEFT JOIN '. $tbl .'thread t ON t.id=m.thread_id LEFT JOIN '. $tbl .'forum f ON f.id=t.forum_id WHERE (m.poster_id!=0 AND u.id IS NULL) OR t.id IS NULL OR f.id IS NULL');

	draw_stat('Checking threads against forums');
	delete_zero($tbl .'thread', 'SELECT t.id FROM '. $tbl .'thread t LEFT JOIN '. $tbl .'forum f ON f.id=t.forum_id WHERE f.id IS NULL');

	draw_stat('Checking message approvals');
	$m = db_all('SELECT m.id FROM '. $tbl .'msg m INNER JOIN '. $tbl .'thread t ON m.thread_id=t.id INNER JOIN '. $tbl .'forum f ON t.forum_id=f.id WHERE m.apr=0 AND '. q_bitand('f.forum_opt', 2) .' = 0');
	if ($m) {
		q('UPDATE '. $tbl .'msg SET apr=1 WHERE id IN('. implode(',', $m) .')');
		unset($m);
	}
	draw_stat('Done: Checking message approvals');

	$cnt = 0;
	$del = $tr = array();
	draw_stat('Checking threads against messages');
	q('UPDATE '. $tbl .'thread SET replies=0');
	$c = uq('SELECT m.thread_id, t.id, count(*) as cnt FROM '. $tbl .'thread t LEFT JOIN '. $tbl .'msg m ON t.id=m.thread_id WHERE m.apr=1 GROUP BY m.thread_id,t.id ORDER BY count(*)');
	while ($r = db_rowarr($c)) {
		if (!$r[0]) {
			$del[] = $r[1];
			++$cnt;
		} else {
			$tr[$r[2] - 1][] = $r[1];
		}
	}
	unset($c);
	if ($del) {
		q('DELETE FROM '. $tbl .'thread WHERE id='. implode(',', $del));
	}
	unset($tr[0]);
	foreach ($tr as $k => $v) {
		q('UPDATE '. $tbl .'thread SET replies='. $k .' WHERE id IN('. implode(',', $v) .')');
	}
	unset($tr, $del);
	draw_info($cnt);

	draw_stat('Checking thread last & first post ids');
	$m1 = $m2 = array();
	$c = uq('SELECT m1.id, m2.id, t.id FROM '. $tbl .'thread t LEFT JOIN '. $tbl .'msg m1 ON t.root_msg_id=m1.id LEFT JOIN '. $tbl .'msg m2 ON t.last_post_id=m2.id WHERE m1.id IS NULL or m2.id IS NULL');
	while ($r = db_rowarr($c)) {
		if (!$r[0]) {
			$m1[] = (int)$r[2];
		}
		if (!$r[1]) {
			$m2[] = (int)$r[2];
		}
	}
	unset($c);
	foreach ($m1 as $v) {
		if (!($root = q_singleval(q_limit('SELECT id FROM '. $tbl .'msg WHERE thread_id='. $v .' ORDER BY post_stamp', 1)))) {
			q('DELETE FROM '. $tbl .'thread WHERE id='. $v);
		} else {
			q('UPDATE '. $tbl .'thread SET root_msg_id='. $root .' WHERE id='. $v);
		}
	}
	foreach ($m2 as $v) {
		$r2 = db_saq(q_limit('SELECT id, post_stamp FROM '. $tbl .'msg WHERE thread_id='. $v .' ORDER BY post_stamp DESC', 1));
		if (!$r2) {
			q('DELETE FROM '. $tbl .'thread WHERE id='. $v);
		} else {
			q('UPDATE '. $tbl .'thread SET last_post_id='. $r2[0] .', last_post_date='. $r2[1] .' WHERE id='. $v);
		}
	}
	unset($m1, $m2);
	draw_stat('Done: Checking thread last & first post ids');

	draw_stat('Checking forum & topic relations');
	q('UPDATE '. $tbl .'forum SET thread_count=0, post_count=0, last_post_id=0');
	$tmp = array();
	$c = uq('SELECT SUM(replies), COUNT(*), t.forum_id, MAX(t.last_post_id) FROM '. $tbl .'thread t INNER JOIN '. $tbl .'msg m ON t.root_msg_id=m.id AND m.apr=1 WHERE t.moved_to=0 GROUP BY t.forum_id');
	while ($r = db_rowarr($c)) {
		if ($r[1]) {
			$tmp[] = $r;
		}
	}
	unset($c);
	foreach ($tmp as $r) {
		q('UPDATE '. $tbl .'forum SET thread_count='. $r[1] .', post_count='. ($r[0] + $r[1]) .', last_post_id='. (int)$r[3] .' WHERE id='. $r[2]);
	}
	unset($c, $tmp);
	draw_stat('Done: Checking forum & topic relations');

	draw_stat('Validating Forum Order');
	$cat = 0; $tmp = array();
	$c = uq('SELECT id, cat_id, view_order FROM '. $tbl .'forum WHERE cat_id>0 ORDER BY cat_id, view_order');
	while ($f = db_rowarr($c)) {
		if ($cat != $f[1]) {
			$i = 0;
			$cat = $f[1];
		}
		++$i;
		if ($i != $f[2]) {
			$tmp[(int)$f[0]] = $i;
		}
	}
	unset($c);
	foreach ($tmp as $k => $v) {
		q('UPDATE '. $tbl .'forum SET view_order='. $v .' WHERE id='. $k);
	}
	unset($tmp);
	draw_stat('Done: Validating Forum Order');

	draw_stat('Checking thread_exchange');
	delete_zero($tbl .'thr_exchange', 'SELECT te.id FROM '. $tbl .'thr_exchange te LEFT JOIN '. $tbl .'thread t ON t.id=te.th LEFT JOIN '. $tbl .'forum f ON f.id=te.frm WHERE t.id IS NULL or f.id IS NULL');

	draw_stat('Checking read table against users & threads');
	delete_zero($tbl .'read', 'SELECT r.id FROM '. $tbl .'read r LEFT JOIN '. $tbl .'users u ON r.user_id=u.id LEFT JOIN '. $tbl .'thread t ON r.thread_id=t.id WHERE t.id IS NULL OR u.id IS NULL');

	draw_stat('Checking file attachments against messages and private messages');
	$attach_rm = db_all('SELECT a.id FROM '. $tbl .'attach a LEFT JOIN '. $tbl .'msg m ON a.message_id=m.id WHERE m.id IS NULL AND attach_opt=0');
	$attach_rm = array_merge($attach_rm, db_all('SELECT a.id FROM '. $tbl .'attach a LEFT JOIN '. $tbl .'pmsg pm ON a.message_id=pm.id WHERE pm.id IS NULL AND attach_opt=1'));
	if (($cnt = count($attach_rm))) {
		foreach ($attach_rm as $a) {
			@unlink($FILE_STORE . $a .'atch');
		}
		q('DELETE FROM '. $tbl .'attach WHERE id IN('. implode(',', $attach_rm) .')');
	}
	draw_info($cnt);

	draw_stat('Rebuild attachment cache for regular messages');
	$oldm = '';
	$atr = array();
	q('UPDATE '. $tbl .'msg SET attach_cnt=0, attach_cache=NULL');
	$c = q('SELECT a.id, a.original_name, a.fsize, a.dlcount, COALESCE(mi.icon, \'unknown.gif\'), a.message_id FROM '. $tbl .'attach a LEFT JOIN '. $tbl .'mime mi ON a.mime_type=mi.id WHERE attach_opt=0');
	while ($r = db_rowarr($c)) {
		if ($oldm != $r[5]) {
			if ($oldm) {
				q('UPDATE '. $tbl .'msg SET attach_cnt='. count($atr) .', attach_cache='. ssn(serialize($atr)) .' WHERE id='. $oldm);
				$atr = array();
			}
			$oldm = $r[5];
		}
		unset($r[5]);
		$atr[] = $r;
	}
	unset($c);
	if ($atr) {
		q('UPDATE '. $tbl .'msg SET attach_cnt='. count($atr) .', attach_cache='. ssn(serialize($atr)) .' WHERE id='. $oldm);
		unset($atr);
	}
	draw_stat('Done: Rebuild attachment cache for regular messages');

	draw_stat('Rebuild attachment cache for private messages');
	q('UPDATE '. $tbl .'pmsg SET attach_cnt=0');
	$c = q('SELECT count(*), message_id FROM '. $tbl .'attach WHERE attach_opt=1 GROUP BY message_id');
	while ($r = db_rowarr($c)) {
		q('UPDATE '. $tbl .'pmsg SET attach_cnt='. $r[0] .' WHERE id='. $r[1]);
	}
	unset($c);
	draw_stat('Done: Rebuild attachment cache for private messages');

	draw_stat('Correcting Attachment Paths...');
	$c = q('SELECT id, location FROM '. $tbl .'attach WHERE location IS NOT NULL AND location NOT LIKE '. _esc($FILE_STORE .'%'));
	while ($r = db_rowobj($c)) {
		draw_stat(' - fix path for: '. $r->location);
		preg_match('!(.*)/!', $r->location, $m);
		q('UPDATE '. $tbl .'attach SET location=REPLACE(location, '. _esc($m[1] .'/') .', '. _esc($FILE_STORE) .') WHERE id='. $r->id);
	}

	draw_stat('Validate the forum\'s stats cache');
	// The stats_cache should have one and only one row.
	$cnt = q_singleval('SELECT count(*) FROM '. $DBHOST_TBL_PREFIX .'stats_cache');
	if ($cnt > 1) {
		draw_stat(' - too many entries, delete them all');
		q('DELETE FROM '. $DBHOST_TBL_PREFIX .'stats_cache');
		$cnt = 0;
	}
	if ($cnt == 0) {
		draw_stat(' - add single row');
		q('INSERT INTO '. $DBHOST_TBL_PREFIX .'stats_cache (online_users_text) VALUES(\'\')');
	}
	
	draw_stat('Checking message reports');
	delete_zero($tbl .'msg_report', 'SELECT mr.id FROM '. $tbl .'msg_report mr LEFT JOIN '. $tbl .'msg m ON mr.msg_id=m.id WHERE m.id IS NULL');

	draw_stat('Checking polls against messages');
	delete_zero($tbl .'poll', 'SELECT p.id FROM '. $tbl .'poll p LEFT JOIN '. $tbl .'msg m ON p.id=m.poll_id WHERE m.id IS NULL');

	draw_stat('Checking messages against polls');
	$tmp = db_all('SELECT m.id FROM '. $tbl .'msg m LEFT JOIN '. $tbl .'poll p ON p.id=m.poll_id WHERE p.id IS NULL AND m.poll_id > 0');
	if ($tmp) {
		q('UPDATE '. $tbl .'msg SET poll_id=0, poll_cache=NULL WHERE id IN('. implode(',', $tmp) .')');
		unset($tmp);
	}

	draw_stat('Checking polls options against polls');
	delete_zero($tbl .'poll_opt', 'SELECT po.id FROM '. $tbl .'poll_opt po LEFT JOIN '. $tbl .'poll p ON p.id=po.poll_id WHERE p.id IS NULL');

	draw_stat('Checking polls votes');
	delete_zero($tbl .'poll_opt_track', 'SELECT pot.id FROM '. $tbl .'poll_opt_track pot LEFT JOIN '. $tbl .'poll p ON p.id=pot.poll_id LEFT JOIN '. $tbl .'poll_opt po ON po.id=pot.poll_opt LEFT JOIN '. $tbl .'users u ON u.id=pot.user_id WHERE u.id IS NULL OR po.id IS NULL OR p.id IS NULL');

	draw_stat('Rebuilding poll cache');
	// First we validate to vote counts for each option.
	q('UPDATE '. $tbl .'poll_opt SET votes=0');
	$c = q('SELECT poll_opt, count(*) FROM '. $tbl .'poll_opt_track GROUP BY poll_opt');
	while ($r = db_rowarr($c)) {
		q('UPDATE '. $tbl .'poll_opt SET votes='. (int)$r[1] .' WHERE id='. $r[0]);
	}
	unset($c);

	// Now we rebuild the individual message poll cache.
	$oldp = '';
	$opts = array();
	$vt = 0;
	$c = q('SELECT id, name, votes, poll_id FROM '. $tbl .'poll_opt ORDER BY poll_id, id');
	while ($r = db_rowarr($c)) {
		if ($oldp != $r[3]) {
			if ($oldp) {
				q('UPDATE '. $tbl .'msg SET poll_cache='. ssn(serialize($opts)) .' WHERE poll_id='. $oldp);
				q('UPDATE '. $tbl .'poll SET total_votes='. $vt .' WHERE id='. $oldp);
				$opts = array();
				$vt = 0;
			}
			$oldp = $r[3];
		}
		$opts[$r[0]] = array($r[1], $r[2]);
		$vt += $r[2];
	}
	unset($c);
	if ($opts) {
		q('UPDATE '. $tbl .'msg SET poll_cache='. ssn(serialize($opts)) .' WHERE poll_id='. $oldp);
		q('UPDATE '. $tbl .'poll SET total_votes='. $vt .' WHERE id='. $oldp);
	}
	draw_stat('Done: Rebuilding poll cache');

	draw_stat('Validating poll activation');
	$c = q('SELECT t.forum_id, p.id FROM '. $tbl .'poll p INNER JOIN '. $tbl .'msg m ON m.poll_id=p.id INNER JOIN '. $tbl .'thread t ON m.thread_id=t.id AND m.apr=1 WHERE t.forum_id!=p.forum_id');
	while ($r = db_rowarr($c)) {
		q('UPDATE '. $tbl .'poll SET forum_id='. $r[0] .' WHERE id='. $r[1]);
	}
	unset($c);
	draw_stat('Done: Validating poll activation');

	draw_stat('Checking smilies against disk files');
	$cnt = $i = 0;
	$c = q('SELECT img, id FROM '. $tbl .'smiley ORDER BY vieworder');
	while ($r = db_rowarr($c)) {
		if (!@file_exists($WWW_ROOT_DISK .'images/smiley_icons/'. $r[0])) {
			++$cnt;
			q('DELETE FROM '. $tbl .'smiley WHERE id='. $r[1]);
		}
		$sml[$r[0]] = 1;
		q('UPDATE '. $tbl .'smiley SET vieworder='. (++$i) .' WHERE id='. $r[1]);
	}
	unset($c);
	draw_info($cnt);

	draw_stat('Checking disk files against smilies');
	$cnt = 0;
	if (!defined('GLOB_BRACE')) {
		$files = array_merge(
			(array)glob($WWW_ROOT_DISK .'images/smiley_icons/*.gif', GLOB_NOSORT),
			(array)glob($WWW_ROOT_DISK .'images/smiley_icons/*.jpg', GLOB_NOSORT),
			(array)glob($WWW_ROOT_DISK .'images/smiley_icons/*.png', GLOB_NOSORT),
			(array)glob($WWW_ROOT_DISK .'images/smiley_icons/*.jpeg', GLOB_NOSORT)
		);
	} else {
		$files = glob($WWW_ROOT_DISK .'images/smiley_icons/{*.gif,*.jpg,*.png,*.jpeg}', GLOB_BRACE|GLOB_NOSORT);
	}
	foreach ($files as $file) {
		if (!isset($sml[basename($file)])) {
			if (@unlink($file)) {
				draw_stat('Delete unused smiley icon: '. $file);
				++$cnt;
			} else {
				draw_info('Unable to delete smiley icon: '. $file);
			}
		}
	}
	rebuild_icon_cache();
	unset($sml);
	draw_info($cnt);

	draw_stat('Rebuild Smiley Cache');
	smiley_rebuild_cache();

	draw_stat('Rebuild Custom Field Cache');
	fud_custom_field::rebuild_cache();

	draw_stat('Rebuild Announcement Cache');
	fud_announce::rebuild_cache();

	draw_stat('Rebuild Plugin Cache');
	fud_plugin::rebuild_cache();

	draw_stat('Rebuild Spider Cache');
	fud_spider::rebuild_cache();

	draw_stat('Checking topic notification');
	q('DELETE FROM '. $tbl .'thread_notify WHERE NOT EXISTS (SELECT id FROM '. $tbl .'users  WHERE '. $tbl .'thread_notify.user_id = id)');
	q('DELETE FROM '. $tbl .'thread_notify WHERE NOT EXISTS (SELECT id FROM '. $tbl .'thread WHERE '. $tbl .'thread_notify.thread_id = id)');
	// delete_zero($tbl .'thread_notify', 'SELECT tn.user_id, tn.thread_id FROM '. $tbl .'thread_notify tn LEFT JOIN '. $tbl .'thread t ON t.id=tn.thread_id LEFT JOIN '. $tbl .'users u ON u.id=tn.user_id WHERE u.id IS NULL OR t.id IS NULL');

	draw_stat('Checking topic bookmarks');
	q('DELETE FROM '. $tbl .'bookmarks WHERE NOT EXISTS (SELECT id FROM '. $tbl .'users  WHERE '. $tbl .'bookmarks.user_id = id)');
	q('DELETE FROM '. $tbl .'bookmarks WHERE NOT EXISTS (SELECT id FROM '. $tbl .'thread WHERE '. $tbl .'bookmarks.thread_id = id)');
	// delete_zero($tbl .'bookmarks', 'SELECT bm.user_id, bm.thread_id FROM '. $tbl .'bookmarks bm LEFT JOIN '. $tbl .'thread t ON t.id=bm.thread_id LEFT JOIN '. $tbl .'users u ON u.id=bm.user_id WHERE u.id IS NULL OR t.id IS NULL');

	draw_stat('Checking forum notification');
	q('DELETE FROM '. $tbl .'forum_notify WHERE NOT EXISTS (SELECT id FROM '. $tbl .'users WHERE '. $tbl .'forum_notify.user_id = id)');
	q('DELETE FROM '. $tbl .'forum_notify WHERE NOT EXISTS (SELECT id FROM '. $tbl .'forum WHERE '. $tbl .'forum_notify.forum_id = id)');
	// delete_zero($tbl .'forum_notify', 'SELECT fn.id FROM '. $tbl .'forum_notify fn LEFT JOIN '. $tbl .'forum f ON f.id=fn.forum_id LEFT JOIN '. $tbl .'users u ON u.id=fn.user_id WHERE u.id IS NULL OR f.id IS NULL');

	if ($thorough) {
		draw_stat('Checking search indexes');
		q('DELETE FROM '. $tbl .'index WHERE NOT EXISTS (SELECT id FROM '. $tbl .'search WHERE '. $tbl .'index.word_id = id)');
		q('DELETE FROM '. $tbl .'index WHERE NOT EXISTS (SELECT id FROM '. $tbl .'msg    WHERE '. $tbl .'index.msg_id = id)');
		q('DELETE FROM '. $tbl .'title_index WHERE NOT EXISTS (SELECT id FROM '. $tbl .'search WHERE '. $tbl .'title_index.word_id = id)');
		q('DELETE FROM '. $tbl .'title_index WHERE NOT EXISTS (SELECT id FROM '. $tbl .'msg    WHERE '. $tbl .'title_index.msg_id = id)');
		q('DELETE FROM '. $tbl .'search WHERE NOT EXISTS (SELECT * FROM '. $tbl .'index WHERE '. $tbl .'search.id = word_id) AND NOT EXISTS (SELECT * FROM '. $tbl .'title_index WHERE '. $tbl .'search.id = word_id)');
	}

	draw_stat('Checking topic votes against topics');
	delete_zero($tbl.'thread_rate_track', 'SELECT trt.id FROM '. $tbl .'thread_rate_track trt LEFT JOIN '. $tbl .'thread t ON t.id=trt.thread_id LEFT JOIN '. $tbl .'users u ON u.id=trt.user_id WHERE u.id IS NULL OR t.id IS NULL');

	draw_stat('Rebuild topic rating cache');
	q('UPDATE '. $tbl .'thread SET rating=0, n_rating=0');
	$c = q('SELECT thread_id, count(*), AVG(rating) FROM '. $tbl .'thread_rate_track GROUP BY thread_id');
	while ($r = db_rowarr($c)) {
		q('UPDATE '. $tbl .'thread SET rating='. round($r[2]) .', n_rating='. (int)$r[1] .' WHERE id='. $r[0]);
	}
	unset($c);
	draw_stat('Done: Rebuild topic rating cache');

	draw_stat('Rebuilding user ranks, message counts & last post ids');
	q('UPDATE '. $tbl .'users SET level_id=0, posted_msg_count=0, u_last_post_id=0, custom_status=NULL');
	$c = q('SELECT MAX(post_stamp), poster_id, count(*) FROM '. $tbl .'msg WHERE apr=1 GROUP BY poster_id');
	while (list($ps, $uid, $cnt) = db_rowarr($c)) {
		if (!$uid) { continue; }
		q('UPDATE '. $tbl .'users SET posted_msg_count='. $cnt .', u_last_post_id=('. q_limit('SELECT id FROM '. $tbl .'msg WHERE post_stamp='. $ps .' AND apr=1 AND poster_id='. $uid, 1) .') WHERE id='. $uid);
	}
	unset($c);

	$c = q('SELECT id, post_count FROM '. $tbl .'level ORDER BY post_count DESC');
	while ($r = db_rowarr($c)) {
		q('UPDATE '. $tbl .'users SET level_id='. $r[0] .' WHERE level_id=0 AND posted_msg_count>='. $r[1]);
	}
	unset($c);

	draw_stat('Done: Rebuilding user levels, message counts & last post ids');

	draw_stat('Checking buddy list entries');
	delete_zero($tbl .'buddy', 'SELECT b.id FROM '. $tbl .'buddy b LEFT JOIN '. $tbl .'users u1 ON u1.id=b.user_id LEFT JOIN '. $tbl .'users u2 ON u2.id=b.bud_id WHERE u1.id IS NULL OR u2.id IS NULL');

	draw_stat('Checking ignore list entries');
	delete_zero($tbl .'user_ignore', 'SELECT i.id FROM '. $tbl .'user_ignore i LEFT JOIN '. $tbl .'users u1 ON u1.id=i.user_id LEFT JOIN '. $tbl .'users u2 ON u2.id=i.ignore_id WHERE u1.id IS NULL OR u2.id IS NULL');

	// We do this together to avoid dupe query.
	q('UPDATE '. $tbl .'users SET buddy_list=NULL, ignore_list=NULL');

	draw_stat('Rebuilding buddy list cache');
	$oldu = '';
	$br = array();
	$c = q('SELECT bud_id, user_id FROM '. $tbl .'buddy ORDER BY user_id');
	while ($r = db_rowarr($c)) {
		if ($oldu != $r[1]) {
			if ($oldu) {
				q('UPDATE '. $tbl .'users SET buddy_list='. ssn(serialize($br)) .' WHERE id='. $oldu);
				$br = array();
			}
			$oldu = $r[1];
		}
		$br[$r[0]] = 1;
	}
	unset($c);
	if ($br) {
		q('UPDATE '. $tbl .'users SET buddy_list='. ssn(serialize($br)) .' WHERE id='. $oldu);
		unset($br);
	}
	draw_stat('Done: Rebuilding buddy list cache');

	draw_stat('Rebuilding ignore list cache');
	$oldu = '';
	$ir = array();
	$c = q('SELECT ignore_id, user_id FROM '. $tbl .'user_ignore ORDER BY user_id');
	while ($r = db_rowarr($c)) {
		if ($oldu != $r[1]) {
			if ($oldu) {
				q('UPDATE '. $tbl .'users SET ignore_list='. ssn(serialize($ir)) .' WHERE id='. $oldu);
				$ir = array();
			}
			$oldu = $r[1];
		}
		$ir[$r[0]] = 1;
	}
	unset($c);
	if ($ir) {
		q('UPDATE '. $tbl .'users SET ignore_list='. ssn(serialize($ir)) .' WHERE id='. $oldu);
		unset($ir);
	}
	draw_stat('Done: Rebuilding ignore list cache');

	draw_stat('Rebuilding ip filter cache');
	ip_cache_rebuild();
	draw_stat('Done: Rebuilding ip filter cache');

	draw_stat('Rebuilding login filter cache');
	login_cache_rebuild();
	draw_stat('Done: Rebuilding login filter cache');

	draw_stat('Rebuilding email filter cache');
	email_cache_rebuild();
	draw_stat('Done: Rebuilding email filter cache');

	draw_stat('Rebuilding extension filter cache');
	ext_cache_rebuild();
	draw_stat('Done: Rebuilding extension filter cache');

	draw_stat('Rebuilding custom tags for users');
	$c = q('SELECT distinct(user_id) FROM '. $tbl .'custom_tags');
	while ($r = db_rowarr($c)) {
		ctag_rebuild_cache($r[0]);
	}
	unset($c);
	draw_stat('Done rebuilding custom tags for users');

	draw_stat('Validating group resources');
	delete_zero($tbl .'group_resources', 'SELECT gr.id FROM '. $tbl .'group_resources gr LEFT JOIN '. $tbl .'forum f ON f.id=gr.resource_id LEFT JOIN '. $tbl .'groups g ON g.id=gr.group_id WHERE f.id IS NULL OR g.id IS NULL');
	draw_stat('Done: Validating group resources');

	draw_stat('Validating group validity');
	// Technically a group cannot exist without being assigned to at least 1 resource so, when we encounter such as group, we do our patriotic duty and remove it.
	delete_zero($tbl .'groups', 'SELECT g.id FROM '. $tbl .'groups g LEFT JOIN '. $tbl .'group_resources gr ON g.id=gr.group_id WHERE g.id > 2 AND gr.id IS NULL');
	delete_zero($tbl .'groups', 'SELECT g.id FROM '. $tbl .'groups g LEFT JOIN '. $tbl .'forum f ON g.forum_id=f.id WHERE g.forum_id > 0 AND g.id > 2 AND f.id IS NULL');
	draw_stat('Done: Validating group validity');

	draw_stat('Validating group members');
	delete_zero($tbl .'group_members', 'SELECT gm.id FROM '. $tbl .'group_members gm LEFT JOIN '. $tbl .'users u ON u.id=gm.user_id LEFT JOIN '. $tbl .'groups g ON g.id=gm.group_id WHERE (u.id IS NULL AND gm.user_id NOT IN(0, 2147483647)) OR g.id IS NULL');
	draw_stat('Done: Validating group members');

	draw_stat('Validating group/forum relations');
	$c = q('SELECT f.id, f.name FROM '. $tbl .'forum f LEFT JOIN '. $tbl .'groups g ON f.id=g.forum_id WHERE g.id IS NULL');
	while ($r = db_rowarr($c)) {
		group_add($r[0], $r[1], 2);
	}
	unset($c);
	draw_stat('Done: Validating group/forum relations');

	draw_stat('Validating group/forum names');
	$c = q('SELECT g.id, f.name FROM '. $tbl .'groups g INNER JOIN '. $tbl .'forum f ON f.id=g.forum_id WHERE g.id>2 AND f.name!=g.name');
	$i = 0;
	while ($r = db_rowarr($c)) {
		q('UPDATE '. $tbl .'groups SET name='. _esc($r[1]) .' WHERE id='. $r[0]);
		++$i;
	}
	unset($r);
	draw_stat('Done: Validating group/forum names (fixed: '. $i .' relations)');

	draw_stat('Validating group/primary user relations');
	$c = uq('SELECT g.id, gm1.id, gm2.id FROM '. $tbl .'groups g LEFT JOIN '. $tbl .'group_members gm1 ON gm1.group_id=g.id AND gm1.user_id=0 LEFT JOIN '. $tbl .'group_members gm2 ON gm2.group_id=g.id AND gm2.user_id=2147483647 WHERE g.id>2 AND g.forum_id>0 AND (gm1.id IS NULL OR gm2.id IS NULL)');
	while ($r = db_rowarr($c)) {
		if (!$r[1]) {
			$glm[$r[0]][] = 0;
		}
		if (!$r[2]) {
			$glm[$r[0]][] = 2147483647;
		}
	}
	unset($c);
	if (isset($glm)) {
		// Make group based on 'primary' 1st group.
		$anon = q_singleval('SELECT groups_opt FROM '. $tbl .'groups WHERE id=1');
		$regu = q_singleval('SELECT groups_opt FROM '. $tbl .'groups WHERE id=2');
		foreach ($glm as $k => $v) {
			foreach ($v as $uid) {
				q('INSERT INTO '. $tbl .'group_members (group_id, user_id, group_members_opt) VALUES ('. $k .', '. $uid .', '. (!$uid ? $anon : $regu) .')');
			}
		}
	}
	draw_stat('Done: Validating group/primary user relations');

	draw_stat('Rebuilding group leader cache');
	$c = q('SELECT DISTINCT(user_id) FROM '. $tbl .'group_members WHERE group_members_opt>=131072 AND '. q_bitand('group_members_opt', 131072) .' > 0');
	while ($r = db_rowarr($c)) {
		rebuild_group_ldr_cache($r[0]);
	}
	unset($c);
	draw_stat('Done: Rebuilding group leader cache');

	draw_stat('Rebuilding group cache');
	grp_rebuild_cache();
	draw_stat('Done: Rebuilding group cache');

	draw_stat('Validating User/Theme Relations');
	q('UPDATE '. $tbl .'users SET theme=(SELECT id FROM '. $tbl .'themes thm WHERE '. q_bitand('theme_opt', 3) .' = 3) WHERE theme NOT IN( (SELECT id FROM '. $tbl .'themes WHERE '. q_bitand('theme_opt', 1) .' > 0))');
	draw_stat('Done: Validating User/Theme Relations');

        draw_stat('Correcting Avatar Paths...');
        $urlparts = parse_url($GLOBALS['WWW_ROOT']);    // Extract relative path from forum URL and remove trailing slash.
        $urlpath = empty($urlparts['path']) ? '' : rtrim( $urlparts['path'], '/');
        $c = q('SELECT id, avatar_loc FROM '. $tbl .'users WHERE avatar_loc IS NOT NULL AND users_opt>=8388608 AND '. q_bitand('users_opt', (8388608|16777216)) .'>0');
        while ($r = db_rowobj($c)) {
                preg_match('!img src="(.*)/images/!', $r->avatar_loc, $m);
                if (isset($m[1])) {
                        $new_avatar = str_replace($m[1], $urlpath, $r->avatar_loc);
                        // Check if the avatar URL needs to be updated (for example, when the URL or path changed).
                        if ($new_avatar !== $r->avatar_loc) {
                                pf(' - change '. htmlentities($r->avatar_loc) .' to '. htmlentities($new_avatar));
                                $r->avatar_loc = $new_avatar;
                                q('UPDATE '. $tbl .'users SET avatar_loc='. _esc($new_avatar) .' WHERE id = '. $r->id);
                        }
                }cd 
                // Remove protocol from external avatar URL's - for example for gravatar images.
                $new_avatar = preg_replace('#https?://#i', '//', $r->avatar_loc);
                if ($new_avatar !== $r->avatar_loc) {
                        pf(' - change '. htmlentities($r->avatar_loc) .' to '. htmlentities($new_avatar));
                        q('UPDATE '. $tbl .'users SET avatar_loc='. _esc($new_avatar) .' WHERE id = '. $r->id);
                }
        }

	draw_stat('Rebuilding Forum/Category order cache');
	rebuild_forum_cat_order();
	draw_stat('Done: Rebuilding Forum/Category order cache');

	draw_stat('Remove absolete entries inside sessions table');
	q('DELETE FROM '. $tbl .'ses WHERE user_id>2000000000 AND time_sec < '. (__request_timestamp__ - $SESSION_TIMEOUT));
	draw_stat('Done: Removing absolete entries inside sessions table');

	draw_stat('Remove old action log entries (older than 90 days)');
	q('DELETE FROM '. $tbl .'action_log WHERE logtime < '. (__request_timestamp__ - (86400 * 90)));
	draw_stat('Done: Removing old action log entries');

	draw_stat('Rebuilding Topic Views');
	foreach (db_all('SELECT id FROM '. $tbl .'forum') as $v) {
		rebuild_forum_view_ttl($v);
	}
	draw_stat('Done: Rebuilding Topic Views');

	draw_stat('Unlocking database');
	db_unlock();
	draw_stat('Database unlocked');

	draw_stat('Cleaning forum\'s tmp directory');
	if (($files = glob($TMP .'*', GLOB_NOSORT))) {
		foreach ($files as $file) {
			// Remove ALL files, except forum backup files.
			if (is_file($file) && !preg_match("/FUDforum_.*\.fud.*|LAST_CRON_RUN/", $file)) {
				pf('- remove file: '. $file);
				@unlink($file);
			}
		}
	}
	draw_stat('Done: Cleaning forum\'s tmp directory');

	draw_stat('Cleaning forum\'s error log files');
	if (($files = glob($ERROR_PATH .'*_errors', GLOB_NOSORT))) {
		foreach ($files as $file) {
			$fsize = filesize($file);
			if ($fsize > 102400) {	// Bigger than 100K.
				$fp = fopen($file, 'r');
				fseek($fp, $fsize - 102400);
				$last = fread($fp, 102400);		// Read last 100K.
				fclose($fp);

				//  Discard the first partial record.
				$next_rec_pos = 0;
				while ($last[$next_rec_pos] != '?' && $last[$next_rec_pos+1] != "\n") $next_rec_pos++;
				$last = substr($last, $next_rec_pos+2);

				// Overwrite log file with trimmed content.
				$fp = @fopen($file, 'w');
				if (!$fp) {
					pf('Unable to write to file ['. $file .']. PLEASE FIX ITS PERMISSIONS!');
					continue;
				}
				fwrite($fp, $last);
				fclose($fp);

				unset($last);
				pf('- '. basename($file) .' trimmed down from '. number_format($fsize/1024, 2) .'K to 100K');
			}
		}
	}
	draw_stat('Done: Cleaning forum\'s error log files');

	draw_stat('Validate GLOBALS.php');
	$gvars = array();
	$data = file($GLOBALS['INCLUDE'] .'GLOBALS.php');
	$olc = count($data);
	foreach ($data as $k => $l) {
		if (($p = strpos($l, '$')) !== false) {
			++$p;
			if (($e = strpos($l, '=', $p)) !== false) {
				$var = rtrim(substr($l, $p, ($e - $p)));
				if (isset($gvars[$var])) {
					unset($data[$k]);
				} else {
					$gvars[$var] = 1;
				}
			}
		} else if (!trim($l)) {
			unset($data[$k]);
		}
	}
	if ($olc != count($data)) {
		$fp = fopen($GLOBALS['INCLUDE'] .'GLOBALS.php', 'w');
		fwrite($fp, implode('', $data));
		if (strpos(array_pop($data), '?>') === false) {
			fwrite($fp, "\n?>");
		}
		fclose($fp);
	}
	draw_stat('Done: Validate GLOBALS.php');

	draw_stat('Validating symlinks to GLOBALS.php');
	if ( !file_exists($WWW_ROOT_DISK .'GLOBALS.php') || md5_file($WWW_ROOT_DISK .'GLOBALS.php') != md5_file($INCLUDE .'GLOBALS.php') ) { 
		pf('Recreate symlink to GLOBALS.php');
		fud_symlink($INCLUDE .'GLOBALS.php',  $WWW_ROOT_DISK .'GLOBALS.php');
	}
	if ( !file_exists($WWW_ROOT_DISK .'adm/GLOBALS.php') || md5_file($WWW_ROOT_DISK .'adm/GLOBALS.php') != md5_file($INCLUDE .'GLOBALS.php') ) { 
		pf('Recreate symlink to adm/GLOBALS.php');
		fud_symlink($INCLUDE .'GLOBALS.php',  $WWW_ROOT_DISK .'adm/GLOBALS.php');
	}
	if ( !file_exists($DATA_DIR .'scripts/GLOBALS.php') || md5_file($DATA_DIR .'scripts/GLOBALS.php') != md5_file($INCLUDE .'GLOBALS.php') ) { 
		pf('Recreate symlink to scripts/GLOBALS.php');
		fud_symlink($INCLUDE .'GLOBALS.php',  $DATA_DIR .'scripts/GLOBALS.php');
	}
	draw_stat('Done: Validating symlinks to GLOBALS.php');

	// Call plugins with XXX_check() functions!
	$c = q('SELECT name FROM '. $tbl .'plugins');	// Get all enabled.
	while ($r = db_rowarr($c)) {
		$func_base = substr($r[0], 0, strrpos($r[0], '.'));
		if (defined('fud_debug')) echo 'Call hook for plugin '. $func_base .'<br />';
		$check_func = $func_base .'_check';
		if (function_exists($check_func)) {
			list($ok, $err) = $check_func();
			if ($ok)  echo successify($r[0] .': '. $ok);
			if ($err) echo errorify(  $r[0] .': '. $err);
		}
	}

	if ($FUD_OPT_1 & 1 || isset($_GET['enable_forum'])) {
		draw_stat('Re-enabling the forum.');
		maintenance_status(0);
	} else {
		pf('<font size="+1" color="red">Your forum is currently disabled, to re-enable it go to the <a href="admglobal.php?'.__adm_rsid.'">Global Settings Manager</a> and re-enable it.</font>');
	}

	draw_stat('DONE!');

	if (!defined('shell_script')) {
		pf('<hr /><div class="tutor">It is recommended that you run the Database Optimizer after completing the consistency check.<br />To do so now, <span style="white-space:nowrap">&gt;&gt; <b><a href="consist.php?opt=1&amp;'.__adm_rsid.'">click here</a></b> &lt;&lt;</span>, keep in mind that this process may take several minutes to perform.</div>');
	}
	require($WWW_ROOT_DISK .'adm/footer.php');
?>
