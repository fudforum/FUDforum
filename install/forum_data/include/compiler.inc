<?php
/***************************************************************************
* copyright            : (C) 2001-2003 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: compiler.inc,v 1.43 2003/10/10 18:39:36 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it 
* under the terms of the GNU General Public License as published by the 
* Free Software Foundation; either version 2 of the License, or 
* (at your option) any later version.
***************************************************************************/

	@set_time_limit(6000);

$GLOBALS['CMP_FILE'] = array(
				".'." => ".<?php echo ",
				".''." => '.',
				".'" => '; ?>',
				"'." => '<?php echo '
			);
$GLOBALS['CPM_CLEAN'] = array(
				" ''." => ' ',
				".'';" => ';',
				".''"  => ''
			);

function resolve_refs($file_name)
{
	if (isset($GLOBALS['file_cache'][$file_name])) {
		return;
	}
	$fc =& $GLOBALS['file_cache'][$file_name];

	/* Fetch file definition */
	$GLOBALS['CURFILE'] = $GLOBALS['DATA_DIR'].'thm/'.$GLOBALS['__COMPILER__']['tset'].'/tmpl/'.$file_name;
	if (($str = @file_get_contents($GLOBALS['CURFILE'])) === false) {
		$file1 = $GLOBALS['DATA_DIR'].'thm/default/tmpl/'.$file_name;
		if (($str = @file_get_contents($file1)) === false) {
			exit('referenced file named (<b>'.$file_name.'</b>) doesn\'t exist');
		}
	}
	if (($st = strpos($str, '{PHP_FILE: ')) !== false) {
		$st += 11;
		$st = substr($str, $st, (strpos($str, '}', $st) - $st - 1));
		$fc['src'] = substr($st, 7, (strpos($st, ';', 7) - 7));
		$fc['dst'] = substr($st, (strrpos($st, ' ') + 1));
		if (!$fc['src'] || !$fc['dst']) {
			exit('invalid PHP_FILE directive inside (<b>'.$file_name.'</b>)');
		}
		parse_sections($str, $fc['dst']);
	} else {
		$fc['inline'] = 1;
		parse_sections($str, '');
	}

	if (strpos($GLOBALS['CURFILE'], 'thm/default/tmpl') === false) {
		if (($tmp = @file_get_contents($GLOBALS['DATA_DIR'].'thm/default/tmpl/'.$file_name)) !== false) {
			parse_sections($tmp, ($st !== false ? $fc['dst'] : ''), 1);
		}
	}

	$p = 0;
	while (($p = strpos($str, '{REF: ', $p)) !== false) {
		$p += 6;
		$e = strpos($str, '}', $p);
		$nr = substr($str, $p, ($e - $p));
		if (!isset($fc['include'][$nr])) {
			$fc['include'][$nr] = $nr;
		}
		resolve_refs($nr);

		$p = $e + 1;
	}
}

$GLOBALS['__FUD_SECTIONS__'] = array('MAIN_SECTION' => '{MAIN_SECTION: ', 'PAGE' => '{PAGE: ', 'SECTION' => '{SECTION: ');

function parse_sections($str, $file, $sl=0)
{
	$PHP_FILE = ($file && substr($file, -4) == '.php') ? 1 : 0;

	/* we parse each section individually, it is faster the looking for all of them together
	 * due to the large number of {} that can be found inside the template files
	 */

	foreach ($GLOBALS['__FUD_SECTIONS__'] as $k => $v) {
		$p = 0;
		$tr =& $GLOBALS['TC'][$k];
		while (($p = strpos($str, $v, $p)) !== false) {
			$p += strlen($v);
			$e = strpos($str, '}', $p);
			$name = substr($str, $p, ($e - $p));
			if (($p = strpos($name, ' ')) !== false) {
				$name = substr($name, 0, $p);
			}
			++$e;

			if (isset($tr[$name])) {
				if (!$sl) {
					exit('DUPE: '.$k.' -> '.$name.' found in '.$tr[$name]['file'].' (now parsing '.$GLOBALS['CURFILE'].')');
				} else {
					if (($e2 = strpos($str, $v .'END}', $e)) === false) {
						exit('unterminated tag '.$k.' inside '.$file);
					}
					$p = $e2 + strlen($v) + 4;
					continue;
				}
			}

			if (($e2 = strpos($str, $v .'END}', $e)) === false) {
				exit('unterminated tag '.$k.' inside '.$file);
			}
			if ($PHP_FILE) {
				$GLOBALS['PHP_FILE_SEC'][$k][$name] = 1;
			}
			if (isset($tr[$name])) {
				array_push($tr[$name], array('file' => $GLOBALS['CURFILE'], 'data' => trim(substr($str, $e, ($e2 - $e)))));
			} else {
				$tr[$name] = array('file' => $GLOBALS['CURFILE'], 'data' => trim(substr($str, $e, ($e2 - $e))));
			}

			$p = $e2 + strlen($v) + 4;
		}
	}
}

