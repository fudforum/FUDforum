<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: compiler.inc,v 1.4 2002/06/26 20:01:57 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/
	
	@set_time_limit(6000);

$GLOBALS['__COMPILER__']['ROOT'] = $GLOBALS['WWW_ROOT'];
if ( $GLOBALS['__COMPILER__']['ROOT'][strlen($GLOBALS['__COMPILER__']['ROOT'])-1] != '/' ) $GLOBALS['__COMPILER__']['ROOT'] .= '/';

$GLOBALS['__COMPILER__']['ROOT'] .= 'index.php';

function resolve_refs($file_name)
{
	if( isset($GLOBALS['file_cache'][$file_name]) ) return;
	
	/* Fetch file definition */
	$file1 = $GLOBALS['DATA_DIR'].'thm/'.$GLOBALS['__COMPILER__']['tset'].'/tmpl/'.$file_name;
	$file2 = $GLOBALS['DATA_DIR'].'thm/default/tmpl/'.$file_name;
	
	if ( is_file($file1) ) {
		$str = filetomem($file1);
		$GLOBALS['CURFILE'] = $file1;
	}
	else if ( !is_file($file2) ) {
		$str = filetomem($file2);
		$GLOBALS['CURFILE'] = $file2;
	}
	else exit('referenced file named (<b>'.$file_name.'</b>) doesn\'t exist');
	
	if( $st = strchr($str, '{PHP_FILE:') ) {
		$st = substr($st, 0, strpos($st, '}')-1);
	
		list($in,$out) = explode(';', $st);
		$GLOBALS['file_cache'][$file_name]['src'] = trim(strrchr($in, ' '));
		$GLOBALS['file_cache'][$file_name]['dst'] = trim(strrchr($out, ' '));
	}
	else {
		$GLOBALS['file_cache'][$file_name]['inline'] = 1;
	}
	
	$start = $str;
	parse_sections($str, $GLOBALS['file_cache'][$file_name]['dst']);
	while ( $start = strchr($start, '{REF:') ) 
	{
		$endp = strpos($start, '}');
		$new_name = trim(substr($start, 5, $endp-5));

		if( !isset($GLOBALS['file_cache'][$file_name]['include'][$new_name]) ) 		
			$GLOBALS['file_cache'][$file_name]['include'][$new_name] = $new_name;		

		$start = substr($start, $endp);
		resolve_refs($new_name);
	}	
}
	
function parse_sections($str, $file)
{
	$start = $str;
	
	$PHP_FILE = ( !empty($file) && substr($file, -4) == '.php') ? 1 : 0;
	
	while ( $start = strchr($start, '{') ) 
	{
		$endp = strpos($start, '}')+1;
		$tag_entry = substr($start, $start, $endp);
		$start = substr($start, $endp);
		
		$pos = strpos($tag_entry, ':');
		$tag = substr($tag_entry, 1, $pos-1);
		$tag_entry = trim(substr($tag_entry, $pos+1));		
		$pos=strpos($tag_entry, '}');
		$pos2=strpos($tag_entry, ' ');
		
		if ( is_integer($pos2) && ($pos2<$pos) ) {
			$pos = $pos2;
		}
		
		$name = substr($tag_entry, 0, $pos);
		
		switch ( $tag ) 
		{
			case 'MAIN_SECTION':
			case 'PAGE':
			case 'SECTION':
				$end = strpos($start, '{'.$tag.': END}');
				$section_data = substr($start, 0, $end);
				$start = substr($start, $end+strlen('{'.$tag.': END}'));
				
				if( isset($GLOBALS['TC'][$tag][$name]) ) {
					echo "DUPE: $tag -> $name found in ".$GLOBALS['TC'][$tag][$name]['file']." (now parsing ".$GLOBALS['CURFILE'].")\n";
					exit;
				}
				
				if( $PHP_FILE ) $GLOBALS['PHP_FILE_SEC'][$tag][$name]=1;
				
				$GLOBALS['TC'][$tag][$name]['file'] = $GLOBALS['CURFILE'];
				$GLOBALS['TC'][$tag][$name]['data'] = trim($section_data);
				break;
		}
	}	
}

