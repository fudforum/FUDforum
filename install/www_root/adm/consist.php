<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: consist.php,v 1.62 2003/10/09 14:29:12 hackie Exp $
****************************************************************************

****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

	@set_time_limit(600);
	@ini_set("memory_limit", "100M");
	define('back_to_main', 1);

	require('./GLOBALS.php');

	fud_use('adm.inc', true);
	fud_use('glob.inc', true);
	fud_use('widgets.inc', true);
	fud_use('ext.inc', true);
	fud_use('ipfilter.inc', true);
	fud_use('login_filter.inc', true);
	fud_use('email_filter.inc', true);
	fud_use('customtags.inc', true);
	fud_use('groups_adm.inc', true);
	fud_use('cat.inc', true);
	fud_use('imsg.inc');
	fud_use('imsg_edt.inc');
	fud_use('err.inc');
	fud_use('private.inc');
	fud_use('th.inc');
	fud_use('ipoll.inc');
	fud_use('attach.inc');
	fud_use('groups.inc');
	fud_use('th_adm.inc');
	fud_use('users_reg.inc');

function draw_stat($text)
{
	echo '<b>'.htmlspecialchars($text).'</b><br />' . "\n";
	flush();
}

function draw_info($cnt)
{
	draw_stat(($cnt < 1 ? 'OK' : $cnt . ' entries unmatched, deleted'));
}

function delete_zero($tbl, $q)
{
	$cnt = 0;
	$c = q($q);
	while ($r = db_rowarr($c)) {
		$a[] = $r[0];
		++$cnt;
	}
	if ($cnt) {
		q('DELETE FROM '.$tbl.' WHERE id IN ('.implode(',', $a).')');
	}
	qf($c);
	draw_info($cnt);
}

	include($WWW_ROOT_DISK . 'adm/admpanel.php');

	if (!isset($_POST['conf']) && !isset($_GET['enable_forum']) && !isset($_GET['opt'])) {
?>
<form method="post" action="consist.php">
<div align="center">
Consistency check is a complex process which may take several minutes to run, while it is running your
forum will be disabled.<br><br>
<h2>Do you wish to proceed?</h2>
<input type="submit" name="cancel" value="No">&nbsp;&nbsp;&nbsp;<input type="submit" name="conf" value="Yes">
</div>
<?php echo _hs; ?>
</form>
<?php
		readfile($WWW_ROOT_DISK . 'adm/admclose.html');
		exit;
	}

	if ($FUD_OPT_1 & 1) {
		draw_stat('Disabling the forum for the duration of maintenance run');
		maintenance_status('Undergoing maintenance, please come back later.', 1);
	}
	if (isset($_GET['opt'])) {
		draw_stat('Optimizing forum\'s SQL tables');
		optimize_tables();
		draw_stat('Done: Optimizing forum\'s SQL tables');

		if ($FUD_OPT_1 & 1 || isset($_GET['enable_forum'])) {
			draw_stat('Re-enabling the forum.');
			maintenance_status($DISABLED_REASON, 0);
		} else {
			echo '<font size="+1" color="red">Your forum is currently disabled, to re-enable it go to the <a href="admglobal.php?'._rsid.'">Global Settings Manager</a> and re-enable it.</font><br>';
		}

		readfile($WWW_ROOT_DISK . 'adm/admclose.html');
		exit;
	}
?>
<script language="Javascript1.2">
	var intervalID;
	function scrolldown()
	{
		window.scroll(0, 30000);
	}
	intervalID = setInterval('scrolldown()', 100);
