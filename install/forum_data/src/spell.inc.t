<?php
/**
* copyright            : (C) 2001-2021 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id$
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; version 2 of the License.
**/

function init_spell($lang)
{
	// $pspell_config = pspell_config_create($lang);
	// pspell_config_mode($pspell_config, 'PSPELL_FAST');
	// pspell_config_personal($pspell_config, $GLOBALS['FORUM_SETTINGS_PATH'] .'forum.pws');
	// pspell_config_ignore($pspell_config, 2);
	// define('__FUD_SPELL_LINK__', pspell_new_config($pspell_config));

	$r = enchant_broker_init();
	if (enchant_broker_dict_exists($r, $lang)) {
		$d1 = enchant_broker_request_dict($r, $lang);
		$d2 = enchant_broker_request_pwl_dict($r, $GLOBALS['FORUM_SETTINGS_PATH'] .'forum.pwl');
		define('__FUD_SPELL_LINK__',  $d1);
		define('__FUD_PSPELL_LINK__', $d2);
		return true;
	} else {
		return false;
	}
}

function tokenize_string($data)
{
	if (!($len = strlen($data))) {
		return array();
	}
	$wa = array();

	$i = $p = 0;
	$seps = array(','=>1,' '=>1,'/'=>1,'\\'=>1,'.'=>1,','=>1,'!'=>1,'>'=>1,'?'=>1,"\n"=>1,"\r"=>1,"\t"=>1,')'=>1,'('=>1,'}'=>1,'{'=>1,'['=>1,']'=>1,'*'=>1,';'=>1,'='=>1,':'=>1,'1'=>1,'2'=>1,'3'=>1,'4'=>1,'5'=>1,'6'=>1,'7'=>1,'8'=>1,'9'=>1,'0'=>1);

	while ($i < $len) {
		if (isset($seps[$data[$i]])) {
			if (isset($str)) {
				$wa[] = array('token'=>$str, 'check'=>1);
				unset($str);
			}
			$wa[] = array('token'=>$data[$i], 'check'=>0);
		} else if ($data[$i] == '<') {
			if (($p = strpos($data, '>', $i)) !== false) {
				if (isset($str)) {
					$wa[] = array('token'=>$str, 'check'=>1);
					unset($str);
				}

				$wrd = substr($data,$i,($p-$i)+1);
				$p3 = $l = null;

				/* remove code blocks */
				if ($wrd == '<pre>') {
					$l = 'pre';
					
				/* Deal with bad old style quotes - remove in future release. */
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
					
				/* Remove new style quotes. */
				} else if ($wrd == '<blockquote>') {
					$l = 1;
					$p3 = $p;

					while ($l > 0) {
						$p3 = strpos($data, 'blockquote', $p3);

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
				$i = $p;
			} else {
				$str .= $data[$i];
			}
		} else if ($data[$i] == '&') {
			if (isset($str)) {
				$wa[] = array('token'=>$str, 'check'=>1);
				unset($str);
			}

			$regs = array();
			if (preg_match('!(\&[A-Za-z0-9]{2,5}\;)!', substr($data,$i,6), $regs)) {
				$wa[] = array('token'=>$regs[1], 'check'=>0);
				$i += strlen($regs[1])-1;
			} else {
				$wa[] = array('token'=>$data[$i], 'check'=>0);
			}
		} else if (isset($str)) {
			$str .= $data[$i];
		} else {
			$str = $data[$i];
		}
		$i++;
	}

	if (isset($str)) {
		$wa[] = array('token'=>$str, 'check'=>1);
	}

	return $wa;
}

function draw_spell_sug_select($v, $k, $type)
{
	$sel_name = 'spell_chk_'. $type .'_'. $k;
	$data = '<select name="'. $sel_name .'">';
	$data .= '<option value="'. htmlspecialchars($v['token']) .'">'. htmlspecialchars($v['token']) .'</option>';
	$i = 0;
	// foreach(pspell_suggest(__FUD_SPELL_LINK__, $v['token']) as $va) {
	foreach(enchant_dict_suggest(__FUD_SPELL_LINK__, $v['token']) as $va) {
		$data .= '<option value="'. $va .'">'. ++$i .') '. $va .'</option>';
	}

	if (!$i) {
		$data .= '<option value="">{TEMPLATE: spell_alts}</option>';
	}

	$data .= '</select>';

	return $data;
}

function spell_replace($wa, $type)
{
	$data = '';

	foreach($wa as $k => $v) {
		if( $v['check']==1 && isset($_POST['spell_chk_'. $type .'_'. $k]) && strlen($_POST['spell_chk_'. $type .'_'. $k])) {
			$data .= $_POST['spell_chk_'. $type .'_'. $k];
		} else {
			$data .= $v['token'];
		}
	}

	return $data;
}

function spell_check_ar($wa, $type)
{
	foreach($wa as $k => $v) {
		// if ($v['check'] > 0 && !pspell_check(__FUD_SPELL_LINK__, $v['token'])) {
		if ($v['check'] > 0 && !enchant_dict_check(__FUD_SPELL_LINK__, $v['token']) && !enchant_dict_check(__FUD_PSPELL_LINK__, $v['token'])) {
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

function check_data_spell($data, $type, $lang)
{
	if (!$data || (!defined('__FUD_SPELL_LINK__') && !init_spell($lang))) {
		return $data;
	}

	return reasemble_string(spell_check_ar(tokenize_string($data), $type));
}
?>
