<?php
/**
* copyright            : (C) 2001-2007 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: admspell.php,v 1.17 2009/01/19 21:14:25 frank Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; version 2 of the License.
**/

	require('./GLOBALS.php');
	if (!($FUD_OPT_1 & 2097152)) {
		exit("Cannot use this control panel, your forum's spell checker is disabled.");
	}

	fud_use('adm.inc', true);
	fud_use('widgets.inc', true);

	$status = 0;
	if (!empty($_POST['words'])) {
		$wl = explode("\n", trim($_POST['words']));
		if (count($wl)) {
			$pspell_config = pspell_config_create($usr->pspell_lang);
			pspell_config_personal($pspell_config, $FORUM_SETTINGS_PATH."forum.pws");
			$pspell_link = pspell_new_config($pspell_config);

			foreach ($wl as $w) {
				if (($w = trim($w))) {
					pspell_add_to_personal($pspell_link, $w);
					pspell_save_wordlist($pspell_link);
					++$status;
				}
			}
		}
	}

	require($WWW_ROOT_DISK . 'adm/admpanel.php');
?>
<h2>Custom Dictionary Spell Checker</h2>
<b>Custom Dictionary Location: </b><?php echo $FORUM_SETTINGS_PATH."forum.pws"; ?><br />
<form method="post" id="spell" action="admspell.php">
<?php
	echo _hs;
	if ($status) {
		echo '<div style="text-align: center; color: green; font-size: 125%">'.$status.' word(s) were added successfully.</div>';
	}
?>
<table class="datatable solidtable">
<tr class="tutor">
	<td>Enter custom words you want added to your personal dictionary that will<br />
	be used in addition to the native pspell/aspell dictionaries. <font size="-1">(1 word per line.)</font></td>
</tr>
<tr class="field">
	<td><textarea tabindex="1" rows=7 cols=30 name="words"></textarea></td>
</tr>
<tr class="fieldaction">
	<td align="right"><input type="submit" name="submit" value="Add Words" tabindex="2" /></td>
</tr>
</table>
</form>
<script>
<!--
document.forms['spell'].words.focus();
//-->
</script>
<?php require($WWW_ROOT_DISK . 'adm/admclose.html'); ?>
