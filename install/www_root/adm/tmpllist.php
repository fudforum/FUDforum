<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: tmpllist.php,v 1.14 2003/04/25 01:43:02 hackie Exp $
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
	
	require('GLOBALS.php');
	fud_use('adm.inc', true);

function minimize($file, $maxl)
{
	if ($file == $maxl) {
		return '';
	} else if (($p = strpos($maxl, $file)) !== FALSE) {
		return ($p ? str_replace(':'. $file, '', $maxl) : str_replace($file. ':', '', $maxl));
	}
	return $maxl;
}

function maximize($file, $maxl)
{
	return ($maxl ? $maxl . ':' . urlencode($file) : urlencode($file)) . '#'. $file;
}

function fetch_section($data, $file, $section, $type)
{
	if ($type == 'MAIN') {
		if (($p = strpos($data, '{PAGE: '.$section)) === FALSE) {
			$p = strpos($data, '{MAIN_SECTION: '.$section);
			$end = '{PAGE: END}';
		} else {
			$end = '{MAIN_SECTION: END}';
		}
	} else {
		$p = strpos($data, '{SECTION: '.$section);
		$end = '{SECTION: END}';
	}
	if ($p === FALSE) {
		return FALSE;
	}
	if (($te = strpos($data, '}', $p)) === FALSE) {
		return FALSE;
	}
	$ti = explode(' ', substr($data, $p, ($te - $p)), 3);
	
	if (($ef = strpos($data, $end, $te)) === FALSE) {
		return FALSE;
	}

	$ret['offset'] = ++$te;
	$ret['len'] = $ef - $ret['offset'];
	$ret['data'] = substr($data, $te, $ret['len']);
	if (isset($ti[2]) && ($ti[2] = trim($ti[2]))) {
		$ret['comment']	= $ti[2];
	}
	return $ret;
}	

