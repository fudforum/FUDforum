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
	fud_use('widgets.inc', true);
	require($WWW_ROOT_DISK . 'adm/header.php');
		
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
?>
<h2>Custom Dictionary Spell Checker</h2>
<?php
	if (!($FUD_OPT_1 & 2097152)) {	// SPELL_CHECK_ENABLED
		echo '<div class="alert">This control panel is currently disabled. Please enable the forum\'s spell checker in the <a href="admglobal.php?'.__adm_rsid.'#14">Global Settings Manager</a>.</div><br />';
		$disabled = 'disabled="disabled"';
	} else {
		$disabled = '';
	}
?>
<b>Custom Dictionary Location:<br /></b><?php echo $FORUM_SETTINGS_PATH."forum.pws"; ?><br />
<form method="post" id="spell" action="admspell.php">
<?php
	echo _hs;
	if ($status) {
		echo '<div style="text-align: center; color: green; font-size: 125%">'.$status.' word(s) were added successfully.</div>';
	}
?>
<br />

<table class="datatable solidtable">
<tr class="tutor">
	<td>Enter custom words you want added to your personal dictionary that will<br />
	be used in addition to the native pspell/aspell dictionaries. <font size="-1">(1 word per line.)</font></td>
</tr>
<tr class="field">
	<td><textarea tabindex="1" rows="7" cols="30" name="words"></textarea></td>
</tr>
<tr class="fieldaction">
	<td align="right"><input type="submit" name="submit" value="Add Words" tabindex="2" <?php echo $disabled; ?>/></td>
</tr>
</table>
</form>
<script type="text/javascript">
/* <![CDATA[ */
document.forms['spell'].words.focus();
/* ]]> */
</script>
<?php require($WWW_ROOT_DISK . 'adm/footer.php'); ?>
