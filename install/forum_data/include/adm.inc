<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: adm.inc,v 1.14 2003/06/02 19:50:15 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

if (!defined('shell_script')) {
	$r = $GLOBALS['USE_PATH_INFO'];
	$GLOBALS['USE_PATH_INFO'] = 'N';
	fud_use('db.inc');
	fud_use('cookies.inc');
	fud_use('users.inc');
	$GLOBALS['USE_PATH_INFO'] = $r;
}

if ($GLOBALS['usr']->is_mod != 'A') {
	header('Location: admloginuser.php?'._rsidl);
        exit;
}
if (isset($_POST['btn_cancel'])) {
	if (defined('back_to_main')) {
		header('Location: admglobal.php?'._rsidl);
		exit;
	}
	$_POST = $_GET = NULL;
}
	
function rebuildmodlist()
{
	$tbl = $GLOBALS['DBHOST_TBL_PREFIX'];
	$lmt = $GLOBALS['SHOW_N_MODS'];
	$c = uq('SELECT u.id, u.alias, f.id FROM '.$tbl.'mod mm INNER JOIN '.$tbl.'users u ON mm.user_id=u.id INNER JOIN '.$tbl.'forum f ON f.id=mm.forum_id ORDER BY f.id,u.alias');
	while ($r = db_rowarr($c)) {
		$u[] = $r[0];
		if (isset($ar[$r[2]]) && count($ar[$r[2]]) >= $lmt) {
			continue;
		}
		$ar[$r[2]][$r[0]] = $r[1];
	}
	qf($c);

	q('UPDATE '.$tbl.'forum SET moderators=NULL');
	if (isset($ar)) {
		foreach ($ar as $k => $v) {
			q('UPDATE '.$tbl.'forum SET moderators='.strnull(addslashes(@serialize($v))).' WHERE id='.$k); 
		}
	}
	q('UPDATE '.$tbl.'users SET is_mod=\'N\' WHERE is_mod=\'Y\'');
	if (isset($u)) {
		q('UPDATE '.$tbl.'users SET is_mod=\'Y\' WHERE id IN('.implode(',', $u).') AND is_mod!=\'A\'');
	}
}
?>