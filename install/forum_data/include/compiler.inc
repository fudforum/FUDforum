<?php
/**
* copyright            : (C) 2001-2023 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id$
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; version 2 of the License.
**/

@set_time_limit(6000);
include 'fs.inc';

$GLOBALS['__FUD_SECTIONS__'] = array('MAIN_SECTION' => '{MAIN_SECTION: ', 'PAGE' => '{PAGE: ', 'SECTION' => '{SECTION: ');

/** Fill-in missing template sections from parent themes. */
function resolve_refs($file_name)
{
	if (isset($GLOBALS['file_cache'][$file_name])) {
		return;
	}
	$fc   =& $GLOBALS['file_cache'][$file_name];
	$tset = $GLOBALS['__COMPILER__']['tset'];
	
	/* Load file definition. First, see if the file is present in the current template set. */
	$GLOBALS['CURFILE'] = $GLOBALS['DATA_DIR'] .'thm/'. $tset .'/tmpl/'. $file_name;
	if (($str = @file_get_contents($GLOBALS['CURFILE'])) === false) {

		// Try to locate file by looking at 'based_on' chain in theme.ini files.
		$ini['based_on'] = $tset;
		while ($ini['based_on'] != 'default' && ($ini = @parse_ini_file($GLOBALS['DATA_DIR'] .'thm/'. $ini['based_on'] .'/theme.ini'))) {
			$GLOBALS['CURFILE'] = $GLOBALS['DATA_DIR'] .'thm/'. $ini['based_on'] .'/tmpl/'. $file_name;
			if (($str = @file_get_contents($GLOBALS['CURFILE'])) !== false) {
				$file_loaded = 1;
				break;
			}
		}

		// Still not found, use old lookup method: PATH_INFO and DEFAULT themes.
		if ( !isset( $file_loaded ) ) {
			if ( file_exists($GLOBALS['DATA_DIR'] .'thm/'. $tset .'/.path_info')) {
				$GLOBALS['CURFILE'] = $GLOBALS['DATA_DIR'] .'thm/path_info/tmpl/'. $file_name;
				if (($str = @file_get_contents($GLOBALS['CURFILE'])) === false) {
					// Not found, look in DEFAULT theme.
					$GLOBALS['CURFILE'] = $GLOBALS['DATA_DIR'] .'thm/default/tmpl/'. $file_name;
					if (($str = @file_get_contents($GLOBALS['CURFILE'])) === false) {
						throw new Exception('Referenced file named (<b>'. $file_name .'</b>) doesn\'t exist.');
					}
				}
			} else {	// Not a path_info theme.
				$GLOBALS['CURFILE'] = $GLOBALS['DATA_DIR'] .'thm/default/tmpl/'. $file_name;
				if (($str = @file_get_contents($GLOBALS['CURFILE'])) === false) {
					throw new Exception('Referenced file named (<b>'. $file_name .'</b>) doesn\'t exist.');
				}
			}
		}
	}
	
	if (($st = strpos($str, '{PHP_FILE: ')) !== false) {
		$st += 11;
		$st = substr($str, $st, (strpos($str, '}', $st) - $st - 1));
		$fc['src'] = substr($st, 7, (strpos($st, ';', 7) - 7));
		$fc['dst'] = substr($st, (strrpos($st, ' ') + 1));
		if (!$fc['src'] || !$fc['dst']) {
			throw new Exception('Invalid PHP_FILE directive in file '. $file_name);
		}
		parse_sections($str, $fc['dst']);
	} else {
		$fc['inline'] = 1;
		parse_sections($str, '');
	}

	// Non-default templates may not contain all sections. Parse 'based_on' chain as well.
	if (strpos($GLOBALS['CURFILE'], 'thm/default/tmpl') === false) {
		$ini['based_on'] = $tset;
		while ($ini['based_on'] != 'default' && ($ini = @parse_ini_file($GLOBALS['DATA_DIR'] .'thm/'. $ini['based_on'] .'/theme.ini'))) {
			if (($tmp = @file_get_contents($GLOBALS['DATA_DIR'] .'thm/'. $ini['based_on'] .'/tmpl/'. $file_name)) !== false) {
				parse_sections($tmp, ($st !== false ? $fc['dst'] : ''), 1);
			}
		}
		
		// Still not found, use old lookup method: PATH_INFO and DEFAULT themes.
		if ( $ini == FALSE || $ini['based_on'] != 'default' ) {
			// For undefined sections of a path_info theme, look first in the PATH_INFO template set.
			if (file_exists($GLOBALS['DATA_DIR'] .'thm/'. $tset .'/.path_info')) {
				if (($tmp = @file_get_contents($GLOBALS['DATA_DIR'] .'thm/path_info/tmpl/'. $file_name)) !== false) {
					parse_sections($tmp, ($st !== false ? $fc['dst'] : ''), 1);
				}
			}
			// If all else fails, get it from the default template set.
			if (($tmp = @file_get_contents($GLOBALS['DATA_DIR'] .'thm/default/tmpl/'. $file_name)) !== false) {
				parse_sections($tmp, ($st !== false ? $fc['dst'] : ''), 1);
			}
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
					throw new Exception('Duplicate section: '. $k .' -> '. $name .' found in '. $tr[$name]['file'] .' (now parsing '. $GLOBALS['CURFILE'] .').');
				} else {
					if (($e2 = strpos($str, $v .'END}', $e)) === false) {
						throw new Exception('Unterminated tag '. $k .' in file '. $file);
					}
					$p = $e2 + strlen($v) + 4;
					continue;
				}
			}

			if (($e2 = strpos($str, $v .'END}', $e)) === false) {
				throw new Exception('Unterminated tag '. $k .' in file '. $file);
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

/** Expand tags in sections. */ 
function resolve_sections($file, $secname, $str)
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
			throw new Exception('Unterminated tag '. substr($str, ($p - 1), 255) .'.');
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
					throw new Exception('Invalid message "'. $name .'" in "'. $secname .'" in file '. $file .".\n Please fix or define it in ../i18n/". $GLOBALS['__COMPILER__']['lang'] .'/msg');
				}
				$ret .= $GLOBALS['MSG'][$name]['data'];
				//DEBUG: $ret .= $name .':'. $GLOBALS['MSG'][$name]['data'];
				break;

			case 'PLURAL':	// Pluralize messages at runtime.
				list($var, $forms) = explode('|', $name, 2);
				$forms_arr = str_replace('\'', '&#39;', $forms);	// Encode single quotes.
				$forms_arr = str_replace('|', '\',\'', $forms_arr);
				$forms_arr = preg_replace('/\$\d/', "'.$". $var .".'", $forms_arr);	// $1, $2, etc. refers to the count ($var).
				$ret .= "'.convertPlural($". $var .", array('". $forms_arr ."')).'";
				break;

			case 'VAR':
			case 'TEMPLATE-DATA':
				$ret .= '\'.$'. $name .'.\'';
				break;

			case 'GVAR':
				$ret .= '\'.$GLOBALS[\''. $name .'\'].\'';
				break;

			case 'DEF':
				if ($name == 'img_ext') {
					echo 'WARNING: <b>{DEF: img_ext}</b> is deprecated. Please replace it with <b>.png</b> in '. $file .'.<br />';
					$ret .= '\'. \'.png\' .\'';
					break;
				}
				$ret .= '\'.'. $name .'.\'';
				break;

			case 'VAR-URL':
				$ret .= '\'.urlencode($'. $name .').\'';
				break;

			case 'VAR-HTML':
				// XSS protection
				$ret .= '\'.htmlspecialchars($'. $name .', ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, null, false).\'';
				break;
				
			case 'VAR-SEO':
				$ret .= '\'.seo_url_bit($'. $name .').\'';
				break;

			case 'ALT-VAR':
				list($name, $class1, $class2) = explode(' ', $name, 3);
				$ret .= '\'.alt_var(\''. $name .'\',\''.$class1.'\',\''.$class2.'\').\'';
				break;

			case 'DATE':
				$func = '\'.print_date(\'';
				$close = ').\'';

				list($var, $format) = explode(' ', $name, 2);
				if ($var[0] != '!') {
					$ret .= $func . $format .'\', $'. $var . $close;
				} else {
					$ret .= $func . $format .'\', '. substr($var,1) . $close;
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
					throw new Exception('Undefined template: "'. $name .'" inside "'. $secname .'" in file '. $file);
				}

				$tmpl_resolved = $GLOBALS['TC'][$sec][$name]['data'] = resolve_sections($file, $name, $GLOBALS['TC'][$sec][$name]['data']);

				// Give all COMPILER_EXPAND_TEMPLATE plugins the opportunity to modify the template.
				if (defined('plugins')) {
					list($tmpl_resolved) = plugin_call_hook('COMPILER_EXPAND_TEMPLATE', array($tmpl_resolved, $tag, $sec, $name, basename($GLOBALS['TC'][$sec][$name]['file'])));
				}
				$ret .= $tmpl_resolved;

				break;

			// Run time plugin integration.
			case 'PLUGIN':
				$ret .= '\'. (defined("plugins") ? implode(" ", plugin_call_hook("'. strtoupper($name) .'")) : null) .\'';
				break;

			// Compile time plugin integration.
			case 'INJECTION_POINT':
				// Give all COMPILER_INJECT plugins the opportunity to add code to the template.
				if (defined('plugins')) {
					$code_to_add = null;
					list($code_to_add) = plugin_call_hook('COMPILER_INJECT', array($code_to_add, $name));
					if ($code_to_add) {
						$ret .= $code_to_add;
					}
				}
				$GLOBALS['injection_points'][] = $name;
				break;

			case 'META-CONTENT-LANGUAGE':
				echo 'WARNING: {META-CONTENT-LANGUAGE} is deprecated. Please remove it from "'. $secname .'" in '. $file .'.<br />';
				$ret .= '<meta http-equiv="Content-Type" content="text/html; charset='. $GLOBALS['char_set'] .'" />';
				break;

			case 'CHARSET':	// Character set name.
				$ret .= $GLOBALS['char_set'];
				break;

			case 'DIR':	// Text direction (LTR or RTL).
				$ret .= $GLOBALS['text_dir'];
				break;

			case 'LANG':	// Language code.
				$ret .= $GLOBALS['__COMPILER__']['lang'];
				break;

			case 'FUNC':
				$ret .= "'.". substr($str, $te, ($e - $te)) .".'";
				break;

			case 'IF':
				$ret .= "'.(". substr($str, $te, ($e - $te)) ." ? '";
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

			case 'BASE':		// /forum/
			case 'ROOT':		// /forum/index.php
			case 'FULL_ROOT':	// http://site.com/forum/index.php
			case 'THEME_ROOT':
			case 'THEME_IMAGE_ROOT':
				$ret .= $GLOBALS['__COMPILER__'][$tag];
				break;
			default:
				throw new Exception('No such tag: "'. htmlspecialchars($tag, ENT_QUOTES) .'" in "'. $secname .'" in file '. $file);
		}
	} while (($p = strpos($str, '{', $p)) !== false);

	return $ret . substr($str, $op, (strlen($str) - $op));
}