function resolve_sections($str)
{
	if (($p = strpos($str, '{')) === false) {
		return $str;
	}

	$op = 0;
	$ret = '';
	$ch_list =& $GLOBALS['__char_list'];

	do {
		@$chr = $str[$p + 1];
		if ($chr < 'A' || $chr > 'Z') {
			++$p;
			continue;
		}

		if ($op != $p) {
			$ret .= substr($str, $op, ($p - $op));
		}
		++$p;

		if (($e = strpos($str, '}', $p)) === false) {
			exit('unterminated tag '.substr($str, ($p - 1), 255));
		}

		if (($te = strpos($str, ': ', $p)) !== false && $te < $e) {
			$tag = substr($str, $p, ($te - $p));
			$te += 2;
			if (($ds = strpos($str, ' ', $te)) === false || $ds > $e) {
				$ds = $e;
			}
			$name = substr($str, $te, ($ds - $te));
		} else {
			$tag = substr($str, $p, ($e - $p));
		}

		$op = $p = $e + 1;

		switch ($tag) {
			case 'MSG':
				if (!isset($GLOBALS['MSG'][$name])) {
					exit('Missing Message entry '.$name.' inside i18n/'.$GLOBALS['__COMPILER__']['lang'].'/msg');
				}
				$ret .= $GLOBALS['MSG'][$name]['data'];
				break;
			case 'VAR':
			case 'TEMPLATE-DATA':
				$ret .= '\'.$'.$name.'.\'';
				break;
			case 'GVAR':
				$ret .= '\'.$GLOBALS[\''.$name.'\'].\'';
				break;
			case 'DEF':
				$ret .= '\'.'.$name.'.\'';
				break;
			case 'VAR-URL':
				$ret .= '\'.urlencode($'.$name.').\'';
				break;
			case 'VAR-HTML':
				$ret .= '\'.htmlspecialchars($'.$name.').\'';
				break;
			case 'VAR-UH':
				$ret .= '\'.htmlspecialchars(urldecode($'.$name.')).\'';
				break;
			case 'VAR-FORM':
				$ret .= '\'.stripslashes($'.$name.').\'';
				break;
			case 'ALT-VAR':
				$t = strtok(substr($str, ++$ds, ($e - $ds)), ' ');
				$ret .= '\'.alt_var(\''.$name.'\',\''.$t.'\',\''.strtok(' ').'\').\'';
				break;
			case 'DATE':
				if ($name[0] != '!') {
					$ret .= '\'.strftime("'.ltrim(substr($str, ++$ds, ($e - $ds))).'", $'.$name.').\'';
				} else {
					$ret .= '\'.strftime("'.ltrim(substr($str, ++$ds, ($e - $ds))).'", '.substr($name,1).').\'';
				}
				break;
			case 'TEMPLATE':
				if (isset($GLOBALS['TC']['SECTION'][$name])) {
					$sec = 'SECTION';
				} else if (isset($GLOBALS['TC']['MAIN_SECTION'][$name])) {
					$sec = 'MAIN_SECTION';
				} else if (isset($GLOBALS['TC']['PAGE'][$name])) {
					$sec = 'PAGE';
				} else {
					exit('Undefined template: "'.$tag.'" inside "'.$name.'"');
				}
				$ret .= $GLOBALS['TC'][$sec][$name]['data'] = resolve_sections($GLOBALS['TC'][$sec][$name]['data']);
				break;
			case 'META-CONTENT-LANGUAGE':
				$ret .= '<META HTTP-EQUIV="Content-Type" CONTENT="text/html; charset='.$GLOBALS['char_set'].'">';
				break;
			case 'CHARSET':
				$ret .= $GLOBALS['char_set'];
				break;

			case 'FUNC':
				$ret .= "'." . substr($str, $te, ($e - $te)) . ".'";
				break;

			case 'ROOT':
			case 'THEME_ROOT':
			case 'THEME_IMAGE_ROOT':
				$ret .= $GLOBALS['__COMPILER__'][$tag];
				break;
			default:
				exit('No such tag: '.htmlspecialchars($tag).' -> '.htmlspecialchars($name));
		}
	} while (($p = strpos($str, '{', $p)) !== false);

	return $ret . substr($str, $op, (strlen($str) - $op));
}

