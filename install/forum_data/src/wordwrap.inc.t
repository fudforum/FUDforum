<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: wordwrap.inc.t,v 1.2 2002/07/22 14:53:37 hackie Exp $
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
	if( !($len=strlen($data)) ) return array();
	$wa = array();
	
	$i=$p=0;
	$str = '';
	while( $i<$len ) {
		switch( $data[$i] ) 
		{
			case ' ':
			case "\n":
			case "\t":
				if( strlen($str) ) {
					$wa[] = array('word'=>$str, 'check'=>1);
					$str ='';
				}
				
				$wa[] = array('word'=>$data[$i], 'check'=>0);
				
				break;
			case '<':
				if( ($p=strpos($data, '>', $i)) ) {
					if( strlen($str) ) {
						$wa[] = array('word'=>$str, 'check'=>1);
						$str ='';
					}

					$wa[] = array('word'=>substr($data,$i,($p-$i)+1), 'check'=>0);
					
					$i=$p;
				}
				else 
					$str .= $data[$i];
				break;
			default:
				$str .= $data[$i];	
		}
		$i++;
	}
	
	if( strlen($str) ) 
		$wa[] = array('word'=>$str, 'check'=>1);
	
	reset($wa);
	return $wa;
}

function fud_wordwrap(&$data)
{
	if( !strlen($data) || !$GLOBALS["WORD_WRAP"] ) return;

	$wa = fud_wrap_tok($data);
	
	$data = NULL;
	
	foreach($wa as $v) {
		if( $v['check'] == 1 && strlen($v['word'])>$GLOBALS["WORD_WRAP"] ) 
			$data .= wordwrap($v['word'],$GLOBALS["WORD_WRAP"],' ',1);
		else
			$data .= $v['word'];	
	}
}
?>