function resolve_sections($str)
{
	$start = $str;
	$ret = '';
	while ( $start = strchr($start, '{') ) 
	{
		$ret .= substr($str, 0, strlen($str)-strlen($start));
	
		$chr = ord($start[1]);
		if ( $chr<ord('A') || $chr>ord('Z') ) {
			$ret .= '{';
			$str = $start = substr($start, 1);
			continue;		
		}
		
		$endp = strpos($start, '}')+1;
		$tag_entry = substr($start, $start, $endp);
		$start = substr($start, $endp);
		$str = $start;
		
		$pos = strpos($tag_entry, ':');
		$tag = substr($tag_entry, 1, $pos-1);
		$tag_entry = trim(substr($tag_entry, $pos+1));		
		$pos1=strpos($tag_entry, '}');
		$pos2=strpos($tag_entry, ' ');
		
		$pos = (is_integer($pos2) && ($pos2<$pos1)) ? $pos2 : $pos1;
		
		$name = substr($tag_entry, 0, $pos);
		switch ( $tag ) 
		{
			case 'MSG':
				if( !isset($GLOBALS['MSG'][$name]) ) {
					echo "Missing Message entry $name inside i18n/".$GLOBALS['__COMPILER__']['lang']."/msg\n";
					exit;				
				}
				$ret .= $GLOBALS['MSG'][$name]['data'] =  resolve_sections($GLOBALS['MSG'][$name]['data']);
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
			case 'VAR-FORM':
				$ret .= '\'.stripslashes($'.$name.').\'';
				break;
			case 'ALT-VAR':
				list($ia,$ib,$ic) = explode(' ', substr($tag_entry,0, $pos1), 3);
				$ret .= '\'.alt_var(\''.$ia.'\',\''.$ib.'\',\''.$ic.'\').\'';
				break;	
			case 'DATE':
				$date_str = ltrim(substr($tag_entry, $pos2, ($pos1-$pos2)));
				$ret .= (($name[0] != '!') ? '\'.strftime("'.$date_str.'", $'.$name.').\'' : '\'.strftime("'.$date_str.'", '.substr($name,1).').\'');
				
				//$ret .= '\'.strftime("'.$date_str.'", $'.$name.').\'';
				break;	
			case 'TEMPLATE':
				if ( isset($GLOBALS['TC']['SECTION'][$name]) ) $sec = 'SECTION';
				else if ( isset($GLOBALS['TC']['MAIN_SECTION'][$name]) ) $sec = 'MAIN_SECTION';
				else if ( isset($GLOBALS['TC']['PAGE'][$name]) ) $sec = 'PAGE';
				else {
					echo "Undefined sec: $name\n";
					exit;
				}
				$ret .= $GLOBALS['TC'][$sec][$name]['data'] = resolve_sections($GLOBALS['TC'][$sec][$name]['data']);
				break;
			case 'META-CONTENT-LANGUAGE':
				$ret .= '<META HTTP-EQUIV="Content-Type" CONTENT="text/html; charset='.$GLOBALS['char_set'].'">';
				break;	
			case 'ROOT':
				$ret .= $GLOBALS['__COMPILER__']['ROOT'];
				break;
			case 'THEME_ROOT':
			case 'THEME_IMAGE_ROOT':
				$ret .= $GLOBALS['__COMPILER__'][$tag];
				break;
			default:
				exit("\nNo such tag: $tag -> $name\n");
		}
	}
	
	return $ret.$str;
}

function add_sql_prefix($s)
{
	return str_replace('{SQL_TABLE_PREFIX}', $GLOBALS['DBHOST_TBL_PREFIX'], $s);
}

function cleanup_php($s)
{
	$s = str_replace(".''", '' , $s);
	return str_replace(" ''.", ' ', $s);
}

