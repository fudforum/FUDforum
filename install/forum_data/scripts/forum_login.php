<?php
/* --------- CONFIG OPTIONS START (required) ----------- */
$GLOBALS['PATH_TO_FUD_FORUM_GLOBALS_PHP'] = './GLOBALS.php';
// This value is usually $DATA_DIR/include/theme/default/db.inc, if this is the case
// leave the value empty.
$GLOBALS['PATH_TO_FUD_FORUM_DB_INC'] = '';
/* --------- CONFIG OPTIONS END (required) ----------- */

/* The following function will take the forum's user id and log the user into the forum
   On successful execution the return value will be the session id for the user.
   Upon failure the return value will be NULL, this can only happen if invalid user id is specified.
*/
function external_get_user_by_auth($login, $passwd)
{
	__fud_login_common(1);

	return q_singleval("SELECT id FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."users WHERE login="._esc($login)." AND passwd='".md5($passwd)."'");
}

/*
 * Log user in on the forum.
 */
function external_fud_login($user_id)
{
	if (($user_id = (int) $user_id) < 2 || !__fud_login_common(0, $user_id)) {
		return;
	}

	/* Create session. */
	q("DELETE FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."ses WHERE user_id=".$user_id);
	$sys_id = __ses_make_sysid(($GLOBALS['FUD_OPT_2'] & 256), ($GLOBALS['FUD_OPT_3'] & 16));
	do {
		$ses_id = md5($user_id . time() . getmypid());
	} while (!($id = db_li("INSERT INTO ".$GLOBALS['DBHOST_TBL_PREFIX']."ses (ses_id, time_sec, sys_id, user_id) VALUES ('".$ses_id."', ".time().", '".$sys_id."', ".$user_id.")", $ef, 1)));
	setcookie($GLOBALS['COOKIE_NAME'], $ses_id, time()+$GLOBALS['COOKIE_TIMEOUT'], $GLOBALS['COOKIE_PATH'], $GLOBALS['COOKIE_DOMAIN']);

	return $ses_id;
}

/*
 * Show what the user is busy doing in the forum's action list.
 */
function external_fud_status($action='Busy somewhere outside of the forum')
{
	__fud_login_common(1);
	$ses_id = $_COOKIE[$GLOBALS['COOKIE_NAME']];
	if (!empty($ses_id)) {
		$sys_id = __ses_make_sysid(($GLOBALS['FUD_OPT_2'] & 256), ($GLOBALS['FUD_OPT_3'] & 16));
		q('UPDATE '.$GLOBALS['DBHOST_TBL_PREFIX'].'ses SET sys_id=\''.$sys_id.'\', time_sec='.__request_timestamp__.', action='._esc($action).' WHERE ses_id=\''.$ses_id.'\'');
	}
}

/*
 * Log user out of the forum.
 */
function external_fud_logout($user_id)
{
	if (($user_id = (int) $user_id) < 2 || !__fud_login_common(0, $user_id)) {
		return;
	}

	// Remove session from database.
	q("DELETE FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."ses WHERE user_id=".$user_id);
	// Trash the cookie.
	setcookie($GLOBALS['COOKIE_NAME'], '', 0, $GLOBALS['COOKIE_PATH'], $GLOBALS['COOKIE_DOMAIN']);
}

/*
 * Default error handler, in case user doesn't spesified his/her own.
 */
if (!function_exists('fud_sql_error_handler'))
{
	function fud_sql_error_handler($query, $error_string, $error_number, $server_version)
	{
		exit("Query {$query} failed due to: {$error_string}");
	}
}

function __fud_login_common($skip=0, $user_id=0)
{
	/* load forum config */
	if (!isset($GLOBALS['FUD_OPT_1'])) {
		$data = file_get_contents($GLOBALS['PATH_TO_FUD_FORUM_GLOBALS_PHP']);
		eval(str_replace('<?php', '', substr_replace($data, '', strpos($data, 'require'))));

		/* db.inc needs certain vars inside the global scope to work, so we export them */
		foreach (array('SERVER_TZ', 'MAX_LOGIN_SHOW', 'POSTS_PER_PAGE','WWW_ROOT', 'LOGEDIN_TIMEOUT', 'TMP','COOKIE_DOMAIN','COOKIE_NAME','COOKIE_TIMEOUT','COOKIE_PATH','FUD_OPT_1', 'FUD_OPT_3', 'FUD_OPT_2', 'DBHOST', 'DBHOST_USER', 'DBHOST_PASSWORD', 'DBHOST_DBNAME','DATA_DIR','INCLUDE','DBHOST_TBL_PREFIX') as $v) {
			$GLOBALS[$v] = $$v;
		}
	}

	if (!$GLOBALS['PATH_TO_FUD_FORUM_DB_INC']) {
		require_once $GLOBALS['INCLUDE'] . 'theme/default/db.inc';
	} else {
		require_once $GLOBALS['PATH_TO_FUD_FORUM_DB_INC'];
	}

	if ($skip) {
		return;
	}

	/* validate user */
	if (!q_singleval("SELECT id FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."users WHERE id=".$user_id)) {
		return;
	}

	return 1;
}

/* internal functions, do not modify */
function __ses_make_sysid($a, $b)
{
	if ($a) {
		return;
	}

	$keys = array('HTTP_USER_AGENT', 'SERVER_PROTOCOL', 'HTTP_ACCEPT_CHARSET', 'HTTP_ACCEPT_ENCODING', 'HTTP_ACCEPT_LANGUAGE');
	if ($b && strpos($_SERVER['HTTP_USER_AGENT'], 'AOL') === false) {
		$keys[] = 'HTTP_X_FORWARDED_FOR';
		$keys[] = 'REMOTE_ADDR';
	}
	$pfx = '';
	foreach ($keys as $v) {
		if (isset($_SERVER[$v])) {
			$pfx .= $_SERVER[$v];
		}
	}
	return md5($pfx);
}
?>
