<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: glob.inc,v 1.7 2003/05/20 12:13:00 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

function read_help()
{
	$data = file_get_contents($GLOBALS['INCLUDE'].'GLOBALS_HELP');
	$len = strlen($data);
	$p = 0;

	while (($p = strpos($data, "\n", $p)) !== FALSE) {
		if (++$p >= $len) {
			break;
		}
		if ($data[$p] < 'A' || $data[$p] > 'Z') {
			continue;
		}
		$ke = strpos($data, "\n", $p);
		$key = substr($data, $p, ($ke - $p));
		++$ke;
		$e = strpos($data, ".\n\n", $ke);
		$help_ar[$key] = nl2br(htmlspecialchars(substr($data, $ke, ($e - $ke))));
		$p = $e + 2;
		if ($p >= $len) {
			break;
		}
	}
	return (isset($help_ar) ? $help_ar : NULL);
}

function change_global_settings($list, $name='GLOBALS.php')
{
	$settings = file_get_contents($GLOBALS['INCLUDE'] . $name);
	foreach ($list as $k => $v) {
		if (($p = strpos($settings, '$' . $k)) === FALSE) {
			if ($name == 'GLOBALS.php') {
				$p = strpos($settings, '$ALLOW_REGISTRATION')-1;
			} else if ($name == 'RDF.php') { /* RDF.php */
				$p = strpos($settings, '$AUTH')-1;
			} else if ($name == 'PDF.php') { /* PDF.php */
				$p = strpos($settings, '$PDF_ENABLED')-1;
			}
			$settings = substr_replace($settings, "\t\$$k\t= \"$v\";\n", $p, 0);
		} else {
			$p = strpos($settings, '"', $p) + 1;
			if ($v == 'Y' || $v == 'N') {
				$settings[$p] = $v;
			} else {
				$e = strpos($settings, '";', $p);
				$settings = substr_replace($settings, $v, $p, ($e - $p));
			}
		}
	}
	$fp = fopen($GLOBALS['INCLUDE'] . $name, 'w');
	fwrite($fp, $settings);
	fclose($fp);
}

function maintenance_status($reason, $status)
{
	change_global_settings(array('FORUM_ENABLED' => $status, 'DISABLED_REASON' => $reason));
}
?>