<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: admannounce.php,v 1.1.1.1 2002/06/17 23:00:09 hackie Exp $
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

	include_once "GLOBALS.php";
	
	fud_use('static/widgets.inc');
	fud_use('util.inc');
	fud_use('static/announce_adm.inc');
	fud_use('forum.inc');
	fud_use('cat.inc');
	fud_use('static/adm.inc');
	
	list($ses, $usr) = initadm();
	
	if ( !empty($btn_cancel) ) {
		header("Location: admannounce.php");
		exit();
	}
	
	if ( !empty($del) ) {
		$a_d = new fud_announce_adm;
		$a_d->get($del);
		$a_d->delete();
		
		header("Location: admannounce.php?"._rsid);
		exit();
	}
	
	if ( !empty($edit) && empty($prev_l) ) {
		$a_r = new fud_announce_adm;
		$a_r->get($edit);
		$a_r->export_vars('a_');
		
		list($d_year, $d_month, $d_day) = explode('-', $a_r->date_started);
		list($d2_year, $d2_month, $d2_day) = explode('-', $a_r->date_ended);
		
		$a_r->get_frm_list();
		$a_r->reset_forums();
		while ( $obj = $a_r->each_forum() ) {
			$GLOBALS['frm_'.$obj->forum_id] = 1;
		}
	}
	
	if ( !empty($btn_submit) ) {
		$a_s = new fud_announce_adm;
		$a_s->fetch_vars($HTTP_POST_VARS, 'a_');
		
		$d_year = prepad($d_year, 2, '0');
		$d2_year = prepad($d2_year, 2, '0');
		
		$d_month = prepad($d_month, 2, '0');
		$d2_month = prepad($d2_month, 2, '0');
		
		$d_day = prepad($d_day, 2, '0');
		$d2_day = prepad($d2_day, 2, '0');
		
		$y = strftime("%Y", __request_timestamp__);
		$c = substr($y, 0, strlen($y)-2);
		
		if ( strlen($d_year) < 4 ) $d_year = $c.$d_year;
		if ( strlen($d2_year) < 4 ) $d2_year = $c.$d2_year;
				
		$a_s->date_stared = $d_year.$d_month.$d_day;
		$a_s->date_ended = $d2_year.$d2_month.$d2_day;
		
		$a_s->add();
		
		$f = new fud_forum_adm;
		$f->get_all_forums();
		$a_s->rm_all_forums();
		
		while ( $obj = $f->nextfrm() ) {
			if ( $HTTP_POST_VARS['frm_'.$f->id] ) {
				$a_s->add_forum($f->id);
			} 
		}
		
		header("Location: admannounce.php?"._rsid);
		exit();
	}
	
	if ( !empty($btn_update) && !empty($edit) ) {
		$a_s = new fud_announce_adm;
		
		$a_s->get($edit);
		
		$a_s->fetch_vars($HTTP_POST_VARS, 'a_');
		
		$d_year = prepad($d_year, 2, '0');
		$d2_year = prepad($d2_year, 2, '0');
		
		$d_month = prepad($d_month, 2, '0');
		$d2_month = prepad($d2_month, 2, '0');
		
		$d_day = prepad($d_day, 2, '0');
		$d2_day = prepad($d2_day, 2, '0');
		
		$y = strftime("%Y", __request_timestamp__);
		$c = substr($y, 0, strlen($y)-2);
		
		if ( strlen($d_year) < 4 ) $d_year = $c.$d_year;
		if ( strlen($d2_year) < 4 ) $d2_year = $c.$d2_year;
				
		$a_s->date_stared = $d_year.$d_month.$d_day;
		$a_s->date_ended = $d2_year.$d2_month.$d2_day;
		
		$a_s->sync();
		
		$f = new fud_forum_adm;
		$f->get_all_forums();
		$a_s->rm_all_forums();
		
		while ( $obj = $f->nextfrm() ) {
			if ( $HTTP_POST_VARS['frm_'.$f->id] ) {
				$a_s->add_forum($f->id);
			} 
		}
		
		header("Location: admannounce.php?"._rsid);
		exit();                                                                                                            
	} 
	
	if ( empty($edit) && empty($prev_l) ) {
		$tm_now = __request_timestamp__;
		$tm_exp = $tm_now+86400;
		list($d_day, $d_month, $d_year) = explode(" ", strftime("%d %m %Y", $tm_now));
		list($d2_day, $d2_month, $d2_year) = explode(" ", strftime("%d %m %Y", $tm_exp));
	}
	
	cache_buster();
	
	$a_subject = ( isset($a_subject) ) ? htmlspecialchars($a_subject) : '';
	$a_text = ( isset($a_text) ) ? htmlspecialchars($a_text) : '';
	
	include('admpanel.php'); 