function compile_file($source)
{
	if (empty($source)) {
		exit('No file passed to compile, fatal error');
	}
	if (($file_data = file_get_contents($GLOBALS['DATA_DIR'].'src/'.$source)) === false) {
		exit('Unable to open file '.$GLOBALS['DATA_DIR'].'src/'.$source);
	}

	if (($p = strpos($file_data, '{TEMPLATE: ')) === false) {
		return str_replace('{SQL_TABLE_PREFIX}', $GLOBALS['DBHOST_TBL_PREFIX'], $file_data);
	}

	$gfc =& $GLOBALS['file_cache'];
	$t = substr($source, 0, strpos($source, '.')).'.tmpl';
	if (isset($gfc[$t]['include'])) {
		$ir =& $gfc[$t]['include'];
		foreach ($ir as $v) {
			if (!isset($gfc[$v]['compiled']) && $t != $v) {
				generate_file($v);
			}
		}
	}

	$op = 0;
	$ret = '';
	do {
		if ($op != $p) {
			$ret .= substr($file_data, $op, ($p - $op));
		}
		$p += 11;
		$name = substr($file_data, $p, (strpos($file_data, '}', $p) - $p));
		if (isset($GLOBALS['TC']['SECTION'][$name])) {
			$sec = 'SECTION';
		} else if (isset($GLOBALS['TC']['MAIN_SECTION'][$name])) {
			$sec = 'MAIN_SECTION';
		} else if (isset($GLOBALS['TC']['PAGE'][$name])) {
			$sec = 'PAGE';
		} else {
			exit('Undefined template: "'.$name.'" inside "'.$source.'"');
		}
		$ref =& $GLOBALS['TC'][$sec][$name]['data'];
		$ref = resolve_sections($ref);

		if ($sec == 'PAGE') {
			$ref = strtr($ref, $GLOBALS['CMP_FILE']);
		}
		$ret .= $ref;

		if (($e = strpos($file_data, '}', $p)) === false) {
			exit('Unterminated tag "'.$name.'" inside "'.$source.'"');
		}
		$op = $p = $e + 1;
	} while (($p = strpos($file_data, '{TEMPLATE: ', $p)) !== false);

	return str_replace('{SQL_TABLE_PREFIX}', $GLOBALS['DBHOST_TBL_PREFIX'], $ret . substr($file_data, $op, (strlen($file_data) - $op)));
}

function generate_file($php_file)
{
	if (!isset($GLOBALS['file_cache'][$php_file]['compiled']) && !isset($GLOBALS['file_cache'][$php_file]['inline'])) {
		$GLOBALS['file_cache'][$php_file]['compiled'] = compile_file($GLOBALS['file_cache'][$php_file]['src']);
	}
	if (isset($GLOBALS['file_cache'][$php_file]['inline'])) {
		$GLOBALS['file_cache'][$php_file]['compiled'] = '';
		return;
	}

	$fc =& $GLOBALS['file_cache'][$php_file];
	$file_data = $fc['compiled'];
	if (substr($fc['dst'], -4) != '.inc') {
		/*
		 * ! - end
		 * @ - middle
		 * 0 - start
		 */

		$ins = array('!' => '', '@' => '', 0 => '');

		if (isset($fc['include'])) {
			foreach($fc['include'] as $v2) {
				if ($v2 == $php_file) {
					continue;
				}

				$k = $v2;
				$v =& $GLOBALS['file_cache'][$v2];

				if (empty($v['compiled']) && !isset($v['inline'])) {
					$v['compiled'] = compile_file($v['src']);
				}
	        		if (isset($v['inline'])) {
					continue;
	        		}

				$s = strpos($v['compiled'], '*/')+2;
				$k = $v['dst'][0];
				if (!isset($ins[$k])) {
					$k = 0;
				}
				$ins[$k] .= strtr(trim(substr($v['compiled'], $s, (strrpos($v['compiled'], '?>') - $s))), $GLOBALS['CPM_CLEAN']);
			}

			$file_data = str_replace('/*{PRE_HTML_PHP}*/', $ins[0], str_replace('/*{POST_HTML_PHP}*/', $ins['@'], str_replace('/*{POST_PAGE_PHP_CODE}*/', $ins['!'], $file_data)));
		}

		$dir = $GLOBALS['WWW_ROOT_DISK'].'theme/'.$GLOBALS['__COMPILER__']['tname'].'/';
		$dst = $fc['dst'];
	} else {
		$dir = $GLOBALS['INCLUDE'].'theme/'.$GLOBALS['__COMPILER__']['tname'].'/';
		if ($fc['dst'][0] == '!' || $fc['dst'][0] == '@') {
			$dst = substr($fc['dst'],1);
		} else {
			$dst = $fc['dst'];
		}
	}

	$cmp =& $GLOBALS['__COMPILER__'];
	$file_data = strtr($file_data, $GLOBALS['CMP_LOC']);

	if (!$dst) {
		return;
	}

	if ($dst[0] == '#') {
		$dst = substr($dst, 1);
		$dir = $GLOBALS['WWW_ROOT_DISK'];
	}

	if (!($fp = fopen($dir.$dst, 'wb'))) {
		exit('Couldn\'t write to '. $dir . $dst);
	}
	fwrite($fp, $file_data);
	fclose($fp);
}

