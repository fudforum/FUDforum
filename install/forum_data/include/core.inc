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

$FORUM_VERSION = '3.0.2';

ignore_user_abort('1');
@set_magic_quotes_runtime(0);	// Deprecated as of PHP 5.3.0.

$GLOBALS['RSS'] = $GLOBALS['TITLE_EXTRA'] = '';
$GLOBALS['is_post'] = !empty($_POST);

register_shutdown_function('release_db_lock');
function release_db_lock()
{
	if (connection_status() && defined('fud_sql_lnk') && db_locked()) {
		db_unlock();
	}
}

if (isset($_REQUEST['GLOBALS']) || isset($_FILES['GLOBALS'])) {
	exit;	// GLOBALS overwrite attempt.
}

// Deprecated as of PHP 5.3.0.
if (get_magic_quotes_gpc()) {
	$in = array(&$_GET, &$_POST, &$_COOKIE);
	while (list($k,$v) = each($in)) {
		foreach ($v as $key => $val) {
			if (!is_array($val)) {
				$in[$k][$key] = stripslashes($val);
				continue;
			}
			$in[] =& $in[$k][$key];
		}
	}
	unset($in);

	if (!empty($_FILES)) {
		foreach ($_FILES as $k => $v) {
			$_FILES[$k]['name'] = stripslashes((string)$v['name']);
		}
	}
}

// Debug mode.
if ($GLOBALS['FUD_OPT_3'] & 1073741824) {
	set_error_handler('fud_php_error_handler');
	define('forum_debug', 1);
	define('fud_query_stats', 1);
}

// Load and initialize the plugin handler.
if ($GLOBALS['FUD_OPT_3'] & 4194304) {	// Plugins enabled.
	include_once $GLOBALS['INCLUDE'] .'plugins.inc';
	plugin_activate_all_enbled();
}

define('__request_timestamp__', time());
define('__request_timestamp_exact__', microtime(true)); // For page stats.

function fud_use($file, $static=0)
{
	if ($static) {
		include_once $GLOBALS['INCLUDE'] . $file;
		return;
	}
	if (!defined('fud_theme')) {
		define('fud_theme', 'theme/default/');
	}
	include_once $GLOBALS['INCLUDE'] . fud_theme . $file;
}

function get_random_value($bitlength=32)
{
	$n = round($bitlength/32);
	$v = '';
	while (--$n > -1) {
		$v .= mt_rand();
	}
	return $v;
}

function get_ip()
{
	if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
		$ip = strtok($_SERVER['HTTP_X_FORWARDED_FOR'], ',');
		do {
			$ip = str_replace('::ffff:', '', $ip);
			if (($lip = ip2long($ip)) === -1) {
				continue;
			}
			/* Some disallowed ip ranges, that are normally used for private networks:
			 * 10.0.0.0 - 10.255.255.255
			 * 172.16.0.0 - 172.31.255.255
			 * 192.168.0.0 - 192.168.255.255
			 * 127.0.0.1, 255.255.255.255, 0.0.0.0
			*/
			if (!$lip || (($lip >= 167772160) && ($lip <= 184549375)) || (($lip >= -1408237568) && ($lip <= -1407188993)) || (($lip >= -1062731776) && ($lip <= -1062666241)) || ($lip == 2130706433)) {
				continue;
			}
			return long2ip($lip);
		} while (($ip = strtok(',')));
	}

	$ip = isset($_SERVER['HTTP_CLIENT_IP']) ? $_SERVER['HTTP_CLIENT_IP'] : (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0');
	$ip = str_replace('::ffff:', '', $ip);
	return (!empty($ip) ? $ip : '0.0.0.0');
}

function ssn($str)
{
	if ($str === null || $str === '') {
		return 'NULL';
	}
	return _esc($str);
}

function char_fix($str)
{
        if (strpos($str, '&amp;#') === false) {
		return $str;
	}
        return preg_replace('!&amp;#([0-9]+);!', '&#\1;', $str);
}

function __ffilesize($fp)
{
	$st = fstat($fp);
	return (isset($st['size']) ? $st['size'] : $st[7]);
}

