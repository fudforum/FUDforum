<?php
/***************************************************************************
* copyright            : (C) 2001-2006 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: xmb.php,v 1.14 2006/09/19 14:37:55 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it 
* under the terms of the GNU General Public License as published by the 
* Free Software Foundation; version 2 of the License. 
***************************************************************************/

	/* XMB - FUDforum Conversion script - Brief Instructions */
	
	/* If you intend to run this script via the console, make sure to UNLOCK the 
	 * FUDforum 1st. I recommend running this script via the web unless the forum
	 * you are importing is very large.
	 */
	
	/* Specify the FULL path to the XMB config.php file */
	$XMB_CFG = "/path/to/xmb/config.php";
	

/**** DO NOT EDIT BEYOND THIS POINT ****/
	
function print_msg($msg)
{
	if (__WEB__) {
		echo $msg . "<br />\n";	
	} else {
		echo $msg . "\n";
	}
}

if (!function_exists("html_entity_decode")) {
	$GLOBALS['HET'] = array_flip(get_html_translation_table(HTML_ENTITIES));

	function html_entity_decode($str)
	{
		return strtr ($str, $GLOBALS['HET']);
	}
}

function make_avatar_loc($path, $disk, $web)
{
	$img_info = @getimagesize($disk . $path);

	if ($img_info[2] < 4 && $img_info[2] > 0) {
		return '<img src="'.$web . $path.'" '.$img_info[3].' />';
	} else if ($img_info[2] == 4) {
		return '<embed src="'.$web . $path.'" '.$img_info[3].' />';
	} else {
		return '';
	}
}

