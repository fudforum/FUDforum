#!/usr/local/bin/php -q
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

function make_lang_arr($path)
{
	$eng = file($path);
	foreach ($eng as $v) {
		$key = strtok($v, ':');
		$p = strlen($key);
		while ($v[++$p] == "\t");
		$lang[$key] = substr($v, $p);
	}

	return $lang;
}

function make_lang_todo($list, $eng, $name)
{
	if (!count($list)) {
		return;
	}

	if (!($fp = fopen($name .'.todo', 'w'))) {
		exit('Unable to open '. $name .".todo for writing.\n");
	}
	fwrite($fp, "\n== Untranslated strings for ". ucwords($name) ."==\n\n");	
	foreach ($eng as $k => $v) {
		if (!isset($list[$k])) {
			$n_tabs = 4 - floor((strlen($k) + 1) / 8);
			fwrite($fp, '*'. $k .':'. str_repeat("\t", $n_tabs) . $v);	
		}
	}
	fclose($fp);
}

/* main */
	printf("==Translation status==\n\n");
	$eng = make_lang_arr('en/msg');
	
	$dp = opendir('.');
	while ($de = readdir($dp)) {
		if (!is_dir($de) || $de == '.' || $de == '..' || $de == 'en' || !@file_exists($de . '/msg')) {
			continue;
		}
		$curl = make_lang_arr($de . '/msg');
		printf("* %-15s translation has %-4d (%.1f%%) untranslated strings\n", $de, count($eng)-count($curl), (count($eng)-count($curl))/count($eng)*100);
		make_lang_todo($curl, $eng, $de);
	}
	closedir($dp);
?>