function parse_msgs($file)
{
	$data = file($file);
	foreach ($data as $k => $v) {
		$m = trim(strtok($v, "\t"), ':');
		if (isset($GLOBALS['MSG'][$m])) {
			if ($GLOBALS['MSG'][$m]['file'] != $file) {
				continue;
			}
			exit('duplicated message: '.$m.' in '.$file.' on line #'.($k + 1).', previous entry found on line #'.$GLOBALS['MSG'][$m]['line'].' in '.$GLOBALS['MSG'][$m]['file']);
		}
		$GLOBALS['MSG'][$m] = array('data' => resolve_sections(trim(strtok("\t"))), 'file' => $file, 'line'=> $k);
	}
}

function build_msg($file)
{
	if (isset($GLOBALS['MSG_FILES'][$file])) {
		return;
	}
	$GLOBALS['MSG_FILES'][$file] = 1;

	/*
	 * we 1st read in the default msg file for the equivalent translation and read it in
	 * then we replace simular values from the actual msg file. This allows partial msg
	 * files to be used inside themes
	 */
	$ts = substr(strstr(dirname($file), '/'), 1);
	$ts = $GLOBALS['DATA_DIR'] . 'thm/default/i18n/' . $ts . '/msg';

	$GLOBALS['CURFILE'] = '';

	if (file_exists($file)) {
		if (preg_match('!thm/([^/]+)/i18n!', $file, $m)) {
			parse_sections(@file_get_contents($GLOBALS['DATA_DIR'] . 'thm/'.$m[1].'/tmpl/messages'), '');
		}
		parse_msgs($file);
	}
	if ($ts != $file && file_exists($ts)) {
		parse_sections(file_get_contents($GLOBALS['DATA_DIR'] . 'thm/default/tmpl/messages'), '', 1);
		parse_msgs($ts);
	}
}

function resolve_inc_refs($file)
{
	$curf =& $GLOBALS['file_cache'][$file];
	if (isset($curf['resolved'])) {
		return;
	}
	if (!isset($curf['include'])) {
		$curf['resolved'] = 1;
		$curf['include'] = array();
		return;
	}

	$ar =& $curf['include'];
	$vs = array_keys($ar);

	foreach($vs as $v) {
		if ($v == $file) {
			continue;
		}
		resolve_inc_refs($v);
		$ar = array_merge($ar, $GLOBALS['file_cache'][$v]['include']);
	}
	$ar[$file] = $file;
	$curf['resolved'] = 1;
}

function resolve_compile($file)
{
	if (isset($GLOBALS['file_cache'][$file])) {
		return;
	}
	resolve_refs($file, $file);
	resolve_inc_refs($file);
	if (empty($GLOBALS['file_cache'][$file]['src'])) {
		exit('No source for '.$file);
	}

	$GLOBALS['file_cache'][$file]['compiled'] = compile_file($GLOBALS['file_cache'][$file]['src']);
}

function fud_mkdir($dir, $perms=0700)
{
	if (!@is_dir($dir) && !@mkdir($dir, $perms)) {
		exit('unable to make directory (<b>'.$dir.'</b>)<br>');
	}
}