/** Process a src (*.t) file. */
function compile_file($source, $from_tmpl)
{
	$l = strlen('<?php echo (');

	if (empty($source)) {
		throw new Exception('No file passed to compile, fatal error.');
	}
	if (($file_data = file_get_contents($GLOBALS['DATA_DIR'] .'src/'. $source)) === false) {
		throw new Exception('Unable to open file '. $GLOBALS['DATA_DIR'] .'src/'. $source);
	}

	if (($p = strpos($file_data, '{TEMPLATE: ')) === false) {
		return str_replace('{SQL_TABLE_PREFIX}', $GLOBALS['DBHOST_TBL_PREFIX'], $file_data);
	}

	$gfc =& $GLOBALS['file_cache'];
	$t = substr($source, 0, strpos($source, '.')) .'.tmpl';
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
			throw new Exception('Error compiling '. $from_tmpl .' - cannot find template \''. $name .'\' used in file '. $source);
		}

		$ref =& $GLOBALS['TC'][$sec][$name]['data'];
		$ref = resolve_sections($t, $name, $ref);

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
			/* Block of code below is needed to handled nested IFs inside the final page output
			 * where we convert regular PHP code to one where <?php ?> are being used.
			 */
			$end = $pos = 0;
			while (($pos = strpos($ref, '<?php echo (', $pos)) !== false) { // found if() block
				/* Handle regular component. */
				$new .= str_replace(array(".'.$", ".'", "'."), array('.<?php echo $', '; ?>', '<?php echo '), substr($ref, $end, ($pos - $end)));
				if (($end_p = strpos($ref, '); ?>', $pos)) === false) { // find end of if()
					++$pos;
					continue;
				} else {
					$end = $end_p + 5;
				}
				// The str_replace() is a hack for handling ifs() inside ifs().
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
			// Special handling for php init tags that end up being embedded.
			while (($pos = strpos($ref, '<?php ', $pos)) !== false) {
				$new_loc = $pos + 5;
				$cnt = 0;

				/* Find next PHP tag. */
				if (($new_loc = strpos($ref, '<?php ', $new_loc)) === false) {
					break;
				}

				/* Find end tag. */
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
			throw new Exception('Unterminated tag "'. $name .'" in file '. $source);
		}
		$op = $p = $e + 1;
	} while (($p = strpos($file_data, '{TEMPLATE: ', $p)) !== false);

	return str_replace('{SQL_TABLE_PREFIX}', $GLOBALS['DBHOST_TBL_PREFIX'], $ret . substr($file_data, $op, (strlen($file_data) - $op)));
}