function compile_file($source)
{
	if ( empty($source) ) exit("No file passed to compile, fatal error");
	$file_data = filetomem(realpath($GLOBALS['DATA_DIR'].'src/'.$source));
	if( $dest[0] == '@' || $dest[0] == '!' ) $dest = substr($dest,1);
	
	if( strpos($file_data, '{TEMPLATE:') === false ) {
		return add_sql_prefix($file_data);
	}
	
	$start = $str = $file_data;
	$ret = '';
	while ( $start = strchr($start, '{TEMPLATE:') ) 
	{
		$ret .= substr($str, 0, strlen($str)-strlen($start));
		$name = trim(substr($start, 10, strpos($start, '}', 10)-10));
		$str = $start = substr($start,strpos($start, '}')+1);
		
		if ( isset($GLOBALS['TC']['SECTION'][$name]) ) $sec = 'SECTION';
		else if ( isset($GLOBALS['TC']['MAIN_SECTION'][$name]) ) $sec = 'MAIN_SECTION';
		else if ( isset($GLOBALS['TC']['PAGE'][$name]) ) $sec = 'PAGE';
		else {
			echo "Undefined sec: $name\n";
			exit;
		}
		$GLOBALS['TC'][$sec][$name]['data'] = resolve_sections($GLOBALS['TC'][$sec][$name]['data']);
		if( $sec == 'PAGE' ) {
			$GLOBALS['TC'][$sec][$name]['data'] = str_replace("'.", '<?php echo ', $GLOBALS['TC'][$sec][$name]['data']);
			$GLOBALS['TC'][$sec][$name]['data'] = str_replace(".''", ".'", $GLOBALS['TC'][$sec][$name]['data']);
			$GLOBALS['TC'][$sec][$name]['data'] = str_replace(".'", '; ?>', $GLOBALS['TC'][$sec][$name]['data']);
		}
		
		$ret .= $GLOBALS['TC'][$sec][$name]['data'];
	}
	
	return add_sql_prefix($ret.$str);
}

function generate_file($php_file)
{
	$themes_base = $GLOBALS['WWW_ROOT_DISK'].'theme/';
	if ( !is_dir($themes_base) ) {
		$u = umask(0);
		mkdir($themes_base, 0777);
		umask($u);
	}
	
	$themes_base = $GLOBALS['INCLUDE'].'theme/';
	if ( !is_dir($themes_base) ) {
		$u = umask(0);
		mkdir($themes_base, 0777);
		umask($u);
	}
	
	$dir = $GLOBALS['WWW_ROOT_DISK'].'theme/'.$GLOBALS['__COMPILER__']['tname'];
	if ( !is_dir($dir) ) {
		$u = umask(0);
		if ( !@mkdir($dir, 0777) ) {
			exit("unable to make directory for this theme (<b>$dir</b>)<br>\n");
		}
		umask($u);
	}
	
	$dir = $GLOBALS['INCLUDE'].'theme/'.$GLOBALS['__COMPILER__']['tname'];
	if ( !is_dir($dir) ) {
		$u = umask(0);
		if ( !@mkdir($dir, 0777) ) {
			exit("unable to make directory for this theme (<b>$dir</b>)<br>\n");
		}
		umask($u);
	}
	
	if( !isset($GLOBALS['file_cache'][$php_file]['compiled']) && !isset($GLOBALS['file_cache'][$php_file]['inline']) ) {
		$GLOBALS['file_cache'][$php_file]['compiled'] = compile_file($GLOBALS['file_cache'][$php_file]['src']);
	}
	
	$file_data = $GLOBALS['file_cache'][$php_file]['compiled'];
	
	if( substr($GLOBALS['file_cache'][$php_file]['dst'], -4) != '.inc' ) {
		$php_code_start = $php_code_end = $php_code_middle = '';
	
		if( is_array($GLOBALS['file_cache'][$php_file]['include']) ) {
			reset($GLOBALS['file_cache'][$php_file]['include']);
			while( list(,$v2) = each($GLOBALS['file_cache'][$php_file]['include']) ) {
				if( $v2 == $php_file ) continue;
			
				$k = $v2;
				$v = $GLOBALS['file_cache'][$v2];
		
				if( empty($v['compiled']) && !isset($GLOBALS['file_cache'][$v2]['inline']) ) {
					$v['compiled'] = $GLOBALS['file_cache'][$v2]['compiled'] = compile_file($GLOBALS['file_cache'][$v2]['src']);
				}
		
	        		if( isset($v['inline']) ) continue;
	        		
				$s = strpos($v['compiled'], '*/')+2;
				$e = strrpos($v['compiled'], '?>');

	       			switch( $v['dst'][0] )
	       			{
	        			case '!':
	        				$php_code_end .= cleanup_php(trim(substr($v['compiled'], $s, ($e-$s))));
	        				break;
	        			case '@':
	        				$php_code_middle .= cleanup_php(trim(substr($v['compiled'], $s, ($e-$s))));
	        				break;
	        			default:
						$php_code_start .= cleanup_php(trim(substr($v['compiled'], $s, ($e-$s))));
		        	}
			}
	
			$file_data = str_replace('{PRE_HTML_PHP}', $php_code_start, $file_data);
			$file_data = str_replace('{POST_HTML_PHP}', $php_code_middle.'/* FILE START */', $file_data);
			$file_data = str_replace('{POST_PAGE_PHP_CODE}', $php_code_end, $file_data);
		}
		
		$dir = $GLOBALS['WWW_ROOT_DISK'].'theme/'.$GLOBALS['__COMPILER__']['tname'].'/';
		$dst = $GLOBALS['file_cache'][$php_file]['dst'];
	}
	else {
		$dir = $GLOBALS['INCLUDE'].'theme/'.$GLOBALS['__COMPILER__']['tname'].'/';
		if( $GLOBALS['file_cache'][$php_file]['dst'][0] == '!' || $GLOBALS['file_cache'][$php_file]['dst'][0] == '@' ) 
			$dst = substr($GLOBALS['file_cache'][$php_file]['dst'],1);
		else
			$dst = $GLOBALS['file_cache'][$php_file]['dst'];	
	}
	
	$file_data = str_replace('{ROOT}', $GLOBALS['__COMPILER__']['ROOT'], $file_data);
	$file_data = str_replace('{THEME_ROOT}', $GLOBALS['__COMPILER__']['THEME_ROOT'], $file_data);
	$file_data = str_replace('{THEME_ROOT_DISK}', $GLOBALS['__COMPILER__']['THEME_ROOT_DISK'], $file_data);
	
	if ( $dst[0] == '#' ) {
		$dst = substr($dst, 1);
		$dir = $GLOBALS['WWW_ROOT_DISK'];
	}
	
	if( !($fp = fopen($dir.$dst, 'wb')) ) {
		echo "Couldn't write to $dir$dst\n";
		exit;
	}
		fwrite($fp, $file_data);
	fclose($fp);
}

