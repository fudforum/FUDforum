<?php
/***************************************************************************
* copyright            : (C) 2001-2004 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: mem_limit.inc,v 1.5 2004/01/04 16:38:25 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it 
* under the terms of the GNU General Public License as published by the 
* Free Software Foundation; either version 2 of the License, or 
* (at your option) any later version.
***************************************************************************/

$GLOBALS['BUF_SIZE'] = 2000000;

/* if memory limit is present try to set it higher */
if (($lmt = ini_get('memory_limit'))) {
	/* failed to set memory limit higher, safe_mode */
	if (ini_set('memory_limit', '64M') === false) {
		if (($lmt = mem_limit_to_int($lmt))) {
			$GLOBALS['BUF_SIZE'] = round($lmt / 4);
			/* we can tell the amount of free memory try using that for the limit */
			if (function_exists('memory_get_usage')) {
				$GLOBALS['BUF_SIZE'] = round(($lmt - memory_get_usage()) / 2);
			}
		}
	}
}

function mem_limit_to_int($str)
{
	if (!preg_match('!([0-9]+)([GMKgmk]+)?!', $str, $m)) {
		return;
	}
	$lmt = $m[1];
	if (isset($m[2])) {
		for ($i = 0; $i < strlen($m[2]); $i++) {
			switch ($m[2]{$i}) {
				case 'G':
				case 'g':
					$lmt *= 1024 * 1024 * 1024;
					break;
				case 'M':
				case 'm':
					$lmt *= 1024 * 1024;
					break;
				case 'K':
				case 'k':
					$lmt *= 1024;
					break;
			}
		}
	}
	return $lmt;
}

?>