/** Process a template (.tmpl) file. */
function generate_file($php_file)
{
	if (!isset($GLOBALS['file_cache'][$php_file]['compiled']) && !isset($GLOBALS['file_cache'][$php_file]['inline'])) {
		$GLOBALS['file_cache'][$php_file]['compiled'] = compile_file($GLOBALS['file_cache'][$php_file]['src'], $php_file);
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
					$v['compiled'] = compile_file($v['src'], $php_file);
				}
				if (isset($v['inline'])) {
					continue;
				}

				$s = strpos($v['compiled'],  '*/') + 2;		// Skip comments.
				$e = strrpos($v['compiled'], '?>');		// EOF
				if (empty($s) || empty($e)) {
					throw new Exception('File  '. $v2 .' is incomplete. It must start with comments and end with \'?>\').');
				}

				$k = $v['dst'][0];
				if (!isset($ins[$k])) {
					$k = 0;
				}
				$ins[$k] .= str_replace(array(" ''.", ".'';", ".''"), array(' ', ';', ''), trim(substr($v['compiled'], $s, $e-$s)));
			}
			if (!ini_get('memory_limit')) {
				$file_data = str_replace(array('/*{PRE_HTML_PHP}*/', '/*{POST_HTML_PHP}*/', '/*{POST_PAGE_PHP_CODE}*/'), array($ins[0], $ins['@'], $ins['!']), $file_data);
			} else {
				$file_data = str_replace('/*{PRE_HTML_PHP}*/', $ins[0], $file_data);
				$file_data = str_replace('/*{POST_HTML_PHP}*/', $ins['@'], $file_data);
				$file_data = str_replace('/*{POST_PAGE_PHP_CODE}*/', $ins['!'], $file_data);
			}
			$file_data = str_replace(array(" .'' ", " ''."), array(' ', ' '), $file_data);
		}

		$dir = $GLOBALS['WWW_ROOT_DISK'] .'theme/'. $GLOBALS['__COMPILER__']['tname'] .'/';
		$dst = $fc['dst'];
	} else {
		$dir = $GLOBALS['INCLUDE'] .'theme/'. $GLOBALS['__COMPILER__']['tname'] .'/';
		if ($fc['dst'][0] == '!' || $fc['dst'][0] == '@') {
			$dst = substr($fc['dst'], 1);
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

	if (!($fp = fopen($dir . $dst, 'wb'))) {
		throw new Exception('Couldn\'t write to '. $dir . $dst);
	}

	// Inject debug info to help with theme development.
	if (defined('fud_debug')) {
		$file_data = str_replace('</html>', '<?php echo "<pre>".htmlentities(print_r(get_defined_vars(), 1))."</pre>"; ?> </html>', $file_data);
	}

	// Call all COMPILER_FINALIZE_PAGE plugins.
	if (defined('plugins')) {
		list($file_data) = plugin_call_hook('COMPILER_FINALIZE_PAGE', array($file_data, $dst));
	}

	// Write processed template to disk.
	fwrite($fp, $file_data);
	fclose($fp);
}

/** Read and parse a message file. */
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

		// Encode message text (without sections) to ensure they don't break our templates.
		// NOTE: DON'T ENCODE DOUBLE QUOTES (") USED IN HTML (for example <a href="">).
		$msg = str_replace('\'', '&#39;', $msg);

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
			throw new Exception('Duplicated message: '. $mid .' in '. $file .' on line #'. ($k + 1) .', previous entry found on line #'. $GLOBALS['MSG'][$mid]['line'] .' in '. $GLOBALS['MSG'][$mid]['file']);
		}

		$GLOBALS['MSG'][$mid] = array('data' => resolve_sections($file, $mid, $msg), 'file' => $file, 'line'=> $k);
	}
}