</script>
<?php
	$tbl = $DBHOST_TBL_PREFIX;

	draw_stat('Locking the database for checking');
	if (__dbtype__ == 'mysql') {
		q('DROP TABLE IF EXISTS '.$tbl.'tmp_consist');
		q('CREATE TABLE '.$tbl.'tmp_consist (p INT, ps INT UNSIGNED, c INT)');
	}
	$tbls = get_fud_table_list();
	// add the various table aliases
	array_push($tbls, 	$tbl.'users u', $tbl.'forum f', $tbl.'thread t', $tbl.'poll p', $tbl.'poll_opt po', $tbl.'poll_opt_track pot',
				$tbl.'msg m', $tbl.'pmsg pm', $tbl.'mod mm', $tbl.'thread_rate_track trt', $tbl.'msg_report mr', $tbl.'cat c',
				$tbl.'forum_notify fn', $tbl.'thread_notify tn', $tbl.'buddy b', $tbl.'user_ignore i', $tbl.'msg m1', $tbl.'msg m2',
				$tbl.'users u1', $tbl.'users u2', $tbl.'attach a', $tbl.'thr_exchange te', $tbl.'read r', $tbl.'mime mi',
				$tbl.'group_members gm', $tbl.'group_resources gr', $tbl.'groups g', $tbl.'group_members gm1', $tbl.'group_members gm2', $tbl.'themes thm');

	db_lock(implode(' WRITE, ', $tbls).' WRITE');
	draw_stat('Locked!');

	draw_stat('Validating category order');
	$i = 1;
	$c = q('SELECT id, view_order FROM '.$tbl.'cat ORDER BY view_order, id');
	while ($r = db_rowarr($c)) {
		if ($r[1] != $i) {
			q('UPDATE '.$tbl.'cat SET view_order='.$i.' WHERE id='.$id);
		}
		++$i;
	}
	qf($r);
	draw_stat('Done: Validating category order');

	draw_stat('Checking if moderator and users table match');
	delete_zero($tbl.'mod', 'SELECT mm.id FROM '.$tbl.'mod mm LEFT JOIN '.$tbl.'users u ON mm.user_id=u.id LEFT JOIN '.$tbl.'forum f ON f.id=mm.forum_id WHERE u.id IS NULL OR f.id IS NULL');

	draw_stat('Rebuilding moderators');
	rebuildmodlist();
	draw_stat('Done: Rebuilding moderators');

	draw_stat('Checking if all private messages have users');
	$c = uq('SELECT pm.id FROM '.$tbl.'pmsg pm LEFT JOIN '.$tbl.'users u ON u.id=pm.ouser_id WHERE (pm.pmsg_opt & 16)=0 AND u.id IS NULL');
	while ($r = db_rowarr($c)) {
		$dpm[] = $r[0];
	}
	qf($c);
	$c = q('SELECT pm.id FROM '.$tbl.'pmsg pm LEFT JOIN '.$tbl.'users u ON u.id=pm.duser_id WHERE ((pm.pmsg_opt & 16) > 0 AND pm.pmsg_opt>=16) AND u.id IS NULL');
	while ($r = db_rowarr($c)) {
		$dpm[] = $r[0];
	}
	qf($c);
	if (isset($dpm)) {
		$cnt = count($dpm);
		foreach ($dpm as $v) {
			pmsg_del($v, 5);
		}
	} else {
		$cnt = 0;
	}
	draw_info($cnt);

	draw_stat('Checking messages against users & threads');
	delete_zero($tbl.'msg', 'SELECT m.id FROM '.$tbl.'msg m LEFT JOIN '.$tbl.'users u ON u.id=m.poster_id LEFT JOIN '.$tbl.'thread t ON t.id=m.thread_id LEFT JOIN '.$tbl.'forum f ON f.id=t.forum_id WHERE (m.poster_id!=0 AND u.id IS NULL) OR t.id IS NULL OR f.id IS NULL');

	draw_stat('Checking threads against forums');
	delete_zero($tbl.'thread', 'SELECT t.id FROM '.$tbl.'thread t LEFT JOIN '.$tbl.'forum f ON f.id=t.forum_id WHERE f.id IS NULL');

	draw_stat('Checking message approvals');
	$m = array();
	$c = q('SELECT m.id FROM '.$tbl.'msg m INNER JOIN '.$tbl.'thread t ON m.thread_id=t.id INNER JOIN '.$tbl.'forum f ON t.forum_id=f.id WHERE m.apr=0 AND (f.forum_opt & 2) > 0');
	while ($r = db_rowarr($c)) {
		$m[] = $r[0];
	}
	if (count($m)) {
		q('UPDATE '.$tbl.'msg SET apr=1 WHERE id IN('.implode(',', $m).')');
		unset($m);
	}
	qf($c);
	draw_stat('Done: Checking message approvals');

	$cnt = 0;
	$del = $tr = array();
	draw_stat('Checking threads against messages');
	q('UPDATE '.$tbl.'thread SET replies=0');
	$c = uq('SELECT m.thread_id, t.id, count(*) as cnt FROM '.$tbl.'thread t LEFT JOIN '.$tbl.'msg m ON t.id=m.thread_id WHERE m.apr=1 GROUP BY m.thread_id,t.id ORDER BY cnt');
	while ($r = db_rowarr($c)) {
		if (!$r[0]) {
			$del[] = $r[1];
			++$cnt;
		} else {
			$tr[$r[2] - 1][] = $r[1];
		}
	}
	qf($c);
	if (count($del)) {
		q('DELETE FROM '.$tbl.'thread WHERE id='.implode(',', $del));
	}
	unset($tr[0]);
	foreach ($tr as $k => $v) {
		q('UPDATE '.$tbl.'thread SET replies='.$k.' WHERE id IN('.implode(',', $v).')');
	}
	unset($tr, $del);
	draw_info($cnt);

	draw_stat('Checking thread last & first post ids');
	$c = q('SELECT m1.id, m2.id, t.id FROM '.$tbl.'thread t LEFT JOIN '.$tbl.'msg m1 ON t.root_msg_id=m1.id LEFT JOIN '.$tbl.'msg m2 ON t.last_post_id=m2.id WHERE m1.id IS NULL or m2.id IS NULL');
	while ($r = db_rowarr($c)) {
		if (!$r[0]) {
			if (!($root = q_singleval('SELECT id FROM '.$tbl.'msg WHERE thread_id='.$r[2].' ORDER BY post_stamp LIMIT 1'))) {
				q('DELETE FROM '.$tbl.'thread WHERE id='.$r[2]);
			} else {
				q('UPDATE '.$tbl.'thread SET root_msg_id='.$root.' WHERE id='.$r[2]);
			}
		} else {
			$r2 = db_saq('SELECT id, post_stamp FROM '.$tbl.'msg WHERE thread_id='.$r[2].' ORDER BY post_stamp DESC LIMIT 1');
			if (!$r2) {
				q('DELETE FROM '.$tbl.'thread WHERE id='.$r[2]);
			} else {
				q('UPDATE '.$tbl.'thread SET last_post_id='.$r2[0].', last_post_date='.$r2[1].' WHERE id='.$r[2]);
			}
		}
	}
	draw_stat('Done: Checking thread last & first post ids');

	draw_stat('Checking forum & topic relations');
	$c = q('SELECT id FROM '.$tbl.'forum');
	while ($f = db_rowarr($c)) {
		$r = db_saq('select MAX(last_post_id), SUM(replies), COUNT(*) FROM '.$tbl.'thread t INNER JOIN '.$tbl.'msg m ON t.root_msg_id=m.id AND m.apr=1 WHERE t.forum_id='.$f[0]);
		if (!$r[2]) {
			q('UPDATE '.$tbl.'forum SET thread_count=0, post_count=0, last_post_id=0 WHERE id='.$f[0]);
		} else {
			q('UPDATE '.$tbl.'forum SET thread_count='.$r[2].', post_count='.($r[1] + $r[2]).', last_post_id='.(int)$r[0].' WHERE id='.$f[0]);
		}
	}
	qf($c);
	draw_stat('Done: Checking forum & topic relations');

	draw_stat('Validating Forum Order');
	$cat = 0;
	$c = q('SELECT id, cat_id, view_order FROM '.$tbl.'forum WHERE cat_id>0 ORDER BY cat_id, view_order');
	while ($f = db_rowarr($c)) {
		if ($cat != $f[1]) {
			$i = 0;
			$cat = $f[1];
		}
		++$i;
		if ($i != $f[2]) {
			q('UPDATE '.$tbl.'forum SET view_order='.$i.' WHERE id='.$f[0]);
		}
	}
	draw_stat('Done: Validating Forum Order');

	draw_stat('Checking thread_exchange');
	delete_zero($tbl.'thr_exchange', 'SELECT te.id FROM '.$tbl.'thr_exchange te LEFT JOIN '.$tbl.'thread t ON t.id=te.th LEFT JOIN '.$tbl.'forum f ON f.id=te.frm WHERE t.id IS NULL or f.id IS NULL');

	draw_stat('Checking read table against users & threads');
	delete_zero($tbl.'read', 'SELECT r.id FROM '.$tbl.'read r LEFT JOIN '.$tbl.'users u ON r.user_id=u.id LEFT JOIN '.$tbl.'thread t ON r.thread_id=t.id WHERE t.id IS NULL OR u.id IS NULL');

	draw_stat('Checking file attachments against messages');
	$arm = array();
	$c = q('SELECT a.id FROM '.$tbl.'attach a LEFT JOIN '.$tbl.'msg m ON a.message_id=m.id WHERE m.id IS NULL AND attach_opt=0');
	while ($r = db_rowarr($c)) {
		$arm[] = $r[0];
	}
	qf($c);
	$c = q('SELECT a.id FROM '.$tbl.'attach a LEFT JOIN '.$tbl.'pmsg pm ON a.message_id=pm.id WHERE pm.id IS NULL AND attach_opt=1');
	while ($r = db_rowarr($c)) {
		$arm[] = $r[0];
	}
	qf($c);
	if (($cnt = count($arm))) {
		foreach ($arm as $a) {
			@unlink($FILE_STORE . $a . 'atch');
		}
		q('DELETE FROM '.$tbl.'attach WHERE id IN('.implode(',', $arm).')');
	}
	draw_info($cnt);

	draw_stat('Rebuild attachment cache for regular messages');
	$oldm = '';
	$atr = array();
	q('UPDATE '.$tbl.'msg SET attach_cnt=0, attach_cache=NULL');
	$c = q('SELECT a.id, a.original_name, a.fsize, a.dlcount, CASE WHEN mi.icon IS NULL THEN \'unknown.gif\' ELSE mi.icon END, a.message_id FROM '.$tbl.'attach a LEFT JOIN '.$tbl.'mime mi ON a.mime_type=mi.id WHERE attach_opt=0');
	while ($r = db_rowarr($c)) {
		if ($oldm != $r[5]) {
			if ($oldm) {
				q('UPDATE '.$tbl.'msg SET attach_cnt='.count($atr).', attach_cache='.strnull(addslashes(@serialize($atr))).' WHERE id='.$oldm);
				$atr = array();
			}
			$oldm = $r[5];
		}
		unset($r[5]);
		$atr[] = $r;
	}
	qf($c);
	if (count($atr)) {
		q('UPDATE '.$tbl.'msg SET attach_cnt='.count($atr).', attach_cache='.strnull(addslashes(@serialize($atr))).' WHERE id='.$oldm);
	}
	draw_stat('Done: Rebuild attachment cache for regular messages');

	draw_stat('Rebuild attachment cache for private messages');
	q('UPDATE '.$tbl.'pmsg SET attach_cnt=0');
	$c = q('SELECT count(*), message_id FROM '.$tbl.'attach WHERE attach_opt=1 GROUP BY message_id');
	while ($r = db_rowarr($c)) {
		q('UPDATE '.$tbl.'pmsg SET attach_cnt='.$r[0].' WHERE id='.$r[1]);
	}
	qf($c);
	draw_stat('Done: Rebuild attachment cache for private messages');

	draw_stat('Checking message reports');
	delete_zero($tbl.'msg_report', 'SELECT mr.id FROM '.$tbl.'msg_report mr LEFT JOIN '.$tbl.'msg m ON mr.msg_id=m.id WHERE m.id IS NULL');

	draw_stat('Checking polls against messages');
	$cnt = 0;
	$c = q('SELECT p.id, m.id FROM '.$tbl.'poll p LEFT JOIN '.$tbl.'msg m ON p.id=m.poll_id WHERE m.id IS NULL OR p.id IS NULL');
	while ($r = db_rowarr($c)) {
		if ($r[0]) {
			q('DELETE FROM '.$tbl.'poll WHERE id='.$r[0]);
			++$cnt;
		} else {
			q('UPDATE '.$tbl.'msg SET poll_id=0, poll_cache=NULL WHERE id='.$r[1]);
		}
	}
	qf($c);
	draw_info($cnt);

	draw_stat('Checking polls options against polls');
	delete_zero($tbl.'poll_opt', 'SELECT po.id FROM '.$tbl.'poll_opt po LEFT JOIN '.$tbl.'poll p ON p.id=po.poll_id WHERE p.id IS NULL');

	draw_stat('Checking polls votes');
	delete_zero($tbl.'poll_opt_track', 'SELECT pot.id FROM '.$tbl.'poll_opt_track pot LEFT JOIN '.$tbl.'poll p ON p.id=pot.poll_id LEFT JOIN '.$tbl.'poll_opt po ON po.id=pot.poll_opt LEFT JOIN '.$tbl.'users u ON u.id=pot.user_id WHERE u.id IS NULL OR po.id IS NULL OR p.id IS NULL');

	draw_stat('Rebuilding poll cache');
	// first we validate to vote counts for each option
	q('UPDATE '.$tbl.'poll_opt SET count=0');
	$c = q('SELECT poll_opt, count(*) FROM '.$tbl.'poll_opt_track GROUP BY poll_opt');
	while ($r = db_rowarr($c)) {
		q('UPDATE '.$tbl.'poll_opt SET count='.(int)$r[1].' WHERE id='.$r[0]);
	}
	qf($c);

	// now we rebuild the individual message poll cache
	$oldp = '';
	$opts = array();
	$vt = 0;
	$c = q('SELECT id, name, count, poll_id FROM '.$tbl.'poll_opt ORDER BY poll_id');
	while ($r = db_rowarr($c)) {
		if ($oldp != $r[3]) {
			if ($oldp) {
				q('UPDATE '.$tbl.'msg SET poll_cache='.strnull(addslashes(@serialize($opts))).' WHERE poll_id='.$oldp);
				q('UPDATE '.$tbl.'poll SET total_votes='.$vt.' WHERE id='.$oldp);
				$opts = array();
				$vt = 0;
			}
			$oldp = $r[3];
		}
		$opts[$r[0]] = array($r[1], $r[2]);
		$vt += $r[2];
	}
	qf($c);
	if (count($opts)) {
		q('UPDATE '.$tbl.'msg SET poll_cache='.strnull(addslashes(@serialize($opts))).' WHERE poll_id='.$oldp);
		q('UPDATE '.$tbl.'poll SET total_votes='.$vt.' WHERE id='.$oldp);
	}
	draw_stat('Done: Rebuilding poll cache');

	draw_stat('Validating poll activation');
	$c = q('SELECT t.forum_id, p.id FROM '.$tbl.'poll p INNER JOIN '.$tbl.'msg m ON m.poll_id=p.id INNER JOIN '.$tbl.'thread t ON m.thread_id=t.id AND m.apr=1 WHERE t.forum_id!=p.forum_id');
	while ($r = db_rowarr($c)) {
		q('UPDATE '.$tbl.'poll SET forum_id='.$r[0].' WHERE id='.$r[1]);
	}
	qf($c);
	draw_stat('Done: Validating poll activation');

	draw_stat('Checking smilies against disk files');
	$cnt = $i = 0;
	$c = q('SELECT img, id FROM '.$tbl.'smiley ORDER BY vieworder');
	while ($r = db_rowarr($c)) {
		if (!@file_exists($WWW_ROOT_DISK . 'images/smiley_icons/' . $r[0])) {
			++$cnt;
			q('DELETE FROM '.$tbl.'smiley WHERE id='.$r[1]);
		}
		$sml[$r[0]] = 1;
		q('UPDATE '.$tbl.'smiley SET vieworder='.(++$i).' WHERE id='.$r[1]);
	}
	qf($c);
	draw_info($cnt);

	draw_stat('Checking disk files against smilies');
	$cnt = 0;
	$dp = opendir($WWW_ROOT_DISK . 'images/smiley_icons');
	readdir($dp); readdir($dp);
	while ($f = readdir($dp)) {
		if (!isset($sml[$f]) && !preg_match('!\.(gif|png|jpg|jpeg|html)$!i', $f)) {
			if (@unlink($WWW_ROOT_DISK . 'images/smiley_icons/' . $f)) {
				draw_stat('deleted smiley: ' . $f);
				++$cnt;
			} else {
				draw_info('Unable to delete smiley: ' . $f);
			}
		}
	}
	closedir($dp);
	unset($sml);
	draw_info($cnt);

	draw_stat('Checking topic notification');
	delete_zero($tbl.'thread_notify', 'SELECT tn.id FROM '.$tbl.'thread_notify tn LEFT JOIN '.$tbl.'thread t ON t.id=tn.thread_id LEFT JOIN '.$tbl.'users u ON u.id=tn.user_id WHERE u.id IS NULL OR t.id IS NULL');

	draw_stat('Checking forum notification');
	delete_zero($tbl.'forum_notify', 'SELECT fn.id FROM '.$tbl.'forum_notify fn LEFT JOIN '.$tbl.'forum f ON f.id=fn.forum_id LEFT JOIN '.$tbl.'users u ON u.id=fn.user_id WHERE u.id IS NULL OR f.id IS NULL');

	draw_stat('Checking topic votes against topics');
	delete_zero($tbl.'thread_rate_track', 'SELECT trt.id FROM '.$tbl.'thread_rate_track trt LEFT JOIN '.$tbl.'thread t ON t.id=trt.thread_id LEFT JOIN '.$tbl.'users u ON u.id=trt.user_id WHERE u.id IS NULL OR t.id IS NULL');

	draw_stat('Rebuild topic rating cache');
	q('UPDATE '.$tbl.'thread SET rating=0, n_rating=0');
	$c = q('SELECT thread_id, count(*), AVG(rating) FROM '.$tbl.'thread_rate_track GROUP BY thread_id');
	while ($r = db_rowarr($c)) {
		q('UPDATE '.$tbl.'thread SET rating='.round($r[2]).', n_rating='.(int)$r[1].' WHERE id='.$r[0]);
	}
	qf($c);
	draw_stat('Done: Rebuild topic rating cache');

	draw_stat('Rebuilding Topic Views');
	q('DELETE FROM '.$tbl.'thread_view');
	$c = q('SELECT id FROM '.$tbl.'forum');
	while ($r = db_rowarr($c)) {
		rebuild_forum_view($r[0]);
	}
	qf($fr);
	draw_stat('Done: Rebuilding Topic Views');

	draw_stat('Rebuilding user levels, message counts & last post ids');
	q('UPDATE '.$tbl.'users SET level_id=0, posted_msg_count=0, u_last_post_id=0, custom_status=NULL');
	if (__dbtype__ == 'mysql') {
		q('INSERT INTO '.$tbl.'tmp_consist (ps, p, c) SELECT MAX(post_stamp), poster_id, count(*) FROM '.$tbl.'msg WHERE apr=1 GROUP BY poster_id ORDER BY poster_id');
		$c = q('SELECT '.$tbl.'tmp_consist.p, '.$tbl.'tmp_consist.c, m.id FROM '.$tbl.'tmp_consist INNER JOIN '.$tbl.'msg m ON m.apr=1 AND m.poster_id='.$tbl.'tmp_consist.p AND m.post_stamp='.$tbl.'tmp_consist.ps');
		while ($r = db_rowarr($c)) {
			if (!$r[1]) { continue; }
			q('UPDATE '.$tbl.'users SET u_last_post_id='.$r[2].', posted_msg_count='.$r[1].' WHERE id='.$r[0]);
		}
		qf($c);
	} else {
		$c = q('SELECT MAX(post_stamp), poster_id, count(*) FROM '.$tbl.'msg WHERE apr=1 GROUP BY poster_id ORDER BY poster_id');
		while (list($ps, $uid, $cnt) = db_rowarr($c)) {
			if (!$uid) { continue; }
			q('UPDATE '.$tbl.'users SET posted_msg_count='.$cnt.', u_last_post_id=(SELECT id FROM '.$tbl.'msg WHERE post_stamp='.$ps.' AND apr=1 AND poster_id='.$uid.') WHERE id='.$uid);
		}
		qf($c);
	}

	$c = q('SELECT id, post_count FROM '.$tbl.'level ORDER BY post_count DESC');
	while ($r = db_rowarr($c)) {
		q('UPDATE '.$tbl.'users SET level_id='.$r[0].' WHERE level_id=0 AND posted_msg_count>='.$r[1]);
	}
	qf($c);

	draw_stat('Done: Rebuilding user levels, message counts & last post ids');

	draw_stat('Checking buddy list entries');
	delete_zero($tbl.'buddy', 'SELECT b.id FROM '.$tbl.'buddy b LEFT JOIN '.$tbl.'users u1 ON u1.id=b.user_id LEFT JOIN '.$tbl.'users u2 ON u2.id=b.bud_id WHERE u1.id IS NULL OR u2.id IS NULL');

	draw_stat('Checking ignore list entries');
	delete_zero($tbl.'user_ignore', 'SELECT i.id FROM '.$tbl.'user_ignore i LEFT JOIN '.$tbl.'users u1 ON u1.id=i.user_id LEFT JOIN '.$tbl.'users u2 ON u2.id=i.ignore_id WHERE u1.id IS NULL OR u2.id IS NULL');

	// we do this together to avoid dupe query
	q('UPDATE '.$tbl.'users SET buddy_list=NULL, ignore_list=NULL');

	draw_stat('Rebuilding buddy list cache');
	$oldu = '';
	$br = array();
	$c = q('SELECT bud_id, user_id FROM '.$tbl.'buddy ORDER BY user_id');
	while ($r = db_rowarr($c)) {
		if ($oldu != $r[1]) {
			if ($oldu) {
				q('UPDATE '.$tbl.'users SET buddy_list='.strnull(addslashes(@serialize($br))).' WHERE id='.$oldu);
				$br = array();
			}
			$oldu = $r[1];
		}
		$br[$r[0]] = 1;
	}
	qf($c);
	if (count($br)) {
		q('UPDATE '.$tbl.'users SET buddy_list='.strnull(addslashes(@serialize($br))).' WHERE id='.$oldu);
		unset($br);
	}
	draw_stat('Done: Rebuilding buddy list cache');

	draw_stat('Rebuilding ignore list cache');
	$oldu = '';
	$ir = array();
	$c = q('SELECT ignore_id, user_id FROM '.$tbl.'user_ignore ORDER BY user_id');
	while ($r = db_rowarr($c)) {
		if ($oldu != $r[1]) {
			if ($oldu) {
				q('UPDATE '.$tbl.'users SET ignore_list='.strnull(addslashes(@serialize($ir))).' WHERE id='.$oldu);
				$bi = array();
			}
			$oldu = $r[1];
		}
		$ir[$r[0]] = 1;
	}
	qf($c);
	if (count($ir)) {
		q('UPDATE '.$tbl.'users SET ignore_list='.strnull(addslashes(@serialize($ir))).' WHERE id='.$oldu);
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
	$c = q('SELECT distinct(user_id) FROM '.$tbl.'custom_tags');
	while ($r = db_rowarr($c)) {
		ctag_rebuild_cache($r[0]);
	}
	qf($c);
	draw_stat('Done Rebuilding custom tags for users');

	draw_stat('Validating group resources');
	delete_zero($tbl.'group_resources', 'SELECT gr.id FROM '.$tbl.'group_resources gr LEFT JOIN '.$tbl.'forum f ON f.id=gr.resource_id LEFT JOIN '.$tbl.'groups g ON g.id=gr.group_id WHERE f.id IS NULL OR g.id IS NULL');
	draw_stat('Done: Validating group resources');

	draw_stat('Validating group validity');
	# technically a group cannot exist without being assigned to at least 1 resource
	# so when we encounter such as group, we do our patriotic duty and remove it.
	delete_zero($tbl.'groups', 'SELECT g.id FROM '.$tbl.'groups g LEFT JOIN '.$tbl.'group_resources gr ON g.id=gr.group_id WHERE g.id > 2 AND gr.id IS NULL');
	draw_stat('Done: Validating group validity');

	draw_stat('Validating group members');
	delete_zero($tbl.'group_members', 'SELECT gm.id FROM '.$tbl.'group_members gm LEFT JOIN '.$tbl.'users u ON u.id=gm.user_id LEFT JOIN '.$tbl.'groups g ON g.id=gm.group_id WHERE (u.id IS NULL AND gm.user_id NOT IN(0, 2147483647)) OR g.id IS NULL');
	draw_stat('Done: Validating group members');

	draw_stat('Validating group/forum relations');
	$c = q('SELECT f.id, f.name FROM '.$tbl.'forum f LEFT JOIN '.$tbl.'groups g ON f.id=g.forum_id WHERE g.id IS NULL');
	while ($r = db_rowarr($c)) {
		group_add($r[0], $r[1], 2);
	}
	qf($c);
	draw_stat('Done: Validating group/forum relations');

	draw_stat('Validating group/forum names');
	$c = q('SELECT g.id, f.name FROM '.$tbl.'groups g INNER JOIN '.$tbl.'forum f ON f.id=g.forum_id WHERE g.id>2 AND f.name!=g.name');
	$i = 0;
	while ($r = db_rowarr($c)) {
		q("UPDATE ".$tbl."groups SET name='".addslashes($r[1])."' WHERE id=".$r[0]);
		++$i;
	}
	qf($r);
	draw_stat('Done: Validating group/forum names (fixed: '.$i.' relations)');

	draw_stat('Validating group/primary user relations');
	$c = q('SELECT g.id, gm1.id, gm2.id FROM '.$tbl.'groups g LEFT JOIN '.$tbl.'group_members gm1 ON gm1.group_id=g.id AND gm1.user_id=0 LEFT JOIN '.$tbl.'group_members gm2 ON gm2.group_id=g.id AND gm2.user_id=2147483647 WHERE g.id>2 AND g.forum_id>0 AND (gm1.id IS NULL OR gm2.id IS NULL)');
	while ($r = db_rowarr($c)) {
		if (!$r[1]) {
			$glm[$r[0]][] = 0;
		}
		if (!$r[2]) {
			$glm[$r[0]][] = 2147483647;
		}
	}
	qf($c);
	if (isset($glm)) {
		// make group based on 'primary' 1st group
		$fld_lst = implode(',', $GLOBALS['__GROUPS_INC']['permlist']);
		$anon = "'" . implode("', '", db_arr_assoc('SELECT '.$fld_lst.' FROM '.$tbl.'groups WHERE id=1')) . "'";
		$regu = "'" . implode("', '", db_arr_assoc('SELECT '.$fld_lst.' FROM '.$tbl.'groups WHERE id=2')) . "'";
		$fld_lst = str_replace('p_', 'up_', $fld_lst);
		foreach ($glm as $k => $v) {
			foreach ($v as $uid) {
				q('INSERT INTO '.$tbl.'group_members (group_id, user_id, '.$fld_lst.') VALUES ('.$k.', '.$uid.', '.(!$uid ? $anon : $regu).')');
			}
		}
	}
	draw_stat('Done: Validating group/primary user relations');

	draw_stat('Rebuilding group leader cache');
	$c = q('SELECT DISTINCT(user_id) FROM '.$tbl.'group_members WHERE group_members_opt>=131072 AND (group_members_opt & 131072) > 0');
	while ($r = db_rowarr($c)) {
		rebuild_group_ldr_cache($r[0]);
	}
	qf($c);
	draw_stat('Done: Rebuilding group leader cache');

	draw_stat('Rebuilding group cache');
	grp_rebuild_cache();
	draw_stat('Done: Rebuilding group cache');

	draw_stat('Validating User/Theme Relations');
	$te = array();
	$c = uq('SELECT u.id FROM '.$tbl.'users u LEFT JOIN '.$tbl.'themes thm ON thm.id=u.theme WHERE thm.id IS NULL');
	while (list($uid) = db_rowarr($c)) {
		$te[] = $uid;
	}
	qf($c);
	if ($te) {
		$tid = q_singleval('SELECT id FROM '.$tbl.'themes WHERE theme_opt=3');
		q('UPDATE '.$tbl.'users SET theme='.$tid.' WHERE id IN('.implode(',', $te).')');
	}
	draw_stat('Done: Validating User/Theme Relations');

	draw_stat('Rebuilding Forum/Category order cache');
	rebuild_forum_cat_order();
	draw_stat('Done: Rebuilding Forum/Category order cache');

	draw_stat('Unlocking database');
	db_unlock();
	if (__dbtype__ == 'mysql') {
		q('DROP TABLE '.$tbl.'tmp_consist');
	}
	draw_stat('Database unlocked');

	draw_stat('Cleaning forum\'s tmp directory');
	if (($d = opendir($TMP))) {
		readdir($d); readdir($d);
		while ($f = readdir($d)) {
			if (@is_file($TMP . $f)) {
				@unlink($TMP . $f);
			}
		}
		closedir($d);
	}
	draw_stat('Done: Cleaning forum\'s tmp directory');

	draw_stat('Validate GLOBALS.php');
	$gvars = array();
	$data = file($GLOBALS['INCLUDE'] . 'GLOBALS.php');
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
		$fp = fopen($GLOBALS['INCLUDE'] . 'GLOBALS.php', 'w');
		fwrite($fp, implode('', $data));
		if (strpos(array_pop($data), '?>') === false) {
			fwrite($fp, "\n?>");
		}
		fclose($fp);
	}
	draw_stat('Done: Validate GLOBALS.php');

	if ($FUD_OPT_1 & 1 || isset($_GET['enable_forum'])) {
		draw_stat('Re-enabling the forum.');
		maintenance_status($DISABLED_REASON, 0);
	} else {
		echo '<font size="+1" color="red">Your forum is currently disabled, to re-enable it go to the <a href="admglobal.php?'._rsid.'">Global Settings Manager</a> and re-enable it.</font><br>';
	}

	draw_stat('DONE');

	echo 'It is recommended that you run SQL table optimizer after completing the consistency check. To do so <a href="consist.php?opt=1">click here</a>, keep in mind that this process make take several minutes to perform.';
	echo '<script language="Javascript1.2">clearInterval(intervalID);</script>';
	readfile($WWW_ROOT_DISK . 'adm/admclose.html');
?>