function build_msg($file)
{
	if ( isset($GLOBALS['MSG_FILES'][$file]) ) return;
	$GLOBALS['MSG_FILES'][$file] = 1;
	$fp = fopen($file, 'rb');
	$l=0;
	while( $str = fgets($fp, 20000) ) {
		++$l;
		list($msg, $data) = explode(':', $str, 2);
		if ( empty($msg) ) continue;
		if ( isset($GLOBALS['MSG'][$msg]) && $GLOBALS['MSG'][$msg]['file'] != $file ) continue;
		
		if( isset($GLOBALS['MSG'][$msg]) ) {
			echo "duplicated message: $msg in $file  on line #$l, previous entry found on line #".$GLOBALS['MSG'][$msg]['line']." in ".$GLOBALS['MSG'][$msg]['file']."\n";
			exit;	
		}
	
		$GLOBALS['MSG'][$msg]['data'] = substr(ltrim($data), 0, -1);
		$GLOBALS['MSG'][$msg]['file'] = $file;
		if( strpos($GLOBALS['MSG'][$msg]['data'], '\n') ) $GLOBALS['MSG'][$msg]['data'] = str_replace('\n', "\n", $GLOBALS['MSG'][$msg]['data']);
		$GLOBALS['MSG'][$msg]['line'] = $l;
	}
	fclose($fp);
}

function resolve_inc_refs($file)
{
	if( is_array($GLOBALS['file_cache'][$file]['include']) ) {
		reset($GLOBALS['file_cache'][$file]['include']);
		$ar = array();
		while( list($k,) = each($GLOBALS['file_cache'][$file]['include']) ) {
			if( !isset($GLOBALS['file_cache'][$k]['include']) ) 
				$ar[$k] = $k;
			else {
				if( !isset($GLOBALS['file_cache'][$k]['resolved']) )
					$ar = array_merge($ar, resolve_inc_refs($k));
				else
					$ar = array_merge($ar, $GLOBALS['file_cache'][$k]['include']);	
			}	
		}
		$ar[$file] = $file;
		return $ar;
	}
	else
		return array($file=>$file);
		
	return;	
}

