<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: admprune.php,v 1.1.1.1 2002/06/17 23:00:09 hackie Exp $
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
	
	fud_use('static/widgets.inc');
	fud_use('util.inc');
	fud_use('forum.inc');
	fud_use('cat.inc');
	fud_use('fileio.inc');
	fud_use('static/adm.inc');
	fud_use('imsg.inc');
	fud_use('imsg_edt.inc');
	fud_use('th.inc');
	fud_use('ipoll.inc');
	fud_use('attach.inc');
	
	list($ses, $usr) = initadm();
	
	if ( $btn_prune && is_numeric($thread_age) ) {
		/* count up threads */
		$back = $units*$thread_age;
		$back_t = __request_timestamp__-$back;
				
		if ( $forumsel == '0' ) {
			$QRY_TAIL = "FROM ".$GLOBALS['MYSQL_TBL_PREFIX']."thread WHERE last_post_date<".$back_t;
			$msg = '<font color="red">from all forums</font>';
		}
		else if ( strstr($forumsel, 'cat_') ) {
			$cat_id = substr($forumsel, 4);
			$QRY_TAIL = "FROM 
					".$GLOBALS['MYSQL_TBL_PREFIX']."thread 
					INNER JOIN ".$GLOBALS['MYSQL_TBL_PREFIX']."forum 
						ON ".$GLOBALS['MYSQL_TBL_PREFIX']."thread.forum_id=".$GLOBALS['MYSQL_TBL_PREFIX']."forum.id
					INNER JOIN ".$GLOBALS['MYSQL_TBL_PREFIX']."cat ON ".$GLOBALS['MYSQL_TBL_PREFIX']."forum.cat_id=".$GLOBALS['MYSQL_TBL_PREFIX']."cat.id
					WHERE last_post_date<".$back_t." AND ".$GLOBALS['MYSQL_TBL_PREFIX']."cat.id=".$cat_id;
			$cat_name = Q_SINGLEVAL("SELECT name FROM ".$GLOBALS['MYSQL_TBL_PREFIX']."cat WHERE id=".$cat_id);					
			$msg = '<font color="red"> from all forums in category '.$cat_name.'</font>';
		}
		else if ( $forumsel ) {
			$QRY_TAIL = "FROM 
					".$GLOBALS['MYSQL_TBL_PREFIX']."thread 
					INNER JOIN ".$GLOBALS['MYSQL_TBL_PREFIX']."forum 
						ON ".$GLOBALS['MYSQL_TBL_PREFIX']."thread.forum_id=".$GLOBALS['MYSQL_TBL_PREFIX']."forum.id
					WHERE last_post_date<".$back_t." AND ".$GLOBALS['MYSQL_TBL_PREFIX']."forum.id=".$forumsel;
			$frm_name = Q_SINGLEVAL("SELECT name FROM ".$GLOBALS['MYSQL_TBL_PREFIX']."forum WHERE id=".$forumsel);
			$msg = '<font color="red"> from '.$frm_name.'</font>';
		}
		
		if ( !$btn_conf && !$btn_cancel ) { /* confirmation dialog */
			$v = Q_SINGLEVAL("SELECT count(*) ".$QRY_TAIL);
			$str_time = strftime("%Y-%m-%d %T", $back_t);
			exit('
			<html>
			<div align=center>You are about to delete <font color="red">'.$v.'</font> threads which were posted before <font color="red">'.$str_time.'</font> '.$msg.'<br><br>
			Are you sure you want to do this?<br>
			<form method="post">
			<input type="hidden" name="btn_prune" value="1">
			'._hs.'
			<input type="hidden" name="thread_age" value="'.$thread_age.'">
			<input type="hidden" name="units" value="'.$units.'">
			<input type="hidden" name="forumsel" value="'.$forumsel.'">
			<input type="submit" name="btn_conf" value="Yes">
			<input type="submit" name="btn_cancel" value="No">
			</form>
			</div>
			</html>
			');
		}
		else if ( $btn_cancel ) {
			header("Location: admprune.php?"._rsid."&rand=".get_random_value());
		}
		else if ( $btn_conf ) { /* prune here */
			DB_LOCK($GLOBALS['MYSQL_TBL_PREFIX'].'thread_view+, 
				'.$GLOBALS['MYSQL_TBL_PREFIX'].'cat+, 
				'.$GLOBALS['MYSQL_TBL_PREFIX'].'level+, 
				'.$GLOBALS['MYSQL_TBL_PREFIX'].'forum+, 
				'.$GLOBALS['MYSQL_TBL_PREFIX'].'forum_read+, 
				'.$GLOBALS['MYSQL_TBL_PREFIX'].'thread+, 
				'.$GLOBALS['MYSQL_TBL_PREFIX'].'msg+, 
				'.$GLOBALS['MYSQL_TBL_PREFIX'].'attach+, 
				'.$GLOBALS['MYSQL_TBL_PREFIX'].'poll+, 
				'.$GLOBALS['MYSQL_TBL_PREFIX'].'poll_opt+, 
				'.$GLOBALS['MYSQL_TBL_PREFIX'].'poll_opt_track+, 
				'.$GLOBALS['MYSQL_TBL_PREFIX'].'users+, 
				'.$GLOBALS['MYSQL_TBL_PREFIX'].'thread_notify+, 
				'.$GLOBALS['MYSQL_TBL_PREFIX'].'msg_report+, 
				'.$GLOBALS['MYSQL_TBL_PREFIX'].'thread_rate_track+');
			
			$r = Q("SELECT root_msg_id, forum_id ".$QRY_TAIL);
			while ( $obj = DB_ROWOBJ($r) ) {
				if ( !isset($idlist[$obj->forum_id]) ) $idlist[$obj->forum_id] = $obj->forum_id;
				$msg = new fud_msg_edit;
				$msg->get_by_id($obj->root_msg_id);
				$msg->delete(FALSE);
				unset($msg);
			}
			QF($r);

 			if ( isset($idlist) ) {
				reset($idlist);
				while ( list(,$v) = each($idlist) ) {
					rebuild_forum_view($v);
				}
			}
			DB_UNLOCK();
		}
		header("Location: admprune.php?"._rsid."&rand=".get_random_value());
	}

include('admpanel.php'); 
?>	
<h2>Thread Prunning</h2>
<form method="post" action="admprune.php?rand=<?php echo get_random_value(); ?>">
<table border=0 cellspacing=1 callpadding=3>
<tr>
	<td bgcolor="#bff8ff" nowrap>Threads with last post made:</td>
	<td bgcolor="#bff8ff"><input type="text" name="thread_age"></td>
	<td bgcolor="#bff8ff" nowrap><?php draw_select("units", "Day(s)\nWeek(s)\nMonth(s)\nYear(s)", "86400\n604800\n2635200\n31622400", $units); ?> ago</td>
</tr>

<tr>
	<td bgcolor="#bff8ff">Limit to forum:</td>
	<td colspan=2 bgcolor="#bff8ff" nowrap>
	<?php 
		$r = Q("SELECT ".$GLOBALS['MYSQL_TBL_PREFIX']."forum.id, ".$GLOBALS['MYSQL_TBL_PREFIX']."forum.name, ".$GLOBALS['MYSQL_TBL_PREFIX']."cat.name as cat_name, ".$GLOBALS['MYSQL_TBL_PREFIX']."cat.id as cat_id FROM ".$GLOBALS['MYSQL_TBL_PREFIX']."forum LEFT JOIN ".$GLOBALS['MYSQL_TBL_PREFIX']."cat ON ".$GLOBALS['MYSQL_TBL_PREFIX']."forum.cat_id=".$GLOBALS['MYSQL_TBL_PREFIX']."cat.id ORDER BY ".$GLOBALS['MYSQL_TBL_PREFIX']."cat.view_order, ".$GLOBALS['MYSQL_TBL_PREFIX']."forum.view_order");
		echo '<select name="forumsel">';
		echo '<option value="0">- All Forums -';
		while ( $obj = DB_ROWOBJ($r) ) {
			if ( $cat_name != $obj->cat_name ) { echo '<option value="cat_'.$obj->cat_id.'">'.$obj->cat_name; $cat_name = $obj->cat_name; }
			echo '<option value="'.$obj->id.'">&nbsp;&nbsp;-&nbsp;'.$obj->name.'</option>';
		}
		QF($r);
		echo '</select>';
	?>
</tr>

<tr>
	<td bgcolor="#bff8ff" align=right colspan=3><input type="submit" name="btn_prune" value="Prune"></td>
</tr>
</table>
<?php echo _hs; ?>
</form>
<?php readfile('admclose.html'); ?>