function __fud_cache($t)
{
	if ($GLOBALS['FUD_OPT_3'] & 65536) {	// DISABLE_ANON_CACHE
		return 0;
	}
	$mod = gmdate('D, d M Y H:i:s', $t) .' GMT';

	/* Send caching headers. */
	header('Last-Modified: '. $mod);
	if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && !isset($_SERVER['HTTP_RANGE']) && $_SERVER['HTTP_IF_MODIFIED_SINCE'] == $mod) {
		header('HTTP/1.1 304 Not Modified');
		header('Status: 304 Not Modified');
		return 1;
	}
	return 0;
}

function fud_logerror($error_msg, $logfile) {
	if (defined('plugins')) {
		// Alter, log or block error message.
		$error_msg = plugin_call_hook('LOGERR', $error_msg);
		if (empty($error_msg)) {
			return;
		}
	}

	$pfx = sprintf('?%-10d?%-10d?', strlen($error_msg) + 1, __request_timestamp__);
	ini_set('log_errors_max_len', 0);
	if (!error_log($pfx . $error_msg ."\n", 3, $GLOBALS['ERROR_PATH'] . $logfile)) {
		echo '<hr><b>UNABLE TO WRITE TO LOG FILE:</b><ul>';
		echo $error_msg;
		echo '</ul><hr>';
	}
}

function fud_sql_error_handler($query, $error_string, $error_number, $db_version)
{
	if (db_locked() && !in_array($query, array('COMMIT','COMMIT WORK','UNLOCK TABLES'))) {
		db_unlock();
	}

	$_SERVER['PATH_TRANSLATED'] = '';
	foreach (debug_backtrace() as $v) {
		$_SERVER['PATH_TRANSLATED'] .= "{$v['file']}:{$v['line']}<br />\n";
	}

	$error_msg = '('. $_SERVER['PATH_TRANSLATED'] .') '. $error_number .': '. $error_string ."<br />\n";
	$error_msg .= 'Query: '. htmlspecialchars($query) ."<br />\n";
	if (!empty($_GET)) {
		$error_msg .= '_GET: ';
		if (count($_GET, 1) < 100) {
			$error_msg .= htmlspecialchars(var_export($_GET, 1));
		} else {
			$error_msg .= 'Too many vars to list: '. count($_GET, 1);
		}
		$error_msg .= "<br />\n";
	}
	if (!empty($_POST)) {
		$error_msg .= '_POST: ';
		if (count($_POST, 1) < 100) {
			$error_msg .= htmlspecialchars(var_export($_POST, 1));
		} else {
			$error_msg .= 'Too many vars: '. count($_POST, 1);
		}
		$error_msg .= "<br />\n";
	}
	$error_msg .= 'Database version: '. $db_version ."<br />\n";
	if (isset($_SERVER['HTTP_REFERER'])) {
		$error_msg .= '[Referring Page] '. htmlspecialchars($_SERVER['HTTP_REFERER']) ."<br />\n";
	}

	fud_logerror($error_msg, 'sql_errors');

	if (isset($GLOBALS['usr']->users_opt) && $GLOBALS['usr']->users_opt & 1048576) { // Admin user.
		// ACP may try to catch DB errors, e.g. temp table check in admglobal.php.
		throw new Exception($error_msg);
	} elseif (defined('forum_debug')) {	// Debug mode.
		echo '<hr>'. $error_msg .'<hr>';
	} else {
		trigger_error('SQL Error has occurred, please contact the <a href="mailto:'. $GLOBALS['ADMIN_EMAIL'] .'?subject=SQL%20Error">administrator</a> of the forum and have them review the forum&#39;s SQL query log', E_USER_ERROR);
		if (ini_get('display_errors') !== 1) {
			exit('SQL Error has occurred, please contact the <a href="mailto:'. $GLOBALS['ADMIN_EMAIL'] .'?subject=SQL%20Error">administrator</a> of the forum and have them review the forum&#39;s SQL query log');
		}
	}
	exit;
}