function compile_all($tset='default', $lang='english', $tname='default')
{
	$oldmask = umask(0133);

	/* make sure we can use the specified values to make 'nice' directory names */
	$tname = preg_replace('![^A-Za-z0-9-]!', '_', $tname);
	$tset = preg_replace('![^A-Za-z0-9-]!', '_', $tset);
	$lang = preg_replace('![^A-Za-z0-9-]!', '_', $lang);

	$GLOBALS['__COMPILER__'] 	= array();
	$GLOBALS['MSG']                 = array();
	$GLOBALS['MSG_FILES']           = array();
	$GLOBALS['TC']                  = array();
	$GLOBALS['file_cache']          = array();

	copy($GLOBALS['DATA_DIR'].'sql/'.__dbtype__.'/db.inc', $GLOBALS['DATA_DIR'].'src/db.inc.t');

	$GLOBALS['__COMPILER__'] = array('tset' => $tset, 'lang' => $lang, 'tname' => $tname);
	$cmpl =& $GLOBALS['__COMPILER__'];

	if (substr($GLOBALS['WWW_ROOT'], -1) != '/') {
		$cmpl['ROOT'] = $GLOBALS['WWW_ROOT'] . '/index.php';
		$cmpl['THEME_ROOT'] = $GLOBALS['WWW_ROOT'] . '/theme/' . $tname;
	} else {
		$cmpl['ROOT'] = $GLOBALS['WWW_ROOT'] . 'index.php';
		$cmpl['THEME_ROOT'] = $GLOBALS['WWW_ROOT'] . 'theme/' . $tname;
	}

	$cmpl['THEME_ROOT_DISK'] = $GLOBALS['WWW_ROOT_DISK'].'theme/'.$tname;
	$cmpl['THEME_IMAGE_ROOT'] = $cmpl['THEME_ROOT'].'/images';

	$file1 = $GLOBALS['DATA_DIR'].'thm/'.$tset.'i18n/'.$lang.'/charset';
	$file2 = $GLOBALS['DATA_DIR'].'thm/default/i18n/'.$lang.'/charset';

	$u = umask(0);
	$perms = $GLOBALS['FUD_OPT_2'] & 8388608 ? 0700 : 0777;
	fud_mkdir($GLOBALS['WWW_ROOT_DISK'].'theme', $perms);
	fud_mkdir($GLOBALS['WWW_ROOT_DISK'] . 'theme/' . $tname, $perms);
	fud_mkdir($GLOBALS['DATA_DIR'] . 'include/theme', $perms);
	fud_mkdir($GLOBALS['DATA_DIR'] . 'include/theme/' . $tname, $perms);
	umask($u);

	$GLOBALS['CMP_LOC'] = array(
			'{ROOT}'		=> $cmpl['ROOT'],
			'{THEME_ROOT}'		=> $cmpl['THEME_ROOT'],
			'{THEME_ROOT_DISK}'	=> $cmpl['THEME_ROOT_DISK'],
			".'';"			=> ';',
			".''."			=> '.',
	);

	$charset = @is_file($file1) ? $file1 : $file2;

	$GLOBALS['char_set'] = trim(file_get_contents($charset));

	$prim_msg_file = $GLOBALS['DATA_DIR'].'thm/'.$cmpl['tset'].'/i18n/'.$cmpl['lang'].'/msg';
	$back_msg_file = $GLOBALS['DATA_DIR'].'thm/default/i18n/'.$cmpl['lang'].'/msg';

	/*
	 * build the message array
	 */
	if (@file_exists($prim_msg_file)) {
		build_msg($prim_msg_file);
	}
	/*
	 * append any missing entries from the default language file
	 */
	build_msg($back_msg_file);

	$dir = opendir($GLOBALS['DATA_DIR'].'/thm/default/tmpl');
	readdir($dir); readdir($dir);
	while ($file = readdir($dir)) {
		if (substr($file, -5) != '.tmpl') {
			continue;
		}

		resolve_compile($file);
		if (isset($GLOBALS['file_cache'][$file]['inline'])) {
			continue;
		}

		generate_file($file);

		if (isset($GLOBALS['PHP_FILE_SEC'])) {
			foreach($GLOBALS['PHP_FILE_SEC'] as $k => $v) {
				foreach($v as $k2 => $v2) {
					unset($GLOBALS['TC'][$k][$k2]);
				}
			}
			unset($GLOBALS['PHP_FILE_SEC']);
		}
		unset($GLOBALS['INCLUDE_LIST']);
	}
	closedir($dir);

	@umask($oldmask);
	$thm = new cmpl_local_theme;
	$thm->name = $tname;
	$thm->theme = $tset;
	$thm->lang = $lang;
	copyimages($thm);
	copyhelp($thm);
}

