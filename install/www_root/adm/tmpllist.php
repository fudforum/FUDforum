<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: tmpllist.php,v 1.2 2002/06/18 14:20:39 hackie Exp $
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
	define('admin_form', 1);
	
	include_once "GLOBALS.php";
	fud_use('db.inc');
	fud_use('static/adm.inc');
	list($ses, $usr) = initadm();

function minimize($file)
{
	global $max_list;
	
	$tmp = str_replace('::', '', str_replace($file, '', $max_list));
	if( $tmp == ':' ) $tmp = '';
	
	return $tmp;
}

function maximize($file)
{
	global $max_list;
	
	if( !$max_list ) 
		$tmp = urlencode($file);
	else
		$tmp = $max_list.':'.urlencode($file);	

	$tmp .= '#'.$file;

	return $tmp;
}

function goto_tmpl($tmpl)
{
	global $max_list;
	
	if( !preg_match('!(^|:)'.$tmpl.'!', $max_list) ) $max_list .= ':'.$tmpl;

	return $max_list.'#'.$tmpl;
}

function tmpllist_resolve_refernce($refs, &$file)
{
	global $deps;
	
	reset($refs);
	while( list($k, $v) = each($refs) ) {
		if( is_array($deps[$k]) ) tmpllist_resolve_refernce($deps[$k], $file);
		$file[$k] = $v;
	}
}

	if ( empty($tname) || empty($tlang) ) {
		header("Location: admthemesel.php?ret=tmpllist&"._rsid."&rand=".get_random_value());
		exit();
	}

	if( empty($max_all) ) {
		$max_list = stripslashes($max_list);
		$tmp = explode(':', $max_list);
		$max_opts = '';
		while( list(,$v) = each($tmp) ) 
			if( $v ) $max_opts[$v] = 1;
	}
	
	$f_path = $GLOBALS['DATA_DIR'].'thm/'.$tname.'/tmpl/'.$fl;
	if( $edit ) {
		if( empty($fl) ) 
			exit("Missing template name<br>\n");
		else if( !@file_exists($f_path) )
			exit("Non-existent template<br>\n");
		else if( !($data = @filetomem($f_path)) ) 
			exit("Could not open template<br>\n");
	
		if( !empty($msec) ) {
			$begin = '(PAGE|MAIN_SECTION)';
			$tmpl_name = $msec;
			$arg = 2;
		}	
		else if( !empty($sec) ) {
			$begin = '(SECTION)';
			$tmpl_name = $sec;
			$arg = 2;
		}
		else
			exit("Section parameter not avaliable<br>\n");		
	
		if( !preg_match('!{'.$begin.': '.$tmpl_name.'( .*?)?}(.*?){'.$begin.': END}!is', $data, $regs) ) 
			exit("Couldn't locate template $tmpl_name inside $fl<br>\n");

		if( !$submitted ) { 
			$tmpl_data = $regs[$arg+1];
		}
		else {
			$tmpl_data = trim(stripslashes(str_replace("\r", "", $tmpl_data)));
			$tmpl_data_bk = $tmpl_data;
			$tmpl_data = '{'.$regs[1].': '.$tmpl_name.' '.$regs[$arg]."}\n".$tmpl_data."\n{".$regs[1].": END}";
	
			$fp = fopen($f_path, "wb");
			fwrite($fp, str_replace($regs[0], $tmpl_data, $data));
			fclose($fp);
			@chmod($f_path,0600);
			$tmpl_data = $tmpl_data_bk;
			fud_use('static/compiler.inc');
			$r = Q("SELECT * FROM ".$GLOBALS['MYSQL_TBL_PREFIX']."themes WHERE theme='$tname' AND lang='$tlang'");
			while ( $obj = DB_ROWOBJ($r) )
				compile_all($obj->theme, $obj->lang, $obj->name);
			QF($r);
			exit('<br><a href="tmpllist.php?S='._rsid.'">Back to control panel</a>');
		}
		
		$msg_list = '';
		if( preg_match_all('!{MSG: (.*?)}!is', $tmpl_data, $regs, PREG_SET_ORDER) ) {
			while( list(,$v) = each($regs) ) $msg_list .= urlencode($v[1]).':';
			$msg_list = ' <font size="-1">[ <a href="#" onClick="javascript: window_open(\'msglist.php?tname='.$tname.'&tlang='.$tlang.'&'._rsid.'&NO_TREE_LIST=1&msglist='.substr($msg_list, 0, -1).'\', \'tmpl_msg\', 600,300);">Edit Text Messages</a> ]</font>';
		}
	}
	include('admpanel.php');
