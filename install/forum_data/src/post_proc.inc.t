<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: post_proc.inc.t,v 1.3 2002/07/10 14:45:05 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

$GLOBALS['seps'] = array(' '=>' ', "\n"=>"\n", "\r"=>"\r", "'"=>"'", '"'=>'"', '['=>'[', ']'=>']', '('=>'(', ')'=>')', "\t"=>"\t", '='=>'=', '>'=>'>', '<'=>'<');

function fud_substr_replace($str, $newstr, $pos, $len)
{
        return substr($str, 0, $pos).$newstr.substr($str, $pos+$len);
}

function tags_to_html($str, $allow_img='Y')
{
	if( !defined('no_char') ) $str = htmlspecialchars($str);
	
	$str = str_replace('[*]', '<li>', $str);
	$str = nl2br($str);
	
	$ostr = '';
	$pos = $old_pos = 0;
	
	while ( ($pos = strpos($str, '[', $pos)) !== FALSE ) {
		if( ($epos = strpos($str, ']', $pos)) === FALSE ) break;
		$tag = substr($str, $pos+1, $epos-$pos-1);
		if ( $pparms = strpos($tag, '=') ) {
			$parms = substr($tag, $pparms+1);
			$tag = substr($tag, 0, $pparms);
		}
		else $parms = '';
		
		$tag = strtolower($tag);
		
		switch ( $tag ) 
		{
			case "quote title":
				$tag = 'quote';
				break;
			case "list type":
				$tag = 'list';
				break;
		}
		
		if ( $tag[0] == '/' ) { 
			if( $end_tag[$pos] ) {
				if( ($pos-$old_pos) ) $ostr .= substr($str, $old_pos, $pos-$old_pos);
				$ostr .= $end_tag[$pos];
				$pos = $old_pos = $epos+1; 
			}
			else
				$pos = $epos+1; 	
			
			continue; 
		}

		$cpos = $epos;
		$ctag = '[/'.$tag.']';
		$ctag_l = strlen($ctag);
		$otag = '['.$tag;
		$otag_l = strlen($otag);
		$rf = 1;
		while ( ($cpos = strpos($str, '[', $cpos)) !== FALSE ) {
			if( $end_tag[$cpos] ) {
				$cpos++;
				continue;
			}

			if( ($cepos = strpos($str, ']', $cpos)) === FALSE ) break 2;
			
			if ( strtolower(substr($str, $cpos, $ctag_l)) == $ctag ) $rf--;
			else if ( strtolower(substr($str, $cpos, $otag_l)) == $otag ) $rf++;
			
			if ( !$rf ) break;
			$cpos = $cepos;
		}
		
		if ( $cpos !== FALSE ) {
			if( ($pos-$old_pos) ) $ostr .= substr($str, $old_pos, $pos-$old_pos);
			switch( $tag )
			{
				case 'notag':
					$ostr .= '<span name="notag">'.substr($str, $epos+1, $cpos-1-$epos).'</span>';
					$epos = $cepos;
					break;
				case 'url':
					if( !$parms ) {
						$parms = substr($str, $epos+1, ($cpos-$epos)-1);
						if( strpos(strtolower($parms), 'javascript:') === FALSE )
							$ostr .= '<a href="'.$parms.'" target=_new>'.$parms.'</a>';
						else 
							$ostr .= substr($str, $pos, ($cepos-$pos)+1);	
						
						$epos = $cepos;
						$str[$cpos] = '<';
					}
					else { 
						if( strpos(strtolower($parms), 'javascript:') === FALSE ) {
							$end_tag[$cpos] = '</a>';
							$ostr .= '<a href="'.$parms.'" target=_new>';
						}
						else {
							$ostr .= substr($str, $pos, ($cepos-$pos)+1);	
							$epos = $cepos;
							$str[$cpos] = '<';
						}	
					}	
					break;	
				case 'i':
				case 'u':
				case 'b':
				case 's':
				case 'sub':
				case 'sup':
					$end_tag[$cpos] = '</'.$tag.'>';
					$ostr .= '<'.$tag.'>';
					break;
				case 'email':
					if( !$parms ) {
						$parms = substr($str, $epos+1, ($cpos-$epos)-1);
						$ostr .= '<a href="mailto:'.$parms.'" target=_new>'.$parms.'</a>';
						$epos = $cepos;
						$str[$cpos] = '<';
					}
					else { 
						$end_tag[$cpos] = '</a>';
						$ostr .= '<a href="mailto:'.$parms.'" target=_new>';
					}
					break;
				case 'color':
				case 'size':
				case 'font':
					if( $tag == 'font' ) $tag = 'face';
					$end_tag[$cpos] = '</font>';
					$ostr .= '<font '.$tag.'="'.$parms.'">';
					break;
				case 'code':
					$param = substr($str, $epos+1, ($cpos-$epos)-1);
					reverse_nl2br($param);
					
					$ostr .= '<pre>'.$param.'</pre>';
					$epos = $cepos;
					$str[$cpos] = '<';
					break;
				case 'img':
					if( $allow_img == 'N' ) {
						$ostr .= substr($str, $pos, ($cepos-$pos)+1);
					}
					else {
						if( !$parms ) {
							$parms = substr($str, $epos+1, ($cpos-$epos)-1);
							if( strpos(strtolower($parms), 'javascript:') === FALSE )
								$ostr .= '<img src="'.$parms.'" border=0 alt="'.$parms.'">';
							else 
								$ostr .= substr($str, $pos, ($cepos-$pos)+1);		
						}
						else { 
							if( strpos(strtolower($parms), 'javascript:') === FALSE ) 
								$ostr .= '<img src="'.$parms.'" border=0 alt="'.substr($str, $epos+1, ($cpos-$epos)-1).'">';	
							else
								$ostr .= substr($str, $pos, ($cepos-$pos)+1);
						}
					}	
					$epos = $cepos;
					$str[$cpos] = '<';
					break;
				case 'quote':
					if( !$parms ) $parms = 'Quote:';
					$ostr .= '<table border="0" align="center" width="90%" cellpadding="3" cellspacing="1"><tr><td class="SmallText"><b>'.$parms.'</b></td></tr><tr><td class="quote"><br>';
					$end_tag[$cpos] = '<br></td></tr></table>';
					break;
				case 'align':
					$end_tag[$cpos] = '</div>';
					$ostr .= '<div align="'.$parms.'">';
					break;
				case 'list':
					switch( strtolower($parms) )
					{
						case '1':
						case 'a':
							$end_tag[$cpos] = '</ol>';
							$ostr .= '<ol type="'.$parms.'">';
							break;
						case 'square':
						case 'circle':
						case 'disc':
							$end_tag[$cpos] = '</ul>';
							$ostr .= '<ul type="'.$parms.'">';
							break;
						default:
							$end_tag[$cpos] = '</ul>';
							$ostr .= '<ul>';		
					}
					break;
				case 'spoiler':
					$rnd = get_random_value(64);
					$end_tag[$cpos] = '</div></div>';
					$ostr .= '<div class="dashed" style="padding: 3px;" align="center" width="100%"><a href="javascript://" OnClick="javascript: layerVis(\''.$rnd.'\', 1);">{TEMPLATE: post_proc_reveal_spoiler}</a><div align="left" id="'.$rnd.'" style="visibility: hidden;">';
					break;		
			}
		
			$str[$pos] = '<';
			$pos = $old_pos = $epos+1;	
		}
		else
			$pos = $epos+1;
	}
	$ostr .= substr($str, $old_pos, strlen($str)-$old_pos);
	
	/* url paser */
	$pos = 0;
	$ppos = 0;	
	while ( ($pos = @strpos($ostr, '://', $pos)) !== FALSE ) {
		if ( $pos < $ppos ) break;
		// check if it's inside any tag;
		$i=$pos;
		while (--$i && $i>$ppos ) {
			if ( $ostr[$i] == '>' ) break;
			if ( $ostr[$i] == '<' ) break;
		}
		if ( $ostr[$i]=='<' ) { $pos+=3; continue; }
		
		// check if it's inside the a tag
		$ts = strpos($ostr, '<a ', $pos);
		if ( !$ts ) $ts = strlen($ostr);
		$te = strpos($ostr, '</a>', $pos);
		if ( !$te ) $te = strlen($ostr);
		if ( $te < $ts ) { $ppos = $pos += 3; continue; }
		
		// check if it's inside the pre tag
		$ts = strpos($ostr, '<pre>', $pos);
		if ( !$ts ) $ts = strlen($ostr);
		$te = strpos($ostr, '</pre>', $pos);
		if ( !$te ) $te = strlen($ostr);
		if ( $te < $ts ) { $ppos = $pos += 3; continue; }
		
		$us = $pos;
		while ( 1 ) {
			--$us;
			if ( isset($GLOBALS['seps'][$ostr[$us]]) || $ppos>$us || !isset($ostr[$us]) ) break;
		}
		
		unset($GLOBALS['seps']['=']);
		$ue = $pos;
		while ( 1 ) {
			++$ue;
			if ( isset($GLOBALS['seps'][$ostr[$ue]]) || !isset($ostr[$ue]) ) break;
		}
		$GLOBALS['seps']['='] = '=';
		
		$url = substr($ostr, $us+1, $ue-$us-1);
		
		$html_url = '<a href="'.$url.'" target=_new>'.$url.'</a>';
		$html_url_l = strlen($html_url);
		$ostr = fud_substr_replace($ostr, $html_url, $us+1, $ue-$us-1);
		$ppos = $pos;
		$pos = $us+$html_url_l;
	}
	
	/* email parser */
	$pos = 0;
	$ppos = 0;
	while ( ($pos = @strpos($ostr, '@', $pos)) !== FALSE ) {
		if ( $pos < $ppos ) break;
		
		// check if it's inside any tag;
		$i=$pos;
		while (--$i && $i>$ppos) {
			if ( $ostr[$i] == '>' ) break;
			if ( $ostr[$i] == '<' ) break;
		}
		if ( $ostr[$i]=='<' ) { ++$pos; continue; }
		
		// check if it's inside the a tag
		$ts = strpos($ostr, '<a ', $pos);
		if ( !$ts ) $ts = strlen($ostr);
		$te = strpos($ostr, '</a>', $pos);
		if ( !$te ) $te = strlen($ostr);
		if ( $te < $ts ) { $ppos = $pos += 1; continue; }
		
		// check if it's inside the pre tag
		$ts = strpos($ostr, '<pre>', $pos);
		if ( !$ts ) $ts = strlen($ostr);
		$te = strpos($ostr, '</pre>', $pos);
		if ( !$te ) $te = strlen($ostr);
		if ( $te < $ts ) { $ppos = $pos += 1; continue; }
		
		for ( $es=$pos-1; $es>($ppos-1); $es-- ) {
			if ( 
				( ord($ostr[$es]) >= ord('A') && ord($ostr[$es]) <= ord('z') ) ||
				( ord($ostr[$es]) >= ord(0) && ord($ostr[$es]) <= ord(9) ) ||
				( $ostr[$es] == '.' || $ostr[$es] == '-' )
			) continue;
			++$es;
			break;
		}
		if ( $es == $pos ) { $ppos = $pos += 1; continue; }
		if ( $es < 0 ) $es = 0;
		
		for ( $ee=$pos+1; @isset($ostr[$ee]); $ee++ ) {
			if ( 
				( ord($ostr[$ee]) >= ord('A') && ord($ostr[$ee]) <= ord('z') ) ||
				( ord($ostr[$ee]) >= ord(0) && ord($ostr[$ee]) <= ord(9) ) ||
				( $ostr[$ee] == '.' || $ostr[$ee] == '-' )
			) continue;
			break;
		}
		if ( $ee == ($pos+1) ) { $ppos = $pos += 1; continue; }
				
		$email = substr($ostr, $es, $ee-$es);
		$email_url = '<a href="mailto:'.$email.'" target=_new>'.$email.'</a>';
		$email_url_l = strlen($email_url);
		$ostr = fud_substr_replace($ostr, $email_url, $es, $ee-$es);
		$ppos =	$es+$email_url_l;
		$pos = $ppos;
	}
	
	return $ostr;
}

