<?php
/***************************************************************************
*   copyright            : (c) 2001-2003 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: glob.inc,v 1.9 2003/09/29 22:24:27 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation; either version 2 of the License, or
* (at your option) any later version.
***************************************************************************/

function read_help()
{
	$data = file_get_contents($GLOBALS['INCLUDE'].'GLOBALS_HELP');
	$p = 0;

	while (($p = strpos($data, "{{{", $p)) !== false) {
		$p += 3;
		if (($e = strpos($data, "}}}", $p)) === false) {
			break;
		}
		$t = strpos($data, "\n", $p);
		$tmp = explode("\t", trim(substr($data, $p, ($t - $p))));
		$name = array_shift($tmp);
		if ($tmp) {
			if (isset($tmp[2])) {
				$tmp[1] = substr($tmp[1], 1, -1);
				$tmp[2] = substr($tmp[2], 1, -1);
			}
		} else {
			$tmp = null;
		}

		$help_ar[$name] = array(nl2br(htmlspecialchars(substr($data, ++$t, ($e - $t)))), $tmp);
		$p = $e + 3;
	}

	return (isset($help_ar) ? $help_ar : null);
}

function print_bit_field($descr, $field)
{
	$h =& $GLOBALS['help_ar'][$field]; 
	$o =& $h[1];

	echo '<tr bgcolor="#bff8ff"><td>'.$descr.': <br><font size="-1">'.$h[0].'</font></td><td valign="top">';

	if (!isset($o[2])) {
		echo create_select($o[0].'_'.$field, "Yes\nNo", "{$o[1]}\n0", ($GLOBALS[$o[0]] & (int) $o[1]));
	} else {
		$tmp = explode('\n', $o[2]);
		rsort($tmp);
		$val = 0;
		foreach ($tmp as $v) {
			if ($GLOBALS[$o[0]] & (int) $v) {
				$val = $v;
				break;
			}
		}
		echo create_select($o[0].'_'.$field, str_replace("\\n", "\n", $o[1]), str_replace("\\n", "\n", $o[2]), $val);
	}

	echo '</td></tr>';
}
	
function print_reg_field($descr, $field, $is_int=0)
{
	if (!isset($GLOBALS[$field])) {
		$str = !$is_int ? '' : '0';
	} else {
		$str = !$is_int ? htmlspecialchars($GLOBALS[$field]) : $GLOBALS[$field];
	}

	echo '<tr bgcolor="#bff8ff"><td>'.$descr.': <br><font size="-1">'.$GLOBALS['help_ar'][$field][0].'</font></td><td valign="top"><input type="text" name="CF_'.$field.'" value="'.$str.'"></td></tr>';
}

function change_global_settings($list)
{
	$settings = file_get_contents($GLOBALS['INCLUDE'] . 'GLOBALS.php');
	foreach ($list as $k => $v) {
		if (($p = strpos($settings, '$' . $k)) === false) {
			$pos = strpos($settings, '$ADMIN_EMAIL');
			if (is_int($v)) {
				$settings = substr_replace($settings, "\t{$k}\t= {$v};\n", $p, 0);
			} else {
				$v = addcslashes($v, '\\"');
				$settings = substr_replace($settings, "\t{$k}\t= \"{$v}\";\n", $p, 0);
			}
		} else {
			$p = strpos($settings, '=', $p) + 1;
			$e = strpos($settings, ';', $p);

			if (is_int($v)) {
				$settings = substr_replace($settings, '= '.$v, $p, ($e - $p));
			} else {
				$v = addcslashes($v, '\\"');
				$settings = substr_replace($settings, '= "'.$v.'"', $p, ($e - $p));
			}
		}
	}

	$fp = fopen($GLOBALS['INCLUDE'] . $name, 'w');
	fwrite($fp, $settings);
	fclose($fp);
}

function maintenance_status($reason, $status)
{
	if ($GLOBALS['FUD_OPT_1'] & 1) {
		change_global_settings(array('FUD_OPT_1' => $GLOBALS['FUD_OPT_1'] ^ 1, 'DISABLED_REASON' => $reason));
	} else {
		change_global_settings(array('DISABLED_REASON' => $reason));
	}
}
?>