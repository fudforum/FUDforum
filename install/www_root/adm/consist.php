<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: consist.php,v 1.1.1.1 2002/06/17 23:00:09 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

	@set_time_limit(600);
	define('admin_form', 1);

	include_once "GLOBALS.php";
	
	fud_use('db.inc');
	fud_use('static/glob.inc');
	fud_use('static/adm.inc');
	fud_use('static/widgets.inc');
	fud_use('static/ext.inc');
	fud_use('static/level_adm.inc');
	fud_use('imsg.inc');
	fud_use('imsg_edt.inc');
	fud_use('err.inc');
	fud_use('private.inc');
	fud_use('util.inc');
	fud_use('th.inc');
	fud_use('users.inc');
	fud_use('customtags.inc');
	fud_use('ipoll.inc');
	fud_use('attach.inc');	
	fud_use('groups.inc');
	
	list($ses, $usr) = initadm();
function draw_stat($text)
{
	echo '<b>'.htmlspecialchars($text).'</b><br>';
	flush();
}

function draw_info($cnt)
{
	if ( $cnt < 1 ) 
		draw_stat('OK');
	else
		draw_stat($cnt.' entries unmatched, deleted');
}

	if( !empty($HTTP_POST_VARS['cancel']) ) {
		header("Location: admglobal.php?"._rsid);
		exit;
	}
	include('admpanel.php');

	if( empty($HTTP_POST_VARS['conf']) && empty($HTTP_GET_VARS['enable_forum']) ) {
?>		
<form method="post" action="consist.php">
<div align="center">
Consistency check is a complex process which may take several minutes to run, while it is running your 
forum will be disabled.<br><br>
<h2>Do you wish to proceed?</h2>
<input type="submit" name="cancel" value="No">&nbsp;&nbsp;&nbsp;<input type="submit" name="conf" value="Yes">
</div>
<?php echo _hs; ?>
</form>	
<?php	
		readfile('admclose.html');
		exit;	
	}

	if( $GLOBALS['FORUM_ENABLED'] == 'Y' ) {
		draw_stat('Disabling the forum for the duration of maintenance run');
		maintenance_status('Undergoing maintenance, please come back later.', 'N');
	}
	
	echo '	<script language="Javascript1.2">
			var intervalID;
			function scrolldown()
			{
				window.scroll(0, 30000);
			}
			intervalID=setInterval("scrolldown()", 100);
		</script>';
	
	draw_stat('Locking the database for checking');
	/* normal forum messages */
	DB_LOCK('
		'.$GLOBALS['MYSQL_TBL_PREFIX'].'thread+, 
		'.$GLOBALS['MYSQL_TBL_PREFIX'].'thread_view+,
		'.$GLOBALS['MYSQL_TBL_PREFIX'].'forum+, 
		'.$GLOBALS['MYSQL_TBL_PREFIX'].'cat+, 
		'.$GLOBALS['MYSQL_TBL_PREFIX'].'msg+, 
		'.$GLOBALS['MYSQL_TBL_PREFIX'].'ses+, 
		'.$GLOBALS['MYSQL_TBL_PREFIX'].'mod+, 
		'.$GLOBALS['MYSQL_TBL_PREFIX'].'users+, 
		'.$GLOBALS['MYSQL_TBL_PREFIX'].'users AS fud_users_2+, 
		'.$GLOBALS['MYSQL_TBL_PREFIX'].'read+, 
		'.$GLOBALS['MYSQL_TBL_PREFIX'].'poll+, 
		'.$GLOBALS['MYSQL_TBL_PREFIX'].'poll_opt+, 
		'.$GLOBALS['MYSQL_TBL_PREFIX'].'poll_opt_track+, 
		'.$GLOBALS['MYSQL_TBL_PREFIX'].'smiley+, 
		'.$GLOBALS['MYSQL_TBL_PREFIX'].'thread_notify+, 
		'.$GLOBALS['MYSQL_TBL_PREFIX'].'forum_notify+, 
		'.$GLOBALS['MYSQL_TBL_PREFIX'].'attach+, 
		'.$GLOBALS['MYSQL_TBL_PREFIX'].'msg_report+, 
		'.$GLOBALS['MYSQL_TBL_PREFIX'].'thread_rate_track+, 
		'.$GLOBALS['MYSQL_TBL_PREFIX'].'level+, 
		'.$GLOBALS['MYSQL_TBL_PREFIX'].'custom_tags+,
		'.$GLOBALS['MYSQL_TBL_PREFIX'].'user_ignore+,
		'.$GLOBALS['MYSQL_TBL_PREFIX'].'buddy+,
		'.$GLOBALS['MYSQL_TBL_PREFIX'].'pmsg+,
		'.$GLOBALS['MYSQL_TBL_PREFIX'].'ext_block+,
		'.$GLOBALS['MYSQL_TBL_PREFIX'].'groups+,
		'.$GLOBALS['MYSQL_TBL_PREFIX'].'group_resources+,
		'.$GLOBALS['MYSQL_TBL_PREFIX'].'group_members+,
		'.$GLOBALS['MYSQL_TBL_PREFIX'].'group_cache+
	');
	draw_stat('Locked!');

	draw_stat('Validating category order');
	$i=1;
	$r = Q("SELECT id,view_order FROM ".$GLOBALS['MYSQL_TBL_PREFIX']."cat ORDER BY view_order,id");
	while( list($id,$view_order) = DB_ROWARR($r) ) {
		if( $view_order != $i ) Q("UPDATE ".$GLOBALS['MYSQL_TBL_PREFIX']."cat SET view_order=".$i." WHERE id=".$id);
		$i++;
	}
	QF($r);
	draw_stat('Done: Validating category order');

	draw_stat('Checking if moderator and users table match');
	$cnt = 0;
	/* forum moderators exist, mod->users */
	$result = Q("SELECT ".$GLOBALS['MYSQL_TBL_PREFIX']."mod.id AS tbl_mod_id, ".$GLOBALS['MYSQL_TBL_PREFIX']."users.id AS tbl_user_id, ".$GLOBALS['MYSQL_TBL_PREFIX']."forum.id AS tbl_forum_id FROM ".$GLOBALS['MYSQL_TBL_PREFIX']."mod LEFT JOIN ".$GLOBALS['MYSQL_TBL_PREFIX']."users ON ".$GLOBALS['MYSQL_TBL_PREFIX']."mod.user_id=".$GLOBALS['MYSQL_TBL_PREFIX']."users.id LEFT JOIN ".$GLOBALS['MYSQL_TBL_PREFIX']."forum ON ".$GLOBALS['MYSQL_TBL_PREFIX']."mod.forum_id=".$GLOBALS['MYSQL_TBL_PREFIX']."forum.id");
	while ( $obj = DB_ROWOBJ($result) ) {
		if ( !strlen($obj->tbl_user_id) || !strlen($obj->tbl_forum_id) ) {
			Q("DELETE FROM ".$GLOBALS['MYSQL_TBL_PREFIX']."mod WHERE id=".$obj->tbl_mod_id);
			++$cnt;
		} 
	}
	QF($result);
	draw_info($cnt);
	
	draw_stat('Rebuilding moderators');
	$ar = $ar2= array();
	$r = Q("SELECT ".$GLOBALS['MYSQL_TBL_PREFIX']."users.id,".$GLOBALS['MYSQL_TBL_PREFIX']."users.login,".$GLOBALS['MYSQL_TBL_PREFIX']."mod.forum_id FROM ".$GLOBALS['MYSQL_TBL_PREFIX']."mod LEFT JOIN ".$GLOBALS['MYSQL_TBL_PREFIX']."users ON ".$GLOBALS['MYSQL_TBL_PREFIX']."mod.user_id=".$GLOBALS['MYSQL_TBL_PREFIX']."users.id ORDER BY forum_id");
	while ( $obj = DB_ROWOBJ($r) ) {
		if( empty($ar[$obj->forum_id]) ) {
			$ar[$obj->forum_id]='';
			$ar2[$obj->forum_id]=0;
		}
		if( $ar2[$obj->forum_id] >= $GLOBALS['SHOW_N_MODS'] ) continue;
				
		$ar2[$obj->forum_id]++;	
		$ar[$obj->forum_id] .= $obj->id."\n".htmlspecialchars(trim_show_len($obj->login,'LOGIN'))."\n\n";
	}
	QF($r);
	
	reset($ar);
	while( list($k,$v) = each($ar) ) {
		if( $k ) {
			$v = substr($v, 0, -1);
			Q("UPDATE ".$GLOBALS['MYSQL_TBL_PREFIX']."forum SET moderators='$v' WHERE id=".$k);	
		}
	}
	
	Q("UPDATE ".$GLOBALS['MYSQL_TBL_PREFIX']."users SET is_mod='N' WHERE is_mod!='A'");
	$r = Q("SELECT user_id FROM ".$GLOBALS['MYSQL_TBL_PREFIX']."mod INNER JOIN ".$GLOBALS['MYSQL_TBL_PREFIX']."users ON ".$GLOBALS['MYSQL_TBL_PREFIX']."users.id=".$GLOBALS['MYSQL_TBL_PREFIX']."mod.user_id WHERE ".$GLOBALS['MYSQL_TBL_PREFIX']."users.is_mod!='A' GROUP BY user_id");
	while( list($uid) = DB_ROWARR($r) ) Q("UPDATE ".$GLOBALS['MYSQL_TBL_PREFIX']."users SET is_mod='Y' WHERE id=".$uid);
	QF($r);
	
	$r = Q("SELECT ".$GLOBALS['MYSQL_TBL_PREFIX']."forum.id FROM ".$GLOBALS['MYSQL_TBL_PREFIX']."forum LEFT JOIN ".$GLOBALS['MYSQL_TBL_PREFIX']."mod ON ".$GLOBALS['MYSQL_TBL_PREFIX']."mod.forum_id=".$GLOBALS['MYSQL_TBL_PREFIX']."forum.id WHERE forum_id IS NULL");
	while( list($fid) = DB_ROWARR($r) ) {
		Q("UPDATE ".$GLOBALS['MYSQL_TBL_PREFIX']."forum SET moderators='' WHERE id=".$fid);
	}
	QF($r);
	draw_stat('Done: Rebuilding moderators');
		
	draw_stat('Checking if all private messages have users');
	$cnt = 0;

	$r = Q("SELECT ".$GLOBALS['MYSQL_TBL_PREFIX']."pmsg.id FROM ".$GLOBALS['MYSQL_TBL_PREFIX']."pmsg LEFT JOIN ".$GLOBALS['MYSQL_TBL_PREFIX']."users ON ".$GLOBALS['MYSQL_TBL_PREFIX']."pmsg.ouser_id=".$GLOBALS['MYSQL_TBL_PREFIX']."users.id WHERE mailed='N' AND ".$GLOBALS['MYSQL_TBL_PREFIX']."users.id IS NULL");
	while( $obj = DB_ROWOBJ($r) ) {
		$pmsg = new fud_pmsg;
		QOBJ("SELECT * FROM ".$GLOBALS['MYSQL_TBL_PREFIX']."pmsg WHERE id=".$obj->id, $pmsg);
		$pmsg->del_pmsg('TRASH');
		++$cnt;
	}

	$r = Q("SELECT ".$GLOBALS['MYSQL_TBL_PREFIX']."pmsg.id FROM ".$GLOBALS['MYSQL_TBL_PREFIX']."pmsg LEFT JOIN ".$GLOBALS['MYSQL_TBL_PREFIX']."users ON ".$GLOBALS['MYSQL_TBL_PREFIX']."pmsg.duser_id=".$GLOBALS['MYSQL_TBL_PREFIX']."users.id WHERE mailed='Y' AND ".$GLOBALS['MYSQL_TBL_PREFIX']."users.id IS NULL");
	while( $obj = DB_ROWOBJ($r) ) {
		$pmsg = new fud_pmsg;
		QOBJ("SELECT * FROM ".$GLOBALS['MYSQL_TBL_PREFIX']."pmsg WHERE id=".$obj->id, $pmsg);
		$pmsg->del_pmsg('TRASH');
		++$cnt;
	}
	
	draw_info($cnt);
	
	draw_stat('Checking read table against users');
	$cnt = 0;
	/* read messages->users */
	$result=Q("SELECT ".$GLOBALS['MYSQL_TBL_PREFIX']."read.id, ".$GLOBALS['MYSQL_TBL_PREFIX']."users.id AS usr_id FROM ".$GLOBALS['MYSQL_TBL_PREFIX']."read LEFT JOIN ".$GLOBALS['MYSQL_TBL_PREFIX']."users ON ".$GLOBALS['MYSQL_TBL_PREFIX']."read.user_id=".$GLOBALS['MYSQL_TBL_PREFIX']."users.id");
	while ( $obj = DB_ROWOBJ($result) ) {
		if ( empty($obj->usr_id) ) {
			++$cnt;
			Q("DELETE FROM ".$GLOBALS['MYSQL_TBL_PREFIX']."read WHERE id=".$obj->id);
		}
	}
	QF($result);
	draw_info($cnt);
	
	draw_stat('Checking read table against threads/messages');
	$cnt=0;
 	/* read thread->thread/user */ 
	$result=Q("SELECT ".$GLOBALS['MYSQL_TBL_PREFIX']."read.id, ".$GLOBALS['MYSQL_TBL_PREFIX']."thread.id AS thread_id FROM ".$GLOBALS['MYSQL_TBL_PREFIX']."read LEFT JOIN ".$GLOBALS['MYSQL_TBL_PREFIX']."thread ON ".$GLOBALS['MYSQL_TBL_PREFIX']."read.thread_id=".$GLOBALS['MYSQL_TBL_PREFIX']."thread.id LEFT JOIN ".$GLOBALS['MYSQL_TBL_PREFIX']."msg ON ".$GLOBALS['MYSQL_TBL_PREFIX']."thread.root_msg_id=".$GLOBALS['MYSQL_TBL_PREFIX']."msg.id LEFT JOIN ".$GLOBALS['MYSQL_TBL_PREFIX']."users ON ".$GLOBALS['MYSQL_TBL_PREFIX']."msg.poster_id=".$GLOBALS['MYSQL_TBL_PREFIX']."users.id");
	while ( $obj = DB_ROWOBJ($result) ) {
		if ( empty($obj->thread_id) ) {
			++$cnt;
			Q("DELETE FROM ".$GLOBALS['MYSQL_TBL_PREFIX']."read WHERE id=".$obj->id);
		}
	}
	QF($result);
	draw_info($cnt);
	
	draw_stat('Checking file attachments against messages');
	$result = Q("SELECT ".$GLOBALS['MYSQL_TBL_PREFIX']."attach.id,".$GLOBALS['MYSQL_TBL_PREFIX']."attach.location FROM ".$GLOBALS['MYSQL_TBL_PREFIX']."attach LEFT JOIN ".$GLOBALS['MYSQL_TBL_PREFIX']."msg ON ".$GLOBALS['MYSQL_TBL_PREFIX']."attach.message_id=".$GLOBALS['MYSQL_TBL_PREFIX']."msg.id WHERE ".$GLOBALS['MYSQL_TBL_PREFIX']."msg.id IS NULL");
	if( ($cnt = DB_COUNT($result)) ) {
		while ( $obj = DB_ROWOBJ($result) ) {
			if( file_exists($obj->location) ) unlink($obj->location);
			Q("DELETE FROM ".$GLOBALS['MYSQL_TBL_PREFIX']."attach WHERE id=".$obj->id);	
		}
	}
	if( $cnt ) draw_stat("$cnt Bad Attachments removed");
	draw_stat('Done: Checking file attachments against messages');
	
	draw_stat('Checking attachment counts in messages');
	$result = Q("SELECT ".$GLOBALS['MYSQL_TBL_PREFIX']."msg.id,count(*) AS attach_count,".$GLOBALS['MYSQL_TBL_PREFIX']."msg.attach_cnt FROM ".$GLOBALS['MYSQL_TBL_PREFIX']."attach LEFT JOIN ".$GLOBALS['MYSQL_TBL_PREFIX']."msg ON ".$GLOBALS['MYSQL_TBL_PREFIX']."attach.message_id=".$GLOBALS['MYSQL_TBL_PREFIX']."msg.id WHERE ".$GLOBALS['MYSQL_TBL_PREFIX']."msg.id IS NOT NULL GROUP BY ".$GLOBALS['MYSQL_TBL_PREFIX']."msg.id");
	if( DB_COUNT($result) ) {
		while( $obj = DB_ROWOBJ($result) ) {
			if( $obj->attach_count != $obj->attach_cnt ) 
				Q("UPDATE ".$GLOBALS['MYSQL_TBL_PREFIX']."msg SET attach_cnt=".$obj->attach_count." WHERE id=".$obj->id);
		}
	}
	$result = Q("SELECT ".$GLOBALS['MYSQL_TBL_PREFIX']."msg.id FROM ".$GLOBALS['MYSQL_TBL_PREFIX']."msg LEFT JOIN ".$GLOBALS['MYSQL_TBL_PREFIX']."attach ON ".$GLOBALS['MYSQL_TBL_PREFIX']."attach.message_id=".$GLOBALS['MYSQL_TBL_PREFIX']."msg.id WHERE ".$GLOBALS['MYSQL_TBL_PREFIX']."msg.attach_cnt>0 AND ".$GLOBALS['MYSQL_TBL_PREFIX']."attach.id IS NULL GROUP BY ".$GLOBALS['MYSQL_TBL_PREFIX']."msg.id");
	if( DB_COUNT($result) ) {
		while( $obj = DB_ROWOBJ($result) ) {
			Q("UPDATE ".$GLOBALS['MYSQL_TBL_PREFIX']."msg SET attach_cnt=0 WHERE id=".$obj->id);
		}
	}
	draw_stat('Done: Checking attachment counts in messages');
	
	draw_stat('Checking polls against messages');
	$cnt = 0;
	$result=Q("SELECT ".$GLOBALS['MYSQL_TBL_PREFIX']."poll.id, ".$GLOBALS['MYSQL_TBL_PREFIX']."msg.id AS msg_id FROM ".$GLOBALS['MYSQL_TBL_PREFIX']."poll LEFT JOIN ".$GLOBALS['MYSQL_TBL_PREFIX']."msg ON ".$GLOBALS['MYSQL_TBL_PREFIX']."poll.id=".$GLOBALS['MYSQL_TBL_PREFIX']."msg.poll_id WHERE ".$GLOBALS['MYSQL_TBL_PREFIX']."msg.id IS NULL");
	while ( $obj = DB_ROWOBJ($result) ) {
		$cnt++;
		Q("DELETE FROM ".$GLOBALS['MYSQL_TBL_PREFIX']."poll WHERE id=".$obj->id);
	}
	QF($result);
	draw_info($cnt);
	
	draw_stat('Checking message reports');
	$cnt = 0;
	$result = Q("SELECT ".$GLOBALS['MYSQL_TBL_PREFIX']."msg_report.id FROM ".$GLOBALS['MYSQL_TBL_PREFIX']."msg_report LEFT JOIN ".$GLOBALS['MYSQL_TBL_PREFIX']."msg ON ".$GLOBALS['MYSQL_TBL_PREFIX']."msg_report.msg_id=".$GLOBALS['MYSQL_TBL_PREFIX']."msg.id WHERE ".$GLOBALS['MYSQL_TBL_PREFIX']."msg.id IS NULL");
	while ( $obj = DB_ROWOBJ($result) ) {
		$cnt++;
		Q("DELETE FROM ".$GLOBALS['MYSQL_TBL_PREFIX']."msg_report WHERE id=".$obj->id);	
	}
	QF($result);
	draw_info($cnt);
	
	draw_stat('Checking poll tracking against poll options, users');
	$cnt = 0;
	/* users -> polls tracking */
	$result=Q("SELECT ".$GLOBALS['MYSQL_TBL_PREFIX']."poll_opt_track.id AS opt_id, ".$GLOBALS['MYSQL_TBL_PREFIX']."users.id AS usr_id FROM ".$GLOBALS['MYSQL_TBL_PREFIX']."poll_opt_track LEFT JOIN ".$GLOBALS['MYSQL_TBL_PREFIX']."poll ON ".$GLOBALS['MYSQL_TBL_PREFIX']."poll_opt_track.poll_id=".$GLOBALS['MYSQL_TBL_PREFIX']."poll.id LEFT JOIN ".$GLOBALS['MYSQL_TBL_PREFIX']."users ON ".$GLOBALS['MYSQL_TBL_PREFIX']."poll_opt_track.user_id=".$GLOBALS['MYSQL_TBL_PREFIX']."users.id");
	while ( $obj = DB_ROWOBJ($result) ) {
		if ( empty($obj->usr_id) ) {
			++$cnt;
			Q("DELETE FROM ".$GLOBALS['MYSQL_TBL_PREFIX']."poll_opt_track WHERE id=".$obj->opt_id);
		}
	}
	QF($result);
	draw_info($cnt);
	
	draw_stat('Checking if all poll options belong to existing polls');
	$cnt = 0;
	/* poll options -> polls */
	$result=Q("SELECT ".$GLOBALS['MYSQL_TBL_PREFIX']."poll_opt.id, ".$GLOBALS['MYSQL_TBL_PREFIX']."poll.id AS poll_id FROM ".$GLOBALS['MYSQL_TBL_PREFIX']."poll_opt LEFT JOIN ".$GLOBALS['MYSQL_TBL_PREFIX']."poll ON ".$GLOBALS['MYSQL_TBL_PREFIX']."poll_opt.poll_id=".$GLOBALS['MYSQL_TBL_PREFIX']."poll.id");
	while ( $obj = DB_ROWOBJ($result) ) {
		if ( empty($obj->poll_id) ) {
			++$cnt;
			Q("DELETE FROM ".$GLOBALS['MYSQL_TBL_PREFIX']."poll_opt WHERE id=".$obj->id);
		}
	}
	QF($result);
	draw_info($cnt);
	
	draw_stat('Checking all poll tracking against polls');
	$cnt = 0;
	/* poll votes -> polls */
	$result=Q("SELECT ".$GLOBALS['MYSQL_TBL_PREFIX']."poll_opt_track.id AS t_id, ".$GLOBALS['MYSQL_TBL_PREFIX']."poll.id AS poll_id FROM ".$GLOBALS['MYSQL_TBL_PREFIX']."poll_opt_track LEFT JOIN ".$GLOBALS['MYSQL_TBL_PREFIX']."poll ON ".$GLOBALS['MYSQL_TBL_PREFIX']."poll_opt_track.poll_id=".$GLOBALS['MYSQL_TBL_PREFIX']."poll.id");
	while ( $obj = DB_ROWOBJ($result) ) {
		if ( empty($obj->poll_id) ) {
			++$cnt;
			Q("DELETE FROM ".$GLOBALS['MYSQL_TBL_PREFIX']."poll_opt_track WHERE id=".$obj->t_id);
		}
	}
	QF($result);
	draw_info($cnt);
	
	draw_stat('Checking for messages against polls');
	$cnt = 0;

	$result = Q("SELECT ".$GLOBALS['MYSQL_TBL_PREFIX']."msg.id FROM ".$GLOBALS['MYSQL_TBL_PREFIX']."msg LEFT JOIN ".$GLOBALS['MYSQL_TBL_PREFIX']."poll ON ".$GLOBALS['MYSQL_TBL_PREFIX']."msg.poll_id=".$GLOBALS['MYSQL_TBL_PREFIX']."poll.id WHERE ".$GLOBALS['MYSQL_TBL_PREFIX']."msg.poll_id>0 AND ".$GLOBALS['MYSQL_TBL_PREFIX']."poll.id IS NULL");
	while ( $obj = DB_ROWOBJ($result) ) {
		++$cnt;
		Q("UPDATE ".$GLOBALS['MYSQL_TBL_PREFIX']."msg SET poll_id=0 WHERE id=".$obj->id);
	}
	QF($result);
	draw_info($cnt);
	
	draw_stat('Checking threads forum relations');
	$cnt = 0;
	/* threads -> forum */
	$r = Q("SELECT ".$GLOBALS['MYSQL_TBL_PREFIX']."thread.id FROM ".$GLOBALS['MYSQL_TBL_PREFIX']."thread LEFT JOIN ".$GLOBALS['MYSQL_TBL_PREFIX']."forum ON ".$GLOBALS['MYSQL_TBL_PREFIX']."thread.forum_id=".$GLOBALS['MYSQL_TBL_PREFIX']."forum.id WHERE ".$GLOBALS['MYSQL_TBL_PREFIX']."forum.id IS NULL");
	while ( $obj = DB_ROWOBJ($r) ) {
		if ( empty($obj->id) ) continue;
		++$cnt;
		$thr = new fud_thread;
		QOBJ("SELECT root_msg_id,id,forum_id FROM ".$GLOBALS['MYSQL_TBL_PREFIX']."thread WHERE id=".$obj->id, $thr);
		$thr->delete(FALSE);
		unset($thr);
	}
	QF($r);
	draw_info($cnt);
	
	draw_stat('Checking message thread relations');
	$cnt = 0;
	$r = Q("SELECT ".$GLOBALS['MYSQL_TBL_PREFIX']."msg.id FROM ".$GLOBALS['MYSQL_TBL_PREFIX']."msg LEFT JOIN ".$GLOBALS['MYSQL_TBL_PREFIX']."thread ON ".$GLOBALS['MYSQL_TBL_PREFIX']."msg.thread_id=".$GLOBALS['MYSQL_TBL_PREFIX']."thread.id WHERE ".$GLOBALS['MYSQL_TBL_PREFIX']."thread.id IS NULL");
	while ( $obj=DB_ROWOBJ($r) ) {
		++$cnt;
		$msg = new fud_msg_edit;
		QOBJ("SELECT * FROM ".$GLOBALS['MYSQL_TBL_PREFIX']."msg WHERE id=".$obj->id, $msg);
		$msg->delete(FALSE);
		unset($msg);
	}
	QF($r);
	draw_info($cnt);
	
	draw_stat('Checking smilies against disk files');
	$cnt = 0;
	$r = Q("SELECT * FROM ".$GLOBALS['MYSQL_TBL_PREFIX']."smiley"); 
	$smiley_dsk  = '../images/smiley_icons/';
	while ( $obj = DB_ROWOBJ($r) ) {
		if ( file_exists($smiley_dsk.$obj->img) ) {
			$smiley_ar[md5($obj->img)] = 1;
			continue;
		}
		++$cnt;
		Q("DELETE FROM ".$GLOBALS['MYSQL_TBL_PREFIX']."smiley WHERE id=".$obj->id);
	}
	draw_info($cnt);

	draw_stat('Checking disk files against smilies');
	$cnt = 0;
	$dp = opendir($smiley_dsk);
	while ( $de = readdir($dp) ) {
		if ( $de == '.' || $de == '..' ) continue;
		if ( !preg_match('!\.(gif|png|jpg|jpeg)$!i', $de) ) continue;
		if ( isset($smiley_ar[md5($de)]) ) continue;
		
		draw_stat("deleting: '".$smiley_dsk.$de."'");
		if ( !@unlink($smiley_dsk.$de) ) {
			++$cnt;
			draw_info('Unable to delete '.$smiley_dsk.$de);
		}
	}
	unset($smiley_ar);
	draw_info($cnt);
	
	draw_stat("Checking Approvals");
	$r = Q("SELECT ".$GLOBALS['MYSQL_TBL_PREFIX']."msg.id FROM ".$GLOBALS['MYSQL_TBL_PREFIX']."thread LEFT JOIN ".$GLOBALS['MYSQL_TBL_PREFIX']."forum ON ".$GLOBALS['MYSQL_TBL_PREFIX']."thread.forum_id=".$GLOBALS['MYSQL_TBL_PREFIX']."forum.id LEFT JOIN ".$GLOBALS['MYSQL_TBL_PREFIX']."msg ON ".$GLOBALS['MYSQL_TBL_PREFIX']."thread.id=".$GLOBALS['MYSQL_TBL_PREFIX']."msg.thread_id WHERE ".$GLOBALS['MYSQL_TBL_PREFIX']."forum.moderated='N' AND ".$GLOBALS['MYSQL_TBL_PREFIX']."msg.approved='N'");
	if( DB_COUNT($r) ) {
		while ( list($id) = DB_ROWARR($r) ) {
			Q("UPDATE ".$GLOBALS['MYSQL_TBL_PREFIX']."msg SET approved='Y' WHERE id=".$id);
		}
	}
	draw_stat("Done: Checking Approvals");
	
	/* fixing counts */
	draw_stat('Updating counts for threads');
	$result = Q("SELECT ".$GLOBALS['MYSQL_TBL_PREFIX']."thread.id AS th_id, ".$GLOBALS['MYSQL_TBL_PREFIX']."forum.moderated FROM ".$GLOBALS['MYSQL_TBL_PREFIX']."thread LEFT JOIN ".$GLOBALS['MYSQL_TBL_PREFIX']."forum ON ".$GLOBALS['MYSQL_TBL_PREFIX']."thread.forum_id=".$GLOBALS['MYSQL_TBL_PREFIX']."forum.id");
	while ( $obj = DB_ROWOBJ($result) ) {
		if ( $obj->moderated == 'Y' ) 
			$q = "SELECT count(*) FROM ".$GLOBALS['MYSQL_TBL_PREFIX']."msg WHERE thread_id=".$obj->th_id." AND approved='Y'";
		else
			$q = "SELECT count(*) FROM ".$GLOBALS['MYSQL_TBL_PREFIX']."msg WHERE thread_id=".$obj->th_id;
			
		$r = Q($q);
		list($post_count) = DB_ROWARR($r);
		Q("UPDATE ".$GLOBALS['MYSQL_TBL_PREFIX']."thread SET replies=".$post_count."-1 WHERE id=".$obj->th_id);
		QF($r);
	}
	draw_stat('threads updated');

	draw_stat('Updating forums');
	$result = Q("SELECT * FROM ".$GLOBALS['MYSQL_TBL_PREFIX']."forum");
	draw_stat('Updating thread counts for forums');
	while ( $obj = DB_ROWOBJ($result) ) {
		draw_stat('Forum: '.$obj->name);
		if ( $obj->moderated == 'Y' ) {
			$qth = "SELECT count(*) FROM ".$GLOBALS['MYSQL_TBL_PREFIX']."thread LEFT JOIN ".$GLOBALS['MYSQL_TBL_PREFIX']."msg ON ".$GLOBALS['MYSQL_TBL_PREFIX']."thread.root_msg_id=".$GLOBALS['MYSQL_TBL_PREFIX']."msg.id WHERE forum_id=".$obj->id." AND ".$GLOBALS['MYSQL_TBL_PREFIX']."msg.approved='Y'";
			$qp = "SELECT count(*) FROM ".$GLOBALS['MYSQL_TBL_PREFIX']."thread RIGHT JOIN ".$GLOBALS['MYSQL_TBL_PREFIX']."msg ON ".$GLOBALS['MYSQL_TBL_PREFIX']."msg.thread_id=".$GLOBALS['MYSQL_TBL_PREFIX']."thread.id WHERE ".$GLOBALS['MYSQL_TBL_PREFIX']."thread.forum_id=$obj->id AND ".$GLOBALS['MYSQL_TBL_PREFIX']."msg.approved='Y'";
		}
		else {
			$qth = "SELECT count(*) FROM ".$GLOBALS['MYSQL_TBL_PREFIX']."thread WHERE forum_id=".$obj->id;
			$qp = "SELECT count(*) FROM ".$GLOBALS['MYSQL_TBL_PREFIX']."thread RIGHT JOIN ".$GLOBALS['MYSQL_TBL_PREFIX']."msg ON ".$GLOBALS['MYSQL_TBL_PREFIX']."msg.thread_id=".$GLOBALS['MYSQL_TBL_PREFIX']."thread.id WHERE ".$GLOBALS['MYSQL_TBL_PREFIX']."thread.forum_id=".$obj->id;
		}
		
		$th_count = Q_SINGLEVAL($qth);
		$p_count = Q_SINGLEVAL($qp);
			
		draw_stat('thread count:'.$th_count);
		draw_stat('post count:'.$p_count);
		Q("UPDATE ".$GLOBALS['MYSQL_TBL_PREFIX']."forum SET thread_count=".$th_count.", post_count=".$p_count." WHERE id=".$obj->id);
	}
	QF($result);
	draw_stat('Updating post counts');
	draw_stat('finished forum counts');
	
	/* thread notif */
	draw_stat('Checking thread notification entries against users');
	$cnt = 0;
	$result = Q("SELECT ".$GLOBALS['MYSQL_TBL_PREFIX']."thread_notify.id, ".$GLOBALS['MYSQL_TBL_PREFIX']."users.id AS reg_user_id FROM ".$GLOBALS['MYSQL_TBL_PREFIX']."thread_notify LEFT JOIN ".$GLOBALS['MYSQL_TBL_PREFIX']."users ON ".$GLOBALS['MYSQL_TBL_PREFIX']."thread_notify.user_id=".$GLOBALS['MYSQL_TBL_PREFIX']."users.id");
	while ( $obj = DB_ROWOBJ($result) ) {
		if ( !strlen($obj->reg_user_id) ) {
			Q("DELETE FROM ".$GLOBALS['MYSQL_TBL_PREFIX']."thread_notify WHERE id=".$obj->id);
			++$cnt;
		}
	}
	
	QF($result);
	draw_info($cnt);

	draw_stat('Checking thread notification entries against threads');
	$cnt = 0;
	$result = Q("SELECT ".$GLOBALS['MYSQL_TBL_PREFIX']."thread_notify.id FROM ".$GLOBALS['MYSQL_TBL_PREFIX']."thread_notify LEFT JOIN ".$GLOBALS['MYSQL_TBL_PREFIX']."thread ON ".$GLOBALS['MYSQL_TBL_PREFIX']."thread_notify.thread_id=".$GLOBALS['MYSQL_TBL_PREFIX']."thread.id WHERE ".$GLOBALS['MYSQL_TBL_PREFIX']."thread.id IS NULL");
	while ( $obj = DB_ROWOBJ($result) ) {
		Q("DELETE FROM ".$GLOBALS['MYSQL_TBL_PREFIX']."thread_notify WHERE id=".$obj->id);
		++$cnt;
	}
	QF($result);
	draw_info($cnt);
	
	/* forum notif */
	draw_stat('Checking forum notification entries against users');
	$cnt = 0;
	$result = Q("SELECT ".$GLOBALS['MYSQL_TBL_PREFIX']."forum_notify.id, ".$GLOBALS['MYSQL_TBL_PREFIX']."users.id AS reg_user_id FROM ".$GLOBALS['MYSQL_TBL_PREFIX']."forum_notify LEFT JOIN ".$GLOBALS['MYSQL_TBL_PREFIX']."users ON ".$GLOBALS['MYSQL_TBL_PREFIX']."forum_notify.user_id=".$GLOBALS['MYSQL_TBL_PREFIX']."users.id WHERE ".$GLOBALS['MYSQL_TBL_PREFIX']."users.id IS NULL");
	while ( $obj = DB_ROWOBJ($result) ) {
		Q("DELETE FROM ".$GLOBALS['MYSQL_TBL_PREFIX']."forum_notify WHERE id=".$obj->id);
		++$cnt;
	}
	
	QF($result);
	draw_info($cnt);

	draw_stat('Checking forum notification entries against forums');
	$cnt = 0;
	$result = Q("SELECT ".$GLOBALS['MYSQL_TBL_PREFIX']."forum_notify.id FROM ".$GLOBALS['MYSQL_TBL_PREFIX']."forum_notify LEFT JOIN ".$GLOBALS['MYSQL_TBL_PREFIX']."forum ON ".$GLOBALS['MYSQL_TBL_PREFIX']."forum_notify.forum_id=".$GLOBALS['MYSQL_TBL_PREFIX']."forum.id WHERE ".$GLOBALS['MYSQL_TBL_PREFIX']."forum.id IS NULL");
	while ( $obj = DB_ROWOBJ($result) ) {
		Q("DELETE FROM ".$GLOBALS['MYSQL_TBL_PREFIX']."forum_notify WHERE id=".$obj->id);
		++$cnt;
	}
	QF($result);
	draw_info($cnt);
	
	draw_stat('Updating Thread Last_Post_Id Field');
	$r = Q("SELECT ".$GLOBALS['MYSQL_TBL_PREFIX']."thread.id,MAX(".$GLOBALS['MYSQL_TBL_PREFIX']."msg.id) AS mid,".$GLOBALS['MYSQL_TBL_PREFIX']."msg.post_stamp FROM ".$GLOBALS['MYSQL_TBL_PREFIX']."thread LEFT JOIN ".$GLOBALS['MYSQL_TBL_PREFIX']."msg ON ".$GLOBALS['MYSQL_TBL_PREFIX']."thread.id=".$GLOBALS['MYSQL_TBL_PREFIX']."msg.thread_id WHERE ".$GLOBALS['MYSQL_TBL_PREFIX']."msg.approved='Y' GROUP BY ".$GLOBALS['MYSQL_TBL_PREFIX']."msg.thread_id");
	if( DB_COUNT($r) ) {
		while ( $obj = DB_ROWOBJ($r) ) {
			Q("UPDATE ".$GLOBALS['MYSQL_TBL_PREFIX']."thread SET last_post_id=".$obj->mid.",last_post_date=".$obj->post_stamp." WHERE id=".$obj->id);
		}
	}
	draw_stat('Done: Updating Thread Last_Post_Id Field');
	
	draw_stat('Updating Forum Last_Post_Id Field');
	$r = Q("SELECT id FROM ".$GLOBALS['MYSQL_TBL_PREFIX']."forum");
	while( list($forum_id) = DB_ROWARR($r) ) {
		$last_post_id = Q_SINGLEVAL("SELECT last_post_id FROM ".$GLOBALS['MYSQL_TBL_PREFIX']."thread INNER JOIN ".$GLOBALS['MYSQL_TBL_PREFIX']."msg ON ".$GLOBALS['MYSQL_TBL_PREFIX']."thread.last_post_id=".$GLOBALS['MYSQL_TBL_PREFIX']."msg.id WHERE forum_id=".$forum_id." AND ".$GLOBALS['MYSQL_TBL_PREFIX']."msg.approved='Y' AND ".$GLOBALS['MYSQL_TBL_PREFIX']."thread.moved_to=0 ORDER BY last_post_id DESC LIMIT 1");
		if( !$last_post_id ) $last_post_id=0;
		Q("UPDATE ".$GLOBALS['MYSQL_TBL_PREFIX']."forum SET last_post_id=".$last_post_id." WHERE id=".$forum_id); 
	}
	QF($r);	
	draw_stat('Done: Updating Forum Last_Post_Id Field');
	
	draw_stat('Checking email notification entries against threads');
	$cnt = 0;
	$result = Q("SELECT ".$GLOBALS['MYSQL_TBL_PREFIX']."thread_notify.id, ".$GLOBALS['MYSQL_TBL_PREFIX']."thread.id AS th_id FROM ".$GLOBALS['MYSQL_TBL_PREFIX']."thread_notify LEFT JOIN ".$GLOBALS['MYSQL_TBL_PREFIX']."thread ON ".$GLOBALS['MYSQL_TBL_PREFIX']."thread_notify.thread_id=".$GLOBALS['MYSQL_TBL_PREFIX']."thread.id");
	while ( $obj = DB_ROWOBJ($result) ) {
		if ( !strlen($obj->th_id) ) {
			Q("DELETE FROM ".$GLOBALS['MYSQL_TBL_PREFIX']."thread_notify WHERE id=".$obj->id);
			++$cnt;
		}
	}
	QF($result);
	draw_info($cnt);
	
	draw_stat('Checking thread votes against threads');
	$cnt = 0;
	$r=Q("SELECT ".$GLOBALS['MYSQL_TBL_PREFIX']."thread_rate_track.id FROM ".$GLOBALS['MYSQL_TBL_PREFIX']."thread_rate_track LEFT JOIN ".$GLOBALS['MYSQL_TBL_PREFIX']."thread ON ".$GLOBALS['MYSQL_TBL_PREFIX']."thread_rate_track.thread_id=".$GLOBALS['MYSQL_TBL_PREFIX']."thread.id WHERE ".$GLOBALS['MYSQL_TBL_PREFIX']."thread.id IS NULL");
	while ( $obj = DB_ROWOBJ($r) ) {
		++$cnt;
		Q("DELETE FROM ".$GLOBALS['MYSQL_TBL_PREFIX']."thread_rate_track WHERE id=".$obj->id);
	}
	QF($r);
	draw_info($cnt);
	
	
	draw_stat('Checking thread votes against users');
	$cnt = 0;
	$r=Q("SELECT ".$GLOBALS['MYSQL_TBL_PREFIX']."thread_rate_track.id FROM ".$GLOBALS['MYSQL_TBL_PREFIX']."thread_rate_track LEFT JOIN ".$GLOBALS['MYSQL_TBL_PREFIX']."users ON ".$GLOBALS['MYSQL_TBL_PREFIX']."thread_rate_track.user_id=".$GLOBALS['MYSQL_TBL_PREFIX']."users.id WHERE ".$GLOBALS['MYSQL_TBL_PREFIX']."users.id IS NULL");
	while ( $obj = DB_ROWOBJ($r) ) {
		++$cnt;
		Q("DELETE FROM ".$GLOBALS['MYSQL_TBL_PREFIX']."thread_rate_track WHERE id=".$obj->id);
	}
	QF($r);
	draw_info($cnt);
	
	draw_stat('Rebuilding Thread Views');
	Q("DELETE FROM ".$GLOBALS['MYSQL_TBL_PREFIX']."thread_view");
	$tm=__request_timestamp__;
	$fr = Q("SELECT id FROM ".$GLOBALS['MYSQL_TBL_PREFIX']."forum");
	while ( list($frm_id) = DB_ROWARR($fr) ) {
		$vlist = '';
	
		$r = Q("SELECT 
			".$GLOBALS['MYSQL_TBL_PREFIX']."thread.id, 
			".$GLOBALS['MYSQL_TBL_PREFIX']."thread.forum_id,
			IF(is_sticky='Y' AND (".$GLOBALS['MYSQL_TBL_PREFIX']."msg.post_stamp+".$GLOBALS['MYSQL_TBL_PREFIX']."thread.orderexpiry>".$tm." OR ".$GLOBALS['MYSQL_TBL_PREFIX']."thread.orderexpiry=0), 4200000000, ".$GLOBALS['MYSQL_TBL_PREFIX']."thread.last_post_id) AS sort_order_fld
		FROM 
			".$GLOBALS['MYSQL_TBL_PREFIX']."thread
			INNER JOIN ".$GLOBALS['MYSQL_TBL_PREFIX']."msg
				ON ".$GLOBALS['MYSQL_TBL_PREFIX']."thread.root_msg_id=".$GLOBALS['MYSQL_TBL_PREFIX']."msg.id
		WHERE 
			forum_id=".$frm_id." AND
			".$GLOBALS['MYSQL_TBL_PREFIX']."msg.approved='Y'
		ORDER BY 
			sort_order_fld DESC,
			".$GLOBALS['MYSQL_TBL_PREFIX']."thread.last_post_date DESC
		");

		$i = 0;
		$cnt = 0;
		$page = 1;
		while ( $obj = DB_ROWOBJ($r) ) {
			if ( $i && !($i%$GLOBALS['THREADS_PER_PAGE']) ) {
				$vlist = substr($vlist, 0, -2);
				Q("INSERT INTO ".$GLOBALS['MYSQL_TBL_PREFIX']."thread_view (page, forum_id, thread_id, pos) VALUES $vlist");
				$vlist = '';
				$page++;
				$cnt = 0;
			}	
			
			$vlist .= "($page, $obj->forum_id, $obj->id, ".++$cnt."),\n";
			$i++;
		}
		if ( strlen($vlist) ) {
			$vlist = substr($vlist, 0, -2);
			Q("INSERT INTO ".$GLOBALS['MYSQL_TBL_PREFIX']."thread_view (page, forum_id, thread_id, pos) VALUES $vlist");
		}
		QF($r);	
	}
	QF($fr);
	draw_stat('Done Rebuilding Thread Views');
	
	draw_stat('Rebuilding user levels & message counts');
	$r = Q(" SELECT ".$GLOBALS['MYSQL_TBL_PREFIX']."users.id, count(".$GLOBALS['MYSQL_TBL_PREFIX']."msg.id) AS cnt FROM ".$GLOBALS['MYSQL_TBL_PREFIX']."users LEFT JOIN ".$GLOBALS['MYSQL_TBL_PREFIX']."msg ON ".$GLOBALS['MYSQL_TBL_PREFIX']."users.id=".$GLOBALS['MYSQL_TBL_PREFIX']."msg.poster_id AND ".$GLOBALS['MYSQL_TBL_PREFIX']."msg.approved='Y' GROUP BY ".$GLOBALS['MYSQL_TBL_PREFIX']."users.id");
	while( $obj = DB_ROWOBJ($r) ) {
		$level_id = Q_SINGLEVAL("SELECT id FROM ".$GLOBALS['MYSQL_TBL_PREFIX']."level WHERE post_count<=".$obj->cnt." ORDER BY post_count DESC LIMIT 1");
		if( !$level_id ) $level_id=0;
		Q("UPDATE ".$GLOBALS['MYSQL_TBL_PREFIX']."users SET posted_msg_count=".$obj->cnt.",level_id=".$level_id." WHERE id=".$obj->id);
	}
	QF($r);
	draw_stat('Done rebuilding user levels & message counts');
	
	draw_stat('Rebuilding custom user statuses');
	$c_level = new fud_custom_tag;
	$r = Q("SELECT distinct(user_id) FROM ".$GLOBALS['MYSQL_TBL_PREFIX']."custom_tags");
	while( list($c_level->user_id) = DB_ROWARR($r) ) {
		$c_level->sync();
	}
	draw_stat('Done Rebuilding custom user statuses');

	$cnt = 0;
	draw_stat('Checking buddies against entry owners');
	$r = Q("SELECT ".$GLOBALS['MYSQL_TBL_PREFIX']."buddy.id FROM ".$GLOBALS['MYSQL_TBL_PREFIX']."buddy LEFT JOIN ".$GLOBALS['MYSQL_TBL_PREFIX']."users ON ".$GLOBALS['MYSQL_TBL_PREFIX']."buddy.user_id=".$GLOBALS['MYSQL_TBL_PREFIX']."users.id WHERE ".$GLOBALS['MYSQL_TBL_PREFIX']."users.id IS NULL");
	while ( $obj = DB_ROWOBJ($r) ) {
		Q("DELETE FROM ".$GLOBALS['MYSQL_TBL_PREFIX']."buddy WHERE id=".$obj->id);
		++$cnt;
	}
	QF($r);
	draw_info($cnt);
	
	$cnt = 0;
	draw_stat('Checking buddies against users');
	$r = Q("SELECT ".$GLOBALS['MYSQL_TBL_PREFIX']."buddy.id FROM ".$GLOBALS['MYSQL_TBL_PREFIX']."buddy LEFT JOIN ".$GLOBALS['MYSQL_TBL_PREFIX']."users ON ".$GLOBALS['MYSQL_TBL_PREFIX']."buddy.bud_id=".$GLOBALS['MYSQL_TBL_PREFIX']."users.id WHERE ".$GLOBALS['MYSQL_TBL_PREFIX']."users.id IS NULL");
	while ( $obj = DB_ROWOBJ($r) ) {
		Q("DELETE FROM ".$GLOBALS['MYSQL_TBL_PREFIX']."buddy WHERE id=".$obj->id);
		++$cnt;
	}
	QF($r);
	draw_info($cnt);
	
	$cnt = 0;
	draw_stat('Checking ignore list against entry owners');
	$r = Q("SELECT ".$GLOBALS['MYSQL_TBL_PREFIX']."user_ignore.id FROM ".$GLOBALS['MYSQL_TBL_PREFIX']."user_ignore LEFT JOIN ".$GLOBALS['MYSQL_TBL_PREFIX']."users ON ".$GLOBALS['MYSQL_TBL_PREFIX']."user_ignore.user_id=".$GLOBALS['MYSQL_TBL_PREFIX']."users.id WHERE ".$GLOBALS['MYSQL_TBL_PREFIX']."users.id IS NULL");
	while ( $obj = DB_ROWOBJ($r) ) {
		Q("DELETE FROM ".$GLOBALS['MYSQL_TBL_PREFIX']."buddy WHERE id=".$obj->id);
		++$cnt;
	}
	QF($r);
	draw_info($cnt);
	
	$cnt = 0;
	draw_stat('Checking ignore list against users');
	$r = Q("SELECT ".$GLOBALS['MYSQL_TBL_PREFIX']."user_ignore.id FROM ".$GLOBALS['MYSQL_TBL_PREFIX']."user_ignore LEFT JOIN ".$GLOBALS['MYSQL_TBL_PREFIX']."users ON ".$GLOBALS['MYSQL_TBL_PREFIX']."user_ignore.ignore_id=".$GLOBALS['MYSQL_TBL_PREFIX']."users.id WHERE ".$GLOBALS['MYSQL_TBL_PREFIX']."users.id IS NULL");
	while ( $obj = DB_ROWOBJ($r) ) {
		Q("DELETE FROM ".$GLOBALS['MYSQL_TBL_PREFIX']."buddy WHERE id=".$obj->id);
		++$cnt;
	}
	QF($r);
	draw_info($cnt);
	
	draw_stat('Rebuild File Extension Filter');
	$ext = new fud_ext_block;
	$ext->mk_regexp();
	draw_stat('Done: Rebuilding File Extension Filter');
	
	draw_stat('Rebuilding users\' last post ids');
	$r = Q("SELECT MAX(id),poster_id FROM ".$GLOBALS['MYSQL_TBL_PREFIX']."msg WHERE approved='Y' GROUP BY poster_id");
	while( list($mid,$uid) = DB_ROWARR($r) ) {
		Q("UPDATE ".$GLOBALS['MYSQL_TBL_PREFIX']."users SET u_last_post_id=".$mid." WHERE id=".$uid);	
	}
	QF($r);
	draw_stat('Done: Rebuilding users\' last post ids');
	
	draw_stat('Rebuilding group cache');
	$r = Q("SELECT id FROM ".$GLOBALS['MYSQL_TBL_PREFIX']."groups");
	while( list($gid) = DB_ROWARR($r) ) {
		$grp = new fud_group;
		$grp->id = $gid;
		$grp->rebuild_cache();
	}
	QF($r);
	draw_stat('Done: Rebuilding group cache');
	
	draw_stat('Rebuilding smilies vieworder');
	$i=0;
	$r = Q("SELECT id FROM ".$GLOBALS['MYSQL_TBL_PREFIX']."smiley ORDER BY vieworder");
	while( list($sid) = DB_ROWARR($r) ) {
		Q("UPDATE ".$GLOBALS['MYSQL_TBL_PREFIX']."smiley SET vieworder=".(++$i)." WHERE id=".$sid);	
	}
	QF($r);
	draw_stat('Done: Rebuilding smilies vieworder');
	
	draw_stat('Unlocking database');
	DB_UNLOCK();	
	draw_stat('Database unlocked');

	if( $GLOBALS['FORUM_ENABLED'] == 'Y' || !empty($HTTP_GET_VARS['enable_forum']) ) {
		draw_stat('Re-enabling the forum.');
		maintenance_status($GLOBALS['DISABLED_REASON'], 'Y');
	}
	else {
		echo '<font size=+1 color="red">Your forum is currently disabled, to re-enable it go to the <a href="admglobal.php?'._rsid.'">Global Settings Manager</a> and re-enable it.</font><br>';
	}

	draw_stat('DONE');
	echo '<script language="Javascript1.2">clearInterval(intervalID);</script>';
	readfile('admclose.html');
?>