function html_to_tags($fudml)
{
	while ( preg_match('!<table border="0" align="center" width="90%" cellpadding="3" cellspacing="1"><tr><td class="SmallText"><b>(.*?)</b></td></tr><tr><td class="quote"><br>(.*?)<br></td></tr></table>!is', $fudml) ) 
		$fudml = preg_replace('!<table border="0" align="center" width="90%" cellpadding="3" cellspacing="1"><tr><td class="SmallText"><b>(.*?)</b></td></tr><tr><td class="quote"><br>(.*?)<br></td></tr></table>!is', '[quote title=\1]\2[/quote]', $fudml);

	reverse_nl2br($fudml);

	if( preg_match('!<div class="dashed" style="padding: 3px;" align="center" width="100%"><a href="javascript://" OnClick="javascript: layerVis\(\'.*?\', 1\);">{TEMPLATE: post_proc_reveal_spoiler}</a><div align="left" id=".*?" style="visibility: hidden;">!is', $fudml) ) {	
		$fudml = preg_replace('!\<div class\="dashed" style\="padding: 3px;" align\="center" width\="100%"\>\<a href\="javascript://" OnClick\="javascript: layerVis\(\'.*?\', 1\);">{TEMPLATE: post_proc_reveal_spoiler}\</a\>\<div align\="left" id\=".*?" style\="visibility: hidden;"\>!is', '[spoiler]', $fudml);
		$fudml = str_replace('</div></div>', '[/spoiler]', $fudml);
	}	

	while( preg_match('!<(b|i|u|s|sub|sup)>.*?</\1>!is', $fudml) )
		$fudml = preg_replace('!<(b|i|u|s|sub|sup)>(.*?)</\1>!is', '[\1]\2[/\1]', $fudml);

	while( preg_match('!<div align="(center|left|right)">.*?</div>!is', $fudml) )
		$fudml = preg_replace('!<div align="(center|left|right)">(.*?)</div>!is', '[align=\1]\2[/align]', $fudml);
		
	while ( preg_match('!<pre>.*?</pre>!is', $fudml) ) 
		$fudml = preg_replace('!<pre>(.*?)</pre>!is', '[code]\1[/code]', $fudml);
	
	if( preg_match('!<img src="(.*?)" border=0 alt="\\1">!is', $fudml) )
		$fudml = preg_replace('!<img src="(.*?)" border=0 alt="\\1">!is', '[img]\1[/img]', $fudml);	
	
	if( preg_match('!<img src=".*?" border=0 alt=".*?">!is', $fudml) ) 
		$fudml = preg_replace('!<img src="(.*?)" border=0 alt="(.*?)">!is', '[img=\1]\2[/img]', $fudml);
	
	if( preg_match('!<a href="mailto:(.+?)" target=_new>\\1</a>!is', $fudml) )
		$fudml = preg_replace('!<a href="mailto:(.+?)" target=_new>\\1</a>!is', '[email]\1[/email]', $fudml);
	
	if( preg_match('!<a href="mailto:.+?" target=_new>.+?</a>!is', $fudml) )
		$fudml = preg_replace('!<a href="mailto:(.+?)" target=_new>(.+?)</a>!is', '[email=\1]\2[/email]', $fudml);
	
	if( preg_match('!<a href="(.+?)" target=_new>\\1</a>!is', $fudml) )	
		$fudml = preg_replace('!<a href="(.+?)" target=_new>\\1</a>!is', '[url]\1[/url]', $fudml);
		
	if( preg_match('!<a href=".+?" target=_new>.+?</a>!is', $fudml) )	
		$fudml = preg_replace('!<a href="(.+?)" target=_new>(.+?)</a>!is', '[url=\1]\2[/url]', $fudml);

	while ( preg_match('!<font color=".+?">.*?</font>!is', $fudml) ) 
		$fudml = preg_replace('!<font color="(.+?)">(.*?)</font>!is', '[color=\1]\2[/color]', $fudml);
		
	while ( preg_match('!<font face=".+?">.*?</font>!is', $fudml) ) 	
		$fudml = preg_replace('!<font face="(.+?)">(.*?)</font>!is', '[font=\1]\2[/font]', $fudml);
		
	while ( preg_match('!<font size=".+?">.*?</font>!is', $fudml) ) 
		$fudml = preg_replace('!<font size="(.+?)">(.*?)</font>!is', '[size=\1]\2[/size]', $fudml);
		
	while ( preg_match('!<ul>.*?</ul>!is', $fudml) ) 
		$fudml = preg_replace('!<ul>(.*?)</ul>!is', '[list]\1[/list]', $fudml);
			
	while ( preg_match('!<ol type=".+?">.*?</ol>!is', $fudml) ) 
		$fudml = preg_replace('!<ol type="(.+?)">(.*?)</ol>!is', '[list type=\1]\2[/list]', $fudml);
			
	while ( preg_match('!<ul type=".+?">.*?</ul>!is', $fudml) ) 
		$fudml = preg_replace('!<ul type="(.+?)">(.*?)</ul>!is', '[list type=\1]\2[/list]', $fudml);
	
	while ( preg_match('!<span name="notag">.*?</span>!is', $fudml) ) 
		$fudml = preg_replace('!<span name="notag">(.*?)</span>!is', '[notag]\1[/notag]', $fudml);
	
	$fudml = str_replace('<li>', '[*]', $fudml);
		
	/* unhtmlspecialchars */
	reverse_FMT($fudml);
		
	return $fudml;
}


