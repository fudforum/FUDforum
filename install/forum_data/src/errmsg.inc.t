<?php
/**
* copyright            : (C) 2001-2009 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: errmsg.inc.t,v 1.16 2009/10/25 20:00:18 frank Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; version 2 of the License.
**/

define('__fud_ecore_adm_login_msg', '{TEMPLATE: core_adm_login_msg}');
define('__fud_banned__', '{TEMPLATE: forum_banned_user}');

list($tset,$lang) = db_saq('SELECT name,lang FROM {SQL_TABLE_PREFIX}themes WHERE (theme_opt & (1|2)) = (1|2)');

header('Content-type: text/html; charset={TEMPLATE: errmsg_CHARSET}');
?>
