<?php
/**
* copyright            : (C) 2001-2006 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: errmsg.inc.t,v 1.12 2006/09/19 14:37:55 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; version 2 of the License.
**/

define('__fud_ecore_adm_login_msg', '{TEMPLATE: core_adm_login_msg}');
define('__fud_e_install_script_present_error', '{TEMPLATE: install_script_present_error}');
define('__fud_banned__', '{TEMPLATE: forum_banned_user}');

list($tset,$lang) = db_saq('SELECT name,lang FROM {SQL_TABLE_PREFIX}themes WHERE (theme_opt & (1|2)) = (1|2)');
if (file_exists($GLOBALS['DATA_DIR'].'thm/'.$tset.'/i18n/'.$lang.'/charset')) {
	$char = trim(file_get_contents($GLOBALS['DATA_DIR'].'thm/'.$tset.'/i18n/'.$lang.'/charset'));
} else if (file_exists($GLOBALS['DATA_DIR'].'thm/default/i18n/'.$lang.'/charset')) {
	$char = trim(file_get_contents($GLOBALS['DATA_DIR'].'thm/default/i18n/'.$lang.'/charset'));
} else {
	$char = 'UTF-8';
}
header("Content-type: text/html; charset=".$char);
?>