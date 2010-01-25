<?php
/**
* copyright            : (C) 2001-2010 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id$
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; version 2 of the License.
**/

	require('./GLOBALS.php');
	fud_use('adm.inc', true);
	fud_use('ipfilter.inc', true);

	include($WWW_ROOT_DISK . 'adm/header.php');
		
	/* Validate the IP address. */
	$bits = null;
	if (isset($_POST['ipaddr'])) {
		$bits = explode('.', trim($_POST['ipaddr']));
		foreach ($bits as $k => $v) {
			$bits[$k] = ($v == '..' || $v == '*' || (!$v && $v !== '0')) ? 256 : (int) $v;
		}
		for ($i=count($bits); $i < 4; $i++) {
			$bits[$i] = 256;
		}
	}
	$tbl = $GLOBALS['DBHOST_TBL_PREFIX'];

	if (isset($_POST['edit'], $_POST['btn_update']) && isset($bits)) {
		q('UPDATE '.$tbl.'ip_block SET ca='.$bits[0].', cb='.$bits[1].', cc='.$bits[2].', cd='.$bits[3].' WHERE id='.(int)$_POST['edit']);
		echo successify('IP address ('.$_POST['ipaddr'].') was successfully updated.');
	} else if (isset($_POST['btn_submit']) && isset($bits)) {
		q('INSERT INTO '.$tbl.'ip_block (ca, cb, cc, cd) VALUES ('.$bits[0].', '.$bits[1].', '.$bits[2].', '.$bits[3].')');
		echo successify('IP address ('.$_POST['ipaddr'].') was successfully added.');
	} else if (isset($_GET['del'])) {
		q('DELETE FROM '.$tbl.'ip_block WHERE id='.(int)$_GET['del']);
		echo successify('IP address was successfully removed.');
	} else {
		$nada = 1;
	}
	if (!isset($nada) && db_affected()) {
		ip_cache_rebuild();
	}

	if (isset($_GET['edit'])) {
		if (__dbtype__ == 'mysql') {
			$ipaddr = q_singleval('SELECT CONCAT(ca, \'.\', cb, \'.\', cc, \'.\', cd) FROM '.$tbl.'ip_block WHERE id='.(int)$_GET['edit']);
		} else {
			$ipaddr = q_singleval('SELECT ca || \'.\' || cb || \'.\' || cc || \'.\' || cd FROM '.$tbl.'ip_block WHERE id='.(int)$_GET['edit']);
		}
		$ipaddr = str_replace('256', '*', $ipaddr);
		$edit = $_GET['edit'];
	} else {
		$ipaddr = $edit = '';
	}
?>
<h2>IP Filter System</h2>
<p>Block users with a matching IP address from registering or posting messages on the forum.
A range of IP addresses can be blocked by entering a mask (.*).</p>
<form id="ipf" method="post" action="admipfilter.php">
<?php echo _hs; ?>
<table class="datatable solidtable">
	<tr class="field">
		<td>Block IP Mask:</td>
		<td><input tabindex="1" type="text" name="ipaddr" value="<?php echo $ipaddr; ?>" size="15" maxlength="15" /></td>
	</tr>
	<tr class="fieldaction">
		<td colspan="2" align="right">
		<?php
			if ($edit) {
				echo '<input type="submit" name="btn_cancel" value="Cancel" /> <input type="submit" name="btn_update" value="Update" tabindex="2" />';
			} else {
				echo '<input tabindex="2" type="submit" name="btn_submit" value="Add mask" />';
			}
		?>
		</td>
	</tr>

</table>
<input type="hidden" name="edit" value="<?php echo $edit; ?>" />
</form>
<script type="text/javascript">
/* <![CDATA[ */
document.forms['ipf'].ipaddr.focus();
/* ]]> */
</script>
<h3>Defined filters:</h3>
<table class="resulttable fulltable">
<thead><tr class="resulttopic">
	<th>IP Mask</th>
	<th>Action</th>
</tr></thead>
<?php
	if (__dbtype__ == 'mysql') {
		$c = uq("SELECT id, CONCAT(ca, '.', cb, '.', cc, '.', cd) FROM ".$tbl.'ip_block');
	} else {
		$c = uq("SELECT id, ca || '.' || cb || '.' || cc || '.' || cd FROM ".$tbl.'ip_block');
	}
	$i = 0;
	while ($r = db_rowarr($c)) {
		$r[1] = str_replace('256', '*', $r[1]);
		if ($edit == $r[0]) {
			$bgcolor = ' class="resultrow1"';
		} else {
			$bgcolor = ($i++%2) ? ' class="resultrow1"' : ' class="resultrow2"';
		}
		echo '<tr '.$bgcolor.'><td>'.$r[1].'</td><td>[<a href="admipfilter.php?edit='.$r[0].'&amp;'.__adm_rsid.'">Edit</a>] [<a href="admipfilter.php?del='.$r[0].'&amp;'.__adm_rsid.'">Delete</a>]</td></tr>';
	}
	unset($c);
	if (!$i) {
		echo '<tr class="field"><td colspan="2"><center>No filters found.</center></td></tr>';
	}
?>
</table>
<?php require($WWW_ROOT_DISK . 'adm/footer.php'); ?>