function filter_ext($file_name)
{
	$regexp_file = $GLOBALS['FORUM_SETTINGS_PATH'].'file_filter_regexp';
	
	if( !file_exists($regexp_file) || !filesize($regexp_file) || empty($file_name) ) return 0;
	
	$fp = fopen($regexp_file, 'rb');
		$regexp = fread($fp, filesize($regexp_file));
	fclose($fp);
	
	if( preg_match('/'.$regexp.'/i', $file_name) ) return 0;
	
	return 1;
}

function tmpl_list_ext()
{
	$ext='';
	$r = q("SELECT ext FROM {SQL_TABLE_PREFIX}ext_block");
	while ( $obj = db_rowobj($r) ) $ext .= '{TEMPLATE: allowed_extension}';
	if( empty($ext) ) {
		$obj->ext = '{TEMPLATE: post_proc_all_ext_allowed}';
		$ext .= '{TEMPLATE: allowed_extension}';
	}
	
	qf($r);
	
	return $ext;
}

function safe_tmp_copy($source, $del_source=0)
{
	$umask = umask(0177);
	
	if( function_exists("move_uploaded_file") ) {
		if( !move_uploaded_file($source, ($name=tempnam($GLOBALS['TMP'],getmypid()))) ) return;
	}	
	else {
		if( !copy($source, ($name=tempnam($GLOBALS['TMP'],getmypid()))) ) return;
	}	
		
	umask($umask);
	
	if( $del_source ) unlink($source);

	return substr(strrchr($name, '/'), 1);
}

function reverse_nl2br(&$data)
{
	$data = preg_replace("!<br(\s*/\s*)?>((\r\n)|\r|\n)?!i", "\n", $data);
}
?>