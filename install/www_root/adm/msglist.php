<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: msglist.php,v 1.4 2002/06/26 19:41:21 hackie Exp $
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
	
	fud_use('adm.inc', TRUE);
	
	list($ses, $usr) = initadm();
	
	if ( empty($tname) || empty($tlang) ) {
		header("Location: admthemesel.php?ret=msglist&"._rsid."&rand=".get_random_value());
		exit();
	}

$msgfile = $GLOBALS['DATA_DIR'].'thm/'.$tname.'/i18n/'.$tlang.'/msg';
if ( !@is_file($msgfile) ) {
	$msgfile = $GLOBALS['DATA_DIR'].'thm/default/i18n/'.$tlang.'/msg';
	$warn = 1;
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

function makedeps()
{
	$oldcwd = getcwd();
	chdir($GLOBALS['DATA_DIR'].'thm/'.$GLOBALS['tname'].'/tmpl');
	$dp = opendir('.');
	readdir($dp); readdir($dp);
	while( $file = readdir($dp) ) {
		if( substr($file, -5) == '.tmpl' ) {
			$fp = fopen($file, 'rb');
				$data = fread($fp, __ffilesize($fp));
			fclose($fp);
			
			/* check for msgs int the php code */
			
			if ( preg_match_all('!{MSG: (.*?)}!', $data, $regs, PREG_SET_ORDER) ) {
				while ( list(,$v) = each($regs) ) {
					if ( !isset($tmplmsglist[$file][$v[1]]) ) $tmplmsglist[$file][$v[1]] = 1;
				}
			}
			
			preg_match_all('!{REF: (.*?)}!s', $data, $matches, PREG_SET_ORDER);
			while( list(,$v) = each($matches) ) $deps[$file][$v[1]] = 1;
			
			
		}
	}
	chdir($oldcwd);
	
	/* build reverse deps */
	reset($deps);
	while ( list($file, $reflist) = each($deps) ) {
		reset($reflist);
		while ( list($depfile) = each($reflist) ) $filedeps[$depfile][] = $file;
	}
	
	reset($tmplmsglist);
	
	$v[0] = $tmplmsglist;
	$v[1] = $filedeps;
	
	return $v;
}

	if ( $btn_submit ) {
		$data = filetomem($msgfile);
		preg_match_all('/(.+?):(\s*)(.+?)\n/s', $data, $regs, PREG_SET_ORDER);
		$fdata = '';
		reset($regs);
		while ( list(,$v) = each($regs) ) {
			$nval = str_replace("\n", '\n', stripslashes($HTTP_POST_VARS[$v[1]]));
			if ( isset($HTTP_POST_VARS[$v[1]]) && $v[3] != $nval ) {
				if ( !isset($clist[$v[1]]) ) $clist[$v[1]] = 1;
				$fdata .= $v[1].':'.$v[2].$nval."\n";
			}
			else 
				$fdata .= $v[0];
		}
		$fp = fopen($msgfile, 'wb');
		fwrite($fp, str_replace("\r", "", $fdata));
		fclose($fp);
		@chmod($msgfile, 0600);
		
		/* compile stuff here */
		
		list($tmplmsglist, $filedeps) = makedeps();
		/* build msg->file */
		reset($tmplmsglist);
		while ( list($k, $v) = each($tmplmsglist) ) {
			reset($v);
			while ( list($k2, $v2) = each($v) ) {
				if ( !isset($msgdeps[$k2][$k]) ) $msgdeps[$k2][$k] = 1;
			}
		}
		
		fud_use('compiler.inc', TRUE);
		$r = q("SELECT * FROM ".$GLOBALS['MYSQL_TBL_PREFIX']."themes WHERE theme='$tname' AND lang='$tlang'");
		while ( $obj = db_rowobj($r) )
			compile_all($obj->theme, $obj->lang, $obj->name);

		exit('<br><a href="msglist.php?tname='.$tname.'&tlang='.$tlang.'&'._rsid.'">Back to control panel</a>');
		if( $NO_TREE_LIST ) exit('<html><script>window.close();</script></html>');
	}
	
if ( empty($NO_TREE_LIST) ) {
	list($tmplmsglist, $filedeps) = makedeps();

	include('admpanel.php');
?>
<style>
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
<table border=0 cellspacing=0 cellpadding=0><tr><td valign=top>

<table border=0 cellspacing=0 cellpadding=3>
<?php
	ksort($tmplmsglist);
	reset($tmplmsglist);

if ( $warn ) {
	echo '<div align="center"><font color="green" size="+2">WARNING: EDITING DEFAULT MESSAGE FILE, BECAUSE THIS TEMPLATE DOESN\'T HAVE ONE</font><br><br></div>';
}

	while ( list($file, $msg) = each($tmplmsglist) ) {		
		reset($msg);
		$list = $msgnamelist = '';
		while ( list($msgname) = each($msg) ) {
			$msgnamelist .= urlencode($msgname).':';
			$list .='<tr><td><img src="blank.gif" height=1 width=20><a class="deps" href="msglist.php?tname='.$tname.'&tlang='.$tlang.'&'._rsid.'&msglist='.urlencode($msgname).'&fl='.$file.'">'.$msgname."</a></td></tr>\n";
		}
		$msgnamelist = substr($msgnamelist, 0, -1);
		echo "\t".'<tr><td><a class="file_name" href="msglist.php?tname='.$tname.'&tlang='.$tlang.'&'._rsid.'&msglist='.$msgnamelist.'&fl='.$file.'">'.$file.'</a><a name="'.$file.'"></a></td></tr>'."\n";
		echo $list;
		if ( count($filedeps[$file]) ) {
			echo '<tr><td class="depson"><img src="blank.gif" height=1 width=20><b>&raquo; Used By:</b></td></tr>'."\n";
			reset($filedeps[$file]);
			while ( list(,$v) = each($filedeps[$file]) ) {
				echo "\t\t".'<tr><td><img src="blank.gif" height=1 width=40><a href="#'.$v.'" class="depson">'.$v."</a></td></tr>\n";
			}
		}
		
	}
	echo '</table></td>';
} /* NO_TREE_LIST */ 


if ( $MSG_LIST || !empty($msglist) ) {
	echo '<td valign=top><form method="post" action="msglist.php?tname='.$tname.'&tlang='.$tlang.'"><table border=0>'._hs;
	
	$msglist = $HTTP_GET_VARS['msglist'];
	if ( !$msglist ) $msglist = $HTTP_POST_VARS['msglist'];		
	if ( $msglist ) {
		$msglist_tmp = explode(':', $msglist);
		reset($msglist_tmp);
		while ( list(,$v) = each($msglist_tmp) ) {
			$msglist_arr[$v] = 1;
		}
	}

	$data = filetomem($msgfile);

	preg_match_all('/(.+?):(\s*?)(.+?)\n/s', $data, $regs, PREG_SET_ORDER);
	while ( list(,$v) = each($regs) ) {
		if ( isset($msglist_arr) && !isset($msglist_arr[$v[1]]) ) continue;
		$v[2] = trim($v[3]);
		if ( ($ln=strlen($v[2])) > 50 ) {
			$cols = 50;
			$rows = $ln/$cols;
			$rows += 2;
			$maxrows = 20;
			if ( $rows > $maxrows ) $rows = $maxrows;
			$inptd = "<textarea name=\"$v[1]\" rows=$rows cols=$cols>".htmlspecialchars($v[2])."</textarea>";
		}
		else 
			$inptd = "<input type=\"text\" name=\"$v[1]\" value=\"".htmlspecialchars($v[2])."\" size=50>";
		echo "<tr><td valign=top nowrap><a name=\"$v[1]\"><b>$v[1]</b></a>:</td><td valign=top>$inptd</td></tr>\n";
	}
	?>
	<tr><td align=right colspan=2><input type="submit" name="btn_submit" value="Edit"></td></tr>
	<input type="hidden" name="msglist" value="<?php echo $msglist; ?>">
	<input type="hidden" name="fl" value="<?php echo $fl; ?>">
	</table></td>
	</form>
	<?php
}
?>
</tr></table>
<?php readfile('admclose.html'); ?>