/** Fill-in missing messages from the DEFAULT theme (same lang); then from English. */
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
        if (preg_match('!thm/([^/]+)/i18n!', $file, $m)) {
                // Get theme's 'messages' file.
                $ini['based_on'] = $m[1];
                do {
                        if (($tmp = @file_get_contents($GLOBALS['DATA_DIR'] .'thm/'. $ini['based_on'] .'/tmpl/messages')) !== false) {
                                parse_sections($tmp, '', 1);
                        }
                } while ($ini['based_on'] != 'default' && ($ini = @parse_ini_file($GLOBALS['DATA_DIR'] .'thm/'. $ini['based_on'] .'/theme.ini')));

                // Get theme's date formats file.
                $ini = [];	// Prevent 'Automatic conversion of false to array is deprecated', caused by do...while loop.
                $ini['based_on'] = $m[1];
                do {
                        if (($tmp = @file_get_contents($GLOBALS['DATA_DIR'] .'thm/'. $ini['based_on'] .'/tmpl/date_formats.tmpl')) !== false) {
                                parse_sections($tmp, '', 1);
                        }
                } while ($ini['based_on'] != 'default' && ($ini = @parse_ini_file($GLOBALS['DATA_DIR'] .'thm/'. $ini['based_on'] .'/theme.ini')));

		// Parse the requested message file.
                if (file_exists($file)) {
                        parse_msgs($file);
                }
        }

	/*
	 * Append any missing entries from the DEFAULT template set (same language).
	 */
	$ts = substr(strrchr(dirname($file), '/'), 1);
	$ts = $GLOBALS['DATA_DIR'] .'thm/default/i18n/'. $ts .'/msg';
	if ($ts != $file && file_exists($ts)) {
		parse_sections(file_get_contents($GLOBALS['DATA_DIR'] .'thm/default/tmpl/messages'), '', 1);
		parse_sections(file_get_contents($GLOBALS['DATA_DIR'] .'thm/default/tmpl/date_formats.tmpl'), '', 1);
		parse_msgs($ts);
	}

	/*
	 * Append any missing entries from the DEFAULT ENGLISH template set.
	 */
	$en = $GLOBALS['DATA_DIR'] .'thm/default/i18n/en/msg';
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
		throw new Exception('No source for file '. $file);
	}

	$GLOBALS['file_cache'][$file]['compiled'] = compile_file($GLOBALS['file_cache'][$file]['src'], $file);
}

