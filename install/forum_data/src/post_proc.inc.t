<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: post_proc.inc.t,v 1.15 2003/04/09 10:55:57 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

$GLOBALS['seps'] = array(' '=>' ', "\n"=>"\n", "\r"=>"\r", "'"=>"'", '"'=>'"', '['=>'[', ']'=>']', '('=>'(', ';'=>';', ')'=>')', "\t"=>"\t", '='=>'=', '>'=>'>', '<'=>'<');

function fud_substr_replace($str, $newstr, $pos, $len)
{
        return substr($str, 0, $pos).$newstr.substr($str, $pos+$len);
}

function tags_to_html($str, $allow_img='Y')
{
	if (!defined('no_char')) {	
		$str = htmlspecialchars($str);
	}
	
	$str = nl2br($str);
	
	$ostr = '';
	$pos = $old_pos = 0;
	
	while (($pos = strpos($str, '[', $pos)) !== false) {
		if (($epos = strpos($str, ']', $pos)) === false) {
			break;
		}
		$tag = substr($str, $pos+1, $epos-$pos-1);
		if (($pparms = strpos($tag, '=')) !== false) {
			$parms = substr($tag, $pparms+1);
			$tag = substr($tag, 0, $pparms);
		} else {
			$parms = '';
		}
		
		$tag = strtolower($tag);
		
		switch ($tag) {
			case 'quote title':
				$tag = 'quote';
				break;
			case 'list type':
				$tag = 'list';
				break;
		}
		
		if ($tag[0] == '/') { 
			if (isset($end_tag[$pos])) {
				if( ($pos-$old_pos) ) $ostr .= substr($str, $old_pos, $pos-$old_pos);
				$ostr .= $end_tag[$pos];
				$pos = $old_pos = $epos+1; 
			} else {
				$pos = $epos+1;
			}
			
			continue; 
		}

		$cpos = $epos;
		$ctag = '[/'.$tag.']';
		$ctag_l = strlen($ctag);
		$otag = '['.$tag;
		$otag_l = strlen($otag);
		$rf = 1;
		while (($cpos = strpos($str, '[', $cpos)) !== false) {
			if (isset($end_tag[$cpos])) {
				$cpos++;
				continue;
			}

			if (($cepos = strpos($str, ']', $cpos)) === false) {
				break 2;
			}
			
			if (strcasecmp(substr($str, $cpos, $ctag_l), $ctag) == 0) {
				$rf--;
			} else if (strcasecmp(substr($str, $cpos, $otag_l), $otag) == 0) {
				$rf++;
			} else {
				$cpos++;
				continue;		
			}
			
			if (!$rf) {
				break;
			}
			$cpos = $cepos;
		}
		
		if ($rf && $str[$cpos] == '<') { /* left over [ handler */
			$pos++;
			continue;
		}	
		
		if ($cpos !== false) {
			if (($pos-$old_pos)) {
				$ostr .= substr($str, $old_pos, $pos-$old_pos);
			}
			switch ($tag) {
				case 'notag':
					$ostr .= '<span name="notag">'.substr($str, $epos+1, $cpos-1-$epos).'</span>';
					$epos = $cepos;
					break;
				case 'url':
					if (!$parms) {
						$parms = substr($str, $epos+1, ($cpos-$epos)-1);
						if (strpos(strtolower($parms), 'javascript:') === false) {
							$ostr .= '<a href="'.$parms.'" target="_blank">'.$parms.'</a>';
						} else {
							$ostr .= substr($str, $pos, ($cepos-$pos)+1);	
						}
						
						$epos = $cepos;
						$str[$cpos] = '<';
					} else { 
						if (strpos(strtolower($parms), 'javascript:') === false) {
							$end_tag[$cpos] = '</a>';
							$ostr .= '<a href="'.$parms.'" target="_blank">';
						} else {
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
					if (!$parms) {
						$parms = substr($str, $epos+1, ($cpos-$epos)-1);
						$ostr .= '<a href="mailto:'.$parms.'" target="_blank">'.$parms.'</a>';
						$epos = $cepos;
						$str[$cpos] = '<';
					} else { 
						$end_tag[$cpos] = '</a>';
						$ostr .= '<a href="mailto:'.$parms.'" target="_blank">';
					}
					break;
				case 'color':
				case 'size':
				case 'font':
					if ($tag == 'font') {
						$tag = 'face';
					}
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
				case 'php':
					$param = substr($str, $epos+1, ($cpos-$epos)-1);
					reverse_FMT($param);
					reverse_nl2br($param);
					
					if (strpos($param, '<?') !== false) {
						$param = '<?php '.$param.'?>';
					}
					
					$ostr .= '<span name="php">'.highlight_string($param, true).'</span>';
					$epos = $cepos;
					$str[$cpos] = '<';
					break;
				case 'img':
					if( $allow_img == 'N' ) {
						$ostr .= substr($str, $pos, ($cepos-$pos)+1);
					} else {
						if (!$parms) {
							$parms = substr($str, $epos+1, ($cpos-$epos)-1);
							if (strpos(strtolower($parms), 'javascript:') === false) {
								$ostr .= '<img src="'.$parms.'" border=0 alt="'.$parms.'">';
							} else {
								$ostr .= substr($str, $pos, ($cepos-$pos)+1);
							}
						} else { 
							if (strpos(strtolower($parms), 'javascript:') === false) {
								$ostr .= '<img src="'.$parms.'" border=0 alt="'.substr($str, $epos+1, ($cpos-$epos)-1).'">';	
							} else {
								$ostr .= substr($str, $pos, ($cepos-$pos)+1);
							}
						}
					}	
					$epos = $cepos;
					$str[$cpos] = '<';
					break;
				case 'quote':
					if (!$parms) {
						$parms = 'Quote:';
					}
					$ostr .= '<table border="0" align="center" width="90%" cellpadding="3" cellspacing="1"><tr><td class="SmallText"><b>'.$parms.'</b></td></tr><tr><td class="quote"><br>';
					$end_tag[$cpos] = '<br></td></tr></table>';
					break;
				case 'align':
					$end_tag[$cpos] = '</div>';
					$ostr .= '<div align="'.$parms.'">';
					break;
				case 'list':
					$tmp = substr($str, $epos, ($cpos-$epos));
					$tmp_l = strlen($tmp);
					$tmp2 = str_replace('[*]', '<li>', $tmp);
					$tmp2_l = strlen($tmp2);
					$str = str_replace($tmp, $tmp2, $str);
					
					$diff = $tmp2_l - $tmp_l;
					$cpos += $diff;
					
					if (is_array($end_tag)) {
						foreach($end_tag as $key => $val) {
							if ($key < $epos) {
								continue;
							}
						
							$end_tag[$key+$diff] = $val;
							unset($end_tag[$key]);
						}
					}	
					
					switch (strtolower($parms)) {
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
		} else {
			$pos = $epos+1;
		}
	}
	$ostr .= substr($str, $old_pos, strlen($str)-$old_pos);
	
	/* url paser */
	$pos = 0;
	$ppos = 0;	
	while (($pos = @strpos($ostr, '://', $pos)) !== false) {
		if ($pos < $ppos) {
			break;
		}
		// check if it's inside any tag;
		$i = $pos;
		while (--$i && $i > $ppos) {
			if ($ostr[$i] == '>' || $ostr[$i] == '<') {
				break;
			}
		}
		if ($ostr[$i]=='<') {
			$pos+=3;
			continue;
		}
		
		// check if it's inside the a tag
		if (($ts = strpos($ostr, '<a ', $pos)) === false) {
			$ts = strlen($ostr);
		}
		if (($te = strpos($ostr, '</a>', $pos)) == false) {
			$te = strlen($ostr);
		}
		if ($te < $ts) {
			$ppos = $pos += 3;
			continue;
		}
		
		// check if it's inside the pre tag
		if (($ts = strpos($ostr, '<pre>', $pos)) === false) {
			$ts = strlen($ostr);
		}
		if (($te = strpos($ostr, '</pre>', $pos)) == false) {
			$te = strlen($ostr);
		}
		if ($te < $ts) {
			$ppos = $pos += 3;
			continue;
		}
		
		$us = $pos;
		while (1) {
			--$us;
			if (isset($GLOBALS['seps'][$ostr[$us]]) || $ppos>$us || !isset($ostr[$us])) {
				break;
			}
		}
		
		unset($GLOBALS['seps']['=']);
		$ue = $pos;
		while (1) {
			++$ue;
			if ($ostr[$ue] == '&') {
				if ($ostr[$ue+4] == ';') {
					$ue += 4;
					continue;
				}
				if ($ostr[$ue+3] == ';' || $ostr[$ue+5] == ';') {
					break;
				}
			}	
			
			if (isset($GLOBALS['seps'][$ostr[$ue]]) || !isset($ostr[$ue])) {
				break;
			}
		}
		$GLOBALS['seps']['='] = '=';
		
		$url = substr($ostr, $us+1, $ue-$us-1);
		
		$html_url = '<a href="'.$url.'" target="_blank">'.$url.'</a>';
		$html_url_l = strlen($html_url);
		$ostr = fud_substr_replace($ostr, $html_url, $us+1, $ue-$us-1);
		$ppos = $pos;
		$pos = $us+$html_url_l;
	}
	
	/* email parser */
	$pos = 0;
	$ppos = 0;
	while (($pos = @strpos($ostr, '@', $pos)) !== false) {
		if ($pos < $ppos) {
			break;
		}
		
		// check if it's inside any tag;
		$i = $pos;
		while (--$i && $i>$ppos) {
			if ( $ostr[$i] == '>' || $ostr[$i] == '<') {
				break;
			}
		}
		if ($ostr[$i]=='<') {
			++$pos;
			continue;
		}
		
		
		// check if it's inside the a tag
		if (($ts = strpos($ostr, '<a ', $pos)) === false) {
			$ts = strlen($ostr);
		}
		if (($te = strpos($ostr, '</a>', $pos)) == false) {
			$te = strlen($ostr);
		}
		if ($te < $ts) {
			$ppos = $pos += 1;
			continue;
		}
		
		// check if it's inside the pre tag
		if (($ts = strpos($ostr, '<pre>', $pos)) === false) {
			$ts = strlen($ostr);
		}
		if (($te = strpos($ostr, '</pre>', $pos)) == false) {
			$te = strlen($ostr);
		}
		if ($te < $ts) {
			$ppos = $pos += 1;
			continue;
		}
		
		for ($es = ($pos - 1); $es > ($ppos - 1); $es--) {
			if ( 
				( ord($ostr[$es]) >= ord('A') && ord($ostr[$es]) <= ord('z') ) ||
				( ord($ostr[$es]) >= ord(0) && ord($ostr[$es]) <= ord(9) ) ||
				( $ostr[$es] == '.' || $ostr[$es] == '-' || $ostr[$es] == '\'')
			) { continue; }
			++$es;
			break;
		}
		if ($es == $pos) {
			$ppos = $pos += 1;
			continue;
		}
		if ($es < 0) {
			$es = 0;
		}
		
		for ($ee = ($pos + 1); @isset($ostr[$ee]); $ee++) {
			if (
				( ord($ostr[$ee]) >= ord('A') && ord($ostr[$ee]) <= ord('z') ) ||
				( ord($ostr[$ee]) >= ord(0) && ord($ostr[$ee]) <= ord(9) ) ||
				( $ostr[$ee] == '.' || $ostr[$ee] == '-' )
			) { continue; }
			break;
		}
		if ($ee == ($pos+1)) {
			$ppos = $pos += 1;
			continue;
		}
				
		$email = substr($ostr, $es, $ee-$es);
		$email_url = '<a href="mailto:'.$email.'" target="_blank">'.$email.'</a>';
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

	while ( preg_match('!<span name="php">(.*?)</span>!is', $fudml, $res) ) {
		$res[1] = strip_tags($res[1]);
		$fudml = preg_replace('!<span name="php">.*?</span>!is', '[php]'.$res[1].'[/php]', $fudml);
	}

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
	
	if( preg_match('!<a href="mailto:(.+?)" target=(_new|"_blank")>\\1</a>!is', $fudml) )
		$fudml = preg_replace('!<a href="mailto:(.+?)" target=(_new|"_blank")>\\1</a>!is', '[email]\1[/email]', $fudml);
	
	if( preg_match('!<a href="mailto:.+?" target=(_new|"_blank")>.+?</a>!is', $fudml) )
		$fudml = preg_replace('!<a href="mailto:(.+?)" target=(_new|"_blank")>(.+?)</a>!is', '[email=\1]\2[/email]', $fudml);
	
	if( preg_match('!<a href="(.+?)" target=(_new|"_blank")>\\1</a>!is', $fudml) )	
		$fudml = preg_replace('!<a href="(.+?)" target=(_new|"_blank")>\\1</a>!is', '[url]\1[/url]', $fudml);
		
	if( preg_match('!<a href=".+?" target=(_new|"_blank")>.+?</a>!is', $fudml) )	
		$fudml = preg_replace('!<a href="(.+?)" target=(_new|"_blank")>(.+?)</a>!is', '[url=\1]\3[/url]', $fudml);

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
	if (!($rgx = @file_get_contents($GLOBALS['FORUM_SETTINGS_PATH'].'file_filter_regexp'))) {
		return 0;
	}
	return !preg_match('/'.$rgx.'/i', $file_name);
}

function safe_tmp_copy($source, $del_source=0, $prefx='')
{
	if (!$prefx) {
		 $prefx = getmypid();
	}

	$umask = umask(0177);
	if (!move_uploaded_file($source, ($name = tempnam($GLOBALS['TMP'], $prefx.'_')))) {
		return;
	}
	umask($umask);
	if ($del_source) {
		@unlink($source);
	}
	umask($umask);

	return basename($name);
}

function reverse_nl2br(&$data)
{
	$data = preg_replace("!<br(\s*/\s*)?>((\r\n)|\r|\n)?!i", "\n", $data);
}
?>