function goto_tmpl($tmpl)
{
	global $max_list;
	
	if( !preg_match('!(^|:)'.$tmpl.'!', $max_list) ) $max_list .= ':'.$tmpl;

	return $max_list.'#'.$tmpl;
}

	$tname = isset($_POST['tname']) ? $_POST['tname'] : (isset($_GET['tname']) ? $_GET['tname'] : '');
	$tlang = isset($_POST['tlang']) ? $_POST['tlang'] : (isset($_GET['tlang']) ? $_GET['tlang'] : '');
	$edit = isset($_POST['edit']) ? $_POST['edit'] : (isset($_GET['edit']) ? $_GET['edit'] : '');

	if (!$tname || !$tlang) {
		header('Location: admthemesel.php?ret=tmpllist&'._rsidl);
		exit;
	}

	if (isset($_GET['max_list'])) {
		$max_opts[strtok($_GET['max_list'], ':')] = 1;
		while (($v = strtok(':'))) {
			$max_opts[$v] = 1;
		}
		$max_list = $_GET['max_list'];
	} else {
		$max_list = '';
	}
	
	
	if (isset($_GET['fl'])) {
		$fl = $_GET['fl'];
		$sec = isset($_GET['sec']) ? $_GET['sec'] : '';
		$msec = isset($_GET['msec']) ? $_GET['msec'] : '';
	} else if (isset($_POST['fl'])) {
		$fl = $_POST['fl'];
		$sec = isset($_POST['sec']) ? $_POST['sec'] : '';
		$msec = isset($_POST['msec']) ? $_POST['msec'] : '';
	}

	if ($edit) {
		if (!isset($fl)) {
			exit('Missing template name<br>');
		}
		$f_path = $GLOBALS['DATA_DIR'].'thm/'.$tname.'/tmpl/'.$fl;
		if (!@file_exists($f_path)) {
			exit('Non-existent template<br>');
		} else if (!($data = @file_get_contents($f_path))) {
			exit('Could not open template<br>');
		}
		$tmpl = $sec ? $sec : $msec;
		$tmpl_type = $sec ? 'SECTION' : 'MAIN';
		if (!$tmpl) {
			exit('Section parameter not avaliable<br>');
		}
		if (($sdata = fetch_section($data, $f_path, $tmpl, $tmpl_type)) === FALSE) {
			exit('Couldn\'t locate template "'.$tmpl.'" inside "'.$f_path.'"<br>');
		}

		if (!isset($_POST['submitted'])) {
			$tmpl_data = $sdata['data'];
		} else {
			$tmpl_data = $_POST['tmpl_data'];

			$data = substr_replace($data, str_replace("\r", '', $tmpl_data), $sdata['offset'], $sdata['len']);
			if (!($fp = fopen($f_path, 'wb'))) {
				exit('unable to save modifications to "'.$f_path.'"');
			}
			fwrite($fp, $data);
			fclose($fp);
			fud_use('compiler.inc', true);
			$c = q('SELECT theme FROM '.$GLOBALS['DBHOST_TBL_PREFIX'].'themes WHERE theme=\''.addslashes($tname).'\' AND lang=\''.addslashes($tlang).'\'');
			while ($r = db_rowarr($c)) {
				compile_all($tname, $tlang, $r[0]);
			}
			qf($c);
			$update_ok = 1;
		}
		$p = 0;
		while (($p = strpos($tmpl_data, '{MSG: ', $p)) !== FALSE) {
			$p += 5;
			$e = strpos($tmpl_data, '}', $p);
			$msg_list[] = substr($tmpl_data, $p, ($e - $p));
			$p = $e;
		}
		if (isset($msg_list)) {
			$msg_list = ' <font size="-1">[ <a href="#" onClick="javascript: window_open(\'msglist.php?tname='.$tname.'&tlang='.$tlang.'&'._rsidl.'&NO_TREE_LIST=1&msglist='.urlencode(implode(':', $msg_list)).'\', \'tmpl_msg\', 600,300);">Edit Text Messages</a> ]</font>';
		}
	}
	require($WWW_ROOT_DISK . 'adm/admpanel.php');
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
	$path = $DATA_DIR . 'thm/' . $tname . '/tmpl';
	$pathl = $path . '/';

	if (!($dp = opendir($path))) {
		exit('Unable to open template directory at: "'.$path.'"<br>');
	}
	readdir($dp); readdir($dp);
	while ($f = readdir($dp)) {
		if (substr($f, -5) != '.tmpl') {
			continue;
		}
		$data = file_get_contents($pathl . $f);

		/* fetch file name */
		if (($p = strpos($data, '{PHP_FILE: input: ')) === FALSE) {
			continue;
		}
		$p = strpos($data, '; output: ', $p) + 10;
		if ($data[$p] == '@' || $data[$p] == '!') {
			++$p;
		}
		$file = substr($data, $p, (strpos($data, ';', $p) - $p));
		if (strpos($file, '.inc')) {
			$file = str_replace('.inc', '.tmpl', $file);
		} else {
			$file = str_replace('.php', '.tmpl', $file);
		}
		
		/* build dependency list */
		$p = 0;
		while (($p = strpos($data, '{REF: ', $p)) !== FALSE) {
			$p += 5;
			$deps[$file][substr($data, $p, (strpos($data, '}', $p) - $p))] = 1;
		}

		/* parse sections */
		if (($p = strpos($data, '{PAGE: ')) === FALSE && ($p = strpos($data, '{MAIN_SECTION: ') === FALSE)) {
			/* the file has not templetable data, skipping */
			continue;
		}
		if (isset($max_opts[$file])) { /* we need to show sections inside this file */
			$p = 0;
			while (($p = strpos($data, '{', $p)) !== FALSE) {
				$e = strpos($data, ':', ++$p);
				$tag = substr($data, $p, ($e - $p));
				switch ($tag) {
					case 'SECTION':
					case 'PAGE':
					case 'MAIN_SECTION':
						$e = strpos($data, '}', $p);
						$e2 = strpos($data, '{'.$tag.': END}', $e);
						if ($e === FALSE || $e2 === FALSE) {
							exit('broken template file "'.$file.'"');
						}

						$d = explode(' ', substr($data, $p, ($e - $p)), 3);

						if (!isset($file_info_array[$file])) {
							$file_info_array[$file] = '<a class="file_name" href="tmpllist.php?tname='.$tname.'&tlang='.$tlang.'&'._rsidl.'&max_list='.minimize($file, $max_list).'" title="minimize">[ - ]</a> <b>'.$file.'</b> <a name="'.$file.'">&nbsp;</a><br>';
						}
						if ($tag != 'SECTION') {
							$file_info_array[$file] .= '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<font size="-1">&raquo;</font> <a class="msec" href="tmpllist.php?tname='.$tname.'&tlang='.$tlang.'&'._rsidl.'&edit=1&fl='.$file.'&msec='.urlencode($d[1]).'&max_list='.$max_list.'">'.$d[1].'</a>';
						} else {
							$file_info_array[$file] .= '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<font size="-1">&raquo;</font> <a class="sec" href="tmpllist.php?tname='.$tname.'&tlang='.$tlang.'&'._rsidl.'&edit=1&fl='.$file.'&sec='.urlencode($d[1]).'&max_list='.$max_list.'">'.$d[1].'</a>';
						}
						if (isset($d[2]) && ($d[2] = trim($d[2]))) {
							if (!$edit) {
								$file_info_array[$file] .= '<font size="-1" color="#008800">&nbsp;&nbsp;-&gt;&nbsp;&nbsp;'.htmlspecialchars($d[2]).'</font>';
							}
							$file_info_help[$d[1]] = $d[2];
						}
						$file_info_array[$file] .= '<br>';
						$p = $e2 + 6;
						break;
					default:
						++$p;
						break;
				}
			}
		} else { /* just parse the title & help if avaliable */
			$file_info_array[$file] = '<a class="file_name" href="tmpllist.php?tname='.$tname.'&tlang='.$tlang.'&'._rsidl.'&max_list='.maximize($file, $max_list).'" title="maximize">[ + ]</a> <b>'.$file.'</b> <a name="'.$file.'">&nbsp;</a>';
		}
	}
	closedir($dp);

	foreach($deps as $k => $v) {
		foreach ($v as $k2 => $v2) {
			if (isset($deps[$k2])) {
				$deps[$k] = array_merge($v, $deps[$k2]);
			}
		}
	}
	$php_deps =& $deps;

	$deps_on = array();
	foreach($php_deps as $k => $v) { 
		foreach($v as $k2 => $v2) $deps_on[$k2][] = $k; 
	}
	reset($deps_on);

	if( !empty($fl) ) {
		$tmp = $file_info_array;
		$file_info_array =  array();
		$tmp2[$fl] = $tmp[$fl];
		unset($tmp[$fl]);
		$file_info_array = array_merge($tmp2, $tmp);
	}

	sort($file_info_array);

	foreach($file_info_array as $k => $v) {
		echo $v;
		if(isset($max_opts[$k]) && ($php_deps[$k] || $deps_on[$k]) ) {
			if( is_array($php_deps[$k]) ) {
				$deps = '';
				foreach($php_deps[$k] as $k2 => $v2) {
					if( $file_info_array[$k2] ) $deps .= '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<font size="-1">&raquo;</font> <a href="tmpllist.php?tname='.$tname.'&tlang='.$tlang.'&'._rsid.'&max_list='.goto_tmpl($k2).'" class="deps">'.$k2.'</a><br>';
				}
			
				if( !empty($deps) ) echo '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<font size="-1">&raquo;</font> <font size="-1" color="#00AA00"><b>Dependencies</b></font><br>'.$deps;
			}	
			
			if( is_array($deps_on[$k]) ) {
				$dp = '';
				foreach($deps_on[$k] as $k2) {
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
<?php
	if (isset($update_ok)) {
		echo '<font color="black"><b>***Theme was successfully updated.***</b></font><br><br>';
	}	
?>
<font color="#008800"><b>Explanation:</b> 
<?php
	if (isset($file_info_help[$msec.$sec])) {
		echo $file_info_help[$msec.$sec];
	} else if (isset($sdata['comment'])) {
		echo $sdata['comment'];
	}
?>
</font><br>
<table cellspacing=2 cellpadding=1 border=0>
<form method="post" action="tmpllist.php?tname=<?php echo $tname; ?>&tlang=<?php echo $tlang; ?>" name="tmpledit">
<?php echo _hs; ?>
<tr>
	<td>
		<b><?php echo $tmpl; ?></b>:<?php echo $msg_list; ?><br>
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
<?php require($WWW_ROOT_DISK . 'adm/admclose.html'); ?>