?>
<h2>Announcement System</h2>
<form method="post" name="a_frm">
<?php echo _hs; ?>
<table border=0 cellspacing=1 cellpadding=3>
	<tr bgcolor="#bff8ff">
		<td valign=top>Forums</td>
		<td>
		<?php
			/* draw forums */			
			$frm = new fud_forum_adm;
			$frm->get_all_forums();
			
			$frm->resetfrm();
			
			$rows = 6;
			$tbl =  "<table border=0 cellspacing=1 cellpadding=2>\n";
			$cat = new fud_cat;
			$cat_id=$js_none=$js_all=NULL;
			while ( $frm->nextfrm() ) {
				if ( $cat_id != $frm->cat_id ) {
					$cat->get_cat($frm->cat_id);
					$tbl .= "<tr><td bgcolor=\"#eeeeee\" colspan=$rows><font size=-2>$cat->name</font></td></tr>\n<tr bgcolor=\"#ffffff\">";
					$row = 0;
				}
				$cat_id = $frm->cat_id;
				
				if ( $row++ >= $rows ) {
					$row = 0;
					$tbl .= "</tr><tr bgcolor=\"#ffffff\">";
				}
				
				$tbl .= "<td>".create_checkbox('frm_'.$frm->id, 1, empty($GLOBALS['frm_'.$frm->id])?'':$GLOBALS['frm_'.$frm->id])."<font size=-2> $frm->name</font></td>";
				$js_none .= 'document.a_frm.frm_'.$frm->id.'.checked=false; ';
				$js_all .= 'document.a_frm.frm_'.$frm->id.'.checked=true; ';
			}
			
			$tbl .=  "</tr>\n</table>\n";
			echo '<input type="button" onClick="javascript: '.$js_none.'" value="None"> ';
			echo '<input type="button" onClick="javascript: '.$js_all.'" value="All">';
			echo $tbl;
		?>
		</td>
	</tr>
	
	<tr bgcolor="#bff8ff">
		<td>Starting Date:</td>
		<td>
			<table border=0 cellspacing=1 cellpadding=0>
				<tr><td><font size=-2>Month</font></td><td><font size=-2>Day</font></td><td><font size=-2>Year</td></tr>
				<tr><td><?php draw_month_select('d_month', 0, $d_month); ?></td><td><?php draw_day_select('d_day', 0, $d_day); ?></td><td><input type="text" name="d_year" value="<?php echo ($d_year?$d_year:strftime("%Y", __request_timestamp__)); ?>" size=5></td></tr>
			</table>
		</td>
	</tr>
	
	<tr bgcolor="#bff8ff">
		<td>Ending Date:</td>
		<td>
			<table border=0 cellspacing=1 cellpadding=0>
				<tr><td><font size=-2>Month</font></td><td><font size=-2>Day</font></td><td><font size=-2>Year</td></tr>
				<tr><td><?php draw_month_select('d2_month', 0, $d2_month); ?></td><td><?php draw_day_select('d2_day', 0, $d2_day); ?></td><td><input type="text" name="d2_year" value="<?php echo ($d2_year?$d2_year:strftime("%Y", __request_timestamp__)); ?>" size=5></td></tr>
			</table>
		</td>
	</tr>
	
	<tr bgcolor="#bff8ff">
		<td>Subject:</td>
		<td><input type="text" name="a_subject" value="<?php echo $a_subject; ?>">
	</tr>
	
	<tr bgcolor="#bff8ff">
		<td valign=top>Message:</td>
		<td><textarea cols=40 rows=10 name="a_text"><?php echo $a_text; ?></textarea></td>
	</tr>
	
	<tr bgcolor="#bff8ff">
		<td colspan=2 align=right>
		<?php
			if ( !empty($edit) ) {
				echo '<input type="submit" name="btn_cancel" value="Cancel"> ';
				echo '<input type="submit" name="btn_update" value="Update">';
			}
			else {
				echo '<input type="submit" name="btn_submit" value="Add">';
			}
		?>
		</td>
	</tr>
	
</table>
<input type="hidden" name="edit" value="<?php echo (empty($edit)?'':$edit); ?>">
<input type="hidden" name="prev_l" value="1">
</form>

<table border=0 cellspacing=3 cellpadding=2>
<tr bgcolor="#e5ffe7">
	<td>Subject</td>
	<td>Body</td>
	<td>Starting Date</td>
	<td>Ending Date</td>
	<td>Action</td>
</tr>
<?php

	$a_l = new fud_announce_adm;
	$a_l->getall();
	$a_l->reseta();
	
	$i=1;
	while ( $obj = $a_l->eacha() ) {
		$bgcolor = ($i++%2)?' bgcolor="#fffee5"':'';
		if ( !empty($edit) && $edit==$obj->id ) $bgcolor =' bgcolor="#ffb5b5"';
		
		if ( strlen($obj->text) > 25 ) 
			$b = substr($obj->text, 0, 25).'...';
		else 
			$b = $obj->text;
		
		$ctl = "<td>[<a href=\"admannounce.php?edit=".$obj->id."&"._rsid."\">Edit</a>] [<a href=\"admannounce.php?del=".$obj->id."&"._rsid."\">Delete</a>]</td>";
		echo "<tr".$bgcolor."><td>".$obj->subject."&nbsp;</td><td>".$b."&nbsp;</td><td>".$obj->date_started."</td><td>".$obj->date_ended."</td>".$ctl."</tr>\n";
	}
?>
</table>
<?php readfile('admclose.html'); ?>