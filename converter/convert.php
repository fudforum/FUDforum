<?php
/***************************************************************************
* copyright            : (C) 2001-2011 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id$
*
* This program is free software; you can redistribute it and/or modify it 
* under the terms of the GNU General Public License as published by the 
* Free Software Foundation; version 2 of the License. 
***************************************************************************/

/** Print a message to web browser or command line. */
function pf($str='', $webonly=false)
{
	if (php_sapi_name() == 'cli') {
		if ($webonly) return;
		echo strip_tags($str) ."\n";
	} else {
		if (!preg_match('#<br />#i', $str)) $str .= '<br />';
		echo $str;
		@ob_flush(); flush();
	}
}

/** Print critical error and exit. */
function seterr($msg)
{
	if (php_sapi_name() == 'cli') {
		exit($msg ."\n");
	} else {
		exit('<p class="alert">'. $msg .'</p></body></html>');
	}
}

/** Connect to the source forum's DB. */
function bbconn($host, $port, $dbname, $dbuser, $dbpass, $prefix, $dbtype='mysql') {
	if (preg_match('/mysql/i', $dbtype)) {
		if (!empty($port)) $host = $host .':'. $port;
		if (!($conn = mysql_connect($host, $dbuser, $dbpass))) {
			seterr('Unable to connect to the source forum\'s MySQL database.');
		}
		define('dbtype', 'mysql');
		define('dbpref', $dbname .'.'. $prefix);
		define('dbconn', $conn);
	} else if (preg_match('/pgsql/i', $dbtype) || preg_match('/postgres/i', $dbtype)) {
		$dsn = 'host='. $host .' dbname='. $dbname .' user='. $dbuser .' password='. $dbpass;
		if (!empty($port)) $dsn .= ' port='. $port;
		if (!($conn = pg_connect($dsn))) {
			seterr('Unable to connect to the source forum\'s PostgreSQL database.');
		}
		define('dbtype', 'pgsql');
		define('dbpref', $prefix);
		define('dbconn', $conn);
	} else if (preg_match('/sqlite/i', $dbtype)) {
		class db2 { public static $db; }
		$dsn = 'sqlite:'. $host;	// NOTE: May need to change this to 'sqlite2:' (for older v2 databases).
		try {
			db2::$db = new PDO($dsn, $dbuser, $dbpass);
		} catch (PDOException $e) {
			seterr('Unable to connect to the source forum\'s SQLite database: '. $e->getMessage());
		}
		define('dbtype', 'sqlite');
		define('dbpref', $prefix);
	} else {
		seterr('Unsupported database type ['. $dbtype .']');
	}
	pf('... connected to '. $dbtype .' database '. $dbuser .'@'. $dbname);
}

/** Perform query against source forum's DB. */
function bbq($q, $err=0)
{
	if (dbtype == 'mysql')  $r = mysql_query($q, dbconn);
	if (dbtype == 'pgsql')  $r = pg_query(dbconn, $q);
	if (dbtype == 'sqlite') $r = db2::$db->query($q);

	if ($r) {
		return $r;
	}
	if (!$err) {
		pf('SQL statement: '. $q);
		if (dbtype == 'mysql')  seterr('MySQL error: '.      mysql_error(  dbconn));
		if (dbtype == 'pgsql')  seterr('PostgreSQL error: '. pg_last_error(dbconn));
		if (dbtype == 'sqlite') seterr('SQLite error: '.     end(db2::$db->errorInfo()));
	}
}

/** Fetch a row from the source forum's DB. */
function bbfetch($r)
{
	if (dbtype == 'mysql')  return mysql_fetch_object($r);
	if (dbtype == 'pgsql')  return pg_fetch_object(   $r);
	if (dbtype == 'sqlite') return $r->fetch(PDO::FETCH_OBJ);
}

/** BBCode cleanup and convertion. */
function bbcode2fudcode($str)
{
	// Replace [center] with [align=center].
	$str = preg_replace('!\[center\](.*)\[/center\]!i', '[align=center]\1[/align]', $str);

	$str = preg_replace('!\[(.+?)\:([a-z0-9]+)?\]!s', '[\1]', $str);
	$str = preg_replace('!\[quote\:([a-z0-9]+?)="(.*?)"\]!is', '[quote=\2]', $str);
	$str = preg_replace('!\[code\:([^\]]+)\]!is',  '[code]',  $str);
	$str = preg_replace('!\[/code\:([^\]]+)\]!is', '[/code]', $str);
	$str = preg_replace('!\[list\:([^\]]+)\]!is',  '[list]',  $str);
	$str = preg_replace('!\[/list\:([^\]]+)\]!is', '[/list]', $str);
	$str = preg_replace("#(^|[\n ])((www|ftp)\.[\w\-]+\.[\w\-.\~]+(?:/[^ \"\t\n\r<]*)?)#is", "\\1http://\\2", $str);

	$str = smiley_to_post(tags_to_html($str, 1, 1));

	return $str;
}

