<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: consist.php,v 1.13 2002/07/31 23:26:29 hackie Exp $
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
	fud_use('glob.inc', TRUE);
	fud_use('adm.inc', TRUE);
	fud_use('widgets.inc', TRUE);
	fud_use('ext.inc', TRUE);
	fud_use('level_adm.inc', TRUE);
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
			//intervalID=setInterval("scrolldown()", 100);
		</script>';
	
	draw_stat('Locking the database for checking');
	/* normal forum messages */
	//'.$GLOBALS['DBHOST_TBL_PREFIX'].'users AS fud_users_2+, 
	db_lock('
		'.$GLOBALS['DBHOST_TBL_PREFIX'].'thread+, 
		'.$GLOBALS['DBHOST_TBL_PREFIX'].'thread_view+,
		'.$GLOBALS['DBHOST_TBL_PREFIX'].'forum+, 
		'.$GLOBALS['DBHOST_TBL_PREFIX'].'cat+, 
		'.$GLOBALS['DBHOST_TBL_PREFIX'].'msg+, 
		'.$GLOBALS['DBHOST_TBL_PREFIX'].'ses+, 
		'.$GLOBALS['DBHOST_TBL_PREFIX'].'mod+, 
		'.$GLOBALS['DBHOST_TBL_PREFIX'].'users+, 
		
		'.$GLOBALS['DBHOST_TBL_PREFIX'].'read+, 
		'.$GLOBALS['DBHOST_TBL_PREFIX'].'poll+, 
		'.$GLOBALS['DBHOST_TBL_PREFIX'].'poll_opt+, 
		'.$GLOBALS['DBHOST_TBL_PREFIX'].'poll_opt_track+, 
		'.$GLOBALS['DBHOST_TBL_PREFIX'].'smiley+, 
		'.$GLOBALS['DBHOST_TBL_PREFIX'].'thread_notify+, 
		'.$GLOBALS['DBHOST_TBL_PREFIX'].'forum_notify+, 
		'.$GLOBALS['DBHOST_TBL_PREFIX'].'attach+, 
		'.$GLOBALS['DBHOST_TBL_PREFIX'].'msg_report+, 
		'.$GLOBALS['DBHOST_TBL_PREFIX'].'thread_rate_track+, 
		'.$GLOBALS['DBHOST_TBL_PREFIX'].'level+, 
		'.$GLOBALS['DBHOST_TBL_PREFIX'].'custom_tags+,
		'.$GLOBALS['DBHOST_TBL_PREFIX'].'user_ignore+,
		'.$GLOBALS['DBHOST_TBL_PREFIX'].'buddy+,
		'.$GLOBALS['DBHOST_TBL_PREFIX'].'pmsg+,
		'.$GLOBALS['DBHOST_TBL_PREFIX'].'ext_block+,
		'.$GLOBALS['DBHOST_TBL_PREFIX'].'groups+,
		'.$GLOBALS['DBHOST_TBL_PREFIX'].'group_resources+,
		'.$GLOBALS['DBHOST_TBL_PREFIX'].'group_members+,
		'.$GLOBALS['DBHOST_TBL_PREFIX'].'group_cache+
	');
	draw_stat('Locked!');
	draw_stat('Validating category order');
	$i=1;
	$r = q("SELECT id,view_order FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."cat ORDER BY view_order,id");
	while( list($id,$view_order) = db_rowarr($r) ) {
		if( $view_order != $i ) q("UPDATE ".$GLOBALS['DBHOST_TBL_PREFIX']."cat SET view_order=".$i." WHERE id=".$id);
		$i++;
	}
	qf($r);
	draw_stat('Done: Validating category order');

	draw_stat('Checking if moderator and users table match');
	$cnt = 0;
	/* forum moderators exist, mod->users */
	$result = q("SELECT ".$GLOBALS['DBHOST_TBL_PREFIX']."mod.id AS tbl_mod_id, ".$GLOBALS['DBHOST_TBL_PREFIX']."users.id AS tbl_user_id, ".$GLOBALS['DBHOST_TBL_PREFIX']."forum.id AS tbl_forum_id FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."mod LEFT JOIN ".$GLOBALS['DBHOST_TBL_PREFIX']."users ON ".$GLOBALS['DBHOST_TBL_PREFIX']."mod.user_id=".$GLOBALS['DBHOST_TBL_PREFIX']."users.id LEFT JOIN ".$GLOBALS['DBHOST_TBL_PREFIX']."forum ON ".$GLOBALS['DBHOST_TBL_PREFIX']."mod.forum_id=".$GLOBALS['DBHOST_TBL_PREFIX']."forum.id");
	while ( $obj = db_rowobj($result) ) {
		if ( !strlen($obj->tbl_user_id) || !strlen($obj->tbl_forum_id) ) {
			q("DELETE FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."mod WHERE id=".$obj->tbl_mod_id);
			++$cnt;
		} 
	}
	qf($result);
	draw_info($cnt);
	
	draw_stat('Rebuilding moderators');
	rebuildmodlist();
	draw_stat('Done: Rebuilding moderators');
		
	draw_stat('Checking if all private messages have users');
	$cnt = 0;

	$r = q("SELECT ".$GLOBALS['DBHOST_TBL_PREFIX']."pmsg.id FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."pmsg LEFT JOIN ".$GLOBALS['DBHOST_TBL_PREFIX']."users ON ".$GLOBALS['DBHOST_TBL_PREFIX']."pmsg.ouser_id=".$GLOBALS['DBHOST_TBL_PREFIX']."users.id WHERE mailed='N' AND ".$GLOBALS['DBHOST_TBL_PREFIX']."users.id IS NULL");
	while( $obj = db_rowobj($r) ) {
		$pmsg = new fud_pmsg;
		qobj("SELECT * FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."pmsg WHERE id=".$obj->id, $pmsg);
		$pmsg->del_pmsg('TRASH');
		++$cnt;
	}

	$r = q("SELECT ".$GLOBALS['DBHOST_TBL_PREFIX']."pmsg.id FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."pmsg LEFT JOIN ".$GLOBALS['DBHOST_TBL_PREFIX']."users ON ".$GLOBALS['DBHOST_TBL_PREFIX']."pmsg.duser_id=".$GLOBALS['DBHOST_TBL_PREFIX']."users.id WHERE mailed='Y' AND ".$GLOBALS['DBHOST_TBL_PREFIX']."users.id IS NULL");
	while( $obj = db_rowobj($r) ) {
		$pmsg = new fud_pmsg;
		qobj("SELECT * FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."pmsg WHERE id=".$obj->id, $pmsg);
		$pmsg->del_pmsg('TRASH');
		++$cnt;
	}
	
	draw_info($cnt);
	
	draw_stat('Checking read table against users');
	$cnt = 0;
	/* read messages->users */
	$result=q("SELECT ".$GLOBALS['DBHOST_TBL_PREFIX']."read.id, ".$GLOBALS['DBHOST_TBL_PREFIX']."users.id AS usr_id FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."read LEFT JOIN ".$GLOBALS['DBHOST_TBL_PREFIX']."users ON ".$GLOBALS['DBHOST_TBL_PREFIX']."read.user_id=".$GLOBALS['DBHOST_TBL_PREFIX']."users.id");
	while ( $obj = db_rowobj($result) ) {
		if ( empty($obj->usr_id) ) {
			++$cnt;
			q("DELETE FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."read WHERE id=".$obj->id);
		}
	}
	qf($result);
	draw_info($cnt);
	
	draw_stat('Checking read table against topics/messages');
	$cnt=0;
 	/* read topic->thread/user */ 
	$result=q("SELECT ".$GLOBALS['DBHOST_TBL_PREFIX']."read.id, ".$GLOBALS['DBHOST_TBL_PREFIX']."thread.id AS thread_id FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."read LEFT JOIN ".$GLOBALS['DBHOST_TBL_PREFIX']."thread ON ".$GLOBALS['DBHOST_TBL_PREFIX']."read.thread_id=".$GLOBALS['DBHOST_TBL_PREFIX']."thread.id LEFT JOIN ".$GLOBALS['DBHOST_TBL_PREFIX']."msg ON ".$GLOBALS['DBHOST_TBL_PREFIX']."thread.root_msg_id=".$GLOBALS['DBHOST_TBL_PREFIX']."msg.id LEFT JOIN ".$GLOBALS['DBHOST_TBL_PREFIX']."users ON ".$GLOBALS['DBHOST_TBL_PREFIX']."msg.poster_id=".$GLOBALS['DBHOST_TBL_PREFIX']."users.id");
	while ( $obj = db_rowobj($result) ) {
		if ( empty($obj->thread_id) ) {
			++$cnt;
			q("DELETE FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."read WHERE id=".$obj->id);
		}
	}
	qf($result);
	draw_info($cnt);
	
	draw_stat('Checking file attachments against messages');
	$result = q("SELECT ".$GLOBALS['DBHOST_TBL_PREFIX']."attach.id,".$GLOBALS['DBHOST_TBL_PREFIX']."attach.location FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."attach LEFT JOIN ".$GLOBALS['DBHOST_TBL_PREFIX']."msg ON ".$GLOBALS['DBHOST_TBL_PREFIX']."attach.message_id=".$GLOBALS['DBHOST_TBL_PREFIX']."msg.id WHERE ".$GLOBALS['DBHOST_TBL_PREFIX']."msg.id IS NULL");
	if( ($cnt = db_count($result)) ) {
		while ( $obj = db_rowobj($result) ) {
			if( file_exists($obj->location) ) unlink($obj->location);
			q("DELETE FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."attach WHERE id=".$obj->id);	
		}
	}
	if( $cnt ) draw_stat("$cnt Bad Attachments removed");
	draw_stat('Done: Checking file attachments against messages');
	
	draw_stat('Checking attachment counts in messages');
	$result = q("SELECT ".$GLOBALS['DBHOST_TBL_PREFIX']."msg.id, count(*) AS attach_count FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."attach LEFT JOIN ".$GLOBALS['DBHOST_TBL_PREFIX']."msg ON ".$GLOBALS['DBHOST_TBL_PREFIX']."attach.message_id=".$GLOBALS['DBHOST_TBL_PREFIX']."msg.id WHERE ".$GLOBALS['DBHOST_TBL_PREFIX']."msg.id IS NOT NULL GROUP BY ".$GLOBALS['DBHOST_TBL_PREFIX']."msg.id");
	if( db_count($result) ) {
		while( $obj = db_rowobj($result) ) {
			q("UPDATE ".$GLOBALS['DBHOST_TBL_PREFIX']."msg SET attach_cnt=".$obj->attach_count." WHERE id=".$obj->id);
		}
	}
	$result = q("SELECT ".$GLOBALS['DBHOST_TBL_PREFIX']."msg.id FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."msg LEFT JOIN ".$GLOBALS['DBHOST_TBL_PREFIX']."attach ON ".$GLOBALS['DBHOST_TBL_PREFIX']."attach.message_id=".$GLOBALS['DBHOST_TBL_PREFIX']."msg.id WHERE ".$GLOBALS['DBHOST_TBL_PREFIX']."msg.attach_cnt>0 AND ".$GLOBALS['DBHOST_TBL_PREFIX']."attach.id IS NULL GROUP BY ".$GLOBALS['DBHOST_TBL_PREFIX']."msg.id");
	if( db_count($result) ) {
		while( $obj = db_rowobj($result) ) {
			q("UPDATE ".$GLOBALS['DBHOST_TBL_PREFIX']."msg SET attach_cnt=0 WHERE id=".$obj->id);
		}
	}
	draw_stat('Done: Checking attachment counts in messages');
	
	draw_stat('Checking polls against messages');
	$cnt = 0;
	$result=q("SELECT ".$GLOBALS['DBHOST_TBL_PREFIX']."poll.id, ".$GLOBALS['DBHOST_TBL_PREFIX']."msg.id AS msg_id FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."poll LEFT JOIN ".$GLOBALS['DBHOST_TBL_PREFIX']."msg ON ".$GLOBALS['DBHOST_TBL_PREFIX']."poll.id=".$GLOBALS['DBHOST_TBL_PREFIX']."msg.poll_id WHERE ".$GLOBALS['DBHOST_TBL_PREFIX']."msg.id IS NULL");
	while ( $obj = db_rowobj($result) ) {
		$cnt++;
		q("DELETE FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."poll WHERE id=".$obj->id);
	}
	qf($result);
	draw_info($cnt);
	
	draw_stat('Checking message reports');
	$cnt = 0;
	$result = q("SELECT ".$GLOBALS['DBHOST_TBL_PREFIX']."msg_report.id FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."msg_report LEFT JOIN ".$GLOBALS['DBHOST_TBL_PREFIX']."msg ON ".$GLOBALS['DBHOST_TBL_PREFIX']."msg_report.msg_id=".$GLOBALS['DBHOST_TBL_PREFIX']."msg.id WHERE ".$GLOBALS['DBHOST_TBL_PREFIX']."msg.id IS NULL");
	while ( $obj = db_rowobj($result) ) {
		$cnt++;
		q("DELETE FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."msg_report WHERE id=".$obj->id);	
	}
	qf($result);
	draw_info($cnt);
	
	draw_stat('Checking poll tracking against poll options, users');
	$cnt = 0;
	/* users -> polls tracking */
	$result=q("SELECT ".$GLOBALS['DBHOST_TBL_PREFIX']."poll_opt_track.id AS opt_id, ".$GLOBALS['DBHOST_TBL_PREFIX']."users.id AS usr_id FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."poll_opt_track LEFT JOIN ".$GLOBALS['DBHOST_TBL_PREFIX']."poll ON ".$GLOBALS['DBHOST_TBL_PREFIX']."poll_opt_track.poll_id=".$GLOBALS['DBHOST_TBL_PREFIX']."poll.id LEFT JOIN ".$GLOBALS['DBHOST_TBL_PREFIX']."users ON ".$GLOBALS['DBHOST_TBL_PREFIX']."poll_opt_track.user_id=".$GLOBALS['DBHOST_TBL_PREFIX']."users.id");
	while ( $obj = db_rowobj($result) ) {
		if ( empty($obj->usr_id) ) {
			++$cnt;
			q("DELETE FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."poll_opt_track WHERE id=".$obj->opt_id);
		}
	}
	qf($result);
	draw_info($cnt);
	
	draw_stat('Checking if all poll options belong to existing polls');
	$cnt = 0;
	/* poll options -> polls */
	$result=q("SELECT ".$GLOBALS['DBHOST_TBL_PREFIX']."poll_opt.id, ".$GLOBALS['DBHOST_TBL_PREFIX']."poll.id AS poll_id FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."poll_opt LEFT JOIN ".$GLOBALS['DBHOST_TBL_PREFIX']."poll ON ".$GLOBALS['DBHOST_TBL_PREFIX']."poll_opt.poll_id=".$GLOBALS['DBHOST_TBL_PREFIX']."poll.id");
	while ( $obj = db_rowobj($result) ) {
		if ( empty($obj->poll_id) ) {
			++$cnt;
			q("DELETE FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."poll_opt WHERE id=".$obj->id);
		}
	}
	qf($result);
	draw_info($cnt);
	
	draw_stat('Checking all poll tracking against polls');
	$cnt = 0;
	/* poll votes -> polls */
	$result=q("SELECT ".$GLOBALS['DBHOST_TBL_PREFIX']."poll_opt_track.id AS t_id, ".$GLOBALS['DBHOST_TBL_PREFIX']."poll.id AS poll_id FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."poll_opt_track LEFT JOIN ".$GLOBALS['DBHOST_TBL_PREFIX']."poll ON ".$GLOBALS['DBHOST_TBL_PREFIX']."poll_opt_track.poll_id=".$GLOBALS['DBHOST_TBL_PREFIX']."poll.id");
	while ( $obj = db_rowobj($result) ) {
		if ( empty($obj->poll_id) ) {
			++$cnt;
			q("DELETE FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."poll_opt_track WHERE id=".$obj->t_id);
		}
	}
	qf($result);
	draw_info($cnt);
	
	draw_stat('Checking for messages against polls');
	$cnt = 0;

	$result = q("SELECT ".$GLOBALS['DBHOST_TBL_PREFIX']."msg.id FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."msg LEFT JOIN ".$GLOBALS['DBHOST_TBL_PREFIX']."poll ON ".$GLOBALS['DBHOST_TBL_PREFIX']."msg.poll_id=".$GLOBALS['DBHOST_TBL_PREFIX']."poll.id WHERE ".$GLOBALS['DBHOST_TBL_PREFIX']."msg.poll_id>0 AND ".$GLOBALS['DBHOST_TBL_PREFIX']."poll.id IS NULL");
	while ( $obj = db_rowobj($result) ) {
		++$cnt;
		q("UPDATE ".$GLOBALS['DBHOST_TBL_PREFIX']."msg SET poll_id=0 WHERE id=".$obj->id);
	}
	qf($result);
	draw_info($cnt);
	
	draw_stat('Checking topics forum relations');
	$cnt = 0;
	/* topics -> forum */
	$r = q("SELECT ".$GLOBALS['DBHOST_TBL_PREFIX']."thread.id FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."thread LEFT JOIN ".$GLOBALS['DBHOST_TBL_PREFIX']."forum ON ".$GLOBALS['DBHOST_TBL_PREFIX']."thread.forum_id=".$GLOBALS['DBHOST_TBL_PREFIX']."forum.id WHERE ".$GLOBALS['DBHOST_TBL_PREFIX']."forum.id IS NULL");
	while ( $obj = db_rowobj($r) ) {
		if ( empty($obj->id) ) continue;
		++$cnt;
		$thr = new fud_thread;
		qobj("SELECT root_msg_id,id,forum_id FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."thread WHERE id=".$obj->id, $thr);
		$thr->delete(FALSE);
		unset($thr);
	}
	qf($r);
	draw_info($cnt);
	
	draw_stat('Checking message topic relations');
	$cnt = 0;
	$r = q("SELECT ".$GLOBALS['DBHOST_TBL_PREFIX']."msg.id FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."msg LEFT JOIN ".$GLOBALS['DBHOST_TBL_PREFIX']."thread ON ".$GLOBALS['DBHOST_TBL_PREFIX']."msg.thread_id=".$GLOBALS['DBHOST_TBL_PREFIX']."thread.id WHERE ".$GLOBALS['DBHOST_TBL_PREFIX']."thread.id IS NULL");
	while ( $obj=db_rowobj($r) ) {
		++$cnt;
		$msg = new fud_msg_edit;
		qobj("SELECT * FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."msg WHERE id=".$obj->id, $msg);
		$msg->delete(FALSE);
		unset($msg);
	}
	qf($r);
	draw_info($cnt);
	
	draw_stat('Checking smilies against disk files');
	$cnt = 0;
	$r = q("SELECT * FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."smiley"); 
	$smiley_dsk  = '../images/smiley_icons/';
	while ( $obj = db_rowobj($r) ) {
		if ( file_exists($smiley_dsk.$obj->img) ) {
			$smiley_ar[md5($obj->img)] = 1;
			continue;
		}
		++$cnt;
		q("DELETE FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."smiley WHERE id=".$obj->id);
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
	$r = q("SELECT ".$GLOBALS['DBHOST_TBL_PREFIX']."msg.id FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."thread LEFT JOIN ".$GLOBALS['DBHOST_TBL_PREFIX']."forum ON ".$GLOBALS['DBHOST_TBL_PREFIX']."thread.forum_id=".$GLOBALS['DBHOST_TBL_PREFIX']."forum.id LEFT JOIN ".$GLOBALS['DBHOST_TBL_PREFIX']."msg ON ".$GLOBALS['DBHOST_TBL_PREFIX']."thread.id=".$GLOBALS['DBHOST_TBL_PREFIX']."msg.thread_id WHERE ".$GLOBALS['DBHOST_TBL_PREFIX']."forum.moderated='N' AND ".$GLOBALS['DBHOST_TBL_PREFIX']."msg.approved='N'");
	if( db_count($r) ) {
		while ( list($id) = db_rowarr($r) ) {
			q("UPDATE ".$GLOBALS['DBHOST_TBL_PREFIX']."msg SET approved='Y' WHERE id=".$id);
		}
	}
	draw_stat("Done: Checking Approvals");
	
	/* fixing counts */
	draw_stat('Updating counts for topics');
	$result = q("SELECT ".$GLOBALS['DBHOST_TBL_PREFIX']."thread.id AS th_id, ".$GLOBALS['DBHOST_TBL_PREFIX']."forum.moderated FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."thread LEFT JOIN ".$GLOBALS['DBHOST_TBL_PREFIX']."forum ON ".$GLOBALS['DBHOST_TBL_PREFIX']."thread.forum_id=".$GLOBALS['DBHOST_TBL_PREFIX']."forum.id");
	while ( $obj = db_rowobj($result) ) {
		if ( $obj->moderated == 'Y' ) 
			$q = "SELECT count(*) FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."msg WHERE thread_id=".$obj->th_id." AND approved='Y'";
		else
			$q = "SELECT count(*) FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."msg WHERE thread_id=".$obj->th_id;
			
		$r = q($q);
		list($post_count) = db_rowarr($r);
		q("UPDATE ".$GLOBALS['DBHOST_TBL_PREFIX']."thread SET replies=".$post_count."-1 WHERE id=".$obj->th_id);
		qf($r);
	}
	draw_stat('threads updated');

	draw_stat('Updating forums');
	$result = q("SELECT * FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."forum");
	draw_stat('Updating topic counts for forums');
	while ( $obj = db_rowobj($result) ) {
		draw_stat('Forum: '.$obj->name);
		if ( $obj->moderated == 'Y' ) {
			$qth = "SELECT count(*) FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."thread LEFT JOIN ".$GLOBALS['DBHOST_TBL_PREFIX']."msg ON ".$GLOBALS['DBHOST_TBL_PREFIX']."thread.root_msg_id=".$GLOBALS['DBHOST_TBL_PREFIX']."msg.id WHERE forum_id=".$obj->id." AND ".$GLOBALS['DBHOST_TBL_PREFIX']."msg.approved='Y'";
			$qp = "SELECT count(*) FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."thread RIGHT JOIN ".$GLOBALS['DBHOST_TBL_PREFIX']."msg ON ".$GLOBALS['DBHOST_TBL_PREFIX']."msg.thread_id=".$GLOBALS['DBHOST_TBL_PREFIX']."thread.id WHERE ".$GLOBALS['DBHOST_TBL_PREFIX']."thread.forum_id=$obj->id AND ".$GLOBALS['DBHOST_TBL_PREFIX']."msg.approved='Y'";
		}
		else {
			$qth = "SELECT count(*) FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."thread WHERE forum_id=".$obj->id;
			$qp = "SELECT count(*) FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."thread RIGHT JOIN ".$GLOBALS['DBHOST_TBL_PREFIX']."msg ON ".$GLOBALS['DBHOST_TBL_PREFIX']."msg.thread_id=".$GLOBALS['DBHOST_TBL_PREFIX']."thread.id WHERE ".$GLOBALS['DBHOST_TBL_PREFIX']."thread.forum_id=".$obj->id;
		}
		
		$th_count = q_singleval($qth);
		$p_count = q_singleval($qp);
			
		draw_stat('thread count:'.$th_count);
		draw_stat('post count:'.$p_count);
		q("UPDATE ".$GLOBALS['DBHOST_TBL_PREFIX']."forum SET thread_count=".$th_count.", post_count=".$p_count." WHERE id=".$obj->id);
	}
	qf($result);
	draw_stat('Updating post counts');
	draw_stat('finished forum counts');
	
	/* topic notif */
	draw_stat('Checking topic notification entries against users');
	$cnt = 0;
	$result = q("SELECT ".$GLOBALS['DBHOST_TBL_PREFIX']."thread_notify.id, ".$GLOBALS['DBHOST_TBL_PREFIX']."users.id AS reg_user_id FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."thread_notify LEFT JOIN ".$GLOBALS['DBHOST_TBL_PREFIX']."users ON ".$GLOBALS['DBHOST_TBL_PREFIX']."thread_notify.user_id=".$GLOBALS['DBHOST_TBL_PREFIX']."users.id");
	while ( $obj = db_rowobj($result) ) {
		if ( !strlen($obj->reg_user_id) ) {
			q("DELETE FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."thread_notify WHERE id=".$obj->id);
			++$cnt;
		}
	}
	
	qf($result);
	draw_info($cnt);

	draw_stat('Checking topic notification entries against topics');
	$cnt = 0;
	$result = q("SELECT ".$GLOBALS['DBHOST_TBL_PREFIX']."thread_notify.id FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."thread_notify LEFT JOIN ".$GLOBALS['DBHOST_TBL_PREFIX']."thread ON ".$GLOBALS['DBHOST_TBL_PREFIX']."thread_notify.thread_id=".$GLOBALS['DBHOST_TBL_PREFIX']."thread.id WHERE ".$GLOBALS['DBHOST_TBL_PREFIX']."thread.id IS NULL");
	while ( $obj = db_rowobj($result) ) {
		q("DELETE FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."thread_notify WHERE id=".$obj->id);
		++$cnt;
	}
	qf($result);
	draw_info($cnt);
	
	/* forum notif */
	draw_stat('Checking forum notification entries against users');
	$cnt = 0;
	$result = q("SELECT ".$GLOBALS['DBHOST_TBL_PREFIX']."forum_notify.id, ".$GLOBALS['DBHOST_TBL_PREFIX']."users.id AS reg_user_id FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."forum_notify LEFT JOIN ".$GLOBALS['DBHOST_TBL_PREFIX']."users ON ".$GLOBALS['DBHOST_TBL_PREFIX']."forum_notify.user_id=".$GLOBALS['DBHOST_TBL_PREFIX']."users.id WHERE ".$GLOBALS['DBHOST_TBL_PREFIX']."users.id IS NULL");
	while ( $obj = db_rowobj($result) ) {
		q("DELETE FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."forum_notify WHERE id=".$obj->id);
		++$cnt;
	}
	
	qf($result);
	draw_info($cnt);

	draw_stat('Checking forum notification entries against forums');
	$cnt = 0;
	$result = q("SELECT ".$GLOBALS['DBHOST_TBL_PREFIX']."forum_notify.id FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."forum_notify LEFT JOIN ".$GLOBALS['DBHOST_TBL_PREFIX']."forum ON ".$GLOBALS['DBHOST_TBL_PREFIX']."forum_notify.forum_id=".$GLOBALS['DBHOST_TBL_PREFIX']."forum.id WHERE ".$GLOBALS['DBHOST_TBL_PREFIX']."forum.id IS NULL");
	while ( $obj = db_rowobj($result) ) {
		q("DELETE FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."forum_notify WHERE id=".$obj->id);
		++$cnt;
	}
	qf($result);
	draw_info($cnt);
	
	draw_stat('Updating Topic Last_Post_Id Field');
	$r = q("SELECT ".$GLOBALS['DBHOST_TBL_PREFIX']."msg.thread_id AS id,
			MAX(".$GLOBALS['DBHOST_TBL_PREFIX']."msg.post_stamp) AS post_stamp
		FROM 
			".$GLOBALS['DBHOST_TBL_PREFIX']."msg 
		WHERE 
			".$GLOBALS['DBHOST_TBL_PREFIX']."msg.approved='Y' 
		GROUP BY 
			".$GLOBALS['DBHOST_TBL_PREFIX']."msg.thread_id");

	if( db_count($r) ) {
		while ( $obj = db_rowobj($r) ) {
			$mid = q_singleval("SELECT id FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."msg WHERE post_stamp=$obj->post_stamp AND thread_id=$obj->id");
			q("UPDATE ".$GLOBALS['DBHOST_TBL_PREFIX']."thread SET last_post_id=".$mid.",last_post_date=".$obj->post_stamp." WHERE id=".$obj->id);
		}
	}
	draw_stat('Done: Updating Topic Last_Post_Id Field');
	
	draw_stat('Updating Forum Last_Post_Id Field');
	$r = q("SELECT id FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."forum");
	while( list($forum_id) = db_rowarr($r) ) {
		$last_post_id = q_singleval("SELECT last_post_id FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."thread INNER JOIN ".$GLOBALS['DBHOST_TBL_PREFIX']."msg ON ".$GLOBALS['DBHOST_TBL_PREFIX']."thread.last_post_id=".$GLOBALS['DBHOST_TBL_PREFIX']."msg.id WHERE forum_id=".$forum_id." AND ".$GLOBALS['DBHOST_TBL_PREFIX']."msg.approved='Y' AND ".$GLOBALS['DBHOST_TBL_PREFIX']."thread.moved_to=0 ORDER BY last_post_id DESC LIMIT 1");
		if( !$last_post_id ) $last_post_id=0;
		q("UPDATE ".$GLOBALS['DBHOST_TBL_PREFIX']."forum SET last_post_id=".$last_post_id." WHERE id=".$forum_id); 
	}
	qf($r);	
	draw_stat('Done: Updating Forum Last_Post_Id Field');
	
	draw_stat('Checking email notification entries against topics');
	$cnt = 0;
	$result = q("SELECT ".$GLOBALS['DBHOST_TBL_PREFIX']."thread_notify.id, ".$GLOBALS['DBHOST_TBL_PREFIX']."thread.id AS th_id FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."thread_notify LEFT JOIN ".$GLOBALS['DBHOST_TBL_PREFIX']."thread ON ".$GLOBALS['DBHOST_TBL_PREFIX']."thread_notify.thread_id=".$GLOBALS['DBHOST_TBL_PREFIX']."thread.id");
	while ( $obj = db_rowobj($result) ) {
		if ( !strlen($obj->th_id) ) {
			q("DELETE FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."thread_notify WHERE id=".$obj->id);
			++$cnt;
		}
	}
	qf($result);
	draw_info($cnt);
	
	draw_stat('Checking topic votes against topics');
	$cnt = 0;
	$r=q("SELECT ".$GLOBALS['DBHOST_TBL_PREFIX']."thread_rate_track.id FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."thread_rate_track LEFT JOIN ".$GLOBALS['DBHOST_TBL_PREFIX']."thread ON ".$GLOBALS['DBHOST_TBL_PREFIX']."thread_rate_track.thread_id=".$GLOBALS['DBHOST_TBL_PREFIX']."thread.id WHERE ".$GLOBALS['DBHOST_TBL_PREFIX']."thread.id IS NULL");
	while ( $obj = db_rowobj($r) ) {
		++$cnt;
		q("DELETE FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."thread_rate_track WHERE id=".$obj->id);
	}
	qf($r);
	draw_info($cnt);
	
	
	draw_stat('Checking topic votes against users');
	$cnt = 0;
	$r=q("SELECT ".$GLOBALS['DBHOST_TBL_PREFIX']."thread_rate_track.id FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."thread_rate_track LEFT JOIN ".$GLOBALS['DBHOST_TBL_PREFIX']."users ON ".$GLOBALS['DBHOST_TBL_PREFIX']."thread_rate_track.user_id=".$GLOBALS['DBHOST_TBL_PREFIX']."users.id WHERE ".$GLOBALS['DBHOST_TBL_PREFIX']."users.id IS NULL");
	while ( $obj = db_rowobj($r) ) {
		++$cnt;
		q("DELETE FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."thread_rate_track WHERE id=".$obj->id);
	}
	qf($r);
	draw_info($cnt);
	
	draw_stat('Rebuilding Topic Views');
	q("DELETE FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."thread_view");
	$tm=__request_timestamp__;
	$fr = q("SELECT id FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."forum");
	while ( list($frm_id) = db_rowarr($fr) ) {
		rebuild_forum_view($frm_id);
	}
	qf($fr);
	draw_stat('Done Rebuilding Topic Views');
	
	draw_stat('Rebuilding user levels & message counts');
	$r = q(" SELECT ".$GLOBALS['DBHOST_TBL_PREFIX']."users.id, count(".$GLOBALS['DBHOST_TBL_PREFIX']."msg.id) AS cnt FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."users LEFT JOIN ".$GLOBALS['DBHOST_TBL_PREFIX']."msg ON ".$GLOBALS['DBHOST_TBL_PREFIX']."users.id=".$GLOBALS['DBHOST_TBL_PREFIX']."msg.poster_id AND ".$GLOBALS['DBHOST_TBL_PREFIX']."msg.approved='Y' GROUP BY ".$GLOBALS['DBHOST_TBL_PREFIX']."users.id");
	while( $obj = db_rowobj($r) ) {
		$level_id = q_singleval("SELECT id FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."level WHERE post_count<=".$obj->cnt." ORDER BY post_count DESC LIMIT 1");
		if( !$level_id ) $level_id=0;
		q("UPDATE ".$GLOBALS['DBHOST_TBL_PREFIX']."users SET posted_msg_count=".$obj->cnt.",level_id=".$level_id." WHERE id=".$obj->id);
	}
	qf($r);
	draw_stat('Done rebuilding user levels & message counts');
	
	draw_stat('Rebuilding custom user statuses');
	$c_level = new fud_custom_tag;
	$r = q("SELECT distinct(user_id) FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."custom_tags");
	while( list($c_level->user_id) = db_rowarr($r) ) {
		$c_level->sync();
	}
	draw_stat('Done Rebuilding custom user statuses');

	$cnt = 0;
	draw_stat('Checking buddies against entry owners');
	$r = q("SELECT ".$GLOBALS['DBHOST_TBL_PREFIX']."buddy.id FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."buddy LEFT JOIN ".$GLOBALS['DBHOST_TBL_PREFIX']."users ON ".$GLOBALS['DBHOST_TBL_PREFIX']."buddy.user_id=".$GLOBALS['DBHOST_TBL_PREFIX']."users.id WHERE ".$GLOBALS['DBHOST_TBL_PREFIX']."users.id IS NULL");
	while ( $obj = db_rowobj($r) ) {
		q("DELETE FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."buddy WHERE id=".$obj->id);
		++$cnt;
	}
	qf($r);
	draw_info($cnt);
	
	$cnt = 0;
	draw_stat('Checking buddies against users');
	$r = q("SELECT ".$GLOBALS['DBHOST_TBL_PREFIX']."buddy.id FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."buddy LEFT JOIN ".$GLOBALS['DBHOST_TBL_PREFIX']."users ON ".$GLOBALS['DBHOST_TBL_PREFIX']."buddy.bud_id=".$GLOBALS['DBHOST_TBL_PREFIX']."users.id WHERE ".$GLOBALS['DBHOST_TBL_PREFIX']."users.id IS NULL");
	while ( $obj = db_rowobj($r) ) {
		q("DELETE FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."buddy WHERE id=".$obj->id);
		++$cnt;
	}
	qf($r);
	draw_info($cnt);
	
	$cnt = 0;
	draw_stat('Checking ignore list against entry owners');
	$r = q("SELECT ".$GLOBALS['DBHOST_TBL_PREFIX']."user_ignore.id FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."user_ignore LEFT JOIN ".$GLOBALS['DBHOST_TBL_PREFIX']."users ON ".$GLOBALS['DBHOST_TBL_PREFIX']."user_ignore.user_id=".$GLOBALS['DBHOST_TBL_PREFIX']."users.id WHERE ".$GLOBALS['DBHOST_TBL_PREFIX']."users.id IS NULL");
	while ( $obj = db_rowobj($r) ) {
		q("DELETE FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."buddy WHERE id=".$obj->id);
		++$cnt;
	}
	qf($r);
	draw_info($cnt);
	
	$cnt = 0;
	draw_stat('Checking ignore list against users');
	$r = q("SELECT ".$GLOBALS['DBHOST_TBL_PREFIX']."user_ignore.id FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."user_ignore LEFT JOIN ".$GLOBALS['DBHOST_TBL_PREFIX']."users ON ".$GLOBALS['DBHOST_TBL_PREFIX']."user_ignore.ignore_id=".$GLOBALS['DBHOST_TBL_PREFIX']."users.id WHERE ".$GLOBALS['DBHOST_TBL_PREFIX']."users.id IS NULL");
	while ( $obj = db_rowobj($r) ) {
		q("DELETE FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."buddy WHERE id=".$obj->id);
		++$cnt;
	}
	qf($r);
	draw_info($cnt);
	
	draw_stat('Rebuild File Extension Filter');
	$ext = new fud_ext_block;
	$ext->mk_regexp();
	draw_stat('Done: Rebuilding File Extension Filter');
	
	draw_stat('Rebuilding users\' last post ids');
	$r = q("SELECT MAX(id),poster_id FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."msg WHERE approved='Y' GROUP BY poster_id");
	while( list($mid,$uid) = db_rowarr($r) ) {
		q("UPDATE ".$GLOBALS['DBHOST_TBL_PREFIX']."users SET u_last_post_id=".$mid." WHERE id=".$uid);	
	}
	qf($r);
	draw_stat('Done: Rebuilding users\' last post ids');
	
	draw_stat('Rebuilding group cache');
	$r = q("SELECT id FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."groups");
	while( list($gid) = db_rowarr($r) ) {
		$grp = new fud_group;
		$grp->id = $gid;
		$grp->rebuild_cache();
	}
	qf($r);
	draw_stat('Done: Rebuilding group cache');
	
	draw_stat('Rebuilding smilies vieworder');
	$i=0;
	$r = q("SELECT id FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."smiley ORDER BY vieworder");
	while( list($sid) = db_rowarr($r) ) {
		q("UPDATE ".$GLOBALS['DBHOST_TBL_PREFIX']."smiley SET vieworder=".(++$i)." WHERE id=".$sid);	
	}
	qf($r);
	draw_stat('Done: Rebuilding smilies vieworder');
	
	draw_stat('Unlocking database');
	db_unlock();	
	draw_stat('Database unlocked');
	
	draw_stat('Optimizing forum\'s SQL tables');
	optimize_tables();
	draw_stat('Done: Optimizing forum\'s SQL tables');
	
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