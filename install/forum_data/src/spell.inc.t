<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: spell.inc.t,v 1.11 2003/10/01 21:51:52 hackie Exp $
****************************************************************************

****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

function get_spell_suggest($word)
{
	return pspell_suggest(__FUD_PSPELL_LINK__, $word);
}

function spell_check_w($word)
{
	return pspell_check(__FUD_PSPELL_LINK__, $word);
}

function init_spell($type, $dict)
{
	$pspell_config = pspell_config_create($dict);
	pspell_config_mode($pspell_config, $type);
	pspell_config_personal($pspell_config, $GLOBALS['FORUM_SETTINGS_PATH']."forum.pws");
	pspell_config_ignore($pspell_config, 2);
	define('__FUD_PSPELL_LINK__', pspell_new_config($pspell_config));

	return true;
}

function tokenize_string($data)
{
	if (!($len = strlen($data))) {
		return array();
	}
	$wa = array();

	$i=$p=0;
	while ($i < $len) {
		switch ($data{$i}) {
			case ' ':
			case '/':
			case '\\':
			case '.':
			case ',':
			case '!':
			case '>':
			case '?':
			case "\n":
			case "\r":
			case "\t":
			case ")":
			case "(":
			case "}":
			case "{":
			case "[":
			case "]":
			case "*":
			case ";":
			case '=':
			case ':':
				if (isset($str)) {
					$wa[] = array('token'=>$str, 'check'=>1);
					unset($str);
				}

				$wa[] = array('token'=>$data[$i], 'check'=>0);

				break;
			case '<':
				if (($p = strpos($data, '>', $i)) !== false) {
					if (isset($str)) {
						$wa[] = array('token'=>$str, 'check'=>1);
						unset($str);
					}

					$wrd = substr($data,$i,($p-$i)+1);
					$p3=$l=null;

					if ($wrd == '<pre>') {
						$l = 'pre';
					} else if ($wrd == '<table border="0" align="center" width="90%" cellpadding="3" cellspacing="1">') {
						$l = 1;
						$p3 = $p;

						while ($l > 0) {
							$p3 = strpos($data, 'table', $p3);

							if ($data[$p3-1] == '<') {
								$l++;
							} else if ($data[$p3-1] == '/' && $data[$p3-2] == '<') {
								$l--;
							}

							$p3 = strpos($data, '>', $p3);
						}
					}

					if ($p3) {
						$p = $p3;
						$wrd = substr($data, $i, ($p-$i)+1);
					} else if ($l && ($p2 = strpos($data, '</'.$l.'>', $p))) {
						$p = $p2+1+strlen($l)+1;
						$wrd = substr($data,$i,($p-$i)+1);
					}


					$wa[] = array('token'=>$wrd, 'check'=>0);

					$i=$p;
				} else {
					$str .= $data[$i];
				}
				break;
			case ':':
				if ($data[$i+1] == '/' && $data[$i+2] == '/') {
					$tmp_string = substr($data,$i+3);
					$regs = array();
					if (preg_match('!([A-Za-z0-9\-_\.\%\?\&=/]+)!is', $tmp_string, $regs)) {
						$wa[] = array('token'=>$str.'//'.$regs[1], 'check'=>0);
						unset($str);

						$i += 2+strlen($regs[1]);
						break;
					}
				} else if ($str == 'Re') {
					$wa[] = array('token'=>$str.':', 'check'=>0);
					unset($str);
					break;
				}

				if (isset($str)) {
					$wa[] = array('token'=>$str, 'check'=>1);
					unset($str);
				}
				$wa[] = array('token'=>$data[$i], 'check'=>0);

				break;
			case '&':
				if (isset($str)) {
					$wa[] = array('token'=>$str, 'check'=>1);
					unset($str);
				}

				$regs = array();
				if (preg_match('!(\&[A-Za-z]{2,5}\;)!', substr($data,$i,6), $regs)) {
					$wa[] = array('token'=>$regs[1], 'check'=>0);
					$i += strlen($regs[1])-1;
				} else {
					$wa[] = array('token'=>$data[$i], 'check'=>0);
				}
				break;
			default:
				if (isset($str)) {
					$str .= $data[$i];
				} else {
					$str = $data[$i];
				}
		}
		$i++;
	}

	if (isset($str)) {
		$wa[] = array('token'=>$str, 'check'=>1);
	}

	return $wa;
}

function draw_spell_sug_select($v,$k,$type)
{
	$sel_name = 'spell_chk_'.$type.'_'.$k;
	$data = '<select name="'.$sel_name.'">';
	$data .= '<option value="'.htmlspecialchars($v['token']).'">'.htmlspecialchars($v['token']).'</option>';
	$sug = get_spell_suggest($v['token']);
	$i=0;
	foreach($sug as $va) {
		$data .= '<option value="'.$va.'">'.++$i.') '.$va.'</option>';
	}

	if (!count($sug)) {
		$data .= '<option value="">{TEMPLATE: spell_alts}</option>';
	}

	$data .= '</select>';

	return $data;
}

function spell_replace($wa,$type)
{
	$data = '';

	foreach($wa as $k => $v) {
		if( $v['check']==1 && isset($_POST['spell_chk_'.$type.'_'.$k]) && strlen($_POST['spell_chk_'.$type.'_'.$k])) {
			$data .= $_POST['spell_chk_'.$type.'_'.$k];
		} else {
			$data .= $v['token'];
		}
	}

	return $data;
}

function spell_check_ar($wa,$type)
{
	foreach($wa as $k => $v) {
		if ($v['check'] > 0 && !spell_check_w($v['token'])) {
			$wa[$k]['token'] = draw_spell_sug_select($v, $k, $type);
		}
	}

	return $wa;
}

function reasemble_string($wa)
{
	$data = '';
	foreach($wa as $v) {
		$data .= $v['token'];
	}

	return $data;
}

function check_data_spell($data, $type, $dict)
{
	if (!$data) {
		return $data;
	}
	if (!defined('__FUD_PSPELL_LINK__') && !init_spell(PSPELL_FAST, $dict)) {
		return $data;
	}

	$wa = tokenize_string($data);
	$wa = spell_check_ar($wa, $type);
	$data = reasemble_string($wa);

	return $data;
}
?>