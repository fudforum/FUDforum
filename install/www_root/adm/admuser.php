<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: admuser.php,v 1.14 2002/12/11 18:51:16 hackie Exp $
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
	
	fud_use('adm.inc', true);
	fud_use('users.inc');	
	fud_use('widgets.inc', true);
	fud_use('util.inc');
	fud_use('customtags.inc');
	fud_use('private.inc');
	fud_use('logaction.inc');
	fud_use('iemail.inc');
	
	list($ses, $usr_adm) = initadm();
	
	cache_buster();
	
	$usr = new fud_user_adm;
	
	$rdr_login = "usr_login=".urlencode(stripslashes($usr_login))."&usr_email=".urlencode(stripslashes($usr_email));
	
if( !empty($act) ) {
	$usr->get_user_by_id($usr_id);
	switch ( $act ) {
		case 'block':
			if ( $usr->blocked == 'Y' ) 
				$usr->unblock_user();
			else
				$usr->block_user();
			
			header("Location: admuser.php?"._rsidl."&usr_login=".urlencode($usr->alias));
			exit();
			break;
		case 'del':
			logaction($usr_adm->id, 'DELETE_USER', 0, addslashes(htmlspecialchars($usr->login)));
			$usr->delete_user();
			header("Location: admuser.php?"._rsidl);
			exit();
			break;
		case 'coppa':
			$val = (strtoupper($usr->coppa)=='Y') ? 'N' : 'Y';
			q("UPDATE ".$GLOBALS['DBHOST_TBL_PREFIX']."users SET coppa='$val' WHERE id=".$usr->id);
			
			header("Location: admuser.php?"._rsidl."&usr_login=".urlencode($usr->alias));
			exit();
			break;
		case 'reset':
			$user_theme_name = q_singleval("SELECT name FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."themes WHERE ".(!$usr->theme ? "t_default='Y'" : "id=".$usr->theme));
			include_once($GLOBALS['INCLUDE'] . "theme/".$user_theme_name."/rst.inc");
			
			if ( $EMAIL_CONFIRMATION == 'Y' && $usr->email_conf == 'N' ) {
				$conf_key = $usr->email_unconfirm();
				$url = '{ROOT}?t=emailconf&conf_key='.$conf_key;
				send_email($GLOBALS['NOTIFY_FROM'], $usr->email, $register_conf_subject, $reset_confirmation, "");
			} else {
				$key = $usr->reset_key();
				$url = '{ROOT}?t=reset&reset_key='.$key;
				send_email($GLOBALS['NOTIFY_FROM'], $usr->email, $reset_newpass_title, $reset_reset, "");
			}
			header("Location: admuser.php?"._rsidl."&usr_login=".urlencode($usr->alias));
			break;
		case 'econf':
			$eusr = new fud_user_adm();
			$eusr->get_user_by_id($usr->id);
			if ( $eusr->email_conf == 'N' )
				$eusr->email_confirm();
			else
				$eusr->email_unconfirm();
			
			header("Location: admuser.php?"._rsidl."&usr_login=".urlencode($usr->alias));
			exit();	
			break;	
		case 'admin':
			if( $usr->is_mod == 'A' ) {
				if ( !$adm_confirm ) {
					echo '  <html><title>Adminstrator confirmation</title><body color="white">
						<form method="post">
							'._hs.'
							<input type="hidden" name="usr_login" value="'.$GLOBALS['usr_login'].'">
							<input type="hidden" name="usr_email" value="'.$GLOBALS['usr_email'].'">
							<input type="hidden" name="act" value="admin">
							<input type="hidden" name="adm_confirm" value="1">
							<div align="center">You are taking away administration privileges from <font color="red"><b>'.htmlspecialchars($usr->alias).'</b></font>!<br><br>
							Are you sure you want to do this?<br>
							<input type="submit" value="Yes" name="btn_yes"> <input type="submit" value="No" name="btn_no">
							<div>
						</form>
						</body></html>';
					exit();
				}
				if ( $btn_yes ) $usr->de_admin();
			}
			else if ( !$btn_no ) {
				if ( !$adm_confirm ) {
					echo '  <html><title>Adminstrator confirmation</title><body color="white">
						<form method="post">
							'._hs.'
							<input type="hidden" name="usr_login" value="'.$GLOBALS['usr_login'].'">
							<input type="hidden" name="usr_email" value="'.$GLOBALS['usr_email'].'">
							<input type="hidden" name="act" value="admin">
							<input type="hidden" name="adm_confirm" value="1">
							<div align="center">WARNING: Making <font color="red"><b>'.htmlspecialchars($usr->alias).'</b></font> an <font color="red"><b>administrator</b></font> will give this person full
							administration permissions to the forum. This individual will be able to do anything with the forum, including taking away your own administration permissions.
							<br><br>Are you sure you want to do this?<br>
							<input type="submit" value="Yes" name="btn_yes"> <input type="submit" value="No" name="btn_no">
							<div>
						</form>
						</body></html>';
					exit();
				}
				if ( $btn_yes ) $usr->mk_admin();
			}
			
			header("Location: admuser.php?"._rsidl."&usr_login=".urlencode($usr->alias));
			exit();
			break;								
	}
}

	if ( !empty($c_tag) && !empty($user_id) ) {
		$tag = new fud_custom_tag;
		$tag->user_id = $user_id;
		$tag->name = $c_tag;
		$tag->add();
		header("Location: admuser.php?"._rsidl."&".$rdr_login);
	}
	
	if ( !empty($deltag) ) {
		$tag = new fud_custom_tag;
		$tag->get($deltag);
		$tag->delete();
		header("Location: admuser.php?"._rsidl."&".$rdr_login);
	}
	
	if ( !empty($usr_email) ) {
		$LIKE = strstr($usr_login, '*') ? 1 : 0;
		$usr_email = str_replace("*","%",$usr_email);
		if( __dbtype__ == 'pgsql' && $LIKE ) $usr_login = addslashes(str_replace('\\', '\\\\', stripslashes($usr_login)));
		$r = q("SELECT id, email, alias FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."users WHERE LOWER(email) LIKE '".strtolower($usr_email)."'");
		if( __dbtype__ == 'pgsql' && $LIKE ) $gr_leader = str_replace('\\\\', '\\', $gr_leader);
		if( !db_count($r) ) 
			$usr->id=0;
		else {
			if ( db_count($r) > 1 ) {
				echo "There are ".db_count($r)." users that match this email mask:<br>\n";
				echo "<table border=0 cellspacing=0 cellpadding=3><tr><td>email</td><td>User</td></tr>";
				while ( $obj = db_rowobj($r) ) {
					echo "<tr><td><a href=\"admuser.php?"._rsid."&usr_email=$obj->email\">$obj->email</a></td><td>$obj->alias</td></tr>";
				}
				echo "</table>";
				qf($r);
				exit();
			}
			else {
				list($usr->id) = db_singlearr($r);
				$usr->get_user_by_id($usr->id);
			}
		}	
	}
	
	if( !empty($usr_login) ) {
		if( strstr($usr_login, '*') ) {
			$usr_login = str_replace("*","%",$usr_login);
			$r = q("SELECT id, login, alias FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."users WHERE LOWER(alias) LIKE '".strtolower(addslashes(str_replace('\\', '\\\\', htmlspecialchars(stripslashes($usr_login)))))."'");
		}
		else
			$r = q("SELECT id, login, alias FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."users WHERE LOWER(alias)='".htmlspecialchars(strtolower($usr_login))."'");

		if( !db_count($r) ) {
			$usr->id=0;
		}	
		else {
			if ( db_count($r) > 1 ) {
				echo "There are ".db_count($r)." users that match this mask:<br>\n";
				while ( $obj = db_rowobj($r) )
					echo '<a href="admuser.php?'._rsid.'&usr_login='.$obj->alias.'">'.$obj->alias.'</a> (login: '.$obj->login.')<br>';
				qf($r);
				exit();
			}
			else {
				list($usr->id) = db_singlearr($r);
				$usr->get_user_by_id($usr->id);
			}
		}	
	}
	
	if( !empty($user_id) && !empty($login_name) ) {
		if( !($id = get_id_by_login($login_name)) ) {
			if( $GLOBALS['USE_ALIASES'] != 'Y' ) {
				$alias = stripslashes($login_name);
				if( isset($alias[$GLOBALS['MAX_LOGIN_SHOW']+1]) ) $alias = substr($alias, 0, $GLOBALS['MAX_LOGIN_SHOW']);
				$alias = ", alias='".addslashes(htmlspecialchars($alias))."'";
			}	
			else
				$alias = '';	
			
			q("UPDATE ".$GLOBALS['DBHOST_TBL_PREFIX']."users SET login='".$login_name."'".$alias." WHERE id=".$user_id);
		}	
		else if( $id != $user_id )
			$login_error = '<font color="#FF0000">Someone is already using that login name.</font><br>';
			
		$usr->get_user_by_id($user_id);	
	}
	
	if ( !empty($user_id) && !empty($login_passwd) ) {
		if ( $user_id == 1 ) 
			$passwd_error = '<font color="#FF0000">Not allowed changing root password here, use the normal profile control panel</font><br>';
		else {
			$md5p = md5($login_passwd);
			q("UPDATE ".$GLOBALS['DBHOST_TBL_PREFIX']."users SET passwd='".$md5p."' WHERE id=".$user_id);
			$login_passwd = NULL;
		}
	}
	
	$returnto = urlencode('adm/admuser.php?'._rsid.'&'.$rdr_login.'&'._rsid);

	include('admpanel.php'); 
?>
<h2>User Adminstration System</h2>
<form name="frm_usr" method="get" action="admuser.php">
<?php echo _hs; ?>
<table border=0 cellspacing=1 cellpadding=3>
	<tr bgcolor="#bff8ff">
		<td colspan=2>Search for User</td>
	</tr>
	
	<tr bgcolor="#bff8ff">
		<td>By <?php echo ($GLOBALS['USE_ALIASES']!='Y'?'Login':'Alias'); ?>:</td>
		<td><input type="text" name="usr_login"></td>
	</tr>
	
	<tr bgcolor="#bff8ff">
		<td>By Email:</td>
		<td><input type="text" name="usr_email"></td>
	</tr>
	
	<tr bgcolor="#bff8ff">
		<td colspan=2 align=right><input type="submit" value="Search" name="usr_search"></td>
	</tr>
</table>
</form>
<table border=0 cellspacing=0 cellpadding=3>
<?php if ( !empty($usr->id) ) { ?>
<form action="admuser.php" method="post">
	<?php echo _hs; ?>
	<tr bgcolor="#f1f1f1"><td>Login:</td><td><?php echo $login_error; ?><input type="text" value="<?php echo htmlspecialchars($usr->login); ?>" maxLength="<?php echo $GLOBALS['MAX_LOGIN_SHOW'] ?>" name="login_name"> <input type="submit" name="submit" value="Change Login Name"></td></tr>
	<tr bgcolor="#f1f1f1"><td>Password:</td><td><?php echo $passwd_error; ?><input type="text" value="<?php echo $login_passwd; ?>" name="login_passwd"> <input type="submit" name="submit" value="Change Password"></td></tr>
<input type="hidden" name="user_id" value="<?php echo $usr->id; ?>">	
</form>	
	<?php if( $GLOBALS['USE_ALIASES']=='Y' ) echo '<tr bgcolor="#f1f1f1"><td>Alias:</td><td>'.$usr->alias.'</td></tr>'; ?>
	<tr bgcolor="#f1f1f1"><td>Email:</td><td><?php echo $usr->email; ?></td></tr>
	<tr bgcolor="#f1f1f1"><td>Name:</td><td><?php echo $usr->name; ?></td></tr>
	<?php
		if( $usr->bday ) {
			$b_year = substr($usr->bday, 0, 4); 
			$b_month = substr($usr->bday, 4, 2);
			$b_day = substr($usr->bday, 6, 8);
	?><tr bgcolor="#f1f1f1"><td>Birthday:</td><td><?php echo strftime('%B, %d, %Y', mktime(1,1,1, $b_month, $b_day, $b_year)); ?></td></tr><?php
	 	}
	?>
	<tr bgcolor="#f1f1f1"><td align=middle colspan=2><font size="+1">&gt;&gt; <a href="../<?php echo __fud_index_name__; ?>?t=register&mod_id=<?php echo $usr->id; ?>&<?php echo _rsid; ?>&returnto=<?php echo $returnto.'&'._rsid; ?>">Change User's Profile</a> &lt;&lt;</font></td></tr>
	<tr bgcolor="#f1f1f1"><td><font size="+1"><b>Forum Administrator:</b></td><td><?php echo (($usr->is_mod!='A')?'N':'<b><font size="+2" color="red">Y</font>'); ?> [<a href="admuser.php?act=admin&usr_id=<?php echo $usr->id.'&'._rsid; ?>">Toggle</a>]</td></tr>
	<tr bgcolor="#f1f1f1"><td>Blocked:</td><td><?php echo $usr->blocked; ?> [<a href="admuser.php?act=block&usr_id=<?php echo $usr->id.'&'._rsid; ?>">Toggle</a>]</td></tr>
	<?php
		if ( $GLOBALS['COPPA'] == 'Y' ) { 
	?>
	<tr bgcolor="#f1f1f1"><td>COPPA:</td><td><?php echo $usr->coppa; ?> [<a href="admuser.php?act=coppa&usr_id=<?php echo $usr->id.'&'._rsid;?>">Toggle</a>]</td></tr>
	<?php
		}
	?>
	<tr bgcolor="#f1f1f1"><td>Email Confirmation:</td><td><?php echo $usr->email_conf; ?> [<a href="admuser.php?act=econf&usr_id=<?php echo $usr->id.'&'._rsid;?>">Toggle</a>]</td></tr>
	<tr bgcolor="#f1f1f1"><td valign=top>Moderating Forums:</td>
		<td valign=top>
			<?php
				if ( $usr->getmod() ) {
					$usr->resetmod();
					echo '<table border=0 cellspacing=1 cellpadding=3>';
					while ( $mod = $usr->nextmod() ) {
						echo '<tr><td>'.$mod->name."</td><td></tr>\n";
					}
					echo '</table>';
				}
				else {
					echo "None<br>";
				}
			?>
			<a name="mod_here">
			<a href="#mod_here" onClick="javascript: window.open('admmodfrm.php?usr_id=<?php echo $usr->id.'&'._rsid; ?>', 'frm_mod', 'menubar=false,width=200,height=400,screenX=100,screenY=100,scrollbars=yes');">Modify Moderation Permissions</a>
			&nbsp;
		</td>
	</tr>
	
	<tr bgcolor="#f1f1f1"><td valign=top>Custom Tags:</td>
		<td valign=top>
			<?php
				if ( is_array($usr->get_custom_tags()) ) {
					reset($usr->custom_tags);
					while ( $obj = each($usr->custom_tags) ) {
						echo $obj[1]->name." [<a href=\"admuser.php?deltag=".$obj[1]->id."&usr_login=".urlencode($usr->alias)."&"._rsid."\">Delete</a>]<br>";
					}
				}
			?>
			<form name="extra_tags" method="post">
			<?php echo _hs; ?>
			<input type="text" name="c_tag">
			<input type="submit" value="Add">
			<input type="hidden" name="user_id" value="<?php echo $usr->id; ?>"> 
			<input type="hidden" name="user_login" value="<?php echo htmlspecialchars($usr->alias); ?>"> 
			</form>
		</td>
	</tr>
	
	<tr bgcolor="#f1f1f1">
		<td colspan=2><br><br><b>Actions:</b></td>
	</tr>

	<tr bgcolor="#f1f1f1">
<?php
	echo '<td colspan=2>';

	if( $PM_ENABLED == 'Y' ) echo '<a href="../'.__fud_index_name__.'?t=ppost&returnto='.urlencode($HTTP_SERVER_VARS["REQUEST_URI"]).'&'._rsid.'&msg_to_list='.urlencode($usr->alias).'">Send Private Message</a> | ';
	if( $ALLOW_EMAIL == 'Y' ) 
		echo '<a href="../'.__fud_index_name__.'?t=email&tx_name='.urlencode($usr->alias).'&'._rsid.'&returnto='.urlencode('adm/admuser.php?usr_login='.urlencode($usr->alias).'&'._rsid).'">Send Email</a> | ';
	else
		echo '<a href="mailto:'.$usr->email.'">Send Email</a> | ';
	
	echo '	<a href="../'.__fud_index_name__.'?t=showposts&id='.$usr->id.'&'._rsid.'">See Posts</a> | <a href="admuser.php?act=reset&usr_id='.$usr->id.'&'._rsid.'">Reset Password</a> | <a href="admuser.php?act=del&usr_id='.$usr->id.'&'._rsid.'">Delete User</a></td></tr>';
	
	} else if ( !empty($usr_login) || !empty($usr_email) ) { ?>
	<tr>
		<td>No such user</td>
	</tr>
<?php } ?>
</table>
<?php require('admclose.html'); ?>