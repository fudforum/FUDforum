<?php
/**
* copyright            : (C) 2001-2009 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: compiler.inc,v 1.107 2009/09/09 16:15:00 frank Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; version 2 of the License.
**/

	@set_time_limit(6000);

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

	/* We parse each section individually, it is faster than looking for all of them together
	 * due to the large number of {} that can be found inside the template files.
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
			$name = substr($str, $te, ($e - $te));
		} else {
			$tag = substr($str, $p, ($e - $p));
		}

		$op = $p = $e + 1;

		switch ($tag) {
			case 'MSG':
				if (!isset($GLOBALS['MSG'][$name])) {
					exit('Missing message entry '.$name.' inside i18n/'.$GLOBALS['__COMPILER__']['lang'].'/msg');
				}
				$ret .= $GLOBALS['MSG'][$name]['data'];
				break;
			case 'PLURAL':	// Pluralize messages at runtime.
				list($var, $forms) = explode('|', $name, 2);
				$forms_arr = str_replace('\'', '&#39;', $forms);	// Encode single quotes.
				$forms_arr = str_replace('|', '\',\'', $forms_arr);
				$forms_arr = preg_replace('/\$\d/', "'.$". $var .".'", $forms_arr);	// $1, $2, etc. refers to the count.
				$ret .= "'.convertPlural($". $var .", array('". $forms_arr ."')).'";
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
				list($name, $class1, $class2) = explode(' ', $name, 3);
				$ret .= '\'.alt_var(\''.$name.'\',\''.$class1.'\',\''.$class2.'\').\'';
				break;
			case 'DATE':
				if (stristr($GLOBALS['char_set'], 'utf-8') !== false && substr(PHP_OS, 0, 3) == 'WIN') {
					// Windows doesn't have UTF-8 locales
					$func = '\'.utf8_encode(strftime("';
					$close = ')).\'';
				} else {
					$func = '\'.strftime("';
					$close = ').\'';
				}
				list($var, $format) = explode(' ', $name, 2);
				if ($var[0] != '!') {
					$ret .= $func.$format.'", $'.$var.$close;
				} else {
					$ret .= $func.$format.'", '.substr($var,1).$close;
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
				echo 'WARNING: {META-CONTENT-LANGUAGE} is depricated, please remove it from your templates.<br />';
				$ret .= '<meta http-equiv="Content-Type" content="text/html; charset='.$GLOBALS['char_set'].'" />';
				break;
			case 'CHARSET':
				$ret .= $GLOBALS['char_set'];
				break;
			case 'LANG':
				if (isset($GLOBALS['usr']->pspell_lang)) {
					$ret .= $GLOBALS['usr']->pspell_lang;				
				} else {
					$ret .= 'en';
				}
				break;

			case 'FUNC':
				$ret .= "'." . substr($str, $te, ($e - $te)) . ".'";
				break;

			case 'IF':
				$ret .= "'.(" . substr($str, $te, ($e - $te)) . " ? '";
				break;
				
			case 'ELSE':
				$ret .= "' : '";
				break;
		
			case 'END':
				$ret .= "' ) .'";
				break;

			case 'ENDI':
				$ret .= "' )  .'";
				break;

			case 'ENDIF':
				$ret .= "' : '' ) .'";
				break;
				
			case 'ENDIFI':
				$ret .= "' : '' )  .'";
				break;

			case 'FULL_ROOT':
				$ret .= "'.\$GLOBALS['WWW_ROOT'].'";
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
	$l = strlen('<?php echo (');

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
		foreach ($gfc[$t]['include'] as $v) {
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
			$new = '';
			$ref = str_replace(
				array(".''.", "'.(", 
				"' ) .'' ) .'",
				"' ) .'' : '' ) .'",
				" ) .'"), 
				array('.', '<?php echo (', 
				"')).'",
				"'):'').'",
				'); ?>'),  
				$ref);
			/* block of code below is needed to handled nested ifs inside the final page output where we convert
			 * regular PHP code to one where <?php ?> are being used 
			 */
			$end = $pos = 0;
			while (($pos = strpos($ref, '<?php echo (', $pos)) !== false) { // found if() block
				/* handle regular component */
				$new .= str_replace(array(".'.$", ".'", "'."), array('.<?php echo $', '; ?>', '<?php echo '), substr($ref, $end, ($pos - $end)));
				if (($end_p = strpos($ref, '); ?>', $pos)) === false) { // find end of if()
					++$pos;
					continue;
				} else {
					$end = $end_p + 5;
				}
				// the str_replace() is a hack for handling ifs() inside ifs()
				$new .= substr($ref, $pos, $l);
				$pos += $l;
				$new .= str_replace('<?php echo (', "'.(", substr($ref, $pos, ($end - $pos))); // append if() code
				$pos = $end;
			}
			if (!$end) {
				$ref = str_replace(array(".'.$", ".'", "'."), array('.<?php echo $', '; ?>', '<?php echo '), $ref);
			} else {
				$ref = $new . str_replace(array(".'.$", ".'", "'."), array('.<?php echo $', '; ?>', '<?php echo '), substr($ref, $end));
			}

			$end = $pos = 0;
			// special handling for php init tags that end up being embeded
			while (($pos = strpos($ref, '<?php ', $pos)) !== false) {
				$new_loc = $pos + 5;
				$cnt = 0;
			
				/* find next PHP tag */
				if (($new_loc = strpos($ref, '<?php ', $new_loc)) === false) {
					break;
				}

				/* find end tag */
				if (($loc2 = strpos($ref, ' ?>', $pos)) === false) {
					break;
				}

				if ($loc2 > $new_loc) {
					$ref = substr_replace($ref,
						str_replace(
							array('<?php echo', '<?php ', '; ?>'), 
							array("'.","'.",".'"),
							substr($ref, $new_loc, $loc2 + 3 - $new_loc + 1)
						), 
						$new_loc, $loc2 + 3 - $new_loc + 1);
				} else {
					$pos = $new_loc;
				}
			}
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

				$ins[$k] .= str_replace(array(" ''.", ".'';", ".''"), array(' ', ';', ''), trim(substr($v['compiled'], $s, (strrpos($v['compiled'], '?>') - $s))));
			}

			if (!ini_get("memory_limit")) {
				$file_data = str_replace(array('/*{PRE_HTML_PHP}*/', '/*{POST_HTML_PHP}*/', '/*{POST_PAGE_PHP_CODE}*/'), array($ins[0], $ins['@'], $ins['!']), $file_data);
			} else {
				$file_data = str_replace('/*{PRE_HTML_PHP}*/', $ins[0], $file_data);
				$file_data = str_replace('/*{POST_HTML_PHP}*/', $ins['@'], $file_data);
				$file_data = str_replace('/*{POST_PAGE_PHP_CODE}*/', $ins['!'], $file_data);
			}
			$file_data = str_replace(array(" .'' ", " ''."), array(" ", " "), $file_data);
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
	$file_data = str_replace($GLOBALS['CMP_LOC_S'], $GLOBALS['CMP_LOC_K'], $file_data);

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
	// Read and process message file.
	foreach (file($file) as $k => $v) {
		if (preg_match('/^\s*$|^#/', $v)) {	// Skip empty lines and comments.
			continue;
		}

		// Extract message ID and message text from line.
		$mid = trim(substr($v, 0, strpos($v, ':')));
		$msg = trim(substr($v, strpos($v, ':')+1));

		// Remove {sections} containing variables, templates, etc.
		$sections = NULL; $i = 0;
		if (preg_match_all('#\{(.*?)\}#si', $msg, $sections, PREG_SET_ORDER)) {
			foreach ($sections as $section) {
				$msg = str_replace($section[0], "***sEcTiOn$i***", $msg);
				$sections[$i++] = $section[0];
			}
		}

		// Encode message test (without sections) to ensure they don't break our templates.
		$msg = str_replace('\'', '&#39;', $msg);
		$msg = str_replace('\\\'', '&#39;', $msg); // Delete when all \' are removed from message files

		// Re-insert {sections}.
		if( is_array($sections)) {
			foreach($sections as $i => $section) {
				$msg = str_replace("***sEcTiOn$i***", $section, $msg);
			}
		}

		// Check for duplicate messages
		if (isset($GLOBALS['MSG'][$mid])) {
			if ($GLOBALS['MSG'][$mid]['file'] != $file) {
				continue;
			}
			exit('Duplicated message: '.$mid.' in '.$file.' on line #'.($k + 1).', previous entry found on line #'.$GLOBALS['MSG'][$mid]['line'].' in '.$GLOBALS['MSG'][$mid]['file']);
		}

		$GLOBALS['MSG'][$mid] = array('data' => resolve_sections($msg), 'file' => $file, 'line'=> $k);
	}
}

