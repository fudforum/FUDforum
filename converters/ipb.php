<?php
/***************************************************************************
* copyright            : (C) 2001-2004 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: ipb.php,v 1.4 2004/01/27 01:04:42 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it 
* under the terms of the GNU General Public License as published by the 
* Free Software Foundation; either version 2 of the License, or 
* (at your option) any later version.
***************************************************************************/

	/* Invision Power Board (1.1.X) - FUDforum Conversion script - Brief Instructions */

	/* If you intend to run this script via the console, make sure to UNLOCK the 
	 * FUDforum 1st. I recommend running this script via the web unless the forum
	 * you are importing is very large.
	 */

	/* Specify the FULL path to the Invision Power Board conf_global.php file */
	$IPB_CFG = "/path/to/conf_global.php";


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

	$gl = @include($IPB_CFG);
	if ($gl === FALSE) {
		exit("Unable to open IPB configuration at '{$IPB_CFG}'.\n");
	}

	if (!($ib = mysql_connect($INFO['sql_host'], $INFO['sql_user'], $INFO['sql_pass']))) {
		exit("Failed to connect to database containing IPB setting using MySQL information inside  '{$IPB_CFG}'.\n");
	}
	$ipb = $INFO['sql_database'] . '.' . $INFO['sql_tbl_prefix'];

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
	fud_use('imsg.inc');
	fud_use('imsg_edt.inc');
	fud_use('th.inc');
	fud_use('th_adm.inc');
	fud_use('rev_fmt.inc');
	fud_use('forum.inc');
	fud_use('fileio.inc');
	fud_use('isearch.inc');
	fud_use('attach.inc');
	fud_use('ipoll.inc');
	fud_use('private.inc');
	fud_use('forum_adm.inc', true);
	fud_use('groups_adm.inc', true);
	fud_use('glob.inc', true);

	/* IB includes */
	$path = dirname($IPB_CFG) . '/';
	include $path . "sources/lib/post_parser.php";

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
	$r = mysql_query("SELECT type, swop, m_exact FROM {$ipb}badwords", $ib) or die(mysql_error($ib));
	while (list($t, $s, $m) = mysql_fetch_row($r)) {
		if (!$s) {
			$s = '######';
		}
		if ($m) {
			$f2 = '/(^|\s)' . addcslashes($t, '/') . '($|\s)/i';
			$s2 = "\\1{$s}\\2";
			q("INSERT INTO {$DBHOST_TBL_PREFIX}replace (type, replace_str, with_str, from_post, to_msg) VALUES('PERL', '".addslashes($f2)."', '".addslashes($s2)."', '/".addslashes(addcslashes($s, '/'))."/', '".addslashes($t)."')");
		} else {
			$f = '/' . addcslashes($t, '/') . '/i';
			q("INSERT INTO {$DBHOST_TBL_PREFIX}replace (type, replace_str, with_str) VALUES('REPLACE', '".addslashes($f)."', '".addslashes($s)."')");
		}
		++$i;
	}
	mysql_free_result($r);
	print_msg("Done: Importing {$i} blocked words");
	
	/* Add IPB members */
	print_msg("Importing forum members");

	q("DELETE FROM {$DBHOST_TBL_PREFIX}users WHERE id>1");
	q("DELETE FROM {$DBHOST_TBL_PREFIX}custom_tags");
	q("DELETE FROM {$DBHOST_TBL_PREFIX}ses");
	$i = 0;
	$r = mysql_query("SELECT * FROM {$ipb}members WHERE id>0 ORDER BY id", $ib) or die(mysql_error($ib));

	/* common settings for all users */
	$u = new fud_user_reg;
	$u->plaintext_passwd = 'a';
	$u->gender = 'UNSPECIFIED';
	$u->notify_method = 'EMAIL';
	$u->email_messages = $u->pm_messages = 'Y';
	$u->invisible_mode = 'N';
	$u->default_view = $GLOBALS['DEFAULT_THREAD_VIEW'];
	$u->time_zone = $GLOBALS['SERVER_TZ'];
	$u->user_image = $u->occupation = '';
	$u->theme = q_singleval("SELECT id FROM {$DBHOST_TBL_PREFIX}themes WHERE t_default='Y' AND enabled='Y' LIMIT 1");
	$ext = array(1=>'gif', 2=>'jpg', 3=>'png', 4=>'swf');

	$pp = new post_parser();

	while ($obj = mysql_fetch_object($r)) {
		/* uniqueness checks */
		if (($id = q_singleval("SELECT id FROM {$DBHOST_TBL_PREFIX}users WHERE name='".addslashes($obj->name)."' OR email='".addslashes($obj->email)."'"))) {
			$ib_u[$obj->id]	= $id;
			continue;
		}

		$u->login = $u->name = $obj->name;
		$u->email = $obj->email;
		$u->ignore_admin = $obj->allow_admin_mails ? 'Y' : 'N';
		$u->icq = $obj->icq_number;
		$u->aim = $obj->aim_name;
		$u->yahoo = $obj->yahoo;
		$u->msnm = $obj->msnname;
		$u->location = $obj->location;
		$u->interests = $obj->interests;
		$u->pm_notify = $obj->email_pm ? 'Y' : 'N';
		$u->coppa = !$obj->coppa_user ? 'N' : 'Y';
		$u->notify = $obj->auto_track ? 'Y' : 'N';
		$u->show_sigs = $obj->view_sigs ? 'Y' : 'N';
		$u->show_avatars = $obj->view_avs ? 'Y' : 'N';
		$u->display_email = $obj->hide_email ? 'N' : 'Y';

		if ($obj->bday_day && $obj->bday_month && $obj->bday_year) {
			$u->bday = $obj->bday_year . str_pad($obj->bday_month, 2, '0', STR_PAD_LEFT) . str_pad($obj->bday_day, 2, '0', STR_PAD_LEFT);
		} else {
			$u->bday = 0;
		}
		list($ppg, ) = explode('&', $obj->view_prefs);
		if ($ppg != "-1") {
			$u->posts_ppg = $ppg;
		} else {
			$u->posts_ppg = $INFO['display_max_posts'];
		}

		if ($obj->website) {
			if ($obj->website != 'http://') {
				$tmp = parse_url(preg_replace('!\s.*!', '', $obj->website));
				if (!isset($tmp['scheme'])) {
					$obj->website = 'http://' . $obj->website;
				}
			} else {
				$obj->website = '';
			}
		}
		$u->home_page = $obj->website;

		if ($obj->signature) {
			$tmp = html_clean($pp->unconvert($obj->signature, $INFO['sig_allow_ibc'], $INFO['sig_allow_html']));
			if ($INFO['sig_allow_ibc']) {
				$tmp = tags_to_html($tmp, 1);
			} else if (!$INFO['sig_allow_html']) {
				$tmp = nl2br(htmlspecialchars($tmp));
			}

			$tmp = smiley_to_post($tmp);
			fud_wordwrap($tmp);

			$u->sig = $tmp;
		} else {
			$u->sig = '';
		}

		$ib_u[$obj->id] = $uid = $u->add_user();

		/* update settings we could not change during user creation */
		$avatar_loc = 'NULL';
		$avatar_approved = 'NO';

		if ($obj->avatar && $obj->avatar != 'noavatar') {
			if (!strncmp($obj->avatar, 'upload:', strlen('upload:'))) {
				$obj->avatar = $INFO['upload_dir']  . '/' . substr($obj->avatar, strlen('upload:'));
			} else if (strpos($obj->avatar, '://') === FALSE) {
				$obj->avatar = $INFO['html_url'] . '/avatars/' . $obj->avatar;
			}
			if (($tmpd = file_get_contents($obj->avatar))) {
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
		}

		if ($obj->title) {
			q("INSERT INTO {$DBHOST_TBL_PREFIX}custom_tags (name, user_id) VALUES(".strnull(addslashes($obj->title)).", {$uid})");
		}

		q("UPDATE {$DBHOST_TBL_PREFIX}users SET
			join_date={$obj->joined},
			last_visit={$obj->last_visit},
			last_read={$obj->last_activity},
			avatar_approved='{$avatar_approved}',
			avatar_loc='{$avatar_loc}',
			passwd='{$obj->password}'
		WHERE id=".$uid);

		++$i;
	}
	mysql_free_result($r);
	print_msg("Done: Importing {$i} IPB members");

	print_msg("Importing buddies/ignored users");
	q("DELETE FROM {$DBHOST_TBL_PREFIX}buddy");
	q("DELETE FROM {$DBHOST_TBL_PREFIX}user_ignore");

	$i2 = $i = 0;
	$r = mysql_query("SELECT contact_id, member_id, allow_msg FROM {$ipb}contacts", $ib) or die(mysql_error($ib));
	while (list($c, $m, $a) = mysql_fetch_row($r)) {
		if ($a) {
			q("INSERT INTO {$DBHOST_TBL_PREFIX}buddy (bud_id, user_id) VALUES ({$ib_u[$c]}, {$ib_u[$m]})");
			++$i;
		} else {
			q("INSERT INTO {$DBHOST_TBL_PREFIX}user_ignore (ignore_id, user_id) VALUES ({$ib_u[$c]}, {$ib_u[$m]})");
			++$i2;
		}
	}
	mysql_free_result($r);
	print_msg("Done: Importing {$i} buddies and {$i2} ignored users");

	print_msg("Importing member ranks");
	q("DELETE FROM {$DBHOST_TBL_PREFIX}level");
	$i = 0;
	$r = mysql_query("SELECT posts, title FROM {$ipb}titles WHERE posts>0", $ib) or die(mysql_error($ib));
	while (list($p, $t) = mysql_fetch_row($r)) {
		q("INSERT INTO {$DBHOST_TBL_PREFIX}level (name, post_count) VALUES ('".addslashes($t)."','{$a}')");
		++$i;
	}
	mysql_free_result($r);
	print_msg("Done: Importing {$i} member ranks");	

	print_msg("Importing categories");
	q("DELETE FROM {$DBHOST_TBL_PREFIX}cat");
	$i = 0;
	$r = mysql_query("SELECT id, state, name, description FROM {$ipb}categories WHERE id>0 ORDER BY state", $ib) or die(mysql_error($ib));
	$cat = new fud_cat;
	while ($obj = mysql_fetch_object($r)) {
		$cat->name = $obj->name;
		$cat->description = $obj->description;
		$cat->allow_collapse = 'Y';
		$cat->default_view = $obj->state ? 'OPEN' : 'COLLAPSED';
		$ib_c[$obj->id] = $cat->add('LAST');
		++$i;	
	}
	mysql_free_result($r); unset($cat);
	print_msg("Done: Importing {$i} categories");

	print_msg("Importing forums");
	q("DELETE FROM {$DBHOST_TBL_PREFIX}forum");
	q("DELETE FROM {$DBHOST_TBL_PREFIX}forum_read");
	q("DELETE FROM {$DBHOST_TBL_PREFIX}forum_notify");
	q("DELETE FROM {$DBHOST_TBL_PREFIX}group_cache");
	q("DELETE FROM {$DBHOST_TBL_PREFIX}group_resources");
	q("DELETE FROM {$DBHOST_TBL_PREFIX}mlist");
	q("DELETE FROM {$DBHOST_TBL_PREFIX}nntp");
	q("DELETE FROM {$DBHOST_TBL_PREFIX}group_members WHERE id>2");
	q("DELETE FROM {$DBHOST_TBL_PREFIX}groups WHERE id>2");

	$i = 0;

	$frm = new fud_forum;
	$frm->max_attach_size = 1024;
	$frm->max_file_attachments = 1;
	$frm->message_threshold = 0;
	$frm->moderated = 'N';
	$frm->icon= '';

	$plist = array('upload_perms' => 'up_file', 'start_perms' => 'up_post', 'reply_perms' => 'up_reply', 'read_perms' => 'up_read');

	$r = mysql_query("SELECT * FROM {$ipb}forums ORDER BY category, parent_id, position", $ib) or die(mysql_error($ib));
	while ($obj = mysql_fetch_object($r)) {
		if ($obj->use_ibc) {
			$frm->tag_style = 'ML';
		} else if ($obj->use_html) {
			$frm->tag_style = 'HTML';
		} else {
			$frm->tag_style = 'NONE';
		}

		$frm->name = html_clean($obj->name);
		$frm->descr = $obj->description;
		$frm->cat_id = $ib_c[$obj->category];
		$frm->moderated = $obj->preview_posts ? 'Y' : 'N';

		if ($obj->password) {
			$frm->passwd_posting = 'Y';
			$frm->post_passwd = $obj->password;
		} else {
			$frm->passwd_posting = 'N';
			$frm->post_passwd = NULL;
		}

		$ib_f[$obj->id] = $frm_id = $frm->add('LAST');

		/* Import various forum permissions */
		$gid = q_singleval("SELECT id FROM {$DBHOST_TBL_PREFIX}groups WHERE forum_id=".$frm_id);

		foreach ($plist as $k => $v) {
			if (!$obj->{$k} || $obj->{$k} == "-1") {
				q("UPDATE {$DBHOST_TBL_PREFIX}group_members SET {$v}='N' WHERE group_id=".$gid);
			} else if ($obj->{$k} == '*') {
				q("UPDATE {$DBHOST_TBL_PREFIX}group_members SET {$v}='Y' WHERE group_id=".$gid);
			} else {
				q("UPDATE {$DBHOST_TBL_PREFIX}group_members SET {$v}='N' WHERE group_id=".$gid);
				$gl = explode(',', $obj->{$k});
				foreach ($gl as $gi) {
					if ($gi == $INFO['member_group']) {
						q("UPDATE {$DBHOST_TBL_PREFIX}group_members SET {$v}='Y' WHERE user_id=0 AND group_id=".$gid);
					} else if ($gi == $INFO['guest_group']) {
						q("UPDATE {$DBHOST_TBL_PREFIX}group_members SET {$v}='Y' WHERE user_id=2147483647 AND group_id=".$gid);
					} else if ($gi != $INFO['auth_group'] && $gi != $INFO['admin_group']) {
						$ib_g[$gi][$frm_id][$v] = 'Y';
					}
				}
			}
		}
		++$i;
	}
	mysql_free_result($r); unset($ib_c);
	print_msg("Done: Importing {$i} forums");

	print_msg("Importing permissions");
	$r = mysql_query("SELECT count(*), mgroup FROM {$ipb}members WHERE mgroup NOT IN({$INFO['admin_group']}, {$INFO['auth_group']}, {$INFO['member_group']}, {$INFO['guest_group']}) GROUP BY mgroup", $ib) or die(mysql_error($ib));
	while (list(, $g) = mysql_fetch_row($r)) {
		if (!isset($ib_g[$g])) {
			continue;
		}
		/* list of members for a particular group */
		$users = array();
		$r2 = mysql_query("SELECT id FROM {$ipb}members WHERE mgroup=".$g);
		while (list($id) = mysql_fetch_row($r2)) {
			$users[] = $id;		
		}
		mysql_free_result($r2);

		foreach ($ib_g[$g] as $fl) {
			foreach ($fl as $k => $v) {
				$gid = q_singleval("SELECT id FROM {$DBHOST_TBL_PREFIX}groups WHERE forum_id=".$k);
				$perms = array_change_key_case(db_arr_assoc("SELECT * FROM {$DBHOST_TBL_PREFIX}group_members WHERE group_id={$gid} AND user_id=0"));
				unset($perms['id'], $perms['user_id'], $perms['group_id']);
				$fields = implode(',', array_keys($perms));
				foreach ($v as $pt => $p) {
					$perms[$pt] = $p;
				}
				$data = "'" . implode("', '", $perms);
				foreach ($users as $u) {
					q("INSERT INTO {$DBHOST_TBL_PREFIX}group_members {$fields} VALUES({$u}, {$gid}, {$data})");
				}
			}
		}
	}
	q("UPDATE {$DBHOST_TBL_PREFIX}group_members SET up_visible=up_read");
	mysql_free_result($r); unset($ib_g, $users, $fl, $data, $u, $pt, $p, $v, $perms, $gid);
	print_msg("Done: Importing permissions");

	print_msg("Importing administrators");
	$i = 0;
	$r = mysql_query("SELECT id FROM {$ipb}members WHERE mgroup=".$INFO['admin_group'], $ib) or die(mysql_error($ib));
	$u = array();
	while (list($id) = mysql_fetch_row($r)) {
		$u[] = $ib_u[$id];
		++$i;
	}
	if (count($u)) {
		q("UPDATE {$DBHOST_TBL_PREFIX}users SET is_mod='A' WHERE id IN(".implode(',', $u).")");
	}
	mysql_free_result($r); unset($u);
	print_msg("Done: Importing {$i} administrators");
	
	print_msg("Importing moderators");
	q("DELETE FROM {$DBHOST_TBL_PREFIX}mod");
	$i = 0;
	$r = mysql_query("SELECT forum_id, member_id FROM {$ipb}moderators", $ib) or die(mysql_error($ib));
	while (list($f, $u) = mysql_fetch_row($r)) {
		q("INSERT INTO {$DBHOST_TBL_PREFIX}mod (forum_id, user_id) VALUES({$ib_f[$f]}, {$ib_u[$u]})");
		++$i;
	}
	mysql_free_result($r); unset($u);
	print_msg("Done: Importing {$i} moderators");

	print_msg("Importing messages and topics");
	$i = $i2 = 0;
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
	q("DELETE FROM {$DBHOST_TBL_PREFIX}attach");
	
	$r = mysql_query("SELECT * FROM {$ipb}topics t INNER JOIN {$ipb}posts p ON t.tid=p.topic_id ORDER BY t.start_date", $ib) or die(mysql_error($ib));
	$m = new fud_msg_edit;
	$ib_t = array();
	while ($obj = mysql_fetch_object($r)) {
		if ($obj->state == 'link') {
			continue;
		}
		$m->subject = htmlspecialchars(html_clean($obj->title));
		$m->ip_addr = $obj->ip_address ? $obj->ip_address : '0.0.0.0';
		$m->poster_id = $obj->author_id ? $ib_u[$obj->author_id] : 0;
		$m->post_stamp = $obj->post_date;
		$m->show_sig = $obj->use_sig ? 'Y' : 'N';
		$m->smiley_disabled = $obj->use_emo ? 'N' : 'Y';
		$m->body = html_clean($pp->unconvert($obj->post, $INFO['msg_allow_code'], $INFO['msg_allow_html']));
		if ($INFO['msg_allow_code']) {
			$m->body = tags_to_html($m->body, 'Y');
		} else if (!$INFO['msg_allow_html']) {
			$m->body = nl2br(htmlspecialchars($m->body));
		}
		if ($m->smiley_disabled != 'Y') {
			$m->body = smiley_to_post($m->body);
		}
		fud_wordwrap($m->body);

		if (!isset($ib_t[$obj->topic_id])) {
			$obj->new_topic = 1;
		}

		if (!$obj->new_topic) {
			$m->thread_id = $ib_t[$obj->topic_id][0];
			$m->reply_to = $ib_t[$obj->topic_id][1];
			$m->subject = 'Re: ' .  $m->subject;
		} else {
			$m->thread_id = $m->reply_to = 0;
		}

		$mid = $m->add($ib_f[$obj->forum_id], 0, 'Y', 'Y', 'Y', ($obj->approved ? TRUE : FALSE));

		/* handle file attachments if there any */
		if ($obj->attach_id && $obj->attach_file) {
			$tmpf = tempnam($GLOBALS['TMP'], getmyuid());
			copy($INFO['upload_dir'] . '/' . $obj->attach_id, $tmpf);

			$tmp = array('name' => $obj->attach_file, 'size' => filesize($tmpf), 'tmp_name' => $tmpf);
			$aid = attach_add($tmp, $m->poster_id, 'N', 1);
			q("UPDATE {$DBHOST_TBL_PREFIX}attach SET dlcount={$obj->attach_hits} WHERE id=".$aid);
			attach_finalize(array($aid => 1), $mid, 'N');
		}

		if ($obj->new_topic) {
			$ib_t[$obj->topic_id] = db_saq("SELECT thread_id, id FROM {$DBHOST_TBL_PREFIX}msg WHERE id=".$mid);

			/* handle various topic settings */
			$locked = $obj->state == 'open' ? 'N' : 'Y';
			if ($obj->pinned) {
				$is_sticky = 'Y';
				$ordertype = 'STICKY';
				$orderexpiry = 1000000000;
			} else {
				$is_sticky = 'N';
				$ordertype = 'NONE';
				$orderexpiry = 0;
			}
			
			q("UPDATE {$DBHOST_TBL_PREFIX}thread SET
				views={$obj->views},
				is_sticky='{$is_sticky}',
				ordertype='{$ordertype}',
				orderexpiry={$orderexpiry},
				locked='{$locked}'
			WHERE id={$ib_t[$obj->topic_id][0]}");
			++$i2;
		}
		if ($obj->edit_time) {
			q("UPDATE {$DBHOST_TBL_PREFIX}msg SET update_stamp={$obj->edit_time} WHERE id=".$mid);
		}
		++$i;
	}
	mysql_free_result($r); unset($tmp, $m, $aid, $tmpf);
	print_msg("Done: Importing {$i} messages and {$i2} topics");

	print_msg("Importing polls");
	$i = 0;
	q("DELETE FROM {$DBHOST_TBL_PREFIX}poll");
	q("DELETE FROM {$DBHOST_TBL_PREFIX}poll_opt");
	q("DELETE FROM {$DBHOST_TBL_PREFIX}poll_opt_track");
	define('_uid', 0); /* we need this for polls :( */
	$r = mysql_query("SELECT tid, member_id FROM {$ipb}voters", $ib) or die(mysql_error($ib));
	while (list($t, $m) = mysql_fetch_row($r)) {
		$vt[$t][] = $ib_u[$m];
	}
	/* prevent duplicates */
	foreach ($vt as $k => $v) {
		$vt[$k] = array_unique($v);
	}
	mysql_free_result($r);

	$r = mysql_query("SELECT * FROM {$ipb}polls", $ib) or die(mysql_error($ib));
	while ($obj = mysql_fetch_object($r)) {
		$mid = $ib_t[$obj->tid][1];

		list($poll_name, $poll_status) = mysql_fetch_row(mysql_query("SELECT title, poll_state FROM {$ipb}topics WHERE tid=".$obj->tid, $ib));

		/* create poll */
		$pid = poll_add(html_clean($poll_name), 0, 0);
		q("UPDATE {$DBHOST_TBL_PREFIX}poll SET owner={$ib_u[$obj->starter_id]}, creation_date={$obj->start_date} WHERE id=".$pid);
		++$i;

		/* add options */
		$choices = unserialize($obj->choices);
		$ttl = 0;
		foreach ($choices as $c) {
			$o = html_clean($pp->unconvert($c[1], $INFO['msg_allow_code'], $INFO['msg_allow_html']));
			if ($INFO['msg_allow_code']) {
				$o = tags_to_html($o, 'Y');
			} else if (!$INFO['msg_allow_html']) {
				$o = nl2br(htmlspecialchars($o));
			}
			$o = smiley_to_post($o);
			$oid = poll_opt_add($o, $pid);
			if ($c[2]) {
				$ttl += $c[2];
				q("UPDATE {$DBHOST_TBL_PREFIX}poll_opt SET count={$c[2]} WHERE id=".$oid);

				/* handle poll vote tracking */
				for ($j = 0; $j < $c[2]; $j++) {
					$u = array_pop($vt[$obj->tid]);
					q("INSERT INTO {$DBHOST_TBL_PREFIX}poll_opt_track (poll_id, user_id, poll_opt) VALUES({$pid}, {$u}, {$oid})");
				}
			}
		}

		poll_activate($pid, $ib_f[$obj->forum_id]);

		if ($poll_status != 'open') {
			$expiry_date = __request_timestamp__;
		} else {
			$expiry_date = 0;
		}

		q("UPDATE {$DBHOST_TBL_PREFIX}poll SET expiry_date={$expiry_date}, total_votes={$ttl} WHERE id=".$pid);
		q("UPDATE {$DBHOST_TBL_PREFIX}msg SET poll_id={$pid} WHERE id=".$mid);
	}
	mysql_free_result($r);
	print_msg("Done: Importing {$i} polls");

	print_msg("Importing private messages");
	/* disable pm notification for the duration of the import process */
	$r = uq("SELECT id FROM {$DBHOST_TBL_PREFIX}users WHERE pm_notify='Y'");
	$ul = array();
	while (list($id) = db_rowarr($r)) {
		$ul[] = $id;
	}
	qf($r);
	q("UPDATE {$DBHOST_TBL_PREFIX}users SET pm_notify='N'");

	q("DELETE FROM {$DBHOST_TBL_PREFIX}pmsg");
	$i = 0;
	$r = mysql_query("SELECT * FROM {$ipb}messages", $ib) or die(mysql_error($ib));
	$p = new fud_pmsg;
	/* common settings */
	$p->show_sig = $p->mailed = 'Y';
	$p->icon = '';
	$p->smiley_disabled = 'N';
	$p->ip_addr = '0.0.0.0';
	$p->folder_id = 'SENT';

	/* prevent warnings */
	$GLOBALS['usr']->alias = NULL;

	while ($obj = mysql_fetch_object($r)) {
		$p->subject = html_clean($pp->unconvert($obj->title, $INFO['msg_allow_code'], $INFO['msg_allow_html']));
		$p->subject = str_replace('Sent:  ', '', str_replace('Re:', 'Re: ', $p->subject));
		$p->track = $obj->tracking ? 'Y' : 'N';
		$p->body = html_clean($pp->unconvert($obj->message, $INFO['msg_allow_code'], $INFO['msg_allow_html']));
		if ($INFO['msg_allow_code']) {
			$p->body = tags_to_html($p->body, 'Y');
		} else if (!$INFO['msg_allow_html']) {
			$p->body = nl2br(htmlspecialchars($p->body));
		}
		$p->body = smiley_to_post($p->body);
		fud_wordwrap($p->body);
	
		$p->ouser_id = $ib_u[$obj->from_id];
		$GLOBALS['send_to_array'] = array();
		if ($obj->recipient_id && $obj->recipient_id != 'N/A') {
			$GLOBALS['recv_user_id'] = array($ib_u[$obj->recipient_id]);
			$p->to_list = q_singleval("SELECT alias FROM {$DBHOST_TBL_PREFIX}users WHERE id=".$ib_u[$obj->recipient_id]);
			if (!$obj->read_date) {
				if (!$obj->read_state) {
					$obj->read_date = 0;
				} else {
					$obj->read_date = $obj->msg_date;
				}
			}
		} else {
			$p->folder_id = 'DRAFT';
			unset($GLOBALS['recv_user_id']);
			$p->to_list = '';
		}
		$p->add(($obj->member_id == $obj->from_id));
		
		q("UPDATE {$DBHOST_TBL_PREFIX}pmsg SET post_stamp={$obj->msg_date}, read_stamp={$obj->msg_date} WHERE id=".$p->id);
		if ($p->folder_id == 'SENT' && isset($GLOBALS['send_to_array'][0][1])) {
			q("UPDATE {$DBHOST_TBL_PREFIX}pmsg SET post_stamp={$obj->msg_date}, read_stamp={$obj->read_date} WHERE id=".$GLOBALS['send_to_array'][0][1]);
		} else {
			$p->folder_id = 'SENT';
		}
		++$i;
	}
	q("UPDATE {$DBHOST_TBL_PREFIX}users SET pm_notify='Y' WHERE id IN(".implode(',', $ul).")");
	mysql_free_result($r); unset($ul);
	print_msg("Done: Importing {$i} private messages");

	print_msg("Importing miscellaneous settings");
	
	$list = array();
	$list['CUSTOM_AVATARS'] = $INFO['avatars_on'] ? 'ALL' : 'OFF';
	$list['CUSTOM_AVATAR_MAX_SIZE'] = $INFO['avup_size_max'];
	$list['CUSTOM_AVATAR_MAX_DIM'] = $INFO['avatar_dims'];
	$list['SESSION_TIMEOUT'] = $INFO['session_expiration'];
	if (!$INFO['disable_gzip']) {
		$list['PHP_COMPRESSION_ENABLE'] = 'Y';
		$list['PHP_COMPRESSION_LEVEL'] = '9';
	}
	$list['FORUM_SEARCH'] = $INFO['allow_search'] ? 'Y' : 'N';
	$list['FLOOD_CHECK_TIME'] = $INFO['flood_control'];
	$list['LOGEDIN_LIST'] = $INFO['show_active'] ? 'Y' : 'N';
	$list['FORUM_INFO'] = $list['PUBLIC_STATS'] = $INFO['show_totals'] ? 'Y' : 'N';
	$list['FORUM_TITLE'] = $INFO['boardname'];
	$list['ALLOW_REGISTRATION'] = !$INFO['no_reg'] ? 'Y' : 'N';
	$list['DISABLED_REASON'] = $INFO['offline_msg'];

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