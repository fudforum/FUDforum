<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: admoptimizer2.php,v 1.3 2002/06/26 19:41:21 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

	define('admin_form', 1);

	@set_time_limit(6000);
	
	include_once "GLOBALS.php";
	
	fud_use('db.inc');
	fud_use('adm.inc', TRUE);
	
	list($ses, $usr) = initadm();

$O_LEVEL=20;

function match_close($oc, $cc, $str)
{
	$ln = strlen($str);
	$depth = 0;
	for ( $i=0; $i<$ln; $i++ ) {
		if ( $str[$i] == $oc ) ++$depth;
		else if ( $str[$i] == $cc ) --$depth;
		
		if ( !$depth ) return ++$i;
	}
	
	return 0;
}

function skip_sep($str)
{
	$ln = strlen($str);
	
	for( $i=0; $i<$ln; $i++ ) {
		switch ( $str[$i] ) 
		{
			case "\n":
			case "\r":
			case " ":
			case "\t":
				continue;
			default:
				return $i;	
		}
	}
	return -1;
}

function find_function_end($data, $pos)
{
	$pos = strpos($data, '{', $pos)+1;
	$open = 1;

	for( $i=$pos; ; $i++ ) {
		if( $data[$i]=='{' ) 
			$open++;
		else if( $data[$i]=='}' ) 
			$open--;
		else if( $data[$i]==NULL )
			exit("BAD FUNCTION DECLARATION");
		
		if( !$open ) return $i;
	}
	
	exit("BAD FUNCTION DECLARATION");
}