function build_msg($file)
{
	if (isset($GLOBALS['MSG_FILES'][$file])) {
		return;
	}
	$GLOBALS['MSG_FILES'][$file] = 1;
	$GLOBALS['CURFILE'] = '';

	/*
	 * Read in requested message file.
	 */		
	if (file_exists($file)) {
		if (preg_match('!thm/([^/]+)/i18n!', $file, $m)) {
			parse_sections(@file_get_contents($GLOBALS['DATA_DIR'] . 'thm/'.$m[1].'/tmpl/messages'), '', 1);
			if (file_exists($GLOBALS['DATA_DIR'] . 'thm/'.$m[1].'/tmpl/date_formats.tmpl')) {
				parse_sections(@file_get_contents($GLOBALS['DATA_DIR'] . 'thm/'.$m[1].'/tmpl/date_formats.tmpl'), '', 1);
			}
		}
		parse_msgs($file);
	}

	/*
	 * Append any missing entries from the DEFAULT template set (same language).
	 */
	$ts = substr(strrchr(dirname($file), '/'), 1);
	$ts = $GLOBALS['DATA_DIR'] . 'thm/default/i18n/' . $ts . '/msg';
	if ($ts != $file && file_exists($ts)) {
		parse_sections(file_get_contents($GLOBALS['DATA_DIR'] . 'thm/default/tmpl/messages'), '', 1);
		parse_sections(file_get_contents($GLOBALS['DATA_DIR'] . 'thm/default/tmpl/date_formats.tmpl'), '', 1);
		parse_msgs($ts);
	}

	/*
	 * Append any missing entries from the DEFAULT ENGLISH template set.
	 */
	$en = $GLOBALS['DATA_DIR'] . 'thm/default/i18n/english/msg';
	if ($en != $file && $en != $ts && file_exists($en)) {
		parse_msgs($en);
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

	foreach(array_keys($ar) as $v) {
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

	if (!empty($GLOBALS['file_cache'][$file]['inline'])) {
		return;
	}

	if (empty($GLOBALS['file_cache'][$file]['src'])) {
		exit('No source for '.$file);
	}

	$GLOBALS['file_cache'][$file]['compiled'] = compile_file($GLOBALS['file_cache'][$file]['src']);
}

function fud_mkdir($dir, $perms=0700)
{
	if (!@is_dir($dir) && !@mkdir($dir, $perms)) {
		exit('unable to make directory (<b>'.$dir.'</b>)<br />');
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

	@unlink($GLOBALS['DATA_DIR'].'src/db.inc.t'); // win32 hack
	copy($GLOBALS['DATA_DIR'].'sql/'.$GLOBALS['DBHOST_DBTYPE'].'/db.inc', $GLOBALS['DATA_DIR'].'src/db.inc.t');

	$GLOBALS['__COMPILER__'] = array('tset' => $tset, 'lang' => $lang, 'tname' => $tname);
	$cmpl =& $GLOBALS['__COMPILER__'];

	$cmpl['ROOT'] = 'index.php';
	$cmpl['FULL_ROOT'] = $GLOBALS['WWW_ROOT']{strlen($GLOBALS['WWW_ROOT']) - 1} == '/' ? $GLOBALS['WWW_ROOT'] : $GLOBALS['WWW_ROOT'].'/';
	$cmpl['THEME_ROOT'] = 'theme/' . $tname;

	$cmpl['THEME_ROOT_DISK'] = $GLOBALS['WWW_ROOT_DISK'].'theme/'.$tname;
	$cmpl['THEME_IMAGE_ROOT'] = $cmpl['THEME_ROOT'].'/images';

	$file1 = $GLOBALS['DATA_DIR'].'thm/'.$tset.'/i18n/'.$lang.'/charset';
	$file2 = $GLOBALS['DATA_DIR'].'thm/default/i18n/'.$lang.'/charset';

	$u = umask(0);
	$perms = $GLOBALS['FUD_OPT_2'] & 8388608 ? 0700 : 0777;
	if (strncmp(PHP_SAPI, 'apache', 6) && $perms == 0700) {
		$perms = 0711;
	}

	fud_mkdir($GLOBALS['WWW_ROOT_DISK'].'theme', $perms);
	file_put_contents($GLOBALS['WWW_ROOT_DISK'] . 'theme/README', "Files in this directory are generated. Do not edit them.\nTo regenerate, navigate to the Theme Manager admin control panel.");

	fud_mkdir($GLOBALS['WWW_ROOT_DISK'] . 'theme/' . $tname, $perms);
	fud_mkdir($GLOBALS['INCLUDE'] . 'theme', $perms);
	fud_mkdir($GLOBALS['INCLUDE'] . 'theme/' . $tname, $perms);
	umask($u);

	$GLOBALS['CMP_LOC_S'] = array('{ROOT}', '{FULL_ROOT}', '{THEME_ROOT}', '{THEME_ROOT_DISK}', ".'';", ".''.");
	$GLOBALS['CMP_LOC_K'] = array($cmpl['ROOT'], "'.\$GLOBALS['WWW_ROOT'].'", $cmpl['THEME_ROOT'], $cmpl['THEME_ROOT_DISK'], ';', '.');

	$charset = @is_file($file1) ? $file1 : $file2;
	$GLOBALS['char_set'] = trim(file_get_contents($charset));

	/*	
	 * Build the message array.
	 */
	$prim_msg_file = $GLOBALS['DATA_DIR'].'thm/'.$cmpl['tset'].'/i18n/'.$cmpl['lang'].'/msg';
	build_msg($prim_msg_file);

	$fl = array();
	if ($cmpl['tset'] != 'default') {
		/* check custom theme for templates not found in the default theme */
		foreach (glob($GLOBALS['DATA_DIR'].'/thm/'.$cmpl['tset'].'/tmpl/*.tmpl') as $fe) {
			$fl[basename($fe)] = $fe;
		}
	}
	foreach (glob($GLOBALS['DATA_DIR'].'/thm/default/tmpl/*.tmpl') as $fe) {
		$fl[basename($fe)] = $fe;
	}

	foreach (array_keys($fl) as $file) {
		if ($file == 'date_formats.tmpl') continue;

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

	@umask($oldmask);
	$thm = new cmpl_local_theme;
	$thm->name = $tname;
	$thm->theme = $tset;
	$thm->lang = $lang;
	copyimages($thm);
	copyhelp($thm);
	copylangfuncs($thm);
}

class cmpl_local_theme
{
	var $name;
	var $theme;
	var $lang;
}

function fudcopy($src, $dest, $mask='*', $recur=false)
{
	$src = preg_replace('!/*$!', '', $src);
	$dest = preg_replace('!/*$!', '', $dest);

	$perms = $GLOBALS['FUD_OPT_2'] & 8388608 ? 0600 : 0666;
	if (strncmp(PHP_SAPI, 'apache', 6) && $perms == 0600) {
		$perms = 0644;
	}
	$dperm = $perms == 0600 ? 0700 : 0755;

	$dirs = array($src);
	while (list(,$v) = each($dirs)) {
		if (is_link($v)) {
			continue;
		}
	
		if (!is_readable($v)) {
			echo 'Cannot open directory "'.$v.'"<br />';
			return;
		}

		if (defined('GLOB_BRACE')) {		
			if (!($files = glob($v . '/' . $mask, GLOB_BRACE|GLOB_NOSORT))) {
				continue;
			}
		} else {
			$files = array();
			foreach (explode(',', trim($mask, '{}')) as $m) {
				$files = array_merge($files, (array)glob($v . '/' . $m, GLOB_NOSORT));
			}
			if (!$files) {
				continue;
			}
		}
		foreach ($files as $file) {
			$d = $dest . '/' . str_replace($src, '', $v) . '/' . basename($file);
		
			if (is_dir($file)) {
				if (!is_dir($d) && !mkdir($d)) {
					echo "Cannot create directory '".$d."'<br />";
					return;
				}
				chmod($d, $dperm);
				$dirs[] = $file;
				continue;
			}
	
			if (!@copy($file, $d) && !@file_exists($d)) {
				echo 'cannot copy "'.$file.'" to "'.$d.'"<br />';
			} else {
				@chmod($d, $perms);
			}
		}		
	}

	/* Copy .path_info marker */
	if (is_readable($src.'/.path_info')) {
		@copy($src.'/.path_info', $dest.'/.path_info');
	}
}

/* Copy localized images files to the theme's image directory. */
function copyimages($thm)
{
	$path = $GLOBALS['WWW_ROOT_DISK'].'theme/'.$thm->name.'/images';

	$u = umask(0);
	if (!@is_dir($path) && !@mkdir($path, 0777)) {
		exit('ERROR: Unable to create directory: '.$path.'<br />');
	}

	fudcopy($GLOBALS['DATA_DIR'].'thm/default/images', $path, '{*.jpg,*.gif,*.png,*.jpeg}');
	// some translations don't have customized image buttons
	if (file_exists($GLOBALS['DATA_DIR'].'thm/default/i18n/'.$thm->lang.'/img/flat_view.gif')) {
		fudcopy($GLOBALS['DATA_DIR'].'thm/default/i18n/'.$thm->lang.'/img', $path, '{*.jpg,*.gif,*.png,*.jpeg}');
	} else {
		fudcopy($GLOBALS['DATA_DIR'].'thm/default/i18n/english/img', $path, '{*.jpg,*.gif,*.png,*.jpeg}');
	}
	if ($thm->theme != 'default') {
		if (@file_exists($GLOBALS['DATA_DIR'].'thm/'.$thm->theme.'/images')) {
			fudcopy($GLOBALS['DATA_DIR'].'thm/'.$thm->theme.'/images', $path, '{*.jpg,*.gif,*.png,*.jpeg}');
		}
		if (@file_exists($GLOBALS['DATA_DIR'].'thm/'.$thm->theme.'/i18n/'.$thm->lang.'/img')) {
			fudcopy($GLOBALS['DATA_DIR'].'thm/'.$thm->theme.'/i18n/'.$thm->lang.'/img', $path, '{*.jpg,*.gif,*.png,*.jpeg}');
		}
	}

	umask($u);
}

/* Copy help files to the theme's help directory. */
function copyhelp($thm)
{
	$path = $GLOBALS['WWW_ROOT_DISK'].'theme/'.$thm->name.'/help';
	$src = $GLOBALS['DATA_DIR'].'thm/'.$thm->theme.'/i18n/'.$thm->lang.'/help';
	if (!@file_exists($src)) {
		$src = $GLOBALS['DATA_DIR'].'thm/default/i18n/'.$thm->lang.'/help';
		if (!@file_exists($src)) {
			$src = $GLOBALS['DATA_DIR'].'thm/default/i18n/english/help';
		}
	}

	$rp =& $GLOBALS['__COMPILER__']['ROOT'];

	$u = umask(0);

	$perms = $GLOBALS['FUD_OPT_2'] & 8388608 ? 0600 : 0666;
	if (strncmp(PHP_SAPI, 'apache', 6) && $perms == 0600) {
		$perms = 0644;
	}

	if (!@is_dir($path) && !@mkdir($path, 0777)) {
		exit('ERROR: Unable to create directory: '.$path.'<br />');
	}

	$path .= '/';

	$files = glob($src . '/*.hlp', GLOB_NOSORT);
	if (!$files) {
		exit("Failed to find help files inside '{$src}'");
	}
	foreach ($files as $file) {
		$de = basename($file);
		if (!($fp = fopen($path . $de, 'wb'))) {
			exit('Failed to write to: '.$path . $de.'<br />');
		}
		fwrite($fp, str_replace(array('{ROOT}', '{FULL_ROOT}'), 
					array($rp, "'.\$GLOBALS['WWW_ROOT'].'"), 
					file_get_contents($file))
		);
		fclose($fp);
		@chmod($file, $perms);
	}
	umask($u);
}

/* Copy langauge helper functions (language.inc) to the theme's directory. */
function copylangfuncs($thm) {
	$path = $GLOBALS['WWW_ROOT_DISK'].'theme/'.$thm->name.'/';
	$src = $GLOBALS['DATA_DIR'].'thm/'.$thm->theme.'/i18n/'.$thm->lang.'/language.inc';
	if (!@file_exists($src)) {
		$src = $GLOBALS['DATA_DIR'].'thm/default/i18n/'.$thm->lang.'/language.inc';
		if (!@file_exists($src)) {
			$src = $GLOBALS['DATA_DIR'].'thm/default/i18n/english/language.inc';
		}
	}
	
	$u = umask(0);

	if (!@is_dir($path) && !@mkdir($path, 0777)) {
		exit('ERROR: Unable to create directory: '.$path.'<br />');
	}
	
	fudcopy(dirname($src), $path, '{language.inc}');
	umask($u);
}
?>
