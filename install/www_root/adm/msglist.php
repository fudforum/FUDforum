<?php
/**
* copyright            : (C) 2001-2009 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: msglist.php,v 1.45 2009/01/29 18:37:40 frank Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; version 2 of the License.
**/

	@set_time_limit(6000);

	require('./GLOBALS.php');
	fud_use('adm.inc', true);

	$tname = isset($_POST['tname']) ? $_POST['tname'] : (isset($_GET['tname']) ? $_GET['tname'] : '');
	$tlang = isset($_POST['tlang']) ? $_POST['tlang'] : (isset($_GET['tlang']) ? $_GET['tlang'] : '');

	if (!$tname || !$tlang) {
		header('Location: '.$WWW_ROOT.'adm/admthemesel.php?ret=msglist&'.__adm_rsidl);
		exit;
	}

	$msgfile = $GLOBALS['DATA_DIR'].'thm/'.$tname.'/i18n/'.$tlang.'/msg';
	if (!@file_exists($msgfile)) {
		$msgfile = $GLOBALS['DATA_DIR'].'thm/default/i18n/'.$tlang.'/msg';
		$warn = 1;
	}

function makedeps()
{
	$path = $GLOBALS['DATA_DIR'].'thm/'.$GLOBALS['tname'].'/tmpl';

	$files = glob($path . '/*.tmpl', GLOB_NOSORT);
	if (!$files) {
		echo "Could not get list of template files from {$path}<br/>";
		return array();
	}

	foreach ($files as $f) {
		$data = file_get_contents($f);
		$file = basename($f);

		// check for msgs int the php code
		$s = $e = 0;

		while (($s = strpos($data, '{REF: ', $s)) !== false) {
			$s += 6;
			if (($e = strpos($data, '}', $s)) === false) {
				break;
			}

			$dep = substr($data, $s, ($e - $s));
			if (!isset($deps[$file][$dep])) {
				$deps[$file][$dep] = $dep;
			}
			$s = $e;
		}

		while (($s = strpos($data, '{MSG: ', $s)) !== false) {
			$s += 6;
			if (($e = strpos($data, '}', $s)) === false) {
				break;
			}

			$msg = substr($data, $s, ($e - $s));
			if (!isset($tmplmsglist[$file][$msg])) {
				$tmplmsglist[$file][$msg] = $msg;
			}
			$s = $e;
		}
	}

	// build reverse deps
	foreach($deps as $file => $reflist) {
		foreach($reflist as $depfile) {
			$filedeps[$depfile][] = $file;
		}
	}

	return array($tmplmsglist, $filedeps);
}

	if (isset($_POST['btn_submit'], $_POST['msglist'])) {
		$msglist_arr[] = strtok($_POST['msglist'], ':');
		while (($v = strtok(':'))) {
			$msglist_arr[] = $v;
		}

		$data = "\n" . file_get_contents($msgfile);
		foreach ($msglist_arr as $v) {
			if (($s = strpos($data, "\n" . $v . ':')) === false) {
				continue;
			}
			$s += 3 + strlen($v);
			while ($data[$s] == "\t") {
				++$s;
			}
			if (($e = strpos($data, "\n", $s)) === false) {
				continue;
			}
			$_POST[$v] = str_replace(array("\r", "\n"), array("", "\\n"), trim($_POST[$v]));

			$data = substr_replace($data, $_POST[$v], $s, ($e - $s));
		}
		if (!($fp = fopen($msgfile, 'wb'))) {
			exit('unable to write to "'.$msgfile.'" message file');
		}
		fwrite($fp, ltrim($data));
		fclose($fp);
		fud_use('compiler.inc', true);

		$c = q("SELECT name FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."themes WHERE theme="._esc($tname)." AND lang="._esc($tlang));
		while ($r = db_rowarr($c)) {
			compile_all($tname, $tlang, $r[0]);
		}
		unset($c);

		if (isset($_POST['NO_TREE_LIST'])) {
			exit('<html><script type="text/javascript">window.close();</script></html>');
		}
		exit('<br /><a href="msglist.php?tname='.$tname.'&amp;tlang='.$tlang.'&amp;'.__adm_rsidl.'">Back to control panel</a>');
	}

if (!isset($_GET['NO_TREE_LIST'])) {
	list($tmplmsglist, $filedeps) = makedeps();
	ksort($tmplmsglist);

	require($WWW_ROOT_DISK . 'adm/admpanel.php');
?>
<style type="text/css">
.file_name {
	font-weight: bold;
	color: #ff0000;
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
<table border="0" cellspacing="0" cellpadding="0"><tr><td valign="top">
<table border="0" cellspacing="0" cellpadding="3">
<?php

if (isset($warn)) {
	echo '<div align="center"><font color="green" size="+2">WARNING: EDITING DEFAULT MESSAGE FILE, BECAUSE THIS TEMPLATE DOESN\'T HAVE ONE</font><br /><br /></div>';
}
	$tab = str_repeat('&nbsp;', 5);

	foreach($tmplmsglist as $file => $msg) {
		$list = $msgnamelist = '';
		foreach($msg as $k => $msgname) {
			$msgnamelist .= urlencode($msgname).':';
			$list .='<tr><td><img src="../blank.gif" height="1" width="20" alt="blank"><a class="deps" href="msglist.php?tname='.$tname.'&amp;tlang='.$tlang.'&amp;'.__adm_rsid.'&amp;msglist='.urlencode($msgname).'&amp;fl='.$file.'">'.$msgname.'</a></td></tr>';
		}
		$msgnamelist = substr($msgnamelist, 0, -1);
		echo '<tr><td><a class="file_name" href="msglist.php?tname='.$tname.'&amp;tlang='.$tlang.'&amp;'.__adm_rsid.'&amp;msglist='.$msgnamelist.'&amp;fl='.$file.'">'.$file.'</a><a name="'.$file.'"></a></td></tr>' . $list;
		if (isset($filedeps[$file])) {
			echo '<tr><td class="depson">'.$tab.'<b>&raquo; Used By:</b></td></tr>'."\n";
			foreach($filedeps[$file] as $v) {
				echo '<tr><td>'.$tab.$tab.'<a href="#'.$v.'" class="depson">'.$v.'</a></td></tr>';
			}
		}

	}
	echo '</table></td>';
} /* NO_TREE_LIST */

	$msglist = isset($_GET['msglist']) ? $_GET['msglist'] : (isset($_POST['msglist']) ? $_POST['msglist'] : '');

	if ($msglist) {
		echo '<td valign="top"><form method="post" action="msglist.php?tname='.$tname.'&amp;tlang='.$tlang.'">'._hs.'<table border="0">';
		$msglist_arr[] = strtok(trim($msglist), ':');
		while (($v = strtok(':'))) {
			$msglist_arr[] = trim($v);
		}

		$data = "\n" . file_get_contents($msgfile);

		foreach ($msglist_arr as $v) {
			if (($s = strpos($data, "\n" . $v . ':')) === false) {
				echo '<tr><td nowrap="nowrap"><font color="red">Unable to find "'.$v.'" inside "'.$msgfile.'"</font></td></tr>';
				continue;
			}
			$s += 3 + strlen($v);
			if (($e = strpos($data, "\n", $s)) === false) {
				$e = strlen($data);
			}

			$txt = htmlspecialchars(trim(substr($data, $s, ($e - $s))));
			if (strlen($txt) > 50) {
				$inptd = '<textarea name="'.$v.'" rows="20" cols="60">'.$txt.'</textarea>';
			} else {
				$inptd = '<input type="text" name="'.$v.'" value="'.$txt.'" size="50" />';
			}
			echo '<tr><td valign="top" nowrap="nowrap"><a name="'.$v.'"><b>'.$v.'</b></a>:</td><td valign="top">'.$inptd.'</td></tr>';
		}
		echo '<tr><td align="right" colspan="2"><input type="submit" name="btn_submit" value="Edit" /></td></tr>';
		echo '<tr><td><input type="hidden" name="msglist" value="'.$msglist.'" /></td></tr></table>';
		if (isset($_GET['fl'])) {
			echo '<input type="hidden" name="fl" value="'.$_GET['fl'].'" />';
		}
		if (isset($_GET['NO_TREE_LIST'])) {
			echo '<input type="hidden" name="NO_TREE_LIST" value="1" />';
		}
		echo '</form></td>';
	}
?>
</tr></table>
<?php require($WWW_ROOT_DISK . 'adm/admclose.html'); ?>
