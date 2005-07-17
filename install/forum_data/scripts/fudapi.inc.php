<?php
/* If your script does not include GLOBALS.php already,
 * uncomment the line below and specify the full path to
 * the FUDforum's GLOBALS.php file.
 */
// require("/path/to/GLOBALS.php");

/* 
General Information
--------------------------------------------
 * Most function accept 'id' arguments that allows you to specify
 * what data should be retrieved. Unless otherwise indicated this
 * argument can be either an integer or an array of integers if you
 * want to retrieve more then one item.

 * If you request more then one entry by specifying an array and
 * the result has less then the requested number of entries, no
 * entries will be returned.
 
 * On success for single entry data an object containing the data 
 * will be returned. For multi-entry data an array of objects will
 * be returned.

 * If any functions have optional arguments, they will be indicated
 * in the proto by being inside [], all other arguments must be 
 * considered as required.

 * If you intend to use FUD API from a non-webserver environment,
 * make sure that GLOBALS.php, db.inc & err.inc are world readable.
 * The GLOBALS.php can be found inside the forum's DATA_DIR/include/
 * directory and the inc files can be found inside
 * DATA_DIR/include/theme/default/ directory.
*/

/* {{{ proto: mixed fud_fetch_msg(mixed arg) }}}
 * This function takes message ids as arguments and returns an object 
 * or an array objects representing messages. On failure FALSE will be 
 * returned.
 * Fields:
stdClass Object
(
    [id] => // numeric id of the message
    [thread_id] => // numeric id of the topic
    [poster_id] => // numeric id of the message author (0 == anonymous)
    [reply_to] => // id of the message this message is a reply to
    [ip_addr] => // IP address of the poster
    [host_name] => // hostname of the poster, !!could be empty!!
    [post_stamp] => // unix timestamp representing post date
    [update_stamp] => // unix timestamp representing edit date (0 == never edited)
    [updated_by] => // id of the person who edited the message (0 == never edited)
    [icon] => // message icon, !!could be empty!!
    [subject] => // htmlencoded subject
    [attach_cnt] => // number of file attachments
    [poll_id] => // id of a poll included in the message, !!could be empty!!
    [mlist_msg_id] => // mailing list or nntp message identifier !!could be empty!!
    [forum_id] => // of the forum where the message is posted
    [login] => // html encoded login mame of the user
    [avatar_loc] => // <img src> of the author avatar !!could be empty!!
    [email] => // author e-mail address
    [posted_msg_count] => // author's post count
    [join_date] => // author's join date
    [location] => // author's location !!could be empty!!
    [sig] => // author's signature !!could be empty!!
    [custom_status] =>  // author's custom status (string) !!could be empty!!
    [icq] => // author's ICQ uin !!could be empty!!
    [jabber] =>  // author's jabber uin !!could be empty!!
    [affero] =>  // author's affer uin !!could be empty!!
    [aim] => // author's aim uin !!could be empty!!
    [msnm] => // author's msn uin !!could be empty!! 
    [yahoo] =>  // author's Y! uin !!could be empty!! 
    [users_opt] => // author's settings bitmask
    [time_sec] => // time of author's last visit
    [level_name] => // author's level (based on post count)
    [level_img] => // author's level image !!could be empty!!

--- Poll data, will only be avaliable if a message has a poll ---
    [poll_data] => stdClass Object
        (
            [name] => // poll name
            [creation_date] => // poll creation date (unix timestamp) 
            [total_votes] => // total # of votes
            [id] => // poll id
            [options] => // array of option objects
                (
                    [0] => stdClass Object
                        (
                            [name] => fsa
                            [count] => 0
                        )
                )
        )
--- End of Poll data ---

--- Attachment data, will only be avaliable if a message has file attachments ---
    [attachments] => // array of attachments
        (
            [id] => // attachment id
            [location] => // full path to attachment on disk
            [original_name] => // attachment's original name
            [dlcount] => // number of downloads
            [fsize] => // file size
            [mime_hdr] => // mime type
            [descr] => // text description of the file type
            [icon] => // mime type icon
            [download_url] => // download URL
        )
--- End of Attachment data ---
)
*/
function fud_fetch_msg($arg)
{
	$arg = is_numeric($arg) ? array($arg) : $arg;

	$result = array();
	$c = q("SELECT
		m.*,
		t.forum_id,
		u.alias AS login, u.avatar_loc, u.email, u.posted_msg_count, u.join_date, u.location,
		u.sig, u.custom_status, u.icq, u.jabber, u.affero, u.aim, u.msnm, u.yahoo, u.users_opt, u.last_visit AS time_sec,
		l.name AS level_name, l.img AS level_img
	FROM
		".$GLOBALS['DBHOST_TBL_PREFIX']."msg m
		INNER JOIN ".$GLOBALS['DBHOST_TBL_PREFIX']."thread t ON m.thread_id=t.id
		LEFT JOIN ".$GLOBALS['DBHOST_TBL_PREFIX']."users u ON m.poster_id=u.id
		LEFT JOIN ".$GLOBALS['DBHOST_TBL_PREFIX']."level l ON u.level_id=l.id
		WHERE m.id IN (".implode(',', $arg).") AND m.apr=1");

	while ($r = db_rowobj($c)) {
		if ($r->poll_cache && $r->poll_id) {
			$r->poll_data = fud_fetch_poll($r->poll_id);
			unset($r->poll_data->alias, $r->poll_data->owner);
		}
		if ($r->attach_cnt && !empty($r->attach_cache)) {
			$tmp = @unserialize($r->attach_cache);
			$alist = array();
			foreach ($tmp as $v) {
				$alist[] = $v[0];
			}
			$r->attachments = fud_fetch_attachment($alist);
		}
		unset(
			$r->foff, $r->length, $r->file_id, $r->offset_preview, $r->length_preview, $r->file_id_preview,
			$r->attach_cache, $r->poll_cache, $r->apr, $r->msg_opt
		);
		$result[] = $r;
	}
	unset($c, $r);

	if (count($result) != count($arg)) {
		return FALSE;
	} else {
		if (count($result) == 1) {
			return array_pop($result);
		} else {
			return $result;
		}
	}
}

/* {{{ proto: mixed fud_fetch_full_topic(mixed arg) }}}
 * This function takes topic id(s) as arguments and returns all of the 
 * messages inside the selected topics.
 * The output is identical to that of the fud_fetch_msg() function.
 */
function fud_fetch_full_topic($arg)
{
	return _fud_msg_multi($arg, "SELECT id FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."msg WHERE thread_id IN ({ARG}) AND apr=1");
}

/* {{{ proto: mixed fud_fetch_recent_msg([float arg = 1]) }}}
 * This function retrieves messages that were posted after specified date.
 * The date range is in days and is optional, by default messages newer
 * then 1 day will be returned.
 * The output is identical to that of the fud_fetch_msg() function.
 */
function fud_fetch_recent_msg($arg=1)
{
	$range = time() - 86400 * (float) $arg;
	return _fud_msg_multi(0, "SELECT id FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."msg WHERE apr=1 AND post_stamp > ".$range);
}

/* {{{ proto: mixed fetch_fetch_msg_by_user(mixed arg) }}}
 * This function returns all messages posted by the specified user(s).
 * The output is identical to that of the fud_fetch_msg() function.
 */
function fetch_fetch_msg_by_user($arg)
{
	return _fud_msg_multi(arg, "SELECT id FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."msg WHERE poster_id IN ({ARG}) AND apr=1");
}

/* {{{ proto: mixed fud_fetch_topic(mixed arg) }}}
 * This function returns information about specified topics.
 * Fields:
stdClass Object
(
    [attach_cnt] => // number of attachments in the 'root' message
    [poll_id] => // id of the poll in the the 'root' message
    [subject] => // subject of the topic
    [icon] => // icon of the 'root' message
    [post_stamp] => // creation date (unix timestamp)
    [alias] => // author's login (html encoded)
    [id] => // author's id
    [topic_id] => // topic id
    [moved_to] => // moved to forum id
    [root_msg_id] => // id of the 'root' message
    [replies] => // number of replies
    [rating] => // rating
    [views] => // number of views
    [type] => // sticky || announcement || null (normal topic)
)
 */
function fud_fetch_topic($arg)
{
	$arg = is_numeric($arg) ? array($arg) : $arg;

	$result = array();	

	$c = uq("SELECT
		m.attach_cnt, m.poll_id, m.subject, m.icon, m.post_stamp,
		u.alias, u.id,
		u2.id, u2.alias,
		m2.id, m2.post_stamp,
		t.id AS topic_id, t.moved_to, t.root_msg_id, t.replies, t.rating, t.thread_opt, t.views
		FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."thread t
			INNER JOIN ".$GLOBALS['DBHOST_TBL_PREFIX']."msg	m	ON t.root_msg_id=m.id
			INNER JOIN ".$GLOBALS['DBHOST_TBL_PREFIX']."msg	m2	ON m2.id=t.last_post_id
			LEFT JOIN ".$GLOBALS['DBHOST_TBL_PREFIX']."users	u	ON u.id=m.poster_id
			LEFT JOIN ".$GLOBALS['DBHOST_TBL_PREFIX']."users	u2	ON u2.id=m2.poster_id
			WHERE t.id IN(".implode(',', $arg).")");

	while ($r = db_rowobj($c)) {
		$r->replies++;
		$r->type = $r->thread_opt > 1 ? ($r->thread_opt & 4 ? 'sticky' : 'announcement') : NULL;
		if ($GLOBALS['FUD_OPT_2'] & 4096 && $r->rating) {
			$r->rating = NULL;
		}
		unset($r->thread_opt);
		$result[] = $r;
	}
	unset($c, $r);

	if (count($result) != count($arg)) {
		return FALSE;
	} else {
		if (count($result) == 1) {
			return array_pop($result);
		} else {
			return $result;
		}
	}
}

/* {{{ proto: mixed fud_fetch_poll(mixed arg) }}}
 * This function returns information about specified poll(s).
 * Fields:
stdClass Object
(
    [name] => // poll name
    [creation_date] => // creation date (unix timestamp)
    [total_votes] => // total number of votes
    [alias] => // author's login (html encoded)
    [id] => // poll id
    [owner] => // author's id
    [options] => // Poll options array
        (
            [0] => stdClass Object
                (
                    [name] => // option name
                    [count] => // vote count
                )
        )
)
*/
function fud_fetch_poll($arg)
{
	$arg = is_numeric($arg) ? array($arg) : $arg;
	$result = array();

	$r = q("SELECT p.name, p.creation_date, p.total_votes, u.alias, p.id, p.owner
			FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."poll p
			LEFT JOIN ".$GLOBALS['DBHOST_TBL_PREFIX']."users u ON u.id=p.owner
			WHERE p.id IN(".implode(',', $arg).")");
	while ($row = db_rowobj($r)) {
		$opts = array();
		$r2 = uq("SELECT name, count FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."poll_opt WHERE poll_id=".$row->id." ORDER BY id");
		while ($row2 = db_rowobj($r2)) {
			$opts[] = $row2;
		}
		$row->options = $opts;
		$result[] = $row;
	}
	unset($r2, $r, $row, $row2);

	if (count($result) != count($arg)) {
		return FALSE;
	} else {
		if (count($result) == 1) {
			return array_pop($result);
		} else {
			return $result;
		}
	}
}

/* {{{ proto: mixed fud_fetch_attachment(mixed arg) }}}
 * This function returns information about specified file attachment(s).
 * Fields:
stdClass Object
(
    [id] => // attachment id
    [location] => // path on disk
    [original_name] => // original name
    [owner] => // owner's id
    [message_id] => // associated message id
    [dlcount] => // download count
    [mime_type] => // mime type
    [fsize] => // file size in bytes
    [alias] => // owner's login name (html encoded)
    [mime_hdr] => // mime header
    [descr] => // text description of mime type
    [icon] => // mime icon
    [download_url] => // download URL
)
*/ 
function fud_fetch_attachment($arg)
{
	$res = _fud_simple_fetch_query($arg, "SELECT 
			a.*, u.alias, m.mime_hdr, m.descr, m.icon 
			FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."attach a 
			LEFT JOIN ".$GLOBALS['DBHOST_TBL_PREFIX']."users u ON u.id=a.owner
			LEFT JOIN ".$GLOBALS['DBHOST_TBL_PREFIX']."mime m ON m.id=a.mime_type
			WHERE a.id IN({ARG})");

	if (is_array($res)) {
		foreach ($res as $k => $v) {
			$res[$k]->download_url = $GLOBALS['WWW_ROOT'].'index.php?t=getfile&amp;id='.$v->id;
			unset($res[$k]->attach_opt);
		}
	} else {
		$res->download_url = $GLOBALS['WWW_ROOT'].'index.php?t=getfile&amp;id='.$res->id;
		unset($res->attach_opt);
	}
	return $res;
}

/* {{{ proto: mixed fud_fetch_forum(mixed arg) }}}
 * This function returns information about specified forum(s).
 * Fields:
stdClass Object
(
    [id] => // forum id
    [cat_id] => // category id
    [name] => // forum name (may contain raw HTML)
    [descr] => // forum description (may contain raw HTML)
    [post_passwd] => // forum's posting password
    [forum_icon] => // forum icon
    [date_created] => // forum's creation day
    [thread_count] => // number of topics
    [post_count] => // number of messages
    [last_post_id] => // id of the latest message
    [max_attach_size] => // maximum size of attached files in Kbytes
    [max_file_attachments] => // maximum number of allowed attachments
    [moderators] => Array
        (
            [// moderator's user id] => // moderator's login name (html encoded)
        )
)
*/
function fud_fetch_forum($arg)
{
	return _fud_decode_forum(_fud_simple_fetch_query($arg, "SELECT * FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."forum WHERE id IN({ARG})"));
}

/* {{{ proto: mixed fud_fetch_cat(mixed arg) }}}
 * This function returns information about the specified categories.
 * Fields:
stdClass Object
(
    [id] => // category id
    [name] => // category name (may contain raw html)
    [description] => // category description (may contain raw html)
)
 */
function fud_fetch_cat($arg)
{
	return _fud_simple_fetch_query($arg, "SELECT * FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."cat WHERE id IN({ARG})");
}

/* {{{ proto: mixed fud_fetch_cat_forums(mixed arg) }}}
 * This function returns information about forum(s) inside specified categories.
 * The output is identical to that of the fud_fetch_forum() function.
 */
function fud_fetch_cat_forums($arg)
{
	return _fud_decode_forum(_fud_simple_fetch_query($arg, "SELECT * FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."forum WHERE cat_id IN({ARG})"));
}

/* {{{ proto: mixed fud_forum_stats() }}}
 * This function returns forum statistics.
 * Fields:
Array
(
    [total_msg] => // total number of messages in the forum
    [total_topic] => // total number of topics in the forum
    [total_users] => // total number of forum members
    [online_users] => // number of currently online users
    [newest_user] => // newest forum member
        (
            [id] => // user's id
            [alias] => // user's login (html encoded)
        )
)
*/
function fud_forum_stats()
{
	$tm_expire = __request_timestamp__ - ($GLOBALS['LOGEDIN_TIMEOUT'] * 60);

	$uid = q_singleval("SELECT MAX(id) FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."users");

	$stats = array(
		'total_msg' => q_singleval("SELECT count(*) FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."msg"),
		'total_topic' => q_singleval("SELECT count(*) FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."thread"),
		'total_users' => q_singleval("SELECT count(*) FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."users"),
		'online_users' => q_singleval("SELECT count(*) FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."ses WHERE time_sec>".$tm_expire." AND user_id<2000000000"),
		'newest_user' => db_arr_assoc("SELECT id, alias FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."users WHERE id=".$uid)
	);

	return $stats;
}

/* {{{ proto: mixed fud_fetch_online_users() }}}
 * This function returns a list of currently online users.
 * Fields:
stdClass Object
(
    [id] => // user's id
    [alias] => // user's login name 
    [time_sec] => // time of last access (unix timestamp)
    [private] => // wether or not user want's their online status hidden
)
*/
function fud_fetch_online_users()
{
	$tm_expire = __request_timestamp__ - ($GLOBALS['LOGEDIN_TIMEOUT'] * 60);

	return _fud_simple_fetch_query(0, "SELECT 
			u.id, u.alias, s.time_sec, (u.users_opt &32768) as private
			FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."ses s
			INNER JOIN ".$GLOBALS['DBHOST_TBL_PREFIX']."users u ON s.user_id=u.id
			WHERE time_sec>".$tm_expire);
}

/* {{{ proto: mixed fud_fetch_user(mixed arg) }}}
 * This function returns profile information about the specified user(s).
stdClass Object
(
    [id] => // user's id
    [login] => // user's login name
    [alias] => // user's alias (html encoded) used for printing
    [passwd] => // md5 of the password
    [name] => // user's 'real' name
    [email] => // user's e-mail address
    [location] => // user's geographical location (optional)
    [interests] => // user's interests (optional)
    [occupation] => // user's occuptation (optional)
    [avatar_loc] => // img src of the user's avatar !!could be empty!!
    [icq] => // icq uin
    [aim] => // aim uin
    [yahoo] => // Y! uin
    [msnm] => // msn uin
    [jabber] => // jabber uin
    [affero] => // affero uin
    [time_zone] => // user's timezone of choice
    [bday] => // user's b-day YYYYMMDD
    [join_date] => // date this user registered on (unix timestamp)
    [user_image] => // optional image URL
    [theme] => // id of the forum theme used by this user
    [posted_msg_count] => // number of messages posted by this user
    [last_visit] => // time of last visit (unix timestamp)
    [referer_id] => // id of the user who referred this user
    [custom_status] => // <br /> separated list of the custom tags this user has
    [sig] => signature (html encoded)
    [u_last_post_id] => // id last message posted by this user
    [home_page] => // homepage URL
    [bio] => // HTML safe biography
)
*/
function fud_fetch_user($arg)
{
	return _fud_simple_fetch_query($arg, "SELECT * FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."users WHERE id IN({ARG})");
}

/* {{{ proto: object fud_fetch_newest_user() }}}
 * Return profile information about the forum's newest member.
 */
function fud_fetch_newest_user()
{
	fud_fetch_user(q_singleval("SELECT MAX(id) FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."users"));
}

/* {{{ proto: object fud_fetch_random_user() }}}
 * Fetch profile information about a random forum member.
 */
function fud_fetch_random_user()
{
	return _fud_simple_fetch_query(0, "SELECT * FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."users ORDER BY RAND()");
}

/* {{{ proto: object fud_fetch_top_poster() }}}
 * Return profile information about a forum member with a greatest number of posts.
 */
function fud_fetch_top_poster()
{
	return _fud_simple_fetch_query(0, "SELECT * FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."users ORDER BY posted_msg_count DESC LIMIT 1");
}

/* {{{ proto: object fud_add_user($vals, &$err) }}}
 * Return the id of a newly created user, on error returns 0 and populates $err with the error message.
 * Vals is an array of fields found inside the users table
 *	login	- login name for the account, must be unique. **required**
 *	passwd	- password for the account (plain-text). **required**
 *	email	- e-mail address of the user, must be unique. **required**
 *	name	- the user's real first & last name. **required**
 *	alias	- must be unique, if not available will be generated based on login.
 * 	icq	- ICQ IM id (integer)
 *	aim	- AIM IM id.
 * 	yahoo	- Yahoo IM id.
 *	msnm	- MSN IM id.
 * 	jabber	- Jabber IM id.
 * 	affero	- Affero IM id.
 * 	posts_ppg - Host many messages to display per page, will default to POSTS_PER_PAGE.
 * 	time_zone - Time zone, will default to server timezone as specified in admin settings.
 * 	bday	- Birth day YYYYMMDD format, must be an integer.
 * 	last_visit -  Unix Timestamp of last visitation date.
 *	conf_key - 32 byte confirmation key for unconfirmed accounts.
 *	user_image - url to the user's image
 * 	join_date - Account creation date, will default to current time.
 * 	location - City/Province/Country
 *	theme - the ID of the theme to use, if left blank will pick default theme.
 *	occupation - Job
 *	interests - Interestes
 *	referer_id - User id of the user who referred this user.
 *	last_read - Unix timestamp of last "mark all read" will default to current time.
 *	sig - Signature
 *	home_page - Home Page URL
 *	bio - Biography
 *	users_opt - Account settings, consult fud_users.sql inside the sql/ directory for details.
 *	reg_ip - Registration IP, will default to 127.0.0.1
 */
function fud_add_user($vals, &$err)
{
	// check for required fields 
	foreach (array('login','passwd','email','name') as $v) {
		if (empty($vals[$v])) {
			$err = "missing value for a required field {$v}";
			return 0;
		}
	}

	if (strncmp($vals['passwd'], 'md5', 3)) {
		$vals['passwd'] = md5($vals['passwd']);
	} else {
		$vals['passwd'] = substr($vals['passwd'], 3);
	}

	if (empty($vals['alias'])) {
		if (strlen($vals['login']) > $GLOBALS['MAX_LOGIN_SHOW']) {
			$vals['alias'] = substr($vals['login'], 0, $GLOBALS['MAX_LOGIN_SHOW']);
		} else {
			$vals['alias'] = $vals['login'];
		}
		$vals['alias'] = htmlspecialchars($vals['alias']);
	}

	// some fields must be unique, check them
	foreach (array('login','email','alias')	as $v) {
		if (q_singleval("SELECT id FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."users WHERE {$v}='".addslashes($vals[$v])."'")) {
			$err = "value for {$v} must be unique, specified value of {$vals[$v]} already exists.";
			return 0;
		}
	}

	$o2 =& $GLOBALS['FUD_OPT_2'];
	$users_opt = 4|16|32|128|256|512|2048|4096|8192|16384|131072|4194304;
	$theme = q_singleval("SELECT id FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."themes WHERE theme_opt>=2 AND (theme_opt & 2) > 0 LIMIT 1");
	$time_zone =& $GLOBALS['SERVER_TZ'];
	$posts_ppg =& $GLOBALS['POSTS_PER_PAGE'];
	if (!($o2 & 4)) {
		$users_opt ^= 128;
	}
	if (!($o2 & 8)) {
		$users_opt ^= 256;
	}
	if ($o2 & 1) {
		$o2 ^= 1;
	}
	$reg_ip = "127.0.0.1";
	$last_visit = $last_read = $join_date = __request_timestamp__;

	// make sure all fields are set 
	foreach( array('login','alias','passwd','name','email','icq','aim','yahoo','msnm','jabber',
		'affero','posts_ppg','time_zone','bday','last_visit','conf_key','user_image',
		'join_date','location','theme','occupation','interests','referer_id','last_read',
		'sig','home_page','bio','users_opt','reg_ip') as $v) {
		if (empty($vals[$v])) {
			$vals[$v] = isset($$v) ? $$v : '';
		}
	}

	return db_qid("INSERT INTO
			".$GLOBALS['DBHOST_TBL_PREFIX']."users (
				login,
				alias,
				passwd,
				name,
				email,
				icq,
				aim,
				yahoo,
				msnm,
				jabber,
				affero,
				posts_ppg,
				time_zone,
				bday,
				last_visit,
				conf_key,
				user_image,
				join_date,
				location,
				theme,
				occupation,
				interests,
				referer_id,
				last_read,
				sig,
				home_page,
				bio,
				users_opt,
				reg_ip
			) VALUES (
				'".addslashes($vals['login'])."',
				'".addslashes($vals['alias'])."',
				'".$vals['passwd']."',
				'".addslashes($vals['name'])."',
				'".addslashes($vals['email'])."',
				".(int)$vals['icq'].",
				".ssn(urlencode($vals['aim'])).",
				".ssn(urlencode($vals['yahoo'])).",
				".ssn(urlencode($vals['msnm'])).",
				".ssn(htmlspecialchars($vals['jabber'])).",
				".ssn(urlencode($vals['affero'])).",
				".(int)$vals['posts_ppg'].",
				'".addslashes($vals['time_zone'])."',
				".(int)$vals['bday'].",
				".(int)$vals['last_visit'].",
				'".$vals['conf_key']."',
				".ssn(htmlspecialchars($vals['user_image'])).",
				".$vals['join_date'].",
				".ssn($vals['location']).",
				".(int)$vals['theme'].",
				".ssn($vals['occupation']).",
				".ssn($vals['interests']).",
				".(int)$vals['referer_id'].",
				".(int)$vals['last_read'].",
				".ssn($vals['sig']).",
				".ssn(htmlspecialchars($vals['home_page'])).",
				".ssn($vals['bio']).",
				".(int)$vals['users_opt'].",
				".ip2long($vals['reg_ip'])."
			)
		");
}

/* {{{ proto: object fud_add_user($vals, &$err) }}}
 * Returns 1on success, on error returns 0 and populates $err with the error message.
 * Vals is an array of fields found inside the users table (same as for fud_add_user)
 * The specified values inside the vals array should ONLY be the ones you wish to modify
 */
function fud_update_user($uid, $vals, &$err)
{
	$uid = (int) $uid;

	if (!q_singleval("SELECT id FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."users WHERE id=".$uid)) {
		$err = "Invalid user id";
		return 0;
	}

	foreach (array('login','email','alias')	as $v) {
		if (empty($vals[$v])) continue;
	
		if (q_singleval("SELECT id FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."users WHERE {$v}='".addslashes($vals[$v])."' AND id!=".$uid)) {
			$err = "value for {$v} must be unique, specified value of {$vals[$v]} already exists.";
			return 0;
		}
	}

	$qry = "UPDATE ".$GLOBALS['DBHOST_TBL_PREFIX']."users SET ";
	// apply changes
	foreach( array('login','alias','passwd','name','email','icq','aim','yahoo','msnm','jabber',
		'affero','posts_ppg','time_zone','bday','last_visit','conf_key','user_image',
		'join_date','location','theme','occupation','interests','referer_id','last_read',
		'sig','home_page','bio','users_opt','reg_ip') as $v) {
		if (isset($vals[$v])) {
			$qry = "{$v}='".addslashes($vals[$v])."',";
		}
	}
	uq($qry." WHERE id=".$uid);
	return 1;
}

/* {{{ proto: int message_id fud_new_topic(string subject, string body, int mode *, mixed author, int forum 
					    [, string icon [, array attachmet_list ** [, array poll ***]]]) }}}
 * Create a new topic based on the provided information. (* denoted variables explained below)
 *
 * [mode] integer bitmask controlling the following message options, you can 
 * OR (|) options if more then one is needed
 * 1 - show signature
 * 2 - convert emoticons to images
 *
 * [attachmet_list] a simple array with a list of files to attach to the message
 * Ex. array("/vmlinuz", "/home/www/page.html");
 *
 * [poll] an array containing information about the poll to add
 * array(
 *	'title' => 'value' // (string containing the poll's title)
 *	'options' => array("option1", "option2", "...") // (array of poll's options)
 *	'expiry_date' => int // (unix timestamp when to stop allowing votes) **optional**
 *	'max_votes' => int // (maximum number of votes to allow) **optional**
 * )
 */
function fud_new_topic($subject, $body, $mode, $author, $forum, $icon=null, $attach=null, $poll=null)
{
	return _fud_message_post($subject, $body, $mode, $author, $icon, 0, $forum, 0, $attach, $poll);
}

/* {{{ proto: int message_id fud_new_reply(string subject, string body, int mode *, mixed author, int reply_id 
					    [, string icon [, array attachmet_list ** [, array poll ***]]]) }}}
 * Post a reply to a message denoted by reply_id parameter. (* denoted variables explained below)
 *
 * [mode] integer bitmask controlling the following message options, you can 
 * OR (|) options if more then one is needed
 * 1 - show signature
 * 2 - convert emoticons to images
 *
 * [attachmet_list] a simple array with a list of files to attach to the message
 * Ex. array("/vmlinuz", "/home/www/page.html");
 *
 * [poll] an array containing information about the poll to add
 * array(
 *	'title' => 'value' // (string containing the poll's title)
 *	'options' => array("option1", "option2", "...") // (array of poll's options)
 *	'expiry_date' => int // (unix timestamp when to stop allowing votes) **optional**
 *	'max_votes' => int // (maximum number of votes to allow) **optional**
 * )
 */
function fud_new_reply($subject, $body, $mode, $author, $rep_id, $icon=null, $attach=null, $poll=null)
{
	$forum = q_singleval("SELECT t.forum_id FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."msg m INNER JOIN ".$GLOBALS['DBHOST_TBL_PREFIX']."thread t ON t.id=m.thread_id WHERE m.id=".$rep_id);
	return _fud_message_post($subject, $body, $mode, $author, $icon, 0, $forum, $rep_id, $attach, $poll);
}

/* {{{ proto: int message_id fud_update_message(string subject, string body, int mode *, mixed author, int message_id 
					    [, string icon [, array attachmet_list ** [, array poll ***]]]) }}}
 * Modify an existing message denoted by message_id parameter. (* denoted variables explained below)
 *
 * [mode] integer bitmask controlling the following message options, you can 
 * OR (|) options if more then one is needed
 * 1 - show signature
 * 2 - convert emoticons to images
 *
 * [attachmet_list] a simple array with a list of files to attach to the message
 * Ex. array("/vmlinuz", "/home/www/page.html");
 *
 * [poll] an array containing information about the poll to add
 * array(
 *	'title' => 'value' // (string containing the poll's title)
 *	'options' => array("option1", "option2", "...") // (array of poll's options)
 *	'expiry_date' => int // (unix timestamp when to stop allowing votes) **optional**
 *	'max_votes' => int // (maximum number of votes to allow) **optional**
 * )
 */
function fud_update_message($subject, $body, $mode, $author, $mid, $icon=null, $attach=null, $poll=null)
{
	$forum = q_singleval("SELECT t.forum_id FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."msg m INNER JOIN ".$GLOBALS['DBHOST_TBL_PREFIX']."thread t ON t.id=m.thread_id WHERE m.id=".$mid);
	return _fud_message_post($subject, $body, $mode, $author, $icon, $mid, $forum, 0, $attach, $poll);
}

/* {{{ proto: void fud_fetch_user(mixed arg) }}}
 * This function deletes message(s) specified by arg.
 */
function fud_delete_msg($arg)
{
	if (!$arg) {
		return;
	}

	if (is_numeric($arg)) {
		$arg = array($arg);
	}

	fud_use('imsg_edt.inc');
	fud_use('ipoll.inc');
	fud_use('th_adm.inc');

	$bak = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : NULL;
	define('forum_debug', 1);
	unset($_SERVER['REMOTE_ADDR']);

	fud_use('users.inc');

	foreach ($arg as $mid) {
		$m = new fud_msg_edit;
		$m->delete(true, $mid);
	}

	$_SERVER['REMOTE_ADDR'] = $bak;
}

/* {{{ proto: void fud_delete_topic(mixed arg) }}}
 * This function deletes topic(s) specified by arg.
 */
function fud_delete_topic($arg)
{
	$ent = _fud_simple_fetch_query($arg, "SELECT root_msg_id FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."thread WHERE id IN({ARG})");
	if (!$ent) {
		return;
	} else if (is_object($ent)) {
		fud_delete_msg($ent->root_msg_id);
	} else if (is_array($ent)) {
		$del = array();
		foreach ($ent as $o) {
			$del[] = $o->root_msg_id;
		}
		fud_delete_msg($del);
	}
}

/* {{{ proto: void fud_delete_poll(mixed arg) }}}
 * This function deletes poll(s) specified by arg.
 */
function fud_delete_poll($arg)
{
	$ent = _fud_simple_fetch_query($arg, "SELECT id FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."poll WHERE id IN({ARG})");

	if (!$ent) {
		return;
	}

	fud_use('ipoll.inc');	

	if (!is_array($ent)) {
		poll_delete($ent->id);
	} else {
		foreach ($ent as $p) {
			poll_delete($p->id);
		}
	}
}

/* {{{ proto: void fud_delete_attachment(mixed arg) }}}
 * This function deletes attachment(s) specified by arg.
 */
function fud_delete_attachment($arg)
{
	$data = _fud_simple_fetch_query($arg, "SELECT id, message_id FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."attach WHERE id IN({ARG})");

	if (!$data) {
		return;
	} else if (!is_array($data)) {
		$data = array($data);
	}

	fud_use('attach.inc');

	foreach ($data as $at) {
		q("DELETE FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."attach WHERE id=".$at->id);
		$atl = attach_rebuild_cache($at->message_id);
		q("UPDATE ".$GLOBALS['DBHOST_TBL_PREFIX']."msg SET attach_cnt=attach_cnt-1, attach_cache='".addslashes(@serialize($atl))."' WHERE id=".$at->message_id);
	}
}

/* API FUNCTIONS END HERE */
/* INTERNAL FUNCTIONS, DO NOT TOUCH */

function _fud_msg_multi($arg, $query)
{
	$arg = is_numeric($arg) ? array($arg) : (int) $arg;
	$ids = array();
	$r = uq(str_replace('{ARG}', implode(',', $arg), $query));
	while ($row = db_rowarr($r)) {
		$ids[] = $row[0];
	}
	if (!count($ids)) {
		return FALSE;
	}

	return fud_fetch_msg($ids);
}

function _fud_simple_fetch_query($arg, $query)
{
        if ($arg) {    
		$arg = is_numeric($arg) ? array($arg) : $arg;
	} else {
		$arg = array();
	}
	$result = array();

	$r = uq(str_replace('{ARG}', implode(',', $arg), $query));
	while ($row = db_rowobj($r)) {
		$result[] = $row;
	}
	unset($r);

	if ($arg && count($result) != count($arg)) {
		return FALSE;
	} else {
		if (count($result) == 1) {
			return array_pop($result);
		} else {
			return $result;
		}
	}
}

function _fud_decode_forum($data)
{
	if (is_array($data)) {
		foreach ($data as $k => $v) {
			unset($data[$k]->forum_opt, $data[$k]->message_threshold, $data[$k]->view_order);
			$data[$k]->moderators = @unserialize($data[$k]->moderators);
		}
	} else {
		unset($data->forum_opt, $data->message_threshold, $data->view_order);
		$data->moderators = @unserialize($data->moderators);
	}

	return $data;
}

function _fud_add_poll($poll, $forum_id, $forum_opt, $mode, $uid)
{	
	if (empty($poll['title']) || empty($poll['options']) || !is_array($poll['options'])) {
		return;
	}

	$exp_date = !empty($poll['expiry_date']) ? (int) $poll['expiry_date'] : 0;
	$max_v = !empty($poll['max_votes']) ? (int) $poll['max_votes'] : 0;

	$pl_id =  poll_add($poll['title'], $max_v, $exp_date, $uid);

	foreach ($poll['options'] as $opt) {
		if ($forum_opt & 16) {
			$opt = tags_to_html($opt, 1);
		} else if ($forum_opt & 8) {
			$opt = nl2br(htmlspecialchars($opt));
		}
		if ($mode & 2) {
			$opt = smiley_to_post($opt);
		}	

		poll_opt_add($opt, $pl_id);
	}

	return $pl_id;
}

function _fud_message_post($subject, $body, $mode, $author, $icon, $id, $forum, $rep_id=0, $attach=null, $poll=null)
{
	fud_use('imsg_edt.inc');
	fud_use('imsg.inc');
	fud_use('post_proc.inc');
	fud_use('smiley.inc');
	fud_use('th_adm.inc');
	fud_use('iemail.inc');
	fud_use('isearch.inc');
	fud_use('replace.inc');
	fud_use('rev_fmt.inc');
	fud_use('wordwrap.inc');
	fud_use('ipoll.inc');
	fud_use('forum.inc');
	fud_use('fileio.inc');
	fud_use('th.inc');

	$bak = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : NULL;
	define('forum_debug', 1);
	unset($_SERVER['REMOTE_ADDR']);

	fud_use('users.inc');
	
	// do not resolve host
	if ($GLOBALS['FUD_OPT_1'] & 268435456) {
		$GLOBALS['FUD_OPT_1'] ^= 268435456;
	}

	list($forum_opt, $message_threshold) = db_saq("SELECT forum_opt, message_threshold FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."forum WHERE id=".$forum);
	if ($rep_id) {
		$th_id = q_singleval("SELECT thread_id FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."msg WHERE id=".$rep_id);
	}

	$msg = new fud_msg_edit();
	$msg->poster_id = is_numeric($author) ? (int) $author : (int) q_singleval("SELECT id FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."users WHERE login='".addslashes($author)."'");
	$msg->subject = $subject;
	$msg->body = apply_custom_replace($body);
	$msg->icon = $icon;
	$msg->thread_id = $id ? q_singleval("SELECT thread_id FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."msg WHERE id=".$id) : 0;
	$msg->msg_opt = $mode;

	if ($forum_opt & 16) {
		$msg->body = tags_to_html($msg->body, 1);
	} else if ($forum_opt & 8) {
		$msg->body = nl2br(char_fix(htmlspecialchars($msg->body)));
	}

	if ($mode & 2) {
		$msg->body = smiley_to_post($msg->body);
	}

	fud_wordwrap($msg->body);
	$msg->subject = char_fix(htmlspecialchars(apply_custom_replace($msg->subject)));

	if ($attach && is_array($attach)) {
		fud_use('attach.inc');

		foreach ($attach as $file) {
			$file = realpath($file);

			if (!@file_exists($file)) {
				continue;
			}			

			$at['name'] = basename($file);
			$at['size'] = filesize($file);
			$at['tmp_name'] = tempnam($GLOBALS['TMP'], 'fuda_');
			copy($file, $at['tmp_name']);

			$val = attach_add($at, $msg->poster_id);
			$attach_list[$val] = $val;
		}

		$msg->attach_cnt = (int) @count($attach_list);
	}

	if ($poll && is_array($poll)) {
		$msg->poll_id = _fud_add_poll($poll, $forum, $forum_opt, $mode, $msg->poster_id);
	}

	if (!$rep_id && !$id) {
		$create_thread = 1;
		$msg->add($forum, $message_threshold, $forum_opt, 64|4096, false);
	} else if ($rep_id && !$id) {
		$msg->thread_id = $th_id;
		$msg->add_reply($rep_id, $th_id, 64|4096, false);
	} else if ($id) {
		$msg->id = $id;
		$msg->post_stamp = time();
		$msg->sync($msg->poster_id, $forum, $message_threshold, 64|4096);
	}

	if (isset($attach_list)) {
		attach_finalize($attach_list, $msg->id);
	}

	$msg->approve($msg->id);

	$_SERVER['REMOTE_ADDR'] = $bak;

	return $msg->id;
}

fud_use('err.inc');
fud_use('db.inc');
?>