function optimize_file($file)
{
	$data = $file_backup = filetomem($file);

	$arr[' ']=1;
	$arr["\n"]=1;
	$arr["\t"]=1;
	$arr["\r"]=1;
	$arr['.']=1;
	$arr['(']=1;
	$arr['?']=1;
	$arr['>']=1;
	$arr['!']=1;
	$arr[':']=1;
	$arr[';']=1;
	$arr['=']=1;
	$arr['{']=1;
	$arr['}']=1;	

	$pos=0;
	while( $pos = strpos($data, 'function', $pos) ) {
		if( ($fe = strpos($data, '(', $pos)) === FALSE ) break;
		
		$fe_name = trim(substr($data, $pos+9, ($fe-$pos-9)));
		
		if( !preg_match('!^[A-Za-z_]{1}[-A-Za-z0-9_]*$!', $fe_name) ) {
			$pos = $fe;
			continue;
		}
		
		$fe = find_function_end($data, $fe);
		
		$function[$fe_name] = substr($data, $pos, ($fe-$pos));
		$pos = $fe+1;
	}
	
	while( $data=strstr($data, 'function') ) {
		if( preg_match('!^function(\s+)([A-Za-z_]{1})([-A-Za-z0-9_]*)\(!i', $data, $sep) ) {
			$data = '('.substr($data, strlen($sep[0]));
			
			if ( !($end = match_close('(',')', $data)) ) exit("SYNTAX ERROR\n");
			
			$function_name = $sep[2].$sep[3];
			$function2[$function_name] = substr($sep[0],0,-1).substr($data, 0, $end);

			$data = substr($data, $end);
			
			if( (($func_start = skip_sep($data)) == -1) || $data[$func_start]!='{' )  exit("SYNTAX ERROR\n");
			$function2[$function_name] .= substr($data,0,$func_start);
			$data = substr($data, $func_start);
			
			if ( !($end = match_close('{','}', $data)) ) exit("SYNTAX ERROR\n");
			$function2[$function_name] .= substr($data, 0, $end);
			$data = substr($data, $end);
		}
		else 
			$data = substr($data, 8);
	}

	ksort($function);
	ksort($function2);
	reset($function);
	reset($function2);
	echo "<pre>\n".count($function)."\t".count($function2)."\n";
	while ( 1 ) {
		list($k, $v) = each($function);
		list($k2, $v2) = each($function2);
		if ( !$k && !$k2 ) break;
		
		if ( $k != $k2 ) exit("ERROR<br>\n");
		
		echo "\tD: ".$k."\t\t\t$k2\n";
	}
	echo "</pre>";
	return;
	for( $i=0; $i<$GLOBALS['O_LEVEL']; $i++ ) {
		$file_b4 = $file_af = $file_backup;
		
		if ( !is_array($function ) ) continue;
		reset($function);
		while( list($k,$v) = each($function) ) {
			$pos=$func_match=0;
			while( ($pos = strpos($file_af, $k.'(', $pos)) ) {
				if ( isset($arr[$file_af[$pos-1]]) )
					$func_match++;
					
				$pos += strlen($k)+1;
			}

			if( $func_match==1 ) $file_af = str_replace($v, '', $file_af);
		}
		
		if( $file_af == $file_b4 ) 
			break;
		else 
			$file_backup = $file_af;
	}

	/* Cleanup of SQL query syntax */
	//$file_af = preg_replace('!Q\((.*?)\);!se', "trim(preg_replace('!\s+!s', ' ', 'q(\\1);'))", $file_af);

	$file_len = strlen($file_af);
	
	$optimized_file = $space = '';
	for( $i=0; $i<$file_len; $i++ ) {
		switch( $file_af[$i] ) 
		{
			case "\t":
			case "\r":
			case "\n":
			case " ":
				if( !$space ) {
					$optimized_file .= $file_af[$i];
					$space=1;
				}
				break;
			case ";":
			case "}":
			case "{":
				$optimized_file .= $file_af[$i];
				$space=1;
				break;	
			case "'":
			case "\"":
				$quote = $file_af[$i];
				$str_start = $i;
				while ( $i++ < $file_len ) {
					if ( $file_af[$i] == '\\' ) 
						$i++;
					else if ( $file_af[$i] == $quote ) break;
				}
				
				if ( $file_af[$i] != $quote ) exit("parse error\n");
				
				$str_end = $i;
				$optimized_file .= substr($file_af, $str_start, $str_end-$str_start+1);
				$i = $str_end;
				$space=0;
				break;
			case "/":
				if( $file_af[$i+1] == '/' )
					$i = strpos($file_af, "\n", $i+1);
				else if ( $file_af[$i+1] == '*' )
					$i = strpos($file_af, "*/", $i+2)+1;
				else 
					$optimized_file .= $file_af[$i];
				break;	
			case "?":
				if( $file_af[$i+1] == '>' ) {
					if( !($pos = strpos($file_af, '<?php', $i)) ) {
						$optimized_file .= substr($file_af, $i, $file_len-$i);
						$i = $file_len;
					}
					else {
						$optimized_file .= substr($file_af, $i, $pos-$i+1);	
						$i=$pos;
					}
				}
				else
					$optimized_file .= $file_af[$i];
				break;	
			default:
				$prevcase = $prevstr = '';
				$optimized_file .= $file_af[$i];
				$space=0;
		}
	}

	$optimized_file = preg_replace('!include_once "GLOBALS.php";!', 'require_once "GLOBALS.php";', $optimized_file, 1);
	$optimized_file = preg_replace('!include_once "GLOBALS.php";!', '', $optimized_file);
	
	$fp = fopen($file, 'wb');
		fwrite($fp, $optimized_file);
	fclose($fp);
}	
	include('admpanel.php'); 

	if( !empty($btn_submit) ) { 
		$curdir = getcwd();
		chdir($GLOBALS['WWW_ROOT_DISK']);
		$dir = opendir('.');
		readdir($dir); readdir($dir);
		while( $file = readdir($dir) ) {
			if( @is_link($file) || !@is_file($file) || !preg_match('!\.php$!', $file) ) continue;
		
			if( !is_writeable($file) ) {
				echo "<font color=\"#ff0000\">WARNING: cannot open ".$file." for write, file left unoptimized</font><br>\n";
				flush();
				continue;
			}
		
			echo "Optimizing ".$file." ... \n";
			flush();
		
			$old_size = filesize($file);
			optimize_file($file);
			clearstatcache();
			$new_size = filesize($file);
		
			echo "(optimized ".($old_size-$new_size)." bytes) Done<br>\n";
			flush();
		}
		closedir($dir);
		chdir($curdir);
	
		echo "<b>Optimization Process Complete</b><br>\n";
	}
	else {
?>
<table border=0 cellspacing=1 cellpadding=3>
<form method="post" action="admoptimizer2.php">
<?php echo _hs; ?>
<tr bgcolor="#bff8ff"><td>
	The optimization process removes unneeded functions, comments and formatting from the php files compiled from the templates.
	This makes the PHP files smaller, hence have smaller memory foot print and result in faster file parsing by the PHP's parser.
	Overall performance improvements range from 5-15% depending on your FUDforum usage and your system's configuration.<br>
	The optimization process takes approximately 45 seconds on a 433Mhz celeron, on your system it is likely to be much faster.
</td></tr>	
<tr bgcolor="#bff8ff"><td align=center><input type="submit" name="btn_submit" value="Optimize FUDforum"></td></tr>
</form>
</table>
<?php		
	}	
	readfile('admclose.html');
?>