?>
<script language="JavaScript" src="../lib.js"></script>
<style>
.file_name {
	font-weight: bold;
	color: #ff0000;
	font-size: small;
	text-decoration: none;
}
.msec {
	color: #bb0088;
	font-size: small;
	text-decoration: underline;
}

.sec {
	color: #446644;
	font-size: small;
	text-decoration: underline;
}

.deps {
	color: #00AA00;
	font-size: small;
	text-decoration: dashed;
}

.depson {
	color: #CC6600;
	font-size: small;
	text-decoration: dashed;
}
</style>
<table width="100%" border=1 cellspacing=2 cellpadding=2>
<tr>
<td nowrap>
<?php	
	$oldcwd = getcwd();
	chdir($DATA_DIR.'thm/'.$tname.'/tmpl');
	$dp = opendir('.');
	readdir($dp); readdir($dp);
	while( $file = readdir($dp) ) {
		if( substr($file, -5) == '.tmpl' ) {
			$fp = fopen($file, 'rb');
				$data = fread($fp, __ffilesize($fp));
			fclose($fp);
			
			preg_match('!{PHP_FILE: input: .*?; output: (.*?);}!', $data, $res);
			
			if( $res[1][0] == '@' || $res[1][0] == '!' ) 
				$file = substr($res[1], 1);			
			else
				$file = $res[1];
				
			$file = str_replace(".inc", ".tmpl", $file);	

			preg_match_all('!{REF: (.*?)}!s', $data, $matches, PREG_SET_ORDER);
			while( list(,$v) = each($matches) ) $deps[$file][$v[1]] = 1;
		}
	}
	
	
	$php_deps = array();
	
	reset($deps);
	while( list($k, $v) = each($deps) ) {
		tmpllist_resolve_refernce($v, $deps[$k]);
		$php_deps[str_replace('.php', '.tmpl', $k)] = $deps[$k];
	}
	unset($deps);
	reset($php_deps);

	rewinddir($dp);
	readdir($dp); readdir($dp);
	/* Drawing Code */
	
	$file_info_array = array();
	
	while ( $de = readdir($dp) ) {
		if( substr($de,-5) != '.tmpl' ) continue;
		
		$data = filetomem($tmpl_dir.$de);
		if ( !preg_match_all('!{(PAGE|MAIN_SECTION): (.*?)( .*?)?}.*?{(PAGE|MAIN_SECTION): END}!is', $data, $regs, PREG_SET_ORDER) ) continue;
		
		if( $max_all || $max_opts[$de] ) { 
			$file_info_array[$de] = '<a class="file_name" href="tmpllist.php?tname='.$tname.'&tlang='.$tlang.'&'._rsid.'&max_list='.minimize($de).'"><acronym title="minimize">[ - ]</acronym></a> <b>'.$de.'</b> <a name="'.$de.'">&nbsp;</a><br>';
			while ( list(, $v) = each($regs) ) {
				$file_info_array[$de] .= '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<font size="-1">&raquo;</font> <a class="msec" href="tmpllist.php?tname='.$tname.'&tlang='.$tlang.'&'._rsid.'&edit=1&fl='.$de.'&msec='.urlencode($v[2]).'&max_list='.$max_list.'">'.$v[2].'</a>';
				if( empty($edit) && $v[3] ) $file_info_array[$de] .= '<font size="-1" color="#008800">&nbsp;&nbsp;-&gt;&nbsp;&nbsp;'.$v[3].'</font>';
				$file_info_array[$de] .= '<br>';
				
				$file_info_help[$v[2]] = $v[3];
			}
		
			if ( preg_match_all('!{SECTION: (.*?)( .*?)?}.*?{SECTION: END}!s', $data, $regs, PREG_SET_ORDER) ) {
				while ( list(,$v) = each($regs) ) {
					$file_info_array[$de] .= '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<font size="-1">&raquo;</font> <a class="sec" href="tmpllist.php?tname='.$tname.'&tlang='.$tlang.'&'._rsid.'&edit=1&fl='.$de.'&sec='.urlencode($v[1]).'&max_list='.$max_list.'">'.$v[1].'</a>';
					if( empty($edit) && $v[2] ) $file_info_array[$de] .= '<font size="-1" color="#008800">&nbsp;&nbsp;-&gt;&nbsp;&nbsp;'.$v[2].'</font>';
					$file_info_array[$de] .= '<br>';					
					$file_info_help[$v[1]] = $v[2];
				}
			}
		}
		else {
			$file_info_array[$de] = '<a class="file_name" href="tmpllist.php?tname='.$tname.'&tlang='.$tlang.'&'._rsid.'&max_list='.maximize($de).'"><acronym title="maximize">[ + ]</acronym></a> <b>'.$de.'</b> <a name="'.$de.'">&nbsp;</a>';
		}	
	}
	closedir($dp);

	reset($php_deps);
	$deps_on = array();
	while( list($k, $v) = each($php_deps) ) {
		while( list($k2, ) = each($v) ) {
			$deps_on[$k2][] = $k; 
		}	
	}
	reset($php_deps);
	reset($deps_on);

	if( !empty($fl) ) {
		$tmp = $file_info_array;
		$file_info_array =  array();
		$tmp2[$fl] = $tmp[$fl];
		unset($tmp[$fl]);
		$file_info_array = array_merge($tmp2, $tmp);
	}

	sort($file_info_array);
	reset($file_info_array);

	while( list($k,$v) = each($file_info_array) ) {
		echo $v;
		if( ($max_all || $max_opts[$k]) && ($php_deps[$k] || $deps_on[$k]) ) {
			if( is_array($php_deps[$k]) ) {
				$deps = '';
				while( list($k2,) = each($php_deps[$k]) ) {
					if( $file_info_array[$k2] ) $deps .= '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<font size="-1">&raquo;</font> <a href="tmpllist.php?tname='.$tname.'&tlang='.$tlang.'&'._rsid.'&max_list='.goto_tmpl($k2).'" class="deps">'.$k2.'</a><br>';
				}
			
				if( !empty($deps) ) echo '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<font size="-1">&raquo;</font> <font size="-1" color="#00AA00"><b>Dependencies</b></font><br>'.$deps;
			}	
			
			if( is_array($deps_on[$k]) ) {
				$dp = '';
				while( list(,$k2) = each($deps_on[$k]) ) {
					if( $file_info_array[$k2] ) $dp .= '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<font size="-1">&raquo;</font> <a href="tmpllist.php?tname='.$tname.'&tlang='.$tlang.'&'._rsid.'&max_list='.goto_tmpl($k2).'" class="depson">'.$k2.'</a><br>';
				}	
				
				if( !empty($dp) ) echo '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<font size="-1">&raquo;</font> <font size="-1" color="#CC6600"><b>Used By</b></font><br>'.$dp;
			}
		}
		echo '<br>';
	}
