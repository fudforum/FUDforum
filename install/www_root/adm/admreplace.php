<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: admreplace.php,v 1.1.1.1 2002/06/17 23:00:09 hackie Exp $
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
	
	fud_use('cat.inc');
	fud_use('static/widgets.inc');
	fud_use('static/replace_adm.inc');
	fud_use('replace.inc');
	fud_use('objutil.inc');
	fud_use('util.inc');
	fud_use('static/adm.inc');
	
	list($ses, $usr) = initadm();
	
	if ( !empty($edit) && empty($resub) ) {
		$rpl_r = new fud_replace;
		$rpl_r->get($edit);
		$rpl_r->replace_str = addslashes($rpl_r->replace_str);
		$rpl_r->with_str = addslashes($rpl_r->with_str);
		$rpl_r->from_post = addslashes($rpl_r->from_post);
		$rpl_r->to_msg = addslashes($rpl_r->to_msg);
		export_vars('rpl_', $rpl_r);
		list($rpl_replace_str, $rpl_preg_opt) = reg_to_frm($rpl_replace_str);
		list($rpl_from_post, $rpl_from_post_opt) = reg_to_frm($rpl_from_post);
		if ( $rpl_r->type == 'REPLACE' ) {
			$rpl_with_str = stripslashes($rpl_with_str);
			$rpl_replace_str = stripslashes($rpl_replace_str);
		}
	}
	
	if ( !empty($btn_update) ) {
		if ( empty($edit) ) exit("error");
		$rpl_u = new  fud_replace;
		$rpl_u->get($edit);
		fetch_vars('rpl_', $rpl_u, $HTTP_POST_VARS);
		
		if ( $rpl_u->type == 'PERL' ) {
			$rpl_u->replace_str = addslashes(frm_to_reg(stripslashes($rpl_u->replace_str), stripslashes($HTTP_POST_VARS['rpl_preg_opt'])));
			$rpl_u->from_post = addslashes(frm_to_reg(stripslashes($rpl_u->from_post), stripslashes($HTTP_POST_VARS['rpl_from_post_opt'])));
		}
		else {
			$rpl_u->replace_str = addslashes(preg_quote(stripslashes($rpl_u->replace_str)));
			$rpl_u->replace_str = '/'.str_replace('/', '\\\\/',  $rpl_u->replace_str).'/i';
			$rpl_u->with_str =  addslashes(str_replace('\\', '\\\\', stripslashes($rpl_u->with_str)));
		}
		$rpl_u->sync();
		header("Location: admreplace.php?"._rsid);
		exit();
	}
	
	if ( !empty($btn_cancel) ) {
		header("Location: admreplace.php?"._rsid);
		exit();
	}
	
	if ( !empty($btn_submit) ) {
		$rpl = new fud_replace;
		fetch_vars('rpl_', $rpl, $HTTP_POST_VARS);
		
		if ( $rpl->type == 'PERL' ) {
			$rpl->replace_str = addslashes(frm_to_reg(stripslashes($rpl->replace_str), stripslashes($HTTP_POST_VARS['rpl_preg_opt'])));
			$rpl->from_post = addslashes(frm_to_reg(stripslashes($rpl->from_post), stripslashes($HTTP_POST_VARS['rpl_from_post_opt'])));
		}
		else {
			$rpl->replace_str = addslashes(preg_quote(stripslashes($rpl->replace_str)));
			$rpl->replace_str = '/'.str_replace('/', '\\\\/',  $rpl->replace_str).'/i';
			$rpl->with_str =  addslashes(str_replace('\\', '\\\\', stripslashes($rpl->with_str)));
		}

		$rpl->add();
		header("Location: admreplace.php?"._rsid);
		exit();
	}
	
	if ( !empty($del) ) {
		$rpl = new fud_replace;
		$rpl->get($del);
		$rpl->delete();
		header("Location: admreplace.php?"._rsid);
		exit();
	}
	
function reg_to_frm($str)
{
	if ( preg_match('!/(.+)/(.*)!', $str, $regs) ) {
		$arr[0] = str_replace('\/', '/', $regs[1]);
		$arr[1] = $regs[2];
	}
	
	return $arr;
	
}