function resolve_compile($file)
{
	if( isset($GLOBALS['file_cache'][$file]) ) return;
	resolve_refs($file,$file);
	
	if( !isset($GLOBALS['file_cache'][$file]['resolved']) ) {
		$GLOBALS['file_cache'][$file]['include'] = resolve_inc_refs($file);
		$GLOBALS['file_cache'][$file]['resolved'] = 1;
	}
	$GLOBALS['file_cache'][$file]['compiled'] = compile_file($GLOBALS['file_cache'][$file]['src']);
}

function compile_all($tset='default', $lang='english', $tname='default')
{
	$oldmask = umask(0133);
	$olddir = getcwd();
	copy($GLOBALS['DATA_DIR'].'sql/'.__dbtype__.'/db.inc', $GLOBALS['DATA_DIR'].'src/db.inc.t');
	$GLOBALS['__COMPILER__']['tset'] = $tset;
	$GLOBALS['__COMPILER__']['lang'] = $lang;
	$GLOBALS['__COMPILER__']['tname'] = $tname;
	
	$GLOBALS['__COMPILER__']['THEME_ROOT'] = $GLOBALS['WWW_ROOT'];
	if ( $GLOBALS['__COMPILER__']['THEME_ROOT'][strlen($GLOBALS['__COMPILER__']['THEME_ROOT'])-1] != '/' ) $GLOBALS['__COMPILER__']['THEME_ROOT'] .= '/';
	$GLOBALS['__COMPILER__']['THEME_ROOT'] .= 'theme/'.$tname;
	$GLOBALS['__COMPILER__']['THEME_ROOT_DISK'] .= $GLOBALS['WWW_ROOT_DISK'].'theme/'.$tname;
	$GLOBALS['__COMPILER__']['THEME_IMAGE_ROOT'] = $GLOBALS['__COMPILER__']['THEME_ROOT'].'/images';
	
	$file1 = $GLOBALS['DATA_DIR'].'thm/'.$GLOBALS['__COMPILER__']['tset'].'i18n/'.$GLOBALS['__COMPILER__']['lang'].'/charset';
	$file2 = $GLOBALS['DATA_DIR'].'thm/default/i18n/'.$GLOBALS['__COMPILER__']['lang'].'/charset';
	
	
	$u = umask(0);
	if ( !is_dir($GLOBALS['WWW_ROOT_DISK'].'theme') ) 
		mkdir($GLOBALS['WWW_ROOT_DISK'].'theme', 0700);
	
	if ( !is_dir($GLOBALS['__COMPILER__']['THEME_ROOT_DISK']) ) 
		mkdir($GLOBALS['__COMPILER__']['THEME_ROOT_DISK'], 0700);
	
	umask($u);
	
	$charset = ( is_file($file1) ) ? $file1 : $file2;
	
	$GLOBALS['char_set'] = trim(filetomem($charset));

	$prim_msg_file = $GLOBALS['DATA_DIR'].'thm/'.$GLOBALS['__COMPILER__']['tset'].'/i18n/'.$GLOBALS['__COMPILER__']['lang'].'/msg';
	$back_msg_file = $GLOBALS['DATA_DIR'].'thm/default/i18n/'.$GLOBALS['__COMPILER__']['lang'].'/msg';

	/*
	 * build the message array 
	 */
	if( @file_exists($prim_msg_file) ) build_msg($prim_msg_file);
	/*
	 * append any missing entries from the default language file
	 */
	build_msg($back_msg_file);

	chdir($GLOBALS['DATA_DIR'].'/thm/default/tmpl');
	$dir = opendir('.');
	readdir($dir); readdir($dir);
	while( $file = readdir($dir) ) {
		if( substr($file, -5) != '.tmpl' ) continue;
		resolve_compile($file);
		if( isset($GLOBALS['file_cache'][$file]['inline']) ) continue;
		
		generate_file($file);
		
		if( is_array($GLOBALS['PHP_FILE_SEC']) ) { 
			reset($GLOBALS['PHP_FILE_SEC']);
			while( list($k,$v) = each($GLOBALS['PHP_FILE_SEC']) ) {
				reset($v);
 				while ( list($k2,) = each($v) ) unset($GLOBALS['TC'][$k][$k2]);
			}	
			unset($GLOBALS['PHP_FILE_SEC']);
		}	
		unset($GLOBALS['INCLUDE_LIST']);
	}
	
	@umask($oldmask);
	@chdir($olddir);
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

function fudcopy($src, $dest, $mask='!.*!i', $recur=FALSE)
{
	if ( $dest[strlen($dest)-1] != '/' ) $dest .= '/';
	$oldpwd = getcwd();
	if ( $dest[0] != '/' ) $dest = $oldcwd.$dest;
	chdir($src);
	$dp = opendir('.');
	readdir($dp); readdir($dp);
	while ( $de = readdir($dp) ) {
		if ( !preg_match($mask, $de, $regs) ) continue;
		if ( is_dir($de) && $recur == TRUE ) {
			$rdest = $dest.$de;
			if ( !@mkdir($rdest, 0777) ) exit("can't mkdir ($rdest)<br>\n");
			if ( $rdest[0] != '/' ) $rdest = '../'.$rdest;
			fudcopy($de, $rdest, $mask, $recur);
		}
		else {
			if ( !@copy($de, $dest.$de) ) {
				echo "can't copy in (".getcwd().") a file named (".$de.") to (".$dest.$de.")<br>\n";
			}
			else 
				chmod($dest.$de, 0666);
		}
	}
	chdir($oldpwd);
}

function copyimages($thm)
{
	if ( !is_dir($GLOBALS['WWW_ROOT_DISK'].'theme/'.$thm->name.'/images') ) 
		mkdir($GLOBALS['WWW_ROOT_DISK'].'theme/'.$thm->name.'/images', 0777);
		
	fudcopy($GLOBALS['DATA_DIR'].'thm/default/images', $GLOBALS['WWW_ROOT_DISK'].'theme/'.$thm->name.'/images', '!\.(gif|jpeg|png|jpg)$!i');
	fudcopy($GLOBALS['DATA_DIR'].'thm/default/i18n/'.$thm->lang.'/img', $GLOBALS['WWW_ROOT_DISK'].'theme/'.$thm->name.'/images', '!\.(gif|jpeg|png|jpg)$!i');
	fudcopy($GLOBALS['DATA_DIR'].'thm/'.$thm->theme.'/images', $GLOBALS['WWW_ROOT_DISK'].'theme/'.$thm->name.'/images', '!\.(gif|jpeg|png|jpg)$!i');
	fudcopy($GLOBALS['DATA_DIR'].'thm/'.$thm->theme.'/i18n/'.$thm->lang.'/img', $GLOBALS['WWW_ROOT_DISK'].'theme/'.$thm->name.'/images', '!\.(gif|jpeg|png|jpg)$!i');
}

function copyhelp($thm)
{
	
	$u = umask(0);
	if ( !is_dir($GLOBALS['WWW_ROOT_DISK'].'theme/'.$thm->name.'/help') ) 
		mkdir($GLOBALS['WWW_ROOT_DISK'].'theme/'.$thm->name.'/help', 0777);
		
	$oldpwd = getcwd();

	chdir($GLOBALS['DATA_DIR'].'thm/'.$thm->theme.'/i18n/'.$thm->lang.'/help');
	$dp = opendir('.');
	readdir($dp); readdir($dp);
	while ( $de = readdir($dp) ) {
		if ( !is_file($de) || substr($de, -4) != '.hlp' ) continue;
		$str = filetomem($de);
		$str = str_replace('{ROOT}', $GLOBALS['__COMPILER__']['ROOT'], $str);
		$fp = fopen($GLOBALS['WWW_ROOT_DISK'].'theme/'.$thm->name.'/help/'.$de, 'wb');
		fwrite($fp, $str);
		chmod($GLOBALS['WWW_ROOT_DISK'].'theme/'.$thm->name.'/help/'.$de, 0666);
		fclose($fp);
	}
	closedir($dp);
	umask($u);
}
?>