<?php
/**
* copyright            : (C) 2001-2009 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: hlplist.php,v 1.2 2009/09/09 16:15:00 frank Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; version 2 of the License.
**/

	@set_time_limit(6000);

	require('./GLOBALS.php');
	fud_use('adm.inc', true);

	$tname = isset($_POST['tname']) ? $_POST['tname'] : (isset($_GET['tname']) ? $_GET['tname'] : '');
	$tlang = isset($_POST['tlang']) ? $_POST['tlang'] : (isset($_GET['tlang']) ? $_GET['tlang'] : '');

	if (!$tname || !$tlang) {
		header('Location: '.$WWW_ROOT.'adm/admhelp.php?'.__adm_rsidl);
		exit;
	}
	
	require($WWW_ROOT_DISK . 'adm/admpanel.php');

	$path = $GLOBALS['DATA_DIR'].'thm/'.$tname.'/i18n/'.$tlang.'/help';
	$files = glob($path . '/*.hlp');
	if (!$files) {
		echo "Could not get list of help files from {$path}<br/>";
		exit;
	}

	if (isset($_POST['savehelp'], $_POST['file'])) {
		$data = '';
		foreach($_POST['q'] as $k=>$q) {
			if (empty($_POST['q']) || empty($_POST['a'][$k])) continue;
			$data .= 'TOPIC_TITLE: '.$q."\nTOPIC_HELP:\n".$_POST['a'][$k]."\n\n";
		}	
		$file = $path .'/'. $_POST['file'];
		file_put_contents($file, $data);

                // Recompile dependant themes.
                fud_use('compiler.inc', true);
                $c = q('SELECT name FROM '.$GLOBALS['DBHOST_TBL_PREFIX'].'themes WHERE theme='._esc($tname)." AND lang="._esc($tlang));
                while ($r = db_rowarr($c)) {
                        compile_all($tname, $tlang, $r[0]);
                }
                unset($c);

		echo '<font color="green">Help successfully saved to '. $_POST['file'] .'.</font>';
	}
?>
<table border="0" cellspacing="0" cellpadding="0"><tr><td valign="top">

<table border="0" cellspacing="0" cellpadding="3">
<?php
	echo '<tr><td><b>Select file to edit:</b></td></tr>';
	foreach ($files as $f) {
		$file = basename($f);
		echo '<tr><td><img src="../blank.gif" height="1" width="20" alt="blank"><a class="deps" href="hlplist.php?tname='.$tname.'&amp;tlang='.$tlang.'&amp;'.__adm_rsid.'&amp;file='.$file.'" title="Edit this help file.">'.$file.'</a></td></tr>';
	}
?>
</table></td>

<script language="JavaScript">
/* <![CDATA[ */
$(document).ready(function () {
  $('.newEntry').click(function() {
	$(this).parent().html('New topic:'+
				 '<input type="text" name="q[]" value="" size="60" /><br />'+
				 '<textarea name="a[]" rows="10" cols="80"></textarea>');
  });
});
/* ]]> */
</script>
<?php
	if (isset($_GET['file'])) {
		echo '<td valign="top"><form method="post" action="hlplist.php?tname='.$tname.'&amp;tlang='.$tlang.'">'._hs.'<table border="0" cellpadding="3">';
		echo '<tr><td>Blank out content to remove a topic. Click on any of the <i>Add new topic</i> links to add a new topic.</td></tr>';
		$file = $path .'/'. $_GET['file'];
		$data = file_get_contents($file);
		$sections = preg_split('/TOPIC_TITLE: /', $data);
		if (count($sections) < 2 && empty($sections[0])) {	// Default entry for empty help file.
			$sections[0] = "First topic.\nTOPIC_HELP:\n\nSome text or HTML code.";
		}
		foreach ($sections as $sec) {
			if ($sec) {
				$topic = substr($sec, 0, strpos($sec, 'TOPIC_HELP:'));
				$body= substr($sec, strpos($sec, 'TOPIC_HELP:')+11);
				echo '<tr><td>Topic:
					<input type="text" name="q[]" value="'. htmlentities(trim($topic), ENT_COMPAT) .'" size="60" /><br />
					<textarea name="a[]" rows="10" cols="80">'. trim($body). '</textarea><br />
					</td></tr><tr><td>
						<div class="newEntry" align="center">[ <a href="#">Add new topic here</a> ]</div>
					</td></tr>';
			}
		}
		echo '<tr><td colspan="2" align="right">
			<input type="hidden" name="file" value="'. $_GET['file'] .'" />
			<input type="hidden" name="tlang" value="'. $tlang .'" />
			<input type="hidden" name="tname" value="'. $tname .'" />
			<input type="submit" name="savehelp" value="Save Changes" />
		    </td></tr>';
		echo '</table></form></td>';
	}
?>
</tr></table>
<?php require($WWW_ROOT_DISK . 'adm/admclose.html'); ?>

