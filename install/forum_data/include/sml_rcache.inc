<?php
/***************************************************************************
* copyright            : (C) 2001-2004 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: sml_rcache.inc,v 1.1 2004/06/22 16:23:28 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; either version 2 of the License, or
* (at your option) any later version.
***************************************************************************/

function smiley_rebuild_cache()
{
	$rep = $src = $dst = array();

	/* Rebuild POST -> Smiley Cache */
	$c = uq('SELECT code, '.__FUD_SQL_CONCAT__.'(\'images/smiley_icons/\', img), descr FROM '.$GLOBALS['DBHOST_TBL_PREFIX'].'smiley');
	while ($r = db_rowarr($c)) {
		$src[] = $co = '<img src="'.$r[1].'" border=0 alt="'.$r[2].'">';
		$codes = explode('~', strtolower($r[0]));
		$dst[] = $codes[0];
		foreach ($codes as $v) {
			$rep[$v] = $co;
		}
	}

	$fp = fopen($GLOBALS['TMP'].'ps_cache', "w");
	fwrite($fp, '<?php $PS_SRC = '.var_export($src, 1).'; $PS_DST = '.var_export($dst, 1).'; ?>');
	fclose($fp);

	rename($GLOBALS['TMP'].'ps_cache', $GLOBALS['FORUM_SETTINGS_PATH'].'ps_cache');

	$fp = fopen($GLOBALS['TMP'].'sp_cache', "w");
	fwrite($fp, '<?php $SML_REPL = '.var_export($rep, 1).'; ?>');
	fclose($fp);

	rename($GLOBALS['TMP'].'sp_cache', $GLOBALS['FORUM_SETTINGS_PATH'].'sp_cache');
}
?>