class cmpl_local_theme
{
	var $name;
	var $theme;
	var $lang;
}

function fudcopy($src, $dest, $mask='!.*!i', $recur=false)
{
	$src = preg_replace('!/*$!', '', $src);
	$dest = preg_replace('!/*$!', '', $dest);

	if (!($dp = opendir($src))) {
		echo 'Cannot open directory "'.$src.'"<br>';
		return;
	}
	readdir($dp); readdir($dp);
	while ($de = readdir($dp)) {
		if (!preg_match($mask, $de, $regs)) {
			continue;
		}
		$path = $src . '/' . $de;
		$nd = $dest . '/' . $de;

		if (@is_dir($path) && $recur == true) {
			if (!@is_dir($nd) && !@mkdir($nd, 0777)) {
				exit('can\'t mkdir ('.$nd.')<br>');
			}
			fudcopy($path, $nd, $mask, true);
		} else {
			if (!@copy($path, $nd) && !@file_exists($nd)) {
				echo 'cannot copy "'.$path.'" to "'.$nd.'"<br>';
			} else {
				@chmod($nd, $GLOBALS['FUD_OPT_2'] & 8388608 ? 0600 : 0666);
			}
		}
	}
	closedir($dp);
}

function copyimages($thm)
{
	$path = $GLOBALS['WWW_ROOT_DISK'].'theme/'.$thm->name.'/images';

	$u = umask(0);
	if (!@is_dir($path) && !@mkdir($path, 0777)) {
		exit('Failed Creating: '.$path.'<br>');
	}
	fudcopy($GLOBALS['DATA_DIR'].'thm/default/images', $path, '!\.(gif|jpeg|png|jpg)$!i');
	fudcopy($GLOBALS['DATA_DIR'].'thm/default/i18n/'.$thm->lang.'/img', $path, '!\.(gif|jpeg|png|jpg)$!i');
	if (@file_exists($GLOBALS['DATA_DIR'].'thm/'.$thm->theme.'/images')) {
		fudcopy($GLOBALS['DATA_DIR'].'thm/'.$thm->theme.'/images', $path, '!\.(gif|jpeg|png|jpg)$!i');
	}
	if (@file_exists($GLOBALS['DATA_DIR'].'thm/'.$thm->theme.'/i18n/'.$thm->lang.'/img')) {
		fudcopy($GLOBALS['DATA_DIR'].'thm/'.$thm->theme.'/i18n/'.$thm->lang.'/img', $path, '!\.(gif|jpeg|png|jpg)$!i');
	}

	umask($u);
}

function copyhelp($thm)
{
	$path = $GLOBALS['WWW_ROOT_DISK'].'theme/'.$thm->name.'/help';
	$src = $GLOBALS['DATA_DIR'].'thm/'.$thm->theme.'/i18n/'.$thm->lang.'/help';
	if ($thm->theme != 'default' && !@file_exists($src)) {
		$src = $GLOBALS['DATA_DIR'].'thm/default/i18n/'.$thm->lang.'/help';
	}

	$rp =& $GLOBALS['__COMPILER__']['ROOT'];

	$u = umask(0);
	if (!@is_dir($path) && !@mkdir($path, 0777)) {
		exit('Failed Creating: '.$path.'<br>');
	}
	$path .= '/';
	$dp = opendir($src);
	$src .= '/';
	readdir($dp); readdir($dp);
	while ($de = readdir($dp)) {
		if (!@is_file($src . $de) || substr($de, -4) != '.hlp') {
			continue;
		}
		if (!($fp = fopen($path . $de, 'wb'))) {
			exit('Failed to write to: '.$path . '/' . $de.'<br>');
		}
		fwrite($fp, str_replace('{ROOT}', $rp, file_get_contents($src . $de)));
		fclose($fp);
		@chmod($src . $de, $GLOBALS['FUD_OPT_2'] & 8388608 ? 0600 : 0666);
	}
	closedir($dp);

	umask($u);
}
?>