function fud_php_error_handler($no, $str, $file, $line, $ctx)
{
	if (!(error_reporting() & $no)) {
		return;		// Errors that should be ignored.
	}
	fud_logerror('PHP Error '. $no .': '. $str .' in '. $file .' on line '. $line, 'fud_errors');
	if (isset($GLOBALS['usr']->users_opt) && $GLOBALS['usr']->users_opt & 1048576) { // Admin user.
		echo '<pre>';
		echo 'no   : '. $no   ."\n";
		echo 'str  : '. $str  ."\n";
		echo 'file : '. $file ."\n";
		echo 'line : '. $line ."\n";
		echo 'ctx  : ';
		print_r($ctx);
		echo '</pre>';
		die('PHP ERROR TO FIX!');
	}
}

/* timezone_identifiers_list() may not exist in PHP 5.1.*/
if(!function_exists('timezone_identifiers_list')){
	function timezone_identifiers_list(){
		return array(
'Africa/Abidjan', 'Africa/Accra', 'Africa/Addis_Ababa', 'Africa/Algiers', 'Africa/Asmera',
'Africa/Bamako', 'Africa/Bangui', 'Africa/Banjul', 'Africa/Bissau', 'Africa/Blantyre',
'Africa/Brazzaville', 'Africa/Bujumbura', 'Africa/Cairo', 'Africa/Casablanca', 'Africa/Ceuta',
'Africa/Conakry', 'Africa/Dakar', 'Africa/Dar_es_Salaam', 'Africa/Djibouti', 'Africa/Douala',
'Africa/El_Aaiun', 'Africa/Freetown', 'Africa/Gaborone', 'Africa/Harare', 'Africa/Johannesburg',
'Africa/Kampala', 'Africa/Khartoum', 'Africa/Kigali', 'Africa/Kinshasa', 'Africa/Lagos',
'Africa/Libreville', 'Africa/Lome', 'Africa/Luanda', 'Africa/Lubumbashi', 'Africa/Lusaka',
'Africa/Malabo', 'Africa/Maputo', 'Africa/Maseru', 'Africa/Mbabane', 'Africa/Mogadishu',
'Africa/Monrovia', 'Africa/Nairobi', 'Africa/Ndjamena', 'Africa/Niamey', 'Africa/Nouakchott',
'Africa/Ouagadougou', 'Africa/Porto-Novo', 'Africa/Sao_Tome', 'Africa/Timbuktu', 'Africa/Tripoli',
'Africa/Tunis', 'Africa/Windhoek', 'America/Adak', 'America/Anchorage', 'America/Anguilla',
'America/Antigua', 'America/Araguaina', 'America/Argentina/Buenos_Aires', 'America/Argentina/Catamarca',
'America/Argentina/ComodRivadavia', 'America/Argentina/Cordoba', 'America/Argentina/Jujuy',
'America/Argentina/La_Rioja', 'America/Argentina/Mendoza', 'America/Argentina/Rio_Gallegos',
'America/Argentina/San_Juan', 'America/Argentina/Tucuman', 'America/Argentina/Ushuaia', 'America/Aruba',
'America/Asuncion', 'America/Atikokan', 'America/Atka', 'America/Bahia', 'America/Barbados', 'America/Belem',
'America/Belize', 'America/Blanc-Sablon', 'America/Boa_Vista', 'America/Bogota', 'America/Boise',
'America/Buenos_Aires', 'America/Cambridge_Bay', 'America/Campo_Grande', 'America/Cancun', 'America/Caracas',
'America/Catamarca', 'America/Cayenne', 'America/Cayman', 'America/Chicago', 'America/Chihuahua',
'America/Coral_Harbour', 'America/Cordoba', 'America/Costa_Rica', 'America/Cuiaba', 'America/Curacao',
'America/Danmarkshavn', 'America/Dawson', 'America/Dawson_Creek', 'America/Denver', 'America/Detroit',
'America/Dominica', 'America/Edmonton', 'America/Eirunepe', 'America/El_Salvador', 'America/Ensenada',
'America/Fort_Wayne', 'America/Fortaleza', 'America/Glace_Bay', 'America/Godthab', 'America/Goose_Bay',
'America/Grand_Turk', 'America/Grenada', 'America/Guadeloupe', 'America/Guatemala', 'America/Guayaquil',
'America/Guyana', 'America/Halifax', 'America/Havana', 'America/Hermosillo', 'America/Indiana/Indianapolis',
'America/Indiana/Knox', 'America/Indiana/Marengo', 'America/Indiana/Petersburg', 'America/Indiana/Vevay',
'America/Indiana/Vincennes', 'America/Indianapolis', 'America/Inuvik', 'America/Iqaluit', 'America/Jamaica',
'America/Jujuy', 'America/Juneau', 'America/Kentucky/Louisville', 'America/Kentucky/Monticello',
'America/Knox_IN', 'America/La_Paz', 'America/Lima', 'America/Los_Angeles', 'America/Louisville',
'America/Maceio', 'America/Managua', 'America/Manaus', 'America/Martinique', 'America/Mazatlan',
'America/Mendoza', 'America/Menominee', 'America/Merida', 'America/Mexico_City', 'America/Miquelon',
'America/Moncton', 'America/Monterrey', 'America/Montevideo', 'America/Montreal', 'America/Montserrat',
'America/Nassau', 'America/New_York', 'America/Nipigon', 'America/Nome', 'America/Noronha',
'America/North_Dakota/Center', 'America/North_Dakota/New_Salem', 'America/Panama', 'America/Pangnirtung',
'America/Paramaribo', 'America/Phoenix', 'America/Port-au-Prince', 'America/Port_of_Spain', 'America/Porto_Acre',
'America/Porto_Velho', 'America/Puerto_Rico', 'America/Rainy_River', 'America/Rankin_Inlet', 'America/Recife',
'America/Regina', 'America/Rio_Branco', 'America/Rosario', 'America/Santiago', 'America/Santo_Domingo',
'America/Sao_Paulo', 'America/Scoresbysund', 'America/Shiprock', 'America/St_Johns', 'America/St_Kitts',
'America/St_Lucia', 'America/St_Thomas', 'America/St_Vincent', 'America/Swift_Current', 'America/Tegucigalpa',
'America/Thule', 'America/Thunder_Bay', 'America/Tijuana', 'America/Toronto', 'America/Tortola',
'America/Vancouver', 'America/Virgin', 'America/Whitehorse', 'America/Winnipeg', 'America/Yakutat',
'America/Yellowknife', 'Antarctica/Casey', 'Antarctica/Davis', 'Antarctica/DumontDUrville', 'Antarctica/Mawson',
'Antarctica/McMurdo', 'Antarctica/Palmer', 'Antarctica/Rothera', 'Antarctica/South_Pole', 'Antarctica/Syowa',
'Antarctica/Vostok', 'Arctic/Longyearbyen', 'Asia/Aden', 'Asia/Almaty', 'Asia/Amman', 'Asia/Anadyr', 'Asia/Aqtau',
'Asia/Aqtobe', 'Asia/Ashgabat', 'Asia/Ashkhabad', 'Asia/Baghdad', 'Asia/Bahrain', 'Asia/Baku', 'Asia/Bangkok',
'Asia/Beirut', 'Asia/Bishkek', 'Asia/Brunei', 'Asia/Calcutta', 'Asia/Choibalsan', 'Asia/Chongqing',
'Asia/Chungking', 'Asia/Colombo', 'Asia/Dacca', 'Asia/Damascus', 'Asia/Dhaka', 'Asia/Dili', 'Asia/Dubai',
'Asia/Dushanbe', 'Asia/Gaza', 'Asia/Harbin', 'Asia/Hong_Kong', 'Asia/Hovd', 'Asia/Irkutsk', 'Asia/Istanbul',
'Asia/Jakarta', 'Asia/Jayapura', 'Asia/Jerusalem', 'Asia/Kabul', 'Asia/Kamchatka', 'Asia/Karachi', 'Asia/Kashgar',
'Asia/Katmandu', 'Asia/Krasnoyarsk', 'Asia/Kuala_Lumpur', 'Asia/Kuching', 'Asia/Kuwait', 'Asia/Macao',
'Asia/Macau', 'Asia/Magadan', 'Asia/Makassar', 'Asia/Manila', 'Asia/Muscat', 'Asia/Nicosia', 'Asia/Novosibirsk',
'Asia/Omsk', 'Asia/Oral', 'Asia/Phnom_Penh', 'Asia/Pontianak', 'Asia/Pyongyang', 'Asia/Qatar', 'Asia/Qyzylorda',
'Asia/Rangoon', 'Asia/Riyadh', 'Asia/Saigon', 'Asia/Sakhalin', 'Asia/Samarkand', 'Asia/Seoul', 'Asia/Shanghai',
'Asia/Singapore', 'Asia/Taipei', 'Asia/Tashkent', 'Asia/Tbilisi', 'Asia/Tehran', 'Asia/Tel_Aviv', 'Asia/Thimbu',
'Asia/Thimphu', 'Asia/Tokyo', 'Asia/Ujung_Pandang', 'Asia/Ulaanbaatar', 'Asia/Ulan_Bator', 'Asia/Urumqi',
'Asia/Vientiane', 'Asia/Vladivostok', 'Asia/Yakutsk', 'Asia/Yekaterinburg', 'Asia/Yerevan', 'Atlantic/Azores',
'Atlantic/Bermuda', 'Atlantic/Canary', 'Atlantic/Cape_Verde', 'Atlantic/Faeroe', 'Atlantic/Jan_Mayen',
'Atlantic/Madeira', 'Atlantic/Reykjavik', 'Atlantic/South_Georgia', 'Atlantic/St_Helena', 'Atlantic/Stanley',
'Australia/ACT', 'Australia/Adelaide', 'Australia/Brisbane', 'Australia/Broken_Hill', 'Australia/Canberra',
'Australia/Currie', 'Australia/Darwin', 'Australia/Hobart', 'Australia/LHI', 'Australia/Lindeman',
'Australia/Lord_Howe', 'Australia/Melbourne', 'Australia/North', 'Australia/NSW', 'Australia/Perth',
'Australia/Queensland', 'Australia/South', 'Australia/Sydney', 'Australia/Tasmania', 'Australia/Victoria',
'Australia/West', 'Australia/Yancowinna', 'Brazil/Acre', 'Brazil/DeNoronha', 'Brazil/East', 'Brazil/West',
'Canada/Atlantic', 'Canada/Central', 'Canada/East-Saskatchewan', 'Canada/Eastern', 'Canada/Mountain',
'Canada/Newfoundland', 'Canada/Pacific', 'Canada/Saskatchewan', 'Canada/Yukon', 'CET', 'Chile/Continental',
'Chile/EasterIsland', 'CST6CDT', 'Cuba', 'EET', 'Egypt', 'Eire', 'EST', 'EST5EDT', 'Etc/GMT', 'Etc/GMT+0',
'Etc/GMT+1', 'Etc/GMT+10', 'Etc/GMT+11', 'Etc/GMT+12', 'Etc/GMT+2', 'Etc/GMT+3', 'Etc/GMT+4', 'Etc/GMT+5',
'Etc/GMT+6', 'Etc/GMT+7', 'Etc/GMT+8', 'Etc/GMT+9', 'Etc/GMT-0', 'Etc/GMT-1', 'Etc/GMT-10', 'Etc/GMT-11',
'Etc/GMT-12', 'Etc/GMT-13', 'Etc/GMT-14', 'Etc/GMT-2', 'Etc/GMT-3', 'Etc/GMT-4', 'Etc/GMT-5', 'Etc/GMT-6',
'Etc/GMT-7', 'Etc/GMT-8', 'Etc/GMT-9', 'Etc/GMT0', 'Etc/Greenwich', 'Etc/UCT', 'Etc/Universal', 'Etc/UTC',
'Etc/Zulu', 'Europe/Amsterdam', 'Europe/Andorra', 'Europe/Athens', 'Europe/Belfast', 'Europe/Belgrade',
'Europe/Berlin', 'Europe/Bratislava', 'Europe/Brussels', 'Europe/Bucharest', 'Europe/Budapest', 'Europe/Chisinau',
'Europe/Copenhagen', 'Europe/Dublin', 'Europe/Gibraltar', 'Europe/Guernsey', 'Europe/Helsinki',
'Europe/Isle_of_Man', 'Europe/Istanbul', 'Europe/Jersey', 'Europe/Kaliningrad', 'Europe/Kiev', 'Europe/Lisbon',
'Europe/Ljubljana', 'Europe/London', 'Europe/Luxembourg', 'Europe/Madrid', 'Europe/Malta', 'Europe/Mariehamn',
'Europe/Minsk', 'Europe/Monaco', 'Europe/Moscow', 'Europe/Nicosia', 'Europe/Oslo', 'Europe/Paris',
'Europe/Podgorica', 'Europe/Prague', 'Europe/Riga', 'Europe/Rome', 'Europe/Samara', 'Europe/San_Marino',
'Europe/Sarajevo', 'Europe/Simferopol', 'Europe/Skopje', 'Europe/Sofia', 'Europe/Stockholm', 'Europe/Tallinn',
'Europe/Tirane', 'Europe/Tiraspol', 'Europe/Uzhgorod', 'Europe/Vaduz', 'Europe/Vatican', 'Europe/Vienna',
'Europe/Vilnius', 'Europe/Volgograd', 'Europe/Warsaw', 'Europe/Zagreb', 'Europe/Zaporozhye', 'Europe/Zurich',
'Factory', 'GB', 'GB-Eire', 'GMT', 'GMT+0', 'GMT-0', 'GMT0', 'Greenwich', 'Hongkong', 'HST', 'Iceland',
'Indian/Antananarivo', 'Indian/Chagos', 'Indian/Christmas', 'Indian/Cocos', 'Indian/Comoro', 'Indian/Kerguelen',
'Indian/Mahe', 'Indian/Maldives', 'Indian/Mauritius', 'Indian/Mayotte', 'Indian/Reunion', 'Iran', 'Israel',
'Jamaica', 'Japan', 'Kwajalein', 'Libya', 'MET', 'Mexico/BajaNorte', 'Mexico/BajaSur', 'Mexico/General', 'MST',
'MST7MDT', 'Navajo', 'NZ', 'NZ-CHAT', 'Pacific/Apia', 'Pacific/Auckland', 'Pacific/Chatham', 'Pacific/Easter',
'Pacific/Efate', 'Pacific/Enderbury', 'Pacific/Fakaofo', 'Pacific/Fiji', 'Pacific/Funafuti', 'Pacific/Galapagos',
'Pacific/Gambier', 'Pacific/Guadalcanal', 'Pacific/Guam', 'Pacific/Honolulu', 'Pacific/Johnston',
'Pacific/Kiritimati', 'Pacific/Kosrae', 'Pacific/Kwajalein', 'Pacific/Majuro', 'Pacific/Marquesas',
'Pacific/Midway', 'Pacific/Nauru', 'Pacific/Niue', 'Pacific/Norfolk', 'Pacific/Noumea', 'Pacific/Pago_Pago',
'Pacific/Palau', 'Pacific/Pitcairn', 'Pacific/Ponape', 'Pacific/Port_Moresby', 'Pacific/Rarotonga',
'Pacific/Saipan', 'Pacific/Samoa', 'Pacific/Tahiti', 'Pacific/Tarawa', 'Pacific/Tongatapu', 'Pacific/Truk',
'Pacific/Wake', 'Pacific/Wallis', 'Pacific/Yap', 'Poland', 'Portugal', 'PRC', 'PST8PDT', 'ROC', 'ROK',
'Singapore', 'Turkey', 'UCT', 'Universal', 'US/Alaska', 'US/Aleutian', 'US/Arizona', 'US/Central',
'US/East-Indiana', 'US/Eastern', 'US/Hawaii', 'US/Indiana-Starke', 'US/Michigan', 'US/Mountain', 'US/Pacific',
'US/Pacific-New', 'US/Samoa', 'UTC', 'W-SU', 'WET', 'Zulu'
		);
	}
}

?>
