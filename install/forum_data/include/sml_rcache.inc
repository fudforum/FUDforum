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

function smiley_rebuild_cache()
{
	$rep = $src = $dst = array();

	/* Rebuild POST -> Smiley Cache. */
	$c = uq('SELECT code, '. q_concat('\'images/smiley_icons/\'', 'img') .', descr FROM '. $GLOBALS['DBHOST_TBL_PREFIX'] .'smiley ORDER BY vieworder');
	while ($r = db_rowarr($c)) {
		$src[] = $co = '<img src="'. $r[1] .'" border="0" alt="'. $r[2] .'" />';
		$codes = explode('~', strtolower($r[0]));
		$dst[] = $codes[0];
		foreach ($codes as $v) {
			if ($v) {
				$rep[$v] = $co;
			}
		}
	}
	unset($c);

	$fp = fopen($GLOBALS['TMP'] .'ps_cache', 'w');
	fwrite($fp, '<?php $PS_SRC = '. var_export($src, 1) .'; $PS_DST = '. var_export($dst, 1) .'; ?>');
	fclose($fp);

	if (!strncasecmp('win', PHP_OS, 3)) {
		@unlink($GLOBALS['FORUM_SETTINGS_PATH'] .'ps_cache');
	}
	rename($GLOBALS['TMP'] .'ps_cache', $GLOBALS['FORUM_SETTINGS_PATH'] .'ps_cache');

	$fp = fopen($GLOBALS['TMP'] .'sp_cache', 'w');
	fwrite($fp, '<?php $SML_REPL = '. var_export($rep, 1) .'; ?>');
	fclose($fp);

	if (!strncasecmp('win', PHP_OS, 3)) {
		@unlink($GLOBALS['FORUM_SETTINGS_PATH'] .'sp_cache');
	}
	rename($GLOBALS['TMP'] .'sp_cache', $GLOBALS['FORUM_SETTINGS_PATH'] .'sp_cache');
}

?>