function html_clean($str)
{
	return preg_replace('!&#([0-9]+);!me', "chr('\\1')", html_entity_decode($str));
}

	set_time_limit(-1);
	ini_set('memory_limit', '128M');
	define('__WEB__', (isset($_SERVER["REMOTE_ADDR"]) === FALSE ? 0 : 1));

	/* prevent session initialization */
	define('forum_debug', 1);
	unset($_SERVER['REMOTE_ADDR']);

	$gl = @include("./GLOBALS.php"); 
	if ($gl === FALSE) {
		exit("This script must be placed in FUDforum's main web directory.\n");
	}

	if ($FILE_LOCK == 'Y' && !__WEB__) {
		exit("Since you are running conversion script via the console you must UNLOCK forum's files first.\n");
	}

	$gl = @include($XMB_CFG);
	if ($gl === FALSE) {
		exit("Unable to open XMB configuration at '{$XMB_CFG}'.\n");
	}

	if (!($xm = mysql_connect($dbhost, $dbuser, $dbpw))) {
		exit("Failed to connect to database containing XMB setting using MySQL information inside  '{$XMB_CFG}'.\n");
	}
	$xmp = $dbname . '.' . $tablepre;

	/* include all the necessary FUDforum includes */
	fud_use('err.inc');
	fud_use('db.inc');
	fud_use('users.inc');
	fud_use('users_reg.inc');
	fud_use('replace.inc');
	fud_use('smiley.inc');
	fud_use('post_proc.inc');
	fud_use('wordwrap.inc');
	fud_use('cat.inc', true);
	fud_use('groups.inc');
	fud_use('imsg_edt.inc');
	fud_use('th.inc');
	fud_use('th_adm.inc');
	fud_use('rev_fmt.inc');
	fud_use('fileio.inc');
	fud_use('isearch.inc');
	fud_use('attach.inc');
	fud_use('ipoll.inc');
	fud_use('private.inc');
	fud_use('forum_adm.inc', true);
	fud_use('groups_adm.inc', true);
	fud_use('glob.inc', true);

	/* some settings we must turn off */
	$GLOBALS['EMAIL_CONFIRMATION'] = 'N';
	$GLOBALS['MODERATE_USER_REGS'] = 'N';
	$GLOBALS['PUBLIC_RESOLVE_HOST'] = 'N';
	$GLOBALS['FORUM_SEARCH'] = 'N';
	$GLOBALS['FILE_LOCK'] = 'N';
	$GLOBALS['MOD'] = 1;

	/* import blocked words */
	print_msg("Importing blocked words");
	q("DELETE FROM {$DBHOST_TBL_PREFIX}replace");
	$i = 0;
	$r = mysql_query("SELECT find, replace1 FROM {$xmp}words", $xm) or die(mysql_error($xm));
	while (list($f, $rp) = mysql_fetch_row($r)) {
		$f = '/' . addcslashes($f, '/') . '/';
		q("INSERT INTO {$DBHOST_TBL_PREFIX}replace (type, replace_str, with_str) VALUES('REPLACE', '".addslashes($f)."', '".addslashes($rp)."')");
		++$i;
	}
	mysql_free_result($r);
	print_msg("Done: Importing {$i} blocked words");

	/* Add XMB members */
	print_msg("Importing XMB members");

	q("DELETE FROM {$DBHOST_TBL_PREFIX}users WHERE id>1");
	q("DELETE FROM {$DBHOST_TBL_PREFIX}custom_tags");
	q("DELETE FROM {$DBHOST_TBL_PREFIX}ses");
	$i = 0;
	$r = mysql_query("SELECT * FROM {$xmp}members ORDER BY uid", $xm) or die(mysql_error($xm));

	/* common settings for all users */
	$u = new fud_user_reg;
	$u->plaintext_passwd = 'a';
	$u->gender = 'UNSPECIFIED';
	$u->notify_method = 'EMAIL';
	$u->pm_notify = $u->notify = $u->email_messages = $u->pm_messages = $u->append_sig = 'Y';
	$u->invisible_mode = $u->coppa = 'N';
	$u->default_view = $GLOBALS['DEFAULT_THREAD_VIEW'];
	$u->time_zone = $GLOBALS['SERVER_TZ'];
	$u->user_image = $u->occupation = $u->interests = '';
	$u->theme = q_singleval("SELECT id FROM {$DBHOST_TBL_PREFIX}themes WHERE t_default='Y' AND enabled='Y' LIMIT 1");
	$ext = array(1=>'gif', 2=>'jpg', 3=>'png', 4=>'swf');

	while ($obj = mysql_fetch_object($r)) {
		$u->login = $u->name = $obj->username;
		$u->email = $obj->email;
		$u->ignore_admin = $obj->newsletter == 'yes' ? 'Y' : 'N';
		$u->icq = $obj->icq;
		$u->aim = $obj->aim;
		$u->yahoo = $obj->yahoo;
		$u->msnm = $obj->msn;
		$u->posts_ppg = $obj->ppp;
		if ($obj->bday) {
			$u->bday =  date("Ymd", strtotime($obj->bday));
		} else {
			$u->bday = 0;
		}
		$u->location = $obj->location;
		$u->bio = $obj->bio;
		if ($obj->site) {
			$tmp = parse_url($obj->site);
			if (!isset($tmp['scheme'])) {
				$obj->site = 'http://' . $obj->site;
			}
		}
		$u->home_page = $obj->site;
		if ($obj->sig) {
			$tmp = apply_custom_replace(html_clean($obj->sig));
			switch (strtolower($GLOBALS['FORUM_CODE_SIG'])) {
				case 'ml':
					$tmp = tags_to_html($tmp, $GLOBALS['FORUM_IMG_SIG']);
					break;
				case 'html':
					break;
				default:
					$tmp = nl2br(htmlspecialchars($tmp));				       
			}
			if ($GLOBALS['FORUM_SML_SIG'] == 'Y') {
				$tmp = smiley_to_post($tmp);
			}
			fud_wordwrap($tmp);

			$u->sig = $tmp;
		} else {
			$u->sig = '';
		}
		$u->display_email = $obj->showemail == 'yes' ? 'Y' : 'N';

		$uid = $u->add_user();
		$xmb_u[$obj->username] = $uid;

		/* update settings we could not change during user creation */
		switch ($obj->status) {
			case 'Administrator':
			case 'Super Administrator':
				$is_mod = 'A';
				break;
			case 'Super Moderator':
			case 'Moderator':
				$is_mod = 'Y';
				break;
			default:
				$is_mod = 'N';
		}
		$blocked = $obj->ban == 'yes' ? 'Y' : 'N';

		$avatar_loc = 'NULL';
		$avatar_approved = 'NO';

		if ($obj->avatar && ($tmpd = file_get_contents($obj->avatar))) {
			$tmp = tempnam($GLOBALS['TMP'], getmyuid());
			$fp = fopen($tmp, "wb");
			fwrite($fp, $tmpd);
			fclose($fp);

			if (($im = getimagesize($tmp)) && isset($ext[$im[2]])) {
				$ex = $ext[$im[2]];
				$path = "images/custom_avatars/{$uid}.{$ex}";
				copy($tmp, $WWW_ROOT_DISK . $path);
				chmod($path, 0666);
				$avatar_approved = 'Y';
				$avatar_loc = make_avatar_loc($path, $WWW_ROOT_DISK, $WWW_ROOT);
			}
			unlink($tmp);
		}

		if ($obj->customstatus) {
			q("INSERT INTO {$DBHOST_TBL_PREFIX}custom_tags (name, user_id) VALUES(".ssn($obj->customstatus).", {$uid})");
		}

		q("UPDATE {$DBHOST_TBL_PREFIX}users SET
			is_mod='{$is_mod}',
			join_date=".(int)$obj->regdate.",
			last_visit=".(int)$obj->lastvisit.",
			last_read=".(int)$obj->lastvisit.",
			blocked='{$blocked}',
			avatar_approved='{$avatar_approved}',
			avatar_loc='{$avatar_loc}',
			passwd='{$obj->password}'
		WHERE id=".$uid);

		++$i;
	}
	mysql_free_result($r);
	print_msg("Done: Importing {$i} XMB members");

	$xmb_u = array_change_key_case($xmb_u);

	print_msg("Importing buddies");
	q("DELETE FROM {$DBHOST_TBL_PREFIX}buddy");
	q("DELETE FROM {$DBHOST_TBL_PREFIX}user_ignore");

	$i = 0;
	$r = mysql_query("SELECT username, buddyname FROM {$xmp}buddys", $xm) or die(mysql_error($xm));
	while (list($u, $b) = mysql_fetch_row($r)) {
		q("INSERT INTO {$DBHOST_TBL_PREFIX}buddy (bud_id, user_id) VALUES ({$xmb_u[strtolower($b)]}, {$xmb_u[strtolower($u)]})");
		++$i;
	}
	mysql_free_result($r);
	print_msg("Done: Importing {$i} buddies");
	
	print_msg("Importing member ranks");
	q("DELETE FROM {$DBHOST_TBL_PREFIX}level");
	$i = 0;
	$r = mysql_query("SELECT title,posts,allowavatars FROM {$xmp}ranks WHERE posts>0", $xm) or die(mysql_error($xm));
	while (list($t, $p, $a) = mysql_fetch_row($r)) {
		$a = ($a == 'yes' ? 'B' : 'L');

		q("INSERT INTO {$DBHOST_TBL_PREFIX}level (name, pri, post_count) VALUES ('".addslashes($t)."','{$a}', {$p})");
		++$i;
	}
	mysql_free_result($r);
	print_msg("Done: Importing {$i} member ranks");
	
	print_msg("Importing forums");
	q("DELETE FROM {$DBHOST_TBL_PREFIX}forum");
	q("DELETE FROM {$DBHOST_TBL_PREFIX}cat");
	q("DELETE FROM {$DBHOST_TBL_PREFIX}forum_read");
	q("DELETE FROM {$DBHOST_TBL_PREFIX}forum_notify");
	q("DELETE FROM {$DBHOST_TBL_PREFIX}group_cache");
	q("DELETE FROM {$DBHOST_TBL_PREFIX}group_resources");
	q("DELETE FROM {$DBHOST_TBL_PREFIX}mlist");
	q("DELETE FROM {$DBHOST_TBL_PREFIX}nntp");
	q("DELETE FROM {$DBHOST_TBL_PREFIX}group_members WHERE id>2");
	q("DELETE FROM {$DBHOST_TBL_PREFIX}groups WHERE id>2");

	$i = 0;

	$cat = new fud_cat;
	$cat->name = $cat->description = "Default Category";
	$cat->allow_collapse = 'Y';
	$cat->default_view = 'OPEN';
	$cat_id = $cat->add('');

	$frm = new fud_forum;
	$frm->max_attach_size = 1024;
	$frm->max_file_attachments = 1;
	$frm->message_threshold = 0;
	$frm->moderated = 'N';
	$frm->cat_id = $cat_id;
	$frm->icon= '';

	$r = mysql_query("SELECT * FROM {$xmp}forums ORDER BY fup, displayorder", $xm) or die(mysql_error($xm));
	while ($obj = mysql_fetch_object($r)) {
		$frm->name = $obj->name;
		$frm->descr = $obj->description;

		if ($obj->allowbbcode == 'yes') {
			$frm->tag_style = 'ML';
		} else if ($obj->allowhtml == 'yes') {
			$frm->tag_style = 'HTML';
		} else {
			$frm->tag_style = 'NONE';
		}

		if ($obj->password) {
			$frm->passwd_posting = 'Y';
			$frm->post_passwd = $obj->password;
		} else {
			$frm->passwd_posting = 'N';
			$frm->post_passwd = NULL;
		}
		
		$frm_id = $frm->add('LAST');
		$xmb_f[$obj->fid] = $frm_id;

		/* import forum moderators */
		if ($obj->moderator) {
			$mods = explode(', ', $obj->moderator);
			foreach ($mods as $mod) {
				q("INSERT INTO {$DBHOST_TBL_PREFIX}mod (forum_id, user_id) VALUES({$frm_id}, {$xmb_u[strtolower($mod)]})");
			}
		}

		/* Import various forum permissions */
		$gid = q_singleval("SELECT id FROM {$DBHOST_TBL_PREFIX}groups WHERE forum_id=".$frm_id);
		$perms = db_arr_assoc("SELECT * FROM {$DBHOST_TBL_PREFIX}group_members WHERE group_id={$gid} AND user_id=2147483647");
		unset($perms['id'], $perms['approved'], $perms['group_leader'], $perms['group_id'], $perms['user_id']);
		$perms = array_change_key_case($perms);

		$perms['up_sml'] = $obj->allowsmilies == 'yes' ? 'Y' : 'N';
		$perms['up_img'] = $obj->allowimgcode == 'yes' ? 'Y' : 'N';
		$perms['up_file'] = $obj->attachstatus == 'yes' ? 'Y' : 'N';
		$perms['up_vote'] = $perms['up_poll'] = $obj->pollstatus == 'yes' ? 'Y' : 'N';
		$perms['up_read'] = $perms['up_visible'] = $obj->status == 'yes' ? 'Y' : 'N';

		$tmp = explode('|', $obj->postperm);
		$perms['up_post'] = $tmp[0] == '1' ? 'Y' : 'N';
		$perms['up_reply'] = (isset($tmp[1]) && $tmp[1] == '1') ? 'Y' : 'N';

		if ($obj->userlist) {
			$users = explode(',', $obj->userlist);
			$fld = implode(',', array_keys($perms));
			$data = str_replace('N', "'N'", str_replace('Y', "'Y'", implode(',', $perms)));

			foreach ($users as $u) {
				q("INSERT INTO {$DBHOST_TBL_PREFIX}group_members ({$fld} user_id, group_id) VALUES ({$data}, {$xmb_u[strtolower($u)]}, {$gid})");
			}

			foreach ($perms as $k => $v) {
				$perms[$k] = 'N';
			}
			$obj->guestposting = 'yes';
		}
		$tmp = array();
		foreach ($perms as $k => $v) {
			$tmp[] = "$k='{$v}'";
		}

		q("UPDATE {$DBHOST_TBL_PREFIX}group_members SET ".implode(',', $tmp)." WHERE group_id={$gid} AND user_id=2147483647");
		if ($obj->guestposting == 'yes') {
			q("UPDATE {$DBHOST_TBL_PREFIX}group_members SET ".implode(',', $tmp)." WHERE group_id={$gid} AND user_id=0");
		}

		++$i;
	}
	mysql_free_result($r);
	print_msg("Done: Importing {$i} forums");

	print_msg("Importing messages");
	$i = 0;
	q("DELETE FROM {$DBHOST_TBL_PREFIX}thread");
	q("DELETE FROM {$DBHOST_TBL_PREFIX}thr_exchange");
	q("DELETE FROM {$DBHOST_TBL_PREFIX}thread_notify");
	q("DELETE FROM {$DBHOST_TBL_PREFIX}thread_rate_track");
	q("DELETE FROM {$DBHOST_TBL_PREFIX}thread_view");
	q("DELETE FROM {$DBHOST_TBL_PREFIX}msg");
	q("DELETE FROM {$DBHOST_TBL_PREFIX}msg_report");
	q("DELETE FROM {$DBHOST_TBL_PREFIX}mod_que");
	q("DELETE FROM {$DBHOST_TBL_PREFIX}read");
	q("DELETE FROM {$DBHOST_TBL_PREFIX}search");
	q("DELETE FROM {$DBHOST_TBL_PREFIX}search_cache");
	q("DELETE FROM {$DBHOST_TBL_PREFIX}title_index");
	q("DELETE FROM {$DBHOST_TBL_PREFIX}index");
	
	$r = mysql_query("SELECT * FROM {$xmp}posts ORDER BY fid, tid", $xm) or die(mysql_error($xm));
	$m = new fud_msg_edit;
	$xmb_t = array();
	while ($obj = mysql_fetch_object($r)) {
		$m->subject = html_clean($obj->subject);
		$m->ip_addr = $obj->useip ? $obj->useip: '0.0.0.0';
		if (strcasecmp($obj->author, 'anonymous')) {
			$m->poster_id = $xmb_u[strtolower($obj->author)];
		} else {
			$m->poster_id = 0;
		}
		$m->post_stamp = $obj->dateline;
		$m->show_sig = $obj->usesig == 'no' ? 'N' : 'Y';
		$m->smiley_disabled = $obj->smileyoff == 'yes' ? 'Y' : 'N';
		if (isset($xmb_t[$obj->tid])) {
			$m->thread_id = $xmb_t[$obj->tid][0];
			$m->reply_to = $xmb_t[$obj->tid][1];
		} else {
			$m->thread_id = $m->reply_to = 0;
		}
		$m->body = html_clean($obj->message);
		
		$m->body = apply_custom_replace($m->body);
		switch ($frm->tag_style) {
			case 'ML':
				$m->body = tags_to_html($m->body, 'Y');
				break;
			case 'HTML':
				break;
			default:
				$m->body = nl2br(htmlspecialchars($m->body));
		}
		if ($m->smiley_disabled != 'Y') {
			$m->body = smiley_to_post($m->body);
		}

		fud_wordwrap($m->body);

		$m->subject = htmlspecialchars(apply_custom_replace($m->subject));

		$mid = $m->add($xmb_f[$obj->fid], 0, 'Y', 'Y', 'Y');

		if (!isset($xmb_t[$obj->tid])) {
			$xmb_t[$obj->tid] = db_saq("SELECT thread_id, id FROM {$DBHOST_TBL_PREFIX}msg WHERE id=".$mid);
		}

		$xmb_m[$obj->pid] = $mid;

		++$i;
	}
	mysql_free_result($r);

	print_msg("Done: Importing {$i} messages");

	print_msg("Importing threads");
	$i = 0;
	
	$r = mysql_query("SELECT * FROM {$xmp}threads ORDER BY fid", $xm) or die(mysql_error($xm));
	while ($obj = mysql_fetch_object($r)) {
		if ($obj->closed == 'yes') {
			$locked = 'Y';
		} else if (!strncmp($obj->closed, 'moved', 5)) {
			/* moved thread pointer */
			$moved[] = array(substr(strrchr($obj->closed, '|'), 1), $xmb_f[$obj->fid]);
		} else {
			$locked = 'N';
		}

		if ($obj->topped) {
			$ordertype = 'STICKY';	
			$orderexpiry = '1000000000';
			$is_sticky = 'Y';
		} else {
			$ordertype = 'NONE';
			$orderexpiry = '0';
			$is_sticky = 'N';
		}

		q("UPDATE {$DBHOST_TBL_PREFIX}thread SET
			views={$obj->views},
			ordertype='{$ordertype}',
			orderexpiry={$orderexpiry},
			is_sticky='{$is_sticky}',
			locked='{$locked}'
		WHERE id={$xmb_t[$obj->tid][0]}");

		++$i;
	}
	mysql_free_result($r);

	/* handle moved thread pointers if there are any */
	if (isset($moved)) {
		foreach ($moved as $move) {
			$d = db_saq("SELECT {$move[1]}, root_msg_id, last_post_date, last_post_id, forum_id FROM {$DBHOST_TBL_PREFIX}thread WHERE id=".$xmb_t[$move[0]][0]);
			q("INSERT INTO {$DBHOST_TBL_PREFIX}thread (forum_id, root_msg_id, last_post_date, last_post_id, moved_to) VALUES({$d[0]}, {$d[1]}, {$d[2]}, {$d[3]}, {$d[4]})");
		}
		unset($moved, $move, $d);
	}

	print_msg("Done: Importing {$i} threads");

	print_msg("Importing thread subscriptions");
	$i = 0;
	$r = mysql_query("SELECT tid, LOWER(username) FROM {$xmp}favorites WHERE type='subscription'", $xm) or die(mysql_error($xm));
	while (list($t, $u) = mysql_fetch_row($r)) {
		q("INSERT INTO {$DBHOST_TBL_PREFIX}thread_notify (user_id, thread_id) VALUES({$xmb_u[$u]}, {$xmb_t[$t][0]})");
		++$i;
	}
	mysql_free_result($r);
	print_msg("Done: Importing {$i} thread subscriptions");

	print_msg("Importing message attachments");
	$i = 0;
	q("DELETE FROM {$DBHOST_TBL_PREFIX}attach");
	$r = mysql_query("SELECT * FROM {$xmp}attachments", $xm) or die(mysql_error($xm));
	$old_m = 0; $al = array();
	while ($obj = mysql_fetch_object($r)) {
		if ($old_m && $old_m != $obj->pid) {
			attach_finalize($al, $xmb_m[$old_m], 'N');
			$al = array();
		}
		$old_m = $obj->pid;

		$tmpf = tempnam($GLOBALS['TMP'], getmyuid());
		$fp = fopen($tmpf, "wb");
		fwrite($fp, $obj->attachment);
		fclose($fp);

		$tmp = array('name' => $obj->filename, 'size' => $obj->filesize, 'tmp_name' => $tmpf);
		$owner = q_singleval("SELECT poster_id FROM {$DBHOST_TBL_PREFIX}msg WHERE id=".$xmb_m[$obj->pid]);
		$aid = attach_add($tmp, $owner, 'N', 1);

		q("UPDATE {$DBHOST_TBL_PREFIX}attach SET dlcount={$obj->downloads} WHERE id=".$aid);

		$al[$aid] = 1;

		++$i;
	}
	if ($al) {
		attach_finalize($al, $xmb_m[$old_m], 'N');
		unset($al, $old_m);
	}

	mysql_free_result($r);
	print_msg("Done: Importing {$i} message attachments");

	print_msg("Importing polls");
	$i = 0;
	q("DELETE FROM {$DBHOST_TBL_PREFIX}poll");
	q("DELETE FROM {$DBHOST_TBL_PREFIX}poll_opt");
	q("DELETE FROM {$DBHOST_TBL_PREFIX}poll_opt_track");
	$r = mysql_query("SELECT tid, fid, pollopts FROM {$xmp}threads WHERE pollopts != ''", $xm) or die(mysql_error($xm));
	/* we need this for polls :( */
	define('_uid', 0);
	
	while (list($t, $f, $pd) = mysql_fetch_row($r)) {
		list($mid, $own, $tags) = db_saq("SELECT 
							t.root_msg_id,
							m.poster_id,
							f.tag_style
							FROM {$DBHOST_TBL_PREFIX}thread t 
							INNER JOIN {$DBHOST_TBL_PREFIX}msg m ON m.id=t.root_msg_id
							INNER JOIN {$DBHOST_TBL_PREFIX}forum f ON f.id=t.forum_id
							WHERE t.id=".$xmb_t[$t][0]);

		$opts = explode("#|#", str_replace("\n", "", $pd));
		$op = $voters = array();
		$ttl = 0;

		++$i;
		/* create poll */
		$pid = poll_add("Poll #{$i}", 0, 0);
		q("UPDATE {$DBHOST_TBL_PREFIX}poll SET owner={$own} WHERE id=".$pid);

		/* add options */
		foreach ($opts as $opt) {
			if ($opt) {
				if ($opt{0} != ' ') {
					list($o, $v) = explode('||~|~|| ', $opt);
					$o = html_clean(trim($o));
					$o = apply_custom_replace($o);
					switch ($tags) {
						case 'ML':
							$o = tags_to_html($o, 'Y');
							break;
						case 'HTML':
							break;
						default:
							$o = nl2br(htmlspecialchars($o));	
					}
					$o = smiley_to_post($o);

					$oid = poll_opt_add($o, $pid);
					q("UPDATE {$DBHOST_TBL_PREFIX}poll_opt SET count={$v} WHERE id=".$oid);
					$op[$oid] = $v;
					$ttl += $v;
				} else {
					$voters[] = $xmb_u[strtolower(trim($opt))];
				}
			}
		}
		poll_activate($pid, $xmb_f[$f]);

		/* register votes */
		foreach ($op as $k => $v) {
			for ($j = 0; $j < $v; $j++) {
				if (!($uid = array_pop($voters))) {
					/* shouldn't happen, but we check anyway */
					break 2;
				}
				q("INSERT INTO {$DBHOST_TBL_PREFIX}poll_opt_track (poll_id, user_id, poll_opt) VALUES({$pid}, {$uid}, {$k})");
			}
		}
		        
		q("UPDATE {$DBHOST_TBL_PREFIX}poll SET total_votes={$ttl} WHERE id=".$pid);
		q("UPDATE {$DBHOST_TBL_PREFIX}msg SET poll_id={$pid} WHERE id=".$mid);
	}
	mysql_free_result($r);
	print_msg("Done: Importing {$i} polls");

	print_msg("Importing private messages");
	q("DELETE FROM {$DBHOST_TBL_PREFIX}pmsg");
	$i = 0;
	$r = mysql_query("SELECT * FROM {$xmp}u2u", $xm) or die(mysql_error($xm));
	$p = new fud_pmsg;
	/* common settings */
	$p->mailed = 'Y';
	$p->icon = '';
	$p->smiley_disabled = $p->track = 'N';
	$p->show_sig = 'Y';

	while ($obj = mysql_fetch_object($r)) {
		$p->ouser_id = $xmb_u[strtolower($obj->msgfrom)];
		$GLOBALS['recv_user_id'] = $p->duser_id = $xmb_u[strtolower($obj->msgto)];
		$p->to_list = $obj->msgto;

		$p->subject = apply_custom_replace(html_clean($obj->subject));
		$p->body = apply_custom_replace(html_clean($obj->message));
                switch ($PRIVATE_TAGS) {
			case 'ML':
                        	$p->body = tags_to_html($p->body, $PRIVATE_IMAGES);
                                break;
			case 'HTML':
                        	break;
			default:
                        	$p->body = nl2br(htmlspecialchars($p->body));
                        	break;
		}
		$p->body = smiley_to_post($p->body); 
		fud_wordwrap($p->body);

		if ($obj->folder == 'inbox') {
			$p->folder_id = 'INBOX';
		} else {
			$p->folder_id = 'SENT';
		}
		
		$p->add(1);

		if ($obj->folder == 'inbox') {
			$p->post_stamp = $obj->dateline;
			if ($obj->readstatus == 'no') {
				$p->read_stamp = 0;
			}
			$to = $p->duser_id;
		} else {
			$p->post_stamp = $p->read_stamp = $obj->dateline;
			$to = $p->ouser_id;
		}
		
		q("UPDATE {$DBHOST_TBL_PREFIX}pmsg SET post_stamp={$p->post_stamp}, read_stamp={$p->read_stamp}, ouser_id={$to} WHERE id=".$p->id);
		++$i;
	}
	mysql_free_result($r);

	print_msg("Done: Importing {$i} private messages");

	print_msg("Importing miscellaneous settings");
	$r = mysql_query("SELECT * FROM {$xmp}settings", $xm) or die(mysql_error($xm));
	$obj = mysql_fetch_object($r);

	$list = array();

	$list['FORUM_TITLE'] = $obj->bbname;
	$list['POSTS_PER_PAGE'] = $obj->postperpage;
	$list['THREADS_PER_PAGE'] = $obj->topicperpage;
	$list['LOGEDIN_LIST'] = $list['ONLINE_OFFLINE_STATUS'] = $obj->whosonlinestatus == 'on' ? 'Y' : 'N';
	$list['ALLOW_REGISTRATION'] = $obj->regstatus == 'on' ? 'Y' : 'N';
	$list['DISABLED_REASON'] = $obj->bboffreason;
	$list['FLOOD_CHECK_TIME'] = $obj->floodctrl;
	$list['MEMBERS_PER_PAGE'] = $obj->memberperpage;
	$list['EMAIL_CONFIRMATION'] = $obj->emailcheck == 'on' ? 'Y' : 'N';
	$list['FORUM_SEARCH'] = $obj->searchstatus == 'on' ? 'Y' : 'N';
	$list['MEMBER_SEARCH_ENABLED'] = $obj->memliststatus == 'on' ? 'Y' : 'N';
	$list['PHP_COMPRESSION_ENABLE'] = $obj->gzipcompress == 'on' ? 'Y' : 'N';
	$list['COPPA'] = $obj->coppa == 'on' ? 'Y' : 'N';
	$list['ADMIN_EMAIL'] = $obj->adminemail;
	if ($obj->sigbbcode == 'on') {
		$list['FORUM_CODE_SIG'] = 'ML';
	} else if ($obj->sightml == 'on') {
		$list['FORUM_CODE_SIG'] = 'HTML';
	} else {
		$list['FORUM_CODE_SIG'] = 'N';
	}
	$list['SHOW_EDITED_BY'] = $list['EDITED_BY_MOD'] = $obj->editedby == 'on' ? 'Y' : 'N';
	$list['FORUM_INFO'] = $obj->stats == 'on' ? 'Y' : 'N';

	change_global_settings($list);

	print_msg("Done: Importing miscellaneous settings");

	print_msg("Conversion Process Complete!");
	print_msg("");
	print_msg("");
	print_msg("------------------------------------------------------------------------------");
	print_msg("                                IMPORTANT!!!");
	print_msg("------------------------------------------------------------------------------");
	print_msg("To complete the conversion process run the consistency checker at:");
	print_msg("{$WWW_ROOT}adm/consist.php");
	print_msg("You will need to login using the administrator account from the forum you've");
	print_msg("just imported.");	
	print_msg("");
	print_msg("If you want the imported messages to be searcheable, rebuild the search index");
	print_msg("Rebuild Search Index admin control panel.");
	print_msg("------------------------------------------------------------------------------");
	print_msg("");
?>