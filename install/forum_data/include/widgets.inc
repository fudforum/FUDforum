<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: widgets.inc,v 1.1 2002/06/26 19:44:35 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/
	fud_use('db.inc');
	fud_use('util.inc');
	fud_use('rev_fmt.inc');
	
function create_select($name, $options, $opt_val, $def_val, $js='', $extraval='')
{
	if( !strlen($options) ) return;

	$options = str_replace("\t", "", $options);
	$opt_val = str_replace("\t", "", $opt_val);
	
	$opt_nam = explode("\n", $options);
	$opt_val = explode("\n", $opt_val);
	$opt_tag = explode("\n", $extraval);
	
	if ( count($opt_nam) != count($opt_val) ) {
		exit("option count doesn't match value count\n");
	}
	
	if ( $js ) $js = ' '.$js;
	
	$rval = '<select name="'.$name.'"'.$js.'>';
	for ( $i=0; $i<count($opt_nam); $i++ ) {
		$selected = ( $def_val == $opt_val[$i] ) ? " selected" : "";
		if ( isset($opt_tag[$i]) ) 
			$tag = ' '.$opt_tag[$i];
		else $tag = '';
		$rval .= '<option value="'.$opt_val[$i].'"'.$selected.''.$tag.'>'.FMT($opt_nam[$i])."\n";
	}
	$rval .= '</select>';
	return $rval;
}

function draw_select($name, $options, $opt_val, $def_val, $js='')
{
	echo create_select($name, $options, $opt_val, $def_val, $js);
}

function draw_select_ex($name, $options, $opt_val, $def_val, $html)
{
	$options = str_replace("\t", "", $options);
	$opt_val = str_replace("\t", "", $opt_val);
	
	$opt_nam = explode("\n", $options);
	$opt_val = explode("\n", $opt_val);
	
	if ( count($opt_nam) != count($opt_val) ) {
		exit("option count doesn't match value count\n");
	}
	
	echo "<select name=\"$name\"".((strlen($html))?' '.$html:'').">\n";
	for ( $i=0; $i<count($opt_nam); $i++ ) {
		$selected = ( $def_val == $opt_val[$i] ) ? " selected" : "";
		echo '<option value="'.$opt_val[$i].'"'.$selected.'>'.FMT($opt_nam[$i])."\n";
	}
	echo "</select>";
}

function create_checkbox($name, $value, $state) 
{
	$checked = ( $value == $state ) ? " checked" : '';
	return '<input type="checkbox" name="'.$name.'" value="'.$value.'"'.$checked.'>';
}

function draw_checkbox($name, $value, $state) 
{
	echo create_checkbox($name, $value, $state);
}

function create_month_select($name, $allow_null, $def)
{
	$rval = '<select name="'.$name.'">';
	
	if ( $allow_null ) $rval .= '<option value="0">';
	
	for ( $i=1; $i<13; $i++ ) {
		$month = date("F", mktime(1, 1, 1, $i, 1, 0));
		$rval .= '<option value="'.$i.'"'.(($i==$def)?' selected':'').'>'.$month."\n"; 
	}
	$rval .= '</select>';
	return $rval;
}

function draw_month_select($name, $allow_null, $def)
{
	echo create_month_select($name, $allow_null, $def);
}

function create_day_select($name, $allow_null, $def)
{
	$rval = '<select name="'.$name.'">';
	
	if ( $allow_null ) $rval .= '<option value="0">';
	
	for ( $i=1; $i<32; $i++ ) {
		$rval .= '<option value="'.$i.'"'.(($i==$def)?' selected':'').'>'.$i."\n";
	}
	$rval .= '</select>';

	return $rval;
}

function draw_day_select($name, $allow_null, $def)
{
	echo create_day_select($name, $allow_null, $def);
}

function create_radio_buttons($name, $options, $values, $def, $vertical=0)
{
	$opt = explode("\n", $options);
	$val = explode("\n", $values);
	
	if ( count($opt) != count($val) ) exit("number of options doesn't match number of values");
	
	$rval = "";
	for ( $i=0; $i<count($opt); $i++ ) {
		$rval .= '<input type="radio" name="'.$name.'" value="'.$val[$i].'"'.(($val[$i] == $def)?' checked':'').'>'.FMT($opt[$i]);
		if ( $vertical ) 
			$rval .= '<br>';
		else 
			$rval .= '&nbsp;&nbsp;';
	}
	
	return $rval;
}

function create_return()
{
	return '<input type="hidden" name="returnto" value="'.(!empty($GLOBALS['returnto'])?urldecode($GLOBALS['returnto']):'').'">';
}

function draw_return()
{
	echo create_return();
}

function check_return()
{
	if ( empty($GLOBALS['returnto']) ) $GLOBALS['returnto']='index.php?'._rsid;
	header("Location: ".$GLOBALS['returnto']);
	exit();
}

function convert_bdate($val, $month_fmt)
{
	$ret['year'] = substr($val, 0, 4);
	$ret['month'] = substr($val, 4, 2);
	$ret['day'] = substr($val, 6, 2);

	$ret['month'] = date($month_fmt, mktime(1, 1, 1, $ret['month'], 11, 2000));
	return $ret;
}
?>