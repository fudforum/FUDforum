<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: wordwrap.inc.t,v 1.6 2003/06/04 15:22:36 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

function fud_wrap_tok($data)
{
	$wa = array();
	$len = strlen($data);
	
	$i=$j=$p=0;
	$str = '';
	while ($i < $len) {
		switch ($data{$i}) {
			case ' ':
			case "\n":
			case "\t":
				if ($j) {
					$wa[] = array('word'=>$str, 'len'=>($j+1));
					$j=0;
					$str ='';
				}
				
				$wa[] = array('word'=>$data[$i], 'check'=>0);
				
				break;
			case '<':
				if (($p = strpos($data, '>', $i)) !== false) {
					if ($j) {
						$wa[] = array('word'=>$str, 'len'=>($j+1));
						$j=0;
						$str ='';
					}

					$wa[] = array('word'=>substr($data,$i,($p-$i)+1));
					
					$i=$p;
				} else {
					$str .= $data[$i];
					$j++;
				}	
				break;

			default:
				$str .= $data[$i];	
				$j++;
		}
		$i++;
	}
	
	if ($j) {
		$wa[] = array('word'=>$str, 'len'=>($j+1));
	}
	
	return $wa;
}

function fud_wordwrap(&$data)
{
	if (!$GLOBALS['WORD_WRAP'] || $GLOBALS['WORD_WRAP'] >= strlen($data)) {
		return;
	}

	$wa = fud_wrap_tok($data);
	$m = (int) $GLOBALS['WORD_WRAP'];
	
	$data = NULL;
	foreach($wa as $v) {
		if (isset($v['len']) && $v['len'] > $m) { 
			$data .= wordwrap($v['word'], $m, ' ', 1);
		} else {
			$data .= $v['word'];
		}
	}
}
?>