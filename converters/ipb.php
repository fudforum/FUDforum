<?php
/***************************************************************************
* copyright            : (C) 2001-2004 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: ipb.php,v 1.7 2005/03/11 16:13:42 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it 
* under the terms of the GNU General Public License as published by the 
* Free Software Foundation; either version 2 of the License, or 
* (at your option) any later version.
***************************************************************************/

	/* Invision Power Board (1.3.X / 1.1.X) - FUDforum Conversion script - Brief Instructions */

	/* If you intend to run this script via the console, make sure to UNLOCK the 
	 * FUDforum 1st. I recommend running this script via the web unless the forum
	 * you are importing is very large.
	 */

	/* Specify the FULL path to the Invision Power Board conf_global.php file */
	$IPB_CFG = "/path/to/conf_global.php";
	$REMOTE_AVATAR = 1; // if set to 0, no remote avatars will be imported

/**** DO NOT EDIT BEYOND THIS POINT ****/

function print_msg($msg)
{
	if (__WEB__) {
		echo $msg . "<br />\n";	
	} else {
		echo $msg . "\n";
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

	set_time_limit(500000);
	ini_set('memory_limit', '128M');
	define('__WEB__', (isset($_SERVER["REMOTE_ADDR"]) === FALSE ? 0 : 1));

	/* prevent session initialization */
	define('forum_debug', 1);
	unset($_SERVER['REMOTE_ADDR']);

	$gl = @include("./GLOBALS.php"); 
	if ($gl === FALSE) {
		exit("This script must be placed in FUDforum's main web directory.\n");
	}

	if ($FUD_OPT_2 & 8388608 && !__WEB__) {
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
	$FUD_OPT_2 = $FUD_OPT_2 &~ (1|1024|8388608);
	$FUD_OPT_1 = $FUD_OPT_1 &~ (268435456|16777216);

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
		if (!$m) {
			$f2 = '/(^|\s)' . addcslashes($t, '/') . '($|\s)/i';
			$s2 = "\\1{$s}\\2";
			q("INSERT INTO {$DBHOST_TBL_PREFIX}replace (replace_opt, replace_str, with_str, from_post, to_msg) VALUES(0, '".addslashes($f2)."', '".addslashes($s2)."', '/".addslashes(addcslashes($s, '/'))."/', '".addslashes($t)."')");
		} else {
			$f = '/' . addcslashes($t, '/') . '/i';
			q("INSERT INTO {$DBHOST_TBL_PREFIX}replace (replace_str, with_str) VALUES('".addslashes($f)."', '".addslashes($s)."')");
		}
		++$i;
	}
	unset($r);
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
	$u->users_opt = 512 | 4 | 16 | 32 | 128 | 256 | 131072;
	if (!($FUD_OPT_2 & 4)) {
		$this->users_opt ^= 128;
	}
	if (!($FUD_OPT_2 & 8)) {
		$this->users_opt ^= 256;
	}
	$u->time_zone = $GLOBALS['SERVER_TZ'];
	$u->user_image = $u->occupation = '';
	$u->theme = q_singleval("SELECT id FROM {$DBHOST_TBL_PREFIX}themes WHERE theme_opt=3 LIMIT 1");
	$ext = array(1=>'gif', 2=>'jpg', 3=>'png', 4=>'swf');

	$pp = new post_parser();

	while ($obj = mysql_fetch_object($r)) {
		/* uniqueness checks */
		if (($id = q_singleval("SELECT id FROM {$DBHOST_TBL_PREFIX}users WHERE name='".addslashes($obj->name)."' OR email='".addslashes($obj->email)."'"))) {
			$ib_u[$obj->id]	= $id;
			continue;
		}

		$u->alias = $u->login = $u->name = $obj->name;
		$u->email = $obj->email;
		$u->users_opt |= $obj->allow_admin_mails ? 0 : 8;
		$u->icq = $obj->icq_number;
		$u->aim = $obj->aim_name;
		$u->yahoo = $obj->yahoo;
		$u->msnm = $obj->msnname;
		$u->location = $obj->location;
		$u->interests = $obj->interests;
		$u->users_opt |= $obj->email_pm ? 64 : 0;
		$u->users_opt |= !$obj->coppa_user ? 0 : 262144;
		$u->users_opt |= $obj->auto_track ? 2 : 0;
		$u->users_opt |= $obj->view_sigs ? 4096 : 0;
		$u->users_opt |= $obj->view_avs ? 8192 : 0;
		$u->users_opt |= $obj->hide_email ? 0 : 1;

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
			$tmp = html_entity_decode($pp->unconvert($obj->signature, $INFO['sig_allow_ibc'], $INFO['sig_allow_html']));
			if ($INFO['sig_allow_ibc']) {
				$tmp = char_fix(tags_to_html($tmp, 1));
			} else if (!$INFO['sig_allow_html']) {
				$tmp = nl2br(char_fix(htmlspecialchars($tmp)));
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
		$users_opt = 4194304;

		if ($obj->avatar && $obj->avatar != 'noavatar') {
			if (!strncmp($obj->avatar, 'upload:', strlen('upload:'))) {
				$obj->avatar = $INFO['upload_dir']  . '/' . substr($obj->avatar, strlen('upload:'));
				$local = 1;
			} else if (strpos($obj->avatar, '://') === FALSE) {
				$obj->avatar = $INFO['html_url'] . '/avatars/' . $obj->avatar;
				$local = 0;
			} else {
				$local = 0;
			}
			while (1) {
				if ($local && !file_exists($obj->avatar)) {
					print_msg("AVATAR FILE: {$obj->avatar} does not exist");
					break;
				} else if (!$local && !$REMOTE_AVATAR) {
					print_msg("SKIPPING REMOTE AVATAR: {$obj->avatar}");
					break;
				}

				$tmp = tempnam($GLOBALS['TMP'], getmyuid());
				if (!@copy($obj->avatar, $tmp)) {
					print_msg("COULD NOT GET AVATAR FILE: {$obj->avatar}");
					break;
				}

				if (($im = getimagesize($tmp)) && isset($ext[$im[2]])) {
					$ex = $ext[$im[2]];
					$path = "images/custom_avatars/{$uid}.{$ex}";
					rename($tmp, $WWW_ROOT_DISK . $path);
					chmod($path, 0666);
					$users_opt = 8388608;
					$avatar_loc = make_avatar_loc($path, $WWW_ROOT_DISK, $WWW_ROOT);
				} else {
					unlink($tmp);
				}
				break;
			}
		}

		if ($obj->title) {
			q("INSERT INTO {$DBHOST_TBL_PREFIX}custom_tags (name, user_id) VALUES(".strnull(addslashes($obj->title)).", {$uid})");
		}

		q("UPDATE {$DBHOST_TBL_PREFIX}users SET
			join_date={$obj->joined},
			last_visit={$obj->last_visit},
			last_read={$obj->last_activity},
			users_opt=users_opt|{$users_opt},
			avatar_loc='{$avatar_loc}',
			passwd='{$obj->password}',
			reg_ip=".sprintf("%u", ip2long($obj->ip_address))."
		WHERE id=".$uid);

		++$i;
	}
	unset($r);
	print_msg("Done: Importing {$i} IPB members");

	print_msg("Importing buddies/ignored users");
	q("DELETE FROM {$DBHOST_TBL_PREFIX}buddy");
	q("DELETE FROM {$DBHOST_TBL_PREFIX}user_ignore");

	$i2 = $i = 0;
	$r = mysql_query("SELECT contact_id, member_id, allow_msg FROM {$ipb}contacts", $ib) or die(mysql_error($ib));
	while (list($c, $m, $a) = mysql_fetch_row($r)) {
		/* skip if users cannot be matched */
		if (!isset($ib_u[$c], $ib_u[$m])) {
			continue;
		}

		if ($a) {
			q("INSERT INTO {$DBHOST_TBL_PREFIX}buddy (bud_id, user_id) VALUES ({$ib_u[$c]}, {$ib_u[$m]})");
			++$i;
		} else {
			q("INSERT INTO {$DBHOST_TBL_PREFIX}user_ignore (ignore_id, user_id) VALUES ({$ib_u[$c]}, {$ib_u[$m]})");
			++$i2;
		}
	}
	unset($r);
	print_msg("Done: Importing {$i} buddies and {$i2} ignored users");

	print_msg("Importing member ranks");
	q("DELETE FROM {$DBHOST_TBL_PREFIX}level");
	$i = 0;
	$r = mysql_query("SELECT posts, title FROM {$ipb}titles WHERE posts>0", $ib) or die(mysql_error($ib));
	while (list($p, $t) = mysql_fetch_row($r)) {
		q("INSERT INTO {$DBHOST_TBL_PREFIX}level (name, post_count) VALUES ('".addslashes($t)."','{$p}')");
		++$i;
	}
	unset($r);
	print_msg("Done: Importing {$i} member ranks");	

	print_msg("Importing categories");
	q("DELETE FROM {$DBHOST_TBL_PREFIX}cat");
	$i = 0;
	$r = mysql_query("SELECT id, state, name, description FROM {$ipb}categories WHERE id>0 ORDER BY state", $ib) or die(mysql_error($ib));
	$cat = new fud_cat;
	while ($obj = mysql_fetch_object($r)) {
		$cat->name = $obj->name;
		$cat->description = $obj->description;
		$cat->cat_opt = 1;
		$cat->cat_opt |= $obj->state ? 2 : 0;
		$ib_c[$obj->id] = $cat->add('LAST');
		++$i;	
	}
	unset($r); unset($cat);
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
	$frm->forum_opt = 0;
	$frm->icon= '';
	$ib_g = array();

	$plist = array('upload_perms' => 256, 'start_perms' => 4, 'reply_perms' => 8,
			'read_perms' => 1|2|1024|16384|32768|262144|512|128);

	$r = mysql_query("SELECT * FROM {$ipb}forums ORDER BY category, parent_id, position", $ib) or die(mysql_error($ib));
	while ($obj = mysql_fetch_object($r)) {
		if ($obj->use_ibc) {
			$frm->forum_opt |= 16;
		} else if ($obj->use_html) {
			$frm->forum_opt |= 0;
		} else {
			$frm->forum_opt |= 8;
		}

		$frm->name = html_entity_decode($obj->name);
		$frm->descr = $obj->description;
		$frm->cat_id = $ib_c[$obj->category];
		$frm->forum_opt |= $obj->preview_posts ? 2 : 0;

		if ($obj->password) {
			$frm->forum_opt |= 2;
			$frm->post_passwd = $obj->password;
		} else {
			$frm->post_passwd = '';
		}

		$ib_f[$obj->id] = $frm_id = $frm->add('LAST');

		/* Import various forum permissions */
		$gid = q_singleval("SELECT id FROM {$DBHOST_TBL_PREFIX}groups WHERE forum_id=".$frm_id);

		foreach ($plist as $k => $v) {
			if (!$obj->{$k} || $obj->{$k} == "-1") {
				q("UPDATE {$DBHOST_TBL_PREFIX}group_members SET group_members_opt = group_members_opt &~ {$v} WHERE group_id=".$gid);
			} else if ($obj->{$k} == '*') {
				q("UPDATE {$DBHOST_TBL_PREFIX}group_members SET group_members_opt = group_members_opt | {$v} WHERE group_id=".$gid);
			} else {
				q("UPDATE {$DBHOST_TBL_PREFIX}group_members SET group_members_opt = group_members_opt &~ {$v} WHERE group_id=".$gid);
				$gl = explode(',', $obj->{$k});
				foreach ($gl as $gi) {
					if ($gi == $INFO['member_group']) {
						q("UPDATE {$DBHOST_TBL_PREFIX}group_members SET group_members_opt = group_members_opt | {$v} WHERE user_id=0 AND group_id=".$gid);
					} else if ($gi == $INFO['guest_group']) {
						q("UPDATE {$DBHOST_TBL_PREFIX}group_members SET group_members_opt = group_members_opt | {$v} WHERE user_id=2147483647 AND group_id=".$gid);
					} else if ($gi != $INFO['auth_group'] && $gi != $INFO['admin_group']) {
						if (!isset($ib_g[$gi][$frm_id])) {
							$ib_g[$gi][$frm_id] = 0;
						}
						$ib_g[$gi][$frm_id] |= $v;
					}
				}
			}
		}
		++$i;
	}
	unset($r); unset($ib_c);
	print_msg("Done: Importing {$i} forums");

	print_msg("Importing permissions");
	$r = mysql_query("SELECT DISTINCT(mgroup) FROM {$ipb}members WHERE mgroup NOT IN({$INFO['admin_group']}, {$INFO['auth_group']}, {$INFO['member_group']}, {$INFO['guest_group']})", $ib) or die(mysql_error($ib));
	while ($g = mysql_fetch_row($r)) {
		$g = array_pop($g);

		if (!isset($ib_g[$g])) {
			continue;
		}

		foreach ($ib_g[$g] as $k => $v) {
			$gid = q_singleval("SELECT id FROM {$DBHOST_TBL_PREFIX}groups WHERE forum_id=".$k);
			q("INSERT INTO {$DBHOST_TBL_PREFIX}group_members (user_id, group_id, group_members_opt) 
				SELECT id, {$gid}, {$v} FROM {$ipb}members WHERE mgroup=".$g);
		}
	}
	unset($r); unset($ib_g, $users, $fl, $data, $u, $pt, $p, $v, $perms, $gid);
	print_msg("Done: Importing permissions");

	print_msg("Importing administrators");
	$i = 0;
	$r = mysql_query("SELECT id FROM {$ipb}members WHERE mgroup=".$INFO['admin_group'], $ib) or die(mysql_error($ib));
	$u = array();
	while (list($id) = mysql_fetch_row($r)) {
		$u[] = $ib_u[$id];
		++$i;
	}
	if ($u) {
		q("UPDATE {$DBHOST_TBL_PREFIX}users SET users_opt=users_opt|1048576 WHERE id IN(".implode(',', $u).")");
	}
	unset($r); unset($u);
	print_msg("Done: Importing {$i} administrators");
	
	print_msg("Importing moderators");
	q("DELETE FROM {$DBHOST_TBL_PREFIX}mod");
	$i = 0;
	$r = mysql_query("SELECT forum_id, member_id FROM {$ipb}moderators WHERE member_id > 0", $ib) or die(mysql_error($ib));
	while (list($f, $u) = mysql_fetch_row($r)) {
		if (!isset($ib_f[$f], $ib_u[$u])) {
			continue;
		}
		q("INSERT INTO {$DBHOST_TBL_PREFIX}mod (forum_id, user_id) VALUES({$ib_f[$f]}, {$ib_u[$u]})");
		++$i;
	}
	unset($r); unset($u);
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
	
	$r = mysql_query("SELECT * FROM {$ipb}topics t INNER JOIN {$ipb}posts p ON t.tid=p.topic_id ORDER BY p.post_date", $ib) or die(mysql_error($ib));
	$m = new fud_msg_edit;
	$ib_t = array();
	while ($obj = mysql_fetch_object($r)) {
		if ($obj->state == 'link') {
			continue;
		} else if (!isset($ib_f[$obj->forum_id])) {
			print_msg("Invalid forum id {$obj->forum_id} in message id {$obj->pid}");
			continue;
		}
		
		$m->subject = char_fix(htmlspecialchars(html_entity_decode($obj->title)));
		$m->ip_addr = $obj->ip_address ? $obj->ip_address : '0.0.0.0';
		if ($obj->author_id) {
			if (isset($ib_u[$obj->author_id])) {
				$m->poster_id = $ib_u[$obj->author_id];
			} else {
				print_msg("Missing Author id {$obj->author_id} for message id {$obj->pid}");
				$m->poster_id = 0;
			}
		} else {
			$m->poster_id = 0;
		}
		
		$m->post_stamp = $obj->post_date;
		$m->msg_opt = $obj->use_sig ? 1 : 0;
		$m->msg_opt |= $obj->use_emo ? 0 : 2;
		$m->body = html_entity_decode($pp->unconvert($obj->post, $INFO['msg_allow_code'], $INFO['msg_allow_html']));
		if ($INFO['msg_allow_code']) {
			$m->body = char_fix(tags_to_html($m->body, 1));
		} else if (!$INFO['msg_allow_html']) {
			$m->body = nl2br(char_fix(htmlspecialchars($m->body)));
		}
		if (!($m->msg_opt & 2)) {
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

		$mid = $m->add($ib_f[$obj->forum_id], 0, 16, 64|4096, 0);
		if ($obj->approved) {
			q("UPDATE {$DBHOST_TBL_PREFIX}msg SET apr=1 WHERE id=".$mid);
		}

		/* handle file attachments if there any */
		if ($obj->attach_id && $obj->attach_file) {
			$tmpf = tempnam($GLOBALS['TMP'], getmyuid());
			copy($INFO['upload_dir'] . '/' . $obj->attach_id, $tmpf);

			$tmp = array('name' => $obj->attach_file, 'size' => filesize($tmpf), 'tmp_name' => $tmpf);
			$aid = attach_add($tmp, $m->poster_id, 0, 1);
			q("UPDATE {$DBHOST_TBL_PREFIX}attach SET dlcount={$obj->attach_hits} WHERE id=".$aid);
			attach_finalize(array($aid => 1), $mid, 0);
		}

		if ($obj->new_topic) {
			$ib_t[$obj->topic_id] = db_saq("SELECT thread_id, id FROM {$DBHOST_TBL_PREFIX}msg WHERE id=".$mid);

			/* handle various topic settings */
			$thread_opt = (int)($obj->state == 'open');
			if ($obj->pinned) {
				$thread_opt |= 4;
				$orderexpiry = 1000000000;
			} else {
				$orderexpiry = 0;
			}
			
			q("UPDATE {$DBHOST_TBL_PREFIX}thread SET
				views={$obj->views},
				thread_opt={$thread_opt},
				orderexpiry={$orderexpiry}
			WHERE id={$ib_t[$obj->topic_id][0]}");
			++$i2;
		}
		if ($obj->edit_time) {
			q("UPDATE {$DBHOST_TBL_PREFIX}msg SET update_stamp={$obj->edit_time} WHERE id=".$mid);
		}
		++$i;
	}
	unset($r); unset($tmp, $m, $aid, $tmpf);
	print_msg("Done: Importing {$i} messages and {$i2} topics");

	print_msg("Importing polls");
	$i = 0;
	q("DELETE FROM {$DBHOST_TBL_PREFIX}poll");
	q("DELETE FROM {$DBHOST_TBL_PREFIX}poll_opt");
	q("DELETE FROM {$DBHOST_TBL_PREFIX}poll_opt_track");
	define('_uid', 0); /* we need this for polls :( */

	$r = mysql_query("SELECT tid, member_id FROM {$ipb}voters GROUP BY tid,member_id", $ib) or die(mysql_error($ib));
	while (list($t, $m) = mysql_fetch_row($r)) {
		if (isset($ib_u[$m])) {
			$vt[$t][] = $ib_u[$m];
		}
	}
	unset($r);

	$r = mysql_query("SELECT * FROM {$ipb}polls", $ib) or die(mysql_error($ib));
	while ($obj = mysql_fetch_object($r)) {
		if (!isset($ib_f[$obj->forum_id])) {
			print_msg("Invalid forum id {$obj->forum_id} in poll id {$obj->pid}");
			continue;
		}
	
		$mid = $ib_t[$obj->tid][1];

		list($poll_name, $poll_status) = mysql_fetch_row(mysql_query("SELECT title, poll_state FROM {$ipb}topics WHERE tid=".$obj->tid, $ib));

		/* create poll */
		$pid = poll_add(html_entity_decode($poll_name), 0, 0);
		q("UPDATE {$DBHOST_TBL_PREFIX}poll SET owner={$ib_u[$obj->starter_id]}, creation_date={$obj->start_date} WHERE id=".$pid);
		++$i;

		/* add options */
		$choices = unserialize($obj->choices);
		$ttl = 0;
		foreach ($choices as $c) {
			$o = html_entity_decode($pp->unconvert($c[1], $INFO['msg_allow_code'], $INFO['msg_allow_html']));
			if ($INFO['msg_allow_code']) {
				$o = char_fix(tags_to_html($o, 1));
			} else if (!$INFO['msg_allow_html']) {
				$o = nl2br(char_fix(htmlspecialchars($o)));
			}

			$oid = poll_opt_add(smiley_to_post($o), $pid);

			if ($c[2]) {
				$ttl += $c[2];
				q("UPDATE {$DBHOST_TBL_PREFIX}poll_opt SET count={$c[2]} WHERE id=".$oid);

				/* handle poll vote tracking */
				for ($j = 0; $j < $c[2]; $j++) {
					if (empty($vt[$obj->tid])) {
						break;
					}
					$u = array_pop($vt[$obj->tid]);
					db_li("INSERT INTO {$DBHOST_TBL_PREFIX}poll_opt_track (poll_id, user_id, poll_opt) VALUES({$pid}, {$u}, {$oid})", $dummy);
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
	unset($r);
	print_msg("Done: Importing {$i} polls");

	print_msg("Importing private messages");
	/* disable pm notification for the duration of the import process */
	$r = uq("SELECT id FROM {$DBHOST_TBL_PREFIX}users WHERE users_opt>=64 AND (users_opt & 64)>0");
	$ul = array();
	while (list($id) = db_rowarr($r)) {
		$ul[] = $id;
	}
	q("UPDATE {$DBHOST_TBL_PREFIX}users SET users_opt = users_opt &~ 64");

	q("DELETE FROM {$DBHOST_TBL_PREFIX}pmsg");
	$i = 0;
	$r = mysql_query("SELECT * FROM {$ipb}messages", $ib) or die(mysql_error($ib));
	$p = new fud_pmsg;
	/* common settings */
	$p->pmsg_opt = 1|16;
	$p->icon = '';
	$p->ip_addr = '0.0.0.0';
	$p->fldr = 3;

	/* prevent warnings */
	$GLOBALS['usr']->alias = NULL;

	while ($obj = mysql_fetch_object($r)) {
		$p->subject = html_entity_decode($pp->unconvert($obj->title, $INFO['msg_allow_code'], $INFO['msg_allow_html']));
		$p->subject = str_replace('Sent:  ', '', str_replace('Re:', 'Re: ', $p->subject));
		$p->pmsg_opt = $obj->tracking ? 4 : 0;
		$p->body = html_entity_decode($pp->unconvert($obj->message, $INFO['msg_allow_code'], $INFO['msg_allow_html']));
		if ($INFO['msg_allow_code']) {
			$p->body = char_fix(tags_to_html($p->body, 1));
		} else if (!$INFO['msg_allow_html']) {
			$p->body = nl2br(char_fix(htmlspecialchars($p->body)));
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
			$p->fldr = 4;
			unset($GLOBALS['recv_user_id']);
			$p->to_list = '';
		}
		$p->add(($obj->member_id == $obj->from_id));
		
		q("UPDATE {$DBHOST_TBL_PREFIX}pmsg SET post_stamp={$obj->msg_date}, read_stamp={$obj->msg_date} WHERE id=".$p->id);
		if ($p->fldr == 3 && isset($GLOBALS['send_to_array'][0][1])) {
			q("UPDATE {$DBHOST_TBL_PREFIX}pmsg SET post_stamp={$obj->msg_date}, read_stamp={$obj->read_date} WHERE id=".$GLOBALS['send_to_array'][0][1]);
		} else {
			$p->fldr = 3;
		}
		++$i;
	}
	if ($ul) {
		q("UPDATE {$DBHOST_TBL_PREFIX}users SET users_opt=users_opt|64 WHERE id IN(".implode(',', $ul).")");
	}
	unset($r); unset($ul);
	print_msg("Done: Importing {$i} private messages");

	print_msg("Importing miscellaneous settings");
	
	$list = array();
	$list['FUD_OPT_1'] = $FUD_OPT_1;
	if (!$INFO['no_reg']) {
		$list['FUD_OPT_1'] |= 2;
	} else {
		$list['FUD_OPT_1'] = $list['FUD_OPT_1'] &~ 2;
	}
	if ($INFO['avatars_on']) {
		$list['FUD_OPT_1'] |= 28;
	} else {
		$list['FUD_OPT_1'] = $list['FUD_OPT_1'] &~ 28;
	}
	if ($INFO['allow_search']) {
		$list['FUD_OPT_1'] |= 16777216;
	} else {
		 $list['FUD_OPT_1'] = $list['FUD_OPT_1'] &~ 16777216;
	}
	if ($INFO['show_active']) {
		$list['FUD_OPT_1'] |= 1073741824;
	} else {
		$list['FUD_OPT_1'] = $list['FUD_OPT_1'] &~ 1073741824;
	}
	if ($INFO['show_totals']) {
		$list['FUD_OPT_2'] = $FUD_OPT_2 | 16 | 2;
	} else {
		$list['FUD_OPT_2'] = $FUD_OPT_2 &~ (16|2);
	}

	$list['DISABLED_REASON'] = $INFO['offline_msg'];
	$list['FORUM_TITLE'] = $INFO['boardname'];
	if (!$INFO['disable_gzip']) {
		$list['FUD_OPT_2'] |= 16384;
		$list['PHP_COMPRESSION_LEVEL'] = 9;
	}
	$list['SESSION_TIMEOUT'] = (int)$INFO['session_expiration'];
	$list['FLOOD_CHECK_TIME'] = (int)$INFO['flood_control'];
	$list['CUSTOM_AVATAR_MAX_SIZE'] = (int)$INFO['avup_size_max'];
	$list['CUSTOM_AVATAR_MAX_DIM'] = (int)$INFO['avatar_dims'];

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