function compile_all($tset='default', $lang='en', $tname='default', $t_opt=0)
{
	/* Make sure we can use the specified values to make 'nice' directory names. */
	$tname = preg_replace('![^A-Za-z0-9-]!', '_', $tname);
	$tset  = preg_replace('![^A-Za-z0-9-]!',  '_', $tset);
	$lang  = preg_replace('![^A-Za-z0-9-]!',  '_', $lang);

	$GLOBALS['__COMPILER__']        = array();
	$GLOBALS['MSG']                 = array();
	$GLOBALS['MSG_FILES']           = array();
	$GLOBALS['TC']                  = array();
	$GLOBALS['file_cache']          = array();

	@unlink($GLOBALS['DATA_DIR'] .'src/db.inc.t'); // win32 hack, delete before copy.
	copy($GLOBALS['DATA_DIR'] .'sql/'. $GLOBALS['DBHOST_DBTYPE'] .'/db.inc', $GLOBALS['DATA_DIR'] .'src/db.inc.t');

	$GLOBALS['__COMPILER__'] = array('tset' => $tset, 'lang' => $lang, 'tname' => $tname);
	$cmpl =& $GLOBALS['__COMPILER__'];

	// Extract path from forum URL.
	$urlparts = parse_url($GLOBALS['WWW_ROOT']);
	$urlpath = empty($urlparts['path']) ? '/' : $urlparts['path'];
	if ($urlpath[strlen($urlpath)-1] != '/') {
	   $urlpath = $urlpath .'/';
	}

	// Define constants to expand in template files, i.e. {ROOT}.	
	$cmpl['BASE'] = substr($urlpath, 0, strrpos($urlpath, '/')+1 );

	if ($t_opt & 8) {	// Remove index.php from URLs (8==NO_INDEX_URL)
		$cmpl['ROOT']      = rtrim($cmpl['BASE'], '/');
		$cmpl['FULL_ROOT'] = rtrim($GLOBALS['WWW_ROOT']);
	} else {
		$cmpl['ROOT']      = $cmpl['BASE'] .'index.php';
		$cmpl['FULL_ROOT'] = $GLOBALS['WWW_ROOT'] .'index.php';
	}
	$cmpl['THEME_ROOT'] = $cmpl['BASE'] .'theme/'. $tname;

	$cmpl['THEME_ROOT_DISK'] = $GLOBALS['WWW_ROOT_DISK'] .'theme/'. $tname;
	$cmpl['THEME_IMAGE_ROOT'] = $cmpl['THEME_ROOT'] .'/images';

	if (defined('fud_debug') || defined('fud_logging')) {
		print "<pre>";
		print "\nBASE=". $cmpl['BASE'];
		print "\nROOT=". $cmpl['ROOT'];
		print "\nFULL_ROOT=". $cmpl['FULL_ROOT'];
		print "\nTHEME_ROOT=". $cmpl['THEME_ROOT'];
		print "\nTHEME_IMAGE_ROOT=". $cmpl['THEME_IMAGE_ROOT'];
		print "\nTHEME_ROOT_DISK=". $cmpl['THEME_ROOT_DISK'];
		print "</pre>";
	}

	$charset = $GLOBALS['DATA_DIR'] .'thm/'. $tset .'/i18n/'. $lang .'/charset';
	if (!is_file($charset)) {
		$charset = $GLOBALS['DATA_DIR'] .'thm/default/i18n/'. $lang .'/charset';
		if (!is_file($charset)) {
			$charset = $GLOBALS['DATA_DIR'] .'thm/default/i18n/en/charset';
		}
	}
	$GLOBALS['char_set'] = trim(file_get_contents($charset));

	$text_dir = $GLOBALS['DATA_DIR'] .'thm/'. $tset .'/i18n/'. $lang .'/dir';
	if (!is_file($text_dir)) {
		$text_dir = $GLOBALS['DATA_DIR'] .'thm/default/i18n/'. $lang .'/dir';
		if (!is_file($text_dir)) {
			$text_dir = $GLOBALS['DATA_DIR'] .'thm/default/i18n/en/dir';
		}
	}
	$GLOBALS['text_dir'] = trim(file_get_contents($text_dir));

	fud_mkdir($GLOBALS['WWW_ROOT_DISK'].'theme');
	file_put_contents($GLOBALS['WWW_ROOT_DISK'] .'theme/README', "Files in this directory are generated and will be overwritten!\nPlease do not edit or add files here.\nTemplate files are in the 'thm' directory.\nTo regenerate, navigate to the Theme Manager admin control panel.");

	fud_mkdir($GLOBALS['WWW_ROOT_DISK'] .'theme/'. $tname);
	fud_mkdir($GLOBALS['INCLUDE'] .'theme');
	fud_mkdir($GLOBALS['INCLUDE'] .'theme/'. $tname);

	$GLOBALS['CMP_LOC_S'] = array('{BASE}', '{ROOT}', '{FULL_ROOT}', '{THEME_ROOT}', '{THEME_ROOT_DISK}', ".'';", ".''.");
	$GLOBALS['CMP_LOC_K'] = array($cmpl['BASE'], $cmpl['ROOT'], "'.\$GLOBALS['WWW_ROOT'].'", $cmpl['THEME_ROOT'], $cmpl['THEME_ROOT_DISK'], ';', '.');

	/*
	 * Build the message array.
	 */
	$prim_msg_file = $GLOBALS['DATA_DIR'] .'thm/'. $cmpl['tset'] .'/i18n/'. $cmpl['lang'] .'/msg';
	build_msg($prim_msg_file);

	$fl = array();
	if ($cmpl['tset'] != 'default') {
		/* Check custom theme for templates not found in the default theme. */
		foreach (glob($GLOBALS['DATA_DIR'] .'/thm/'. $cmpl['tset'] .'/tmpl/*.tmpl') as $fe) {
			$fl[basename($fe)] = $fe;
		}
	}
	foreach (glob($GLOBALS['DATA_DIR'] .'/thm/default/tmpl/*.tmpl') as $fe) {
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

	// Cache injection points.
	$fp = fopen($GLOBALS['FORUM_SETTINGS_PATH'] .'theme_injection_points', 'w');
	fwrite($fp, '<?php $injection_points = '. var_export(array_unique($GLOBALS['injection_points']), 1) .'; ?>');
	fclose($fp);
	
	copyimages($tname, $tset, $lang);
	copyhelp($tname, $tset, $lang);
	copylangfuncs($tname, $tset, $lang);
}

/** Copy localized image files to the theme's image directory. */
function copyimages($tname, $tset, $lang)
{
	$path = $GLOBALS['WWW_ROOT_DISK'] .'theme/'. $tname .'/images';

	fud_mkdir($path);
	fud_copy($GLOBALS['DATA_DIR'].'thm/default/images', $path, '{*.jpg,*.gif,*.png,*.jpeg}');
	
	// Some translations don't have customized image buttons.
	if (file_exists($GLOBALS['DATA_DIR'] .'thm/default/i18n/'. $lang .'/img/flat_view.gif')) {
		fud_copy($GLOBALS['DATA_DIR'] .'thm/default/i18n/'. $lang .'/img', $path, '{*.jpg,*.gif,*.png,*.jpeg}');
	} else {
		fud_copy($GLOBALS['DATA_DIR'] .'thm/default/i18n/en/img', $path, '{*.jpg,*.gif,*.png,*.jpeg}');
	}
	if ($tset != 'default') {
		if (@file_exists($GLOBALS['DATA_DIR'] .'thm/'. $tset .'/images')) {
			fud_copy($GLOBALS['DATA_DIR'] .'thm/'. $tset .'/images', $path, '{*.jpg,*.gif,*.png,*.jpeg}');
		}
		if (@file_exists($GLOBALS['DATA_DIR'] .'thm/'. $tset .'/i18n/'. $lang .'/img')) {
			fud_copy($GLOBALS['DATA_DIR'] .'thm/'. $tset .'/i18n/'. $lang .'/img', $path, '{*.jpg,*.gif,*.png,*.jpeg}');
		}
	}
}

/** Copy help files to the theme's help directory. */
function copyhelp($tname, $tset, $lang)
{
	$path = $GLOBALS['WWW_ROOT_DISK'] .'theme/'. $tname .'/help';
	$src  = $GLOBALS['DATA_DIR'] .'thm/'. $tset .'/i18n/'. $lang .'/help';
	if (!@file_exists($src)) {
		$src = $GLOBALS['DATA_DIR'] .'thm/default/i18n/'. $lang .'/help';
		if (!@file_exists($src)) {
			$src = $GLOBALS['DATA_DIR'] .'thm/default/i18n/en/help';
		}
	}

	$rp =& $GLOBALS['__COMPILER__']['ROOT'];

	fud_mkdir($path);

	$files = glob($src .'/*.hlp', GLOB_NOSORT);
	$path .= '/';
	if (!$files) {
		throw new Exception('Failed to find help files inside \''. $src .'\'.');
	}
	foreach ($files as $file) {
		$de = basename($file);
		if (!($fp = fopen($path . $de, 'wb'))) {
			throw new Exception('Failed to write to: '. $path . $de .' <br />');
		}
		fwrite($fp, str_replace(array('{ROOT}', '{FULL_ROOT}'), 
					array($rp, "'.\$GLOBALS['WWW_ROOT'].'"), 
					file_get_contents($file))
		);
		fclose($fp);
	}
}

/** Copy language helper functions (language.inc) to the theme's directory.
  * This file will do last minute processing at run-time, for example to pluralize messages.
  * I.e. Instead of saying "1 message(s)", we say "1 message", "2 messages", etc.
  */
function copylangfuncs($tname, $tset, $lang)
{
	$path = $GLOBALS['WWW_ROOT_DISK'] .'theme/'. $tname .'/';
	$src  = $GLOBALS['DATA_DIR'] .'thm/'. $tset .'/i18n/'. $lang .'/language.inc';
	if (!@file_exists($src)) {
		$src = $GLOBALS['DATA_DIR'] .'thm/default/i18n/'. $lang .'/language.inc';
		if (!@file_exists($src)) {
			$src = $GLOBALS['DATA_DIR'] .'thm/default/i18n/en/language.inc';
		}
	}

	fud_mkdir($path);
	fud_copy(dirname($src), $path, '{language.inc}');
}

// Do not close PHP tag (prevent unwanted output in page header).