?>
</td>
<?php if($edit) { ?>
<td width="100%" valign="top">
<font color="#008800"><b>Explanation:</b> <?php if($file_info_help[$msec.$sec]) echo $file_info_help[$msec.$sec]; ?></font><br>
<table cellspacing=2 cellpadding=1 border=0>
<form method="post" action="tmpllist.php?tname=<? echo $tname; ?>&tlang=<? echo $tlang; ?>" name="tmpledit">
<?php echo _hs; ?>
<tr>
	<td>
		<b><?php echo $tmpl_name; ?></b>:<?php echo $msg_list; ?><br>
		<textarea rows="20" cols="60" name="tmpl_data"><?php echo htmlspecialchars($tmpl_data); ?></textarea>
	</td>
</tr>
<tr>
	<td align="right"><input type="reset" name="reset" value="Undo Changes">&nbsp;&nbsp;&nbsp;<input type="Submit" name="Submit" value="Save Changes"></td>
</tr>
<input type="hidden" name="msec" value="<?php echo $msec; ?>">
<input type="hidden" name="max_list" value="<?php echo $max_list; ?>">
<input type="hidden" name="sec" value="<?php echo $sec; ?>">
<input type="hidden" name="submitted" value="1">
<input type="hidden" name="edit" value="1">
<input type="hidden" name="fl" value="<?php echo $fl; ?>">
</form>
</table>
</td>
</tr>
<?php } ?>
</table>
<?php chdir($oldcwd); readfile('admclose.html'); ?>