/** Format an IP address. */
function decode_ip($ip)
{
	if (filter_var($ip, FILTER_VALIDATE_IP)) {
		// We have a valid IPv4 or IPv6 address, return it.
		return $ip;
	}

	if (strlen($ip) == 8) {
		// Convert hex IPv4 address to dotted IP form (mainly used in phpBB).
		// For example: 7f000000 -> 127.0.0.1
		$ip = hexdec(substr($ip, 0, 2)) .'.'. hexdec(substr($ip, 2, 2)) .'.'. hexdec(substr($ip, 4, 2)) .'.'. hexdec(substr($ip, 6, 2));
		return $ip;
	}

	if (is_numeric($ip)) {
		// Convert a numeric (long) encoded IP address.
		return long2ip("0x{$ip}");
	}

	return '127.0.0.1';
}

/** Include a configuration file and expose its vars as GLOBALS. */
function config_file_include($file)
{
	$inc = @include($GLOBALS['CONVERT_FROM_DIR'] .'/'. $file);
	if ($inc === FALSE) {
		seterr('Unable to read configuration file: ['. $GLOBALS['CONVERT_FROM_DIR'] .'/'. $file .']');
	} else {
		pf('... reading config file '. $GLOBALS['CONVERT_FROM_DIR'] .'/'. $file);
	}

	// Export config as global vars.
	$GLOBALS += get_defined_vars();
	$GLOBALS += get_defined_constants();
}

/** Callback to load an avatar into the FUDforum database. */
function target_add_avatar($avatar)
{
	$avatar_file = basename($avatar['file']);
	if (empty($avatar['descr'])) {
		$avatar['descr'] = $avatar_file;
	}

	if (empty($avatar['custom'])) {
		$avatar_dir = $GLOBALS['WWW_ROOT_DISK'] .'images/avatars/';
	} else {
		$avatar_dir = $GLOBALS['WWW_ROOT_DISK'] .'images/custom_avatars/';
	}

	$ext = strtolower(substr(strrchr($avatar['file'], '.'),1));
	if ($ext != 'jpeg' && $ext != 'jpg' && $ext != 'png' && $ext != 'gif') {
		pf('...Skip invalid avatar ['. $avatar['descr'] .']');
		return;
	}

	if ($GLOBALS['VERBOSE']) pf('...'. $avatar['descr']);

	$old_umask=umask(0);
	if( !copy($avatar['file'], $avatar_dir . $avatar_file) ) {
		pf('WARNING: Couldn\'t copy avatar ['. $file .'] to ['. $avatar_dir . $avatar_file .')');
		return;
	}
	umask($old_umask);

	q('INSERT INTO '. $GLOBALS['DBHOST_TBL_PREFIX'] .'avatar (img, descr) VALUES('. _esc($avatar_file) .','. _esc($avatar['descr']) .')');
}

/** Callback to load a smiley into the FUDforum database. */
function target_add_smiley($smiley)
{
	if ($GLOBALS['VERBOSE']) pf('...'. $smiley['descr'] .' '. $smiley['code']);

	if (!@file_exists($smiley['file'])) {
		if (@file_exists($GLOBALS['CONVERT_FROM_DIR'] .'/'. $smiley['file'])) {
			$smiley['file'] = $GLOBALS['CONVERT_FROM_DIR'] .'/'. $smiley['file'];
		} else {
			pf('WARNING: Skip smiley ['. $smiley['file'] .']. File doesn\'t exist.');
			return;
		}
	}

	$img = basename($smiley['file']);

	$old_umask = umask(0111);
	$i = 1;
	if (!q_singleval('SELECT id FROM '. $GLOBALS['DBHOST_TBL_PREFIX'] .'smiley WHERE img='. _esc($img))) {
		if (!copy($smiley['file'], $GLOBALS['WWW_ROOT_DISK'] .'images/smiley_icons/'. $img)) {
			pf('WARNING: Coulnd\'t copy smiley image ['. $smiley['file'] .'] to ['. $GLOBALS['WWW_ROOT_DISK'] .'images/smiley_icons/'. $img .')');
			return;
		}
		q('INSERT INTO '. $GLOBALS['DBHOST_TBL_PREFIX'] .'smiley (img, code, descr, vieworder)
			VALUES('. _esc($img) .','. _esc($smiley['code']) .','. _esc($smiley['descr']) .', '. (int)$smiley['vieworder'] .')');
	} else {
		q('UPDATE '. $GLOBALS['DBHOST_TBL_PREFIX'] .'smiley SET code='. q_concat('code', _esc('~'. $smiley['code']) ) .' WHERE img='. _esc($img));
	}
	umask($old_umask);
}

/** Callback to load a user into the FUDforum database. */
function target_add_user($user)
{
	if ($GLOBALS['VERBOSE']) pf('...'. $user['login'] . (empty($user['email']) ? '' : ' ('.$user['email'].')'));

	// Ensure login and email is unique.
	$i = 1;
	while (q_singleval('SELECT id FROM '. $GLOBALS['DBHOST_TBL_PREFIX'] .'users WHERE login='. _esc($user['login']))) {
		$user['login'] = $user['login'] .'['. $i++ .']';
	}
	while (q_singleval('SELECT id FROM '. $GLOBALS['DBHOST_TBL_PREFIX'] .'users WHERE email='. _esc($user['email']))) {
		$user['email'] = $user['email'] .'['. $i++ .']';
	}

	if (!isset($GLOBALS['theme'])) {
		$GLOBALS['theme'] = q_singleval(q_limit('SELECT id FROM '. $GLOBALS['DBHOST_TBL_PREFIX'] .'themes WHERE '. q_bitand('theme_opt', 3) .' >= 3', 1));
	}

	if ($user['id'] == 1) {
		seterr('Cannot add user with id 1 since it is reserved for the anon user in FUDforum.');
	}

	if (!$user['last_visit']) $user['last_visit'] = max($user['last_read'],  $user['join_date']);
	if (!$user['last_read'] ) $user['last_read']  = max($user['last_visit'], $user['join_date']);
	if (!$user['join_date'] ) $user['join_date']  = max($user['last_read'],  $user['last_visit']);

	// Load avatar.
	$avatar = 0; $avatar_loc = '';
	if (empty($user['avatar'])) {
		$user['users_opt'] |= 4194304;	// avatar_approved (No Avatar).
	} else {
		$user['users_opt'] |= 8388608;	// avatar_approved YES.
		$avatar_file = preg_replace('/\?.*/', '\\1', $user['avatar']);	// Remove URL params.

		if (strpos($avatar_file, '://') ) {
			// External avatar.
			if (!($im = @getimagesize($avatar_file))) {
					pf('WARNING: external avatar ['. $avatar_file .'] is an invalid image.');
					// return;
			}
			$avatar_loc = '<img src="'. $avatar_file .'" alt="" '. $im[3] .' />';
		} else {
			// Check if avatar is in library.
			$avatar = q_singleval('SELECT id FROM '. $GLOBALS['DBHOST_TBL_PREFIX'] .'avatar WHERE img='. _esc(basename($avatar_file)));

			if (!$avatar) {
				// Custom uploaded avatar.
				if (!file_exists($avatar_file)) {
					$avatar_file = $GLOBALS['CONVERT_FROM_DIR'] .'/'. $avatar_file;
				}
				if (!($im = @getimagesize($avatar_file))) {
					pf('WARNING: Cannot find custom uploaded avatar ['. $avatar_file .']');
					// return;
				}
				$new_avatar_file = $GLOBALS['WWW_ROOT_DISK'] .'/images/custom_avatars/'. basename($avatar_file);
				$new_avatar_url  = $GLOBALS['WWW_ROOT']      .'/images/custom_avatars/'. basename($avatar_file);
				if (!copy($avatar_file, $new_avatar_file)) {
					pf('WARNING: Cannot copy custom uploaded avatar ['. $avatar_file .'] to ['. $new_avatar_file .']!');
					// return;
				}
				$avatar_loc = '<img src="'. $new_avatar_url .'" alt="" '. $im[3] .' />';
			}
		}
	}

	// Default user options:
	// 2=notify; 4=notify_method EMAIL; 16=email_messages; 32=pm_messages; 128=default_topic_view (MSG);
	// 512=gender (UNSPECIFIED); 4096=show_sigs; 8192=show_avatars; 16384=show_im; 131072=email_conf;
	$user['users_opt'] |= 2 | 4 | 16 | 32 | 128 | 256 | 512 | 4096 | 8192 | 16384 | 131072;

	// Birthday calculations.
	if (!empty($user['birthday'])) {
		$birthday = strftime('%m%d%Y', $user['birthday']);
	} else {
		$birthday = '';
	}

	q('INSERT INTO '. $GLOBALS['DBHOST_TBL_PREFIX'] .'users 
		(id, login, alias, name, passwd, salt, last_visit, last_read, join_date, 
		email, home_page, location, interests, occupation, birthday,
		time_zone, sig, avatar, avatar_loc,
		icq, aim, yahoo, msnm, users_opt, theme)
		VALUES (
			'. (int)$user['id'] .',
			'. _esc($user['login']) .',
			'. _esc(htmlspecialchars($user['login'])) .',
			'. _esc($user['name']) .',
			\''. $user['passwd'] .'\',
			'. _esc($user['salt']) .',
			'. (int)$user['last_visit'] .',
			'. (int)$user['last_read'] .',
			'. (int)$user['join_date'] .',
			'. _esc($user['email']) .',
			'. _esc($user['home_page']) .',
			'. _esc($user['location']) .',
			'. _esc($user['interests']) .',
			'. _esc($user['occupation']) .',
			'. _esc($birthday) .',
			'. _esc($user['time_zone']) .',
			'. _esc(bbcode2fudcode($user['sig'])) .',
			'. (int)$avatar .',
			'. _esc($avatar_loc) .',
			'. (int)$user['icq'] .',
			'. _esc($user['aim']) .',
			'. _esc($user['yahoo']) .',
			'. _esc($user['msn']) .',
			'. (int)$user['users_opt'] .',
			'. (int)$GLOBALS['theme'] .'
			)');
/*
fud_use('users_reg.inc');
pf($u->title);
$nu = new fud_user_reg;
$nu->login     = $u->username;
$nu->passwd    = $u->password;
$nu->salt      = $u->salt;
$nu->email     = $u->email;
$nu->name      = $u->username;
$nu->reg_ip    = $u->regip;
$nu->add_user();
*/
}

/** Callback to load category into the FUDforum database. */
function target_add_cat($cat)
{
	if ($GLOBALS['VERBOSE']) pf('...'. $cat['name']);

	q('INSERT INTO '. $GLOBALS['DBHOST_TBL_PREFIX'] .'cat (id, name, view_order, cat_opt) 
	   VALUES('. (int)$cat['id'] .','. _esc($cat['name']) .','. (int)$cat['view_order'] .', 3)');
	$GLOBALS['cat_map'][ $cat['id'] ] = $cat['id'];
/*
fud_use('cat.inc', true);
$nc = new fud_cat;
$nc->name        = $c->name;
$nc->description = $c->description;
$nc->view_order  = $c->disporder;
$nc->cat_opt     = 1|2;	// This should be the default in cat.inc. Fix in next release and del this line.
$GLOBALS['cat_map'][$c->fid] = $nc->add('LAST');	// FIRST should also be defaulted.
*/
}

/** Callback to load a forum into the FUDforum database. */
function target_add_forum($forum)
{
	if ($GLOBALS['VERBOSE']) pf('...'. $forum['name']);

	if (!isset($GLOBALS['cat_map'][ $forum['cat_id'] ])) {
		pf('WARNING: Create category for uncategorized forum.');
		$cat_id = q_singleval('SELECT MAX(id)+1 from '. $GLOBALS['DBHOST_TBL_PREFIX'] .'cat');
		if (!$cat_id) $cat_id = 1;
		target_add_cat(array('id'=>$cat_id, 'name'=>'Uncategorized Forums', 'description'=>'', 'view_order'=>$cat_id));
		$GLOBALS['cat_map'][ $forum['cat_id'] ] = $cat_id;
	}

	$forum_opt = 16;	// Set tag_style to BBCode.
	if (!empty($forum['post_passwd'])) {
		$forum_opt |= 4;	// Enable passwd_posting.
	}

	$frm = new fud_forum();
	$frm->cat_id               = $GLOBALS['cat_map'][ $forum['cat_id'] ];
	$frm->name                 = $forum['name'];
	$frm->description          = $forum['description'];
	$frm->view_order           = $forum['view_order'];
	$frm->post_passwd          = $forum['post_passwd'];
	$frm->url_redirect         = $forum['url_redirect'];
	$frm->forum_opt            = $forum_opt;
	$frm->max_attach_size      = 0;	// No limit.
	$frm->max_file_attachments = 5;	// Sensible default.
	$id = $frm->add('LAST');
	$GLOBALS['forum_map'][ (int)$forum['id'] ] = $id;
/*
fud_use('forum_adm.inc', true);
fud_use('groups_adm.inc', true);
fud_use('groups.inc');
$nf = new fud_forum;
// $cat_id, $name, $descr, $parent, $url_redirect, $post_passwd, $forum_icon,
// $forum_opt, $date_created, $message_threshold, $max_attach_size,
// $max_file_attachments
$nf->cat_id      = $GLOBALS['cat_map'][$c->pid];
$nf->name        = $c->name;
$nf->description = $c->description;
$nf->view_order  = $c->disporder;
$nf->post_passwd = $c->password;
// $nf->cat_opt = 1|2;
$GLOBALS['forum_map'][$c->fid] = $nf->add('LAST');
*/
}

/** Callback to load a topic/thread into the FUDforum database. */
function target_add_topic($topic)
{
	// if ($GLOBALS['VERBOSE']) pf('...'. $topic['id']);

	if (!isset($topic['orderexpiry'])) {
		$topic['orderexpiry'] = 0;
	}

	// Set orderexpiry for announcement and sticky topics.
	if (($topic['thread_opt'] & 2) || ($topic['thread_opt'] & 4)) {
		$topic['orderexpiry'] = 1000000000;
	}

	// Skip topics that doesn't belong to a forum.
	if (!isset($GLOBALS['forum_map'][ (int)$topic['forum_id'] ])) {
		pf('WARNING: Skip topic #'. $topic['id'] .'. Probably an announcement or orphaned message!');
		return;
	}

	q('INSERT INTO '. $GLOBALS['DBHOST_TBL_PREFIX'] .'thread (
		id, forum_id, root_msg_id, views, replies, thread_opt, orderexpiry
		) VALUES(
			'. (int)$topic['id'] .',
			'. $GLOBALS['forum_map'][ (int)$topic['forum_id'] ] .',
			'. (int)$topic['root_msg_id'] .',
			'. (int)$topic['views'] .',
			'. (int)$topic['replies'] .',
			'. (int)$topic['thread_opt'] .',
			'. (int)$topic['orderexpiry'] .')
	');
}

/** Callback to load a message/post into the FUDforum database. */
function target_add_message($message)
{
	if ($GLOBALS['VERBOSE']) pf('...'. $message['subject']);

	if (!isset($GLOBALS['forum_map'][ (int)$message['forum_id'] ])) {
		pf('WARNING: Skip message ['. $message['subject'] .']. Cannot add message to non-existing forum.');
		return;
	}
	$file_id = write_body(bbcode2fudcode($message['body']), $len, $off, $GLOBALS['forum_map'][ (int)$message['forum_id'] ] );

	if ($message['poster_id'] == 1 && isset($GLOBALS['hack_id'])) {
		$message['poster_id'] = $GLOBALS['hack_id'];
	}

	q('INSERT INTO '. $GLOBALS['DBHOST_TBL_PREFIX'] .'msg
		(id, thread_id, poster_id, post_stamp, update_stamp, updated_by, subject,
		 ip_addr, foff, length, file_id, msg_opt, apr
	) VALUES (
		'. $message['id'] .',
		'. (int)$message['thread_id'] .',
		'. (int)$message['poster_id'] .',
		'. (int)$message['post_stamp'] .',
		'. (int)$message['update_stamp'] .',
		'. (int)$message['updated_by'] .',
		'. _esc($message['subject']) .',
		'. decode_ip($message['ip_addr']) .',
		'. $off .',
		'. $len .',
		'. $file_id .',
		'. (int)$message['msg_opt'] .',
		1)'
	);
}

/** Callback to load an attachment into the FUDforum database. */
function target_add_attachment($att)
{
	if ($GLOBALS['VERBOSE']) pf('...'. $att['original_name']);

	if (!@file_exists($att['file'])) {
		if (@file_exists($GLOBALS['CONVERT_FROM_DIR'] .'/'. $att['file'])) {
			$att['file'] = $GLOBALS['CONVERT_FROM_DIR'] .'/'. $att['file'];
		} else {
			pf('WARNING: Skip file attachment ['. $att['file'] .']. File doesn\'t exist.');
			return;
		}
	}

	if ($att['user_id'] == 1 && isset($GLOBALS['hack_id'])) {
		$att['user_id'] = $GLOBALS['hack_id'];
	}

	$mime = q_singleval('SELECT id FROM '. $GLOBALS['DBHOST_TBL_PREFIX'] .'mime WHERE fl_ext='. _esc(substr(strrchr($att['original_name'], '.'), 1)));

	$att_id = db_qid('INSERT INTO '. $GLOBALS['DBHOST_TBL_PREFIX'] .'attach 
		(original_name, owner, message_id, dlcount, mime_type, fsize
	) VALUES (
		'. _esc($att['original_name']) .',
		'. (int)$att['user_id'] .',
		'. (int)$att['post_id'] .',
		'. (int)$att['download_count'] .',
		'. (int)$mime .',
		'. (int)filesize($att['file']) .')'
	);

	$old_umask = umask(0111);
	if (!copy($att['file'], $GLOBALS['FILE_STORE'] . $att_id .'.atch')) {
		pf('WARNING: Cannot copy attachment ['. $att['file'] .'] to ['. $GLOBALS['FILE_STORE']. $att_id .'.atch]!');
		return;
	}
	umask($old_umask);
}

/** Callback to load a forum subscription into the FUDforum database. */
function target_add_forum_subscription($sub)
{
	if ($sub['user_id'] == 1 && isset($GLOBALS['hack_id'])) {
		$sub['user_id'] = $GLOBALS['hack_id'];
	}
	q('INSERT INTO '. $GLOBALS['DBHOST_TBL_PREFIX'] .'forum_notify (user_id, forum_id) VALUES('. (int)$sub['user_id'] .', '. (int)$sub['forum_id'] .')');
}

/** Callback to load a topic subscription into the FUDforum database. */
function target_add_topic_subscription($sub)
{
	if ($sub['user_id'] == 1 && isset($GLOBALS['hack_id'])) {
		$sub['user_id'] = $GLOBALS['hack_id'];
	}
	q('INSERT INTO '. $GLOBALS['DBHOST_TBL_PREFIX'] .'thread_notify (user_id, thread_id) VALUES('. (int)$sub['user_id'] .', '. (int)$sub['topic_id'] .')');
}

/** Callback to load a poll into the FUDforum database. */
function target_add_poll($poll)
{
	if ($GLOBALS['VERBOSE']) pf('...'. $poll['name']);

	if ($poll['owner'] == 1 && isset($GLOBALS['hack_id'])) {
		$poll['owner'] = $GLOBALS['hack_id'];
	}
	q('INSERT INTO '. $GLOBALS['DBHOST_TBL_PREFIX'] .'poll (id, name, owner, creation_date, expiry_date, forum_id)
	VALUES(
		'. (int)$poll['id'] .',
		'. _esc($poll['name']) .',
		'. (int)$poll['owner'] .',
		'. (int)$poll['creation_date'] .',
		'. (int)$poll['expiry_date'] .',
		'. $GLOBALS['forum_map'][ (int)$poll['forum_id'] ] .')'
	);

	q('UPDATE '. $GLOBALS['DBHOST_TBL_PREFIX'] .'msg SET poll_id='. (int)$poll['id'] .' WHERE id='. (int)$poll['post_id']);
}

/** Callback to load a poll question into the FUDforum database. */
function target_add_poll_question($q)
{
	$qid = db_qid('INSERT INTO '. $GLOBALS['DBHOST_TBL_PREFIX'] .'poll_opt (poll_id, name)
		VALUES('. (int)$q['id'] .', '. _esc($q['name']) .')');
	return $qid;
}

/** Callback to load a poll vote into the FUDforum database. */
function target_add_poll_vote($vote)
{
	if ($vote['user_id'] == 1 && isset($GLOBALS['hack_id'])) {
		$vote['user_id'] = $GLOBALS['hack_id'];
	}
	q('INSERT INTO '. $GLOBALS['DBHOST_TBL_PREFIX'] .'poll_opt_track (poll_id, user_id, poll_opt)
		VALUES('. (int)$vote['poll_id'] .', '. (int)$vote['user_id'] .', '. (int)$vote['poll_opt'] .')');
}

/** Callback to load a private message into the FUDforum database. */
function target_add_private_message($pm)
{
	if ($GLOBALS['VERBOSE'] && $pm['fldr']==1) pf('...'. $pm['subject']);
	
	list($off, $len) = write_pmsg_body(bbcode2fudcode($pm['body']));

	if ($pm['duser_id'] == 1 && isset($GLOBALS['hack_id'])) {	// To address.
		$pm['duser_id'] = $GLOBALS['hack_id'];
	}
	if ($pm['ouser_id'] == 1 && isset($GLOBALS['hack_id'])) {	// Author id.
		$pm['ouser_id'] = $GLOBALS['hack_id'];
	}

	q('INSERT INTO '. $GLOBALS['DBHOST_TBL_PREFIX'] .'pmsg 
		(ouser_id, duser_id, ip_addr, post_stamp, read_stamp, fldr, subject, pmsg_opt, foff, length, to_list)
		VALUES(
			'. (int)$pm['ouser_id'] .',
			'. (int)$pm['duser_id'] .',
			'. _esc(decode_ip($pm['ip_addr'])) .',
			'. (int)$pm['post_stamp'] .',
			'. (int)$pm['read_stamp'] .',
			'. (int)$pm['fldr'] .',
			'. _esc($pm['subject']) .',
			'. $pm['pmsg_opt'] .',
			'. $off .',
			'. $len .',
			'. _esc($pm['to_list']) .')'
	);
}

/** Callback to load a calendar event into the FUDforum database. */
function target_load_calendar_event($event)
{
	if ($GLOBALS['VERBOSE']) pf('...'. $event['descr']);

	q('INSERT INTO '. $GLOBALS['DBHOST_TBL_PREFIX'] .'calendar (event_day, event_month, event_year, link, descr)
	VALUES(
		'. _esc($poll['day']) .',
		'. _esc($poll['month']) .',
		'. _esc($poll['year']) .',
		'. _esc($poll['link']) .',
		'. _esc($poll['descr']) .')'
	);
}

/* main */
error_reporting(E_ALL | E_NOTICE | E_ERROR | E_WARNING | E_PARSE | E_COMPILE_ERROR);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
ini_set('memory_limit', '-1');
ini_set('default_socket_timeout', 15);
set_time_limit(0);

/* Load GLOBALS.php. */
$gl = @include('./GLOBALS.php');
if ($gl === FALSE) {
	seterr('Please install FUDforum and copy this script into FUDforum\'s main web directory.');
}

/* Check if forum must be un-locked. */
define('__WEB__', (isset($_SERVER['REMOTE_ADDR']) === FALSE ? 0 : 1));
if (strncasecmp('win', PHP_OS, 3) && ($FUD_OPT_2 & 8388608) && !__WEB__) {
	seterr('Since you are running the script via the console, you must first UNLOCK your forum\'s files.');
}

/* List available converter mapping plugins. */
foreach (glob('./conversionmaps/*.map') as $f) {
	$f = preg_replace('/(.*)\.map$/', '\1', basename($f));
	$converters[] = $f;
}
if (empty($converters)) {
	seterr('No converters available that can be used.');
}
natcasesort($converters);

/* Get parameters. */
if (php_sapi_name() == 'cli') {

	if (empty($_SERVER['argv'][1]) || empty($_SERVER['argv'][2])) {
		seterr('Usage: convert.php forum_type source_forum_dir');
	} else {
		$CONVERT_FROM_FORUM = $_SERVER['argv'][1];
		$CONVERT_FROM_DIR   = $_SERVER['argv'][2];
		$VERBOSE            = isset($_SERVER['argv'][2]) ? (int)$_SERVER['argv'][2] : 1;
	}

} else {

?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<title>FUDforum Migration Assistant</title>
	<link rel="styleSheet" href="adm/style/adm.css" />
	<style>html, body { height: 95%; }</style>
	<script src="js/jquery.js"></script>
	<script>
	jQuery(document).ready(function() {
		jQuery(':text:visible:enabled:first').focus();
	});
	</script>
</head>
<body>
<table class="headtable"><tr>
  <td><img src="images/fudlogo.gif" alt="" style="float:left;" border="0" /></td>
  <td><span class="linkhead">FUDforum Migration Assistant</span></td>
  <td> &nbsp; </td>
</tr></table>
<table class="maintable" style="height:100%;">
<tr><td class="maindata">

<?php
	if (!count($_POST)) {
		$dir = str_replace('\\', '/', dirname( getcwd() ) .'/');

?>
<h2>Converter configuration</h2>
<form name="convert" action="<?php echo basename(__FILE__); ?>" method="post">
<table class="datatable solidtable">
<tr class="fieldtopic"><td colspan="2">
	This converter will clear the <abbr title="This is your FUDforum installation.">target forum<sup><small>?</small></sup></abbr> (remove all users, posts, etc) and load the content of the <abbr title="This is the forum you want to convert to FUDforum.">source forum<sup><small>?</small></sup></abbr> into it.
	The conversion process will not harm the source forum and if there is a problem, you can still use the source forum software as you did before while the situation is resolved. <br /><br /></td>
</tr>
<tr class="field">
	<td><b>Convert from:</b><br /><small>Convert from this forum type to FUDforum.</small></td>
	<td><select name="from" />
<?php
	foreach ($converters as $converter) {
		echo '<option value="'. htmlspecialchars($converter) .'">'. htmlspecialchars(strtr($converter, '_', ' ')) .'</option>';
	}
?>
	</select></td>
</tr>
<tr class="field">
	<td><b>Installation directory:</b><br /><small>Directory on server where the source forum is installed.</small></td>
	<td><input type="cfg" name="cfg" value="<?php echo $dir; ?>" size="40" /></td>
</tr>
<tr class="field">
	<td><b>Verbose:</b><br /><small>Print detailed progress info.</small></td>
	<td><input type="checkbox" name="verbose" value="1" checked="checked" /></td>
</tr>
<tr class="field">
	<td><b>Create admin user:</b><br /><small>Create an admin account after conversion. Usually not required, but handy in case you cannot log in.</small></td>
	<td><input type="checkbox" name="add_admin" value="1" /></td>
</tr>
<tr class="fieldaction">
	<td align="right" colspan="2"><input type="submit" class="button" name="submit" value="Start conversion" /></td>
</tr>
</table>
</form>

</td></tr></table>
</body>
</html>
<?php
		exit;
	} else {
		if (empty($_POST['from']) || empty($_POST['cfg'])) {
			seterr('Usage: convert.php forum_type source_forum_dir');
		}
		$CONVERT_FROM_FORUM = $_POST['from'];
		$CONVERT_FROM_DIR   = $_POST['cfg'];
		$VERBOSE            = isset($_POST['verbose']  ) ? 1 : 0;
		$ADD_ADMIN          = isset($_POST['add_admin']) ? 1 : 0;
	}
}

/* Load the relevant converter mapping plugin. */
$inc = @include('./conversionmaps/'. $CONVERT_FROM_FORUM .'.map');
if ($inc === FALSE) {
	pf('Invalid forum_type specified ['. $CONVERT_FROM_FORUM .'] specified.');
	seterr('Available: '. implode(', ', $converters));
}

/* Check source forum directory. */
if (!is_dir($CONVERT_FROM_DIR)) {
	seterr('Source forum direcory is invalid ['. $CONVERT_FROM_DIR .'].');
}

/* Prevent session initialization. */
define('no_session', 1);

/* Include all the necessary FUDforum includes. */
// include './scripts/fudapi.inc.php';
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

// Optimize SQLite db for speed.
if ($GLOBALS['DBHOST_DBTYPE'] == 'pdo_sqlite') {
	define('SQLITE_FAST_BUT_WRECKLESS', 1);
}

$start_time = time();
pf('<h2>'. $CONVERT_FROM_FORUM .' to FUDforum conversion</h2>');

/* Read source source forum's config into $GLOBALS. */
if (function_exists('source_read_config')) {
	pf('Read '. $CONVERT_FROM_FORUM .'\'s config...');
	source_read_config($CONVERT_FROM_DIR);
}

/* Connect to source forum's database. */
if (function_exists('source_db_connect')) {
	pf('Connecting to '. $CONVERT_FROM_FORUM .'\'s database...');
	source_db_connect();
}

if (function_exists('source_load_avatars')) {
	pf('Import avatars...');
	q('DELETE FROM '. $GLOBALS['DBHOST_TBL_PREFIX'] .'avatar');
	source_load_avatars();
}

if (function_exists('source_load_smilies')) {
	pf('Import smilies...');
	q('DELETE FROM '. $DBHOST_TBL_PREFIX .'smiley');
	source_load_smilies();
}

if (function_exists('source_load_users')) {
	pf('Import users...');
	q('DELETE FROM '. $DBHOST_TBL_PREFIX .'users WHERE id>1');
	source_load_users();
}

if (function_exists('source_load_cats')) {
	pf('Import categories...');
	q('DELETE FROM '. $DBHOST_TBL_PREFIX .'cat');
	q('DELETE FROM '. $DBHOST_TBL_PREFIX .'group_resources');
	q('DELETE FROM '. $DBHOST_TBL_PREFIX .'groups WHERE id>2');
	q('DELETE FROM '. $DBHOST_TBL_PREFIX .'group_members');
	source_load_cats();
}

if (function_exists('source_load_forums')) {
	pf('Import forums...');
	if (!function_exists('source_load_cats')) {	// No cats loaded.
		q('DELETE FROM '. $DBHOST_TBL_PREFIX .'cat');
	}
	q('DELETE FROM '. $DBHOST_TBL_PREFIX .'forum');
	source_load_forums();
}

if (function_exists('source_load_topics')) {
	pf('Import topics...');
	q('DELETE FROM '. $DBHOST_TBL_PREFIX .'thread');
	q('DELETE FROM '. $DBHOST_TBL_PREFIX .'thread_rate_track');
	source_load_topics();
}

if (function_exists('source_load_messages')) {
	pf('Import messages...');
	q('DELETE FROM '. $DBHOST_TBL_PREFIX .'msg');
	source_load_messages();
}

if (function_exists('source_load_attachments')) {
	pf('Import attachments...');
	q('DELETE FROM '. $DBHOST_TBL_PREFIX .'attach');
	source_load_attachments();
}

if (function_exists('source_load_polls')) {
	pf('Import polls...');
	q('DELETE FROM '. $DBHOST_TBL_PREFIX .'poll');
	q('DELETE FROM '. $DBHOST_TBL_PREFIX .'poll_opt');
	q('DELETE FROM '. $DBHOST_TBL_PREFIX .'poll_opt_track');
	source_load_polls();
}

if (function_exists('source_load_forum_subscriptions')) {
	pf('Import forum subscriptions...');
	q('DELETE FROM '. $DBHOST_TBL_PREFIX .'forum_notify');
	source_load_forum_subscriptions();
}

if (function_exists('source_load_topic_subscriptions')) {
	pf('Import topic subscriptions...');
	q('DELETE FROM '. $DBHOST_TBL_PREFIX .'thread_notify');
	source_load_topic_subscriptions();
}

if (function_exists('source_load_private_messages')) {
	pf('Import private messages...');
	q('DELETE FROM '. $DBHOST_TBL_PREFIX .'pmsg');
	source_load_private_messages();
}

if (function_exists('source_load_calendar_events')) {
	pf('Import calendar entries...');
	q('DELETE FROM '. $DBHOST_TBL_PREFIX .'calendar');
	source_load_calendar_events();
}

if (function_exists('source_auth_function')) {
	pf('Install plugin to autenticate and convert user passwords...');
	// Enable plugin support.
	$FUD_OPT_3 |= 4194304;
	fud_use('glob.inc', true);
	change_global_settings(array('FUD_OPT_3' => $FUD_OPT_3));
	// Deploy auth plugin.
	copy('./conversionmaps/convert_auth.plugin', $PLUGIN_PATH .'convert_auth.plugin');
	// Write plugin config.
	$ini['auth_func'] = source_auth_function();
	$fp = fopen($GLOBALS['PLUGIN_PATH'] .'convert_auth.ini', 'w');
	fwrite($fp, '<?php $ini = '. var_export($ini, 1).'; ?>');
	fclose($fp);
	// Enable plugin.
	fud_use('plugins.inc', true);
	fud_use('plugin_adm.inc', true);
	fud_plugin::activate('convert_auth.plugin');
}

// Check if we have an admin user. If not, create one.
$admin = q_singleval(q_limit('SELECT id FROM '. $GLOBALS['DBHOST_TBL_PREFIX'] .'users WHERE '. q_bitand('users_opt', 1048576) .' > 0', 1));
if (!$admin || $ADD_ADMIN) {
	$user   = 'admin';
	$salt   = substr(md5(uniqid(mt_rand(), true)), 0, 9);
	$passwd = sha1($salt . sha1('fudforum'));
	$i = 1;
	while (q_singleval('SELECT id FROM '. $GLOBALS['DBHOST_TBL_PREFIX'] .'users WHERE login='. _esc($user))) {
		$user = $user . $i++;
	}
	q('INSERT INTO '. $DBHOST_TBL_PREFIX .'users (login, alias, email, passwd, salt, users_opt, theme) VALUES('. _esc($user) .','. _esc($user) .','. _esc($user) .','. _esc($passwd) .','. _esc($salt) .', 13777910, 1);');

	if (!$admin) {
		pf('<hr>There is no admin account in the database, so we took the liberty of creating one for you.');
	} else {
		pf('<hr>As requested, we\'ve created an admin user for you.');
	}
	pf('Please login with: <b>'. $user .'/fudforum</b>');
}

// Clear old FUDforum sessions.
q('DELETE FROM '. $DBHOST_TBL_PREFIX .'ses');

// Clear & log action.
fud_use('logaction.inc');
q('DELETE FROM '. $DBHOST_TBL_PREFIX .'action_log');
logaction(1, 'Converted from '. $CONVERT_FROM_FORUM, 0, $CONVERT_FROM_DIR);

// Print time taken.
$time_taken = time() - $start_time;
if ($time_taken > 120) {
	$time_taken .= ' seconds.';
} else {
	$m = floor($time_taken/60);
	$s = $time_taken - $m*60;
	$time_taken = $m .' minutes '. $s .' seconds.';
}

pf('<hr><span style="color:darkgreen;">Conversion of '. $CONVERT_FROM_FORUM .' to FUDforum has been completed.</span>');
pf('Time Taken: '. $time_taken);

pf('<hr><span style="color:red;">Note that you will not see the loaded content on your forum just yet.</span>');
pf('You must first <b><a href="'. $WWW_ROOT .'">login</a></b> and run the <b>consistency checker</b>.');
pf('<hr>');

?>