function frm_to_reg($reg, $opt)
{
	if ( empty($reg) ) return;
	return '/'.str_replace('/', '\\/', $reg).'/'.$opt;
}
	
	cache_buster();
	require('admpanel.php'); 
?>
<h2>Replacement Management System</h2>
<form name="frm_rpl" method="post">
<?php echo _hs; ?>
<table border=0 cellspacing=1 cellpadding=3>
	<tr bgcolor="#bff8ff">
		<td>Replacement Type:</td>
		<td><?php draw_select_ex('rpl_type', "Simple Replace\nPerl Regex (preg_replace)", "REPLACE\nPERL", empty($rpl_type)?'':$rpl_type, "onChange=\"document.frm_rpl.submit();\""); ?></td>
	</tr>
	
	<tr bgcolor="#bff8ff">
		<td>Replace mask:</td>
		<? if ( $rpl_type == 'PERL' ) { ?>
			<td>/<input type="text" name="rpl_replace_str" value="<?php echo htmlspecialchars(stripslashes($rpl_replace_str)); ?>">/<input type="text" name="rpl_preg_opt" size=3 value="<? echo htmlspecialchars(stripslashes($rpl_preg_opt)); ?>"></td>
		<? } else { ?>
			<td> <input type="text" name="rpl_replace_str" value="<?php echo htmlspecialchars(stripslashes($rpl_replace_str)); ?>"></td>
		<? } ?>
	</tr>
	
	<tr bgcolor="#bff8ff">
		<td>Replace with:</td>
		<td><input type="text" name="rpl_with_str" value="<?php echo htmlspecialchars(stripslashes($rpl_with_str)); ?>"></td>
	</tr>
	
	<?php if ( $rpl_type == 'PERL' ) { ?>
	<tr>
		<td colspan=2><br></td>
	</tr>
	
	<tr bgcolor="#bff8ff">	
		<td colspan=2><b><font size=-2>Optional with the Perl Regex</font></b><br><font size=-1>(Reverse replacement logic, e.g upon editing a post)</font></td>
	</tr>
	
	<tr bgcolor="#bff8ff">
		<td>Replace mask:</td>
		<td>/<input type="text" name="rpl_from_post" value="<?php echo htmlspecialchars(stripslashes($rpl_from_post)); ?>">/<input type="text" name="rpl_from_post_opt" size=3 value="<? echo htmlspecialchars(stripslashes($rpl_from_post_opt)); ?>"></td></td>
	</tr>
	
	<tr bgcolor="#bff8ff">
		<td>Replace with:<br></td>
		<td><input type="text" name="rpl_to_msg" value="<?php echo (empty($rpl_to_msg)?'':htmlspecialchars(stripslashes($rpl_to_msg))); ?>"></td>
	</tr>
	
	<?php } ?>
	
	<tr bgcolor="#bff8ff" align=right>
		<td colspan=2><?php
			if ( !empty($edit) ) {
				echo '<input type="submit" value="Cancel" name="btn_cancel"> ';
				echo '<input type="submit" value="Update" name="btn_update">';
			}
			else {		
				echo '<input type="submit" value="Add" name="btn_submit">';
			}
		?></td>
	</tr>
	
<?php if ( $rpl_type == 'PERL' ) { ?>
<tr>
		<td colspan=2><br></td>
	</tr>
	
	<tr bgcolor="#bff8ff">	
		<td colspan=2><b><font size=-2>
		Test Area, tryout your regex here
		</font></b></td>
	</tr>
	
	<tr bgcolor="#bff8ff">
		<td>Replace mask:</td>
		<td>/<input type="text" name="regex_str" value="<?php echo htmlspecialchars(stripslashes($regex_str)); ?>">/<input type="text" name="regex_str_opt" size=3 value="<? echo htmlspecialchars(stripslashes($regex_str_opt)); ?>"></td>
	</tr>
	
	<tr bgcolor="#bff8ff">
		<td>Replace with:</td>
		<td><input type="text" name="regex_with" value="<?php echo (empty($regex_with)?'':htmlspecialchars(stripslashes($regex_with))); ?>"></td>
	</tr>
	
	<tr bgcolor="#bff8ff">
		<td valign=top>Test text:</td>
		<td><textarea name="regex_src"><? echo htmlspecialchars(stripslashes($HTTP_POST_VARS['regex_src'])); ?></textarea></td>
	</tr>
	
	<?php
		if ( isset($HTTP_POST_VARS['btn_regex']) ) {
			$regex_str = stripslashes($regex_str);
			$regex_with = stripslashes($regex_with);
			$regex_src = stripslashes($regex_src);

			$regex_str = frm_to_reg($regex_str, $HTTP_POST_VARS['regex_str_opt']);
			$str = preg_replace($regex_str, $regex_with, $regex_src);

			?>
			<tr bgcolor="#bff8ff">
				<td valign=top>Result:</td>
				<td>
					<font size=-1>
						'<?php echo htmlspecialchars($regex_str); ?>' applied to: <br>
						<table border=1 cellspacing=0 cellpadding=3>
						<tr><td><?php echo htmlspecialchars($regex_src); ?></td></tr>
						</table>
						<br>
						produces:<br>
						<table border=1 cellspacing=0 cellpadding=3>
						<tr><td><?php echo htmlspecialchars($str); ?></td></tr>
						</table>
					</font>
				</td>
			</tr>
			<?php
		}
	?>
	
	<tr bgcolor="#bff8ff" align=right>
		<td colspan=2>
			<input type="submit" name="btn_regex" value="Run">
		</td>
	</tr>
<? } ?>

</table>
<input type="hidden" name="edit" value="<?php echo (empty($edit)?'':$edit); ?>">
<input type="hidden" name="resub" value="1">
</form>
<table border=0 cellspacing=3 cellpadding=2>
<tr bgcolor="#e5ffe7">
	<td>Replace Type</td>
	<td>Replace</td>
	<td>With</td>
	<td valign=center><font size=-3>(only if regexp:</font> From</td>
	<td valign=center>To<font size=-3>)</font></td>
	<td>Action</td>
</tr>
<?php
	$rpl_draw = new fud_replace;
	
	$rpl_draw->getall();
	$rpl_draw->resetrpl();	
	$i=1;
	while ( $obj = $rpl_draw->eachrpl() ) {
		$bgcolor = ($i++%2)?' bgcolor="#fffee5"':'';
		if ( !empty($edit) && $edit==$obj->id ) $bgcolor =' bgcolor="#ffb5b5"';
		
		$ctl = '<td>[<a href="admreplace.php?edit='.$obj->id.'&'._rsid.'">Edit</a>] [<a href="admreplace.php?del='.$obj->id.'&'._rsid.'">Delete</a>]</td>';
		if ( $obj->type == 'REPLACE' ) {
			list($obj->replace_str) = reg_to_frm($obj->replace_str);
			$obj->replace_str = stripslashes($obj->replace_str);
			$obj->with_str = stripslashes($obj->with_str);
			echo "<tr$bgcolor><td>String Replace</td><td>".htmlspecialchars($obj->replace_str)."&nbsp;</td><td>".htmlspecialchars($obj->with_str)."&nbsp;</td><td>N/A</td><td>N/A</td>$ctl</tr>\n";
		}
		else {
			switch ( $obj->type ) {
				case "PERL":
					$rpl = "Perl Regex";
					break;
			}
			
			echo "<tr$bgcolor><td>".htmlspecialchars($rpl)."</td>
				<td>".htmlspecialchars($obj->replace_str)."&nbsp;</td>
				<td>".htmlspecialchars($obj->with_str)."&nbsp;</td>
				<td>".htmlspecialchars($obj->from_post)."&nbsp;</td>
				<td>".htmlspecialchars($obj->to_msg)."&nbsp;</td>$ctl</tr>\n";
		}
	}  

?>
</table>
<?php require('admclose.html'); ?>