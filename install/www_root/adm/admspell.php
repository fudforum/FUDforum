<?php
/**
* copyright            : (C) 2001-2021 Advanced Internet Designs Inc.
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
	require($WWW_ROOT_DISK .'adm/header.php');

	$status = 0;
	$custom_dict = $FORUM_SETTINGS_PATH .'forum.pwl';
	if (!empty($_POST['words'])) {
		$wl = explode("\n", trim($_POST['words']));
		sort($wl);
		if (count($wl)) {
			// $pspell_config = pspell_config_create($usr->pspell_lang);
			// pspell_config_personal($pspell_config, $FORUM_SETTINGS_PATH .'forum.pws');
			// $pspell_link = pspell_new_config($pspell_config);
			//
			// foreach ($wl as $w) {
			// 	if (($w = trim($w))) {
			// 		pspell_add_to_personal($pspell_link, $w);
			// 		pspell_save_wordlist($pspell_link);
			// 		++$status;
			// 	}
			// }

			// Create a PWL (personal word list) file with one word per line.
			rename($custom_dict, $custom_dict .'bck');
			$r = enchant_broker_init();
			$d = enchant_broker_request_pwl_dict($r, $custom_dict);
			foreach ($wl as $w) {
			 	if (($w = trim($w))) {
	 				enchant_dict_add_to_personal($d, $w);
					++$status;
			 	}
			 }
		}
	}

	if ($status) {
		echo successify($status .' word(s) were successfully added.');
	}
	
	$word_list = file_exists($custom_dict) ? htmlentities(file_get_contents($custom_dict)) : '';
?>
<h2>Forum Spell Checker</h2>

<div class="tutor">The forum's spell checker can be enabled or disabled in the Global Settings Manager. Spell checking can also be enabled/disabled on a per theme bases. Each theme can spesify a different system dictionary (pick one from the list below).</div>
<br />

<b>Forum spell checker:</b><br />
<?php
	if (!($FUD_OPT_1 & 2097152)) {  // SPELL_CHECK_ENABLED
		echo '<span style="color:red;">Disabled!</span> Enable it in the <a href="admglobal.php?'. __adm_rsid .'#14">Global Settings Manager</a>.';
	} else {
		echo '<span style="color:green;">Enabled.</span>';
	}
?>
<br /><br />

<b>Status:</b><br />
<?php
	if (!extension_loaded('enchant') || !function_exists('enchant_broker_init')) {
		echo 'You cannot use the spell checker as PHP\'s enchant module is currently <span style="color:red">disabled</span>. Please ask your administrator to enable "enchant" support.';
		$disabled = 'disabled="disabled"';
	} else {
		echo 'The PHP enchant module is installed.';
		$disabled = '';
	}
?>
<br /><br />

<b>System dictionaries:</b><br />
<?php
	$r = enchant_broker_init();
	$dicts = enchant_broker_list_dicts($r);
	$langs = array();
	foreach ($dicts as $key => $value) {
		array_push($langs, $value['lang_tag']);
	}
	echo implode(", ", $langs);
?>
<br /><br />

<b>Custom dictionary location:<br /></b>
<?php echo $custom_dict; ?>
<br />

<form method="post" id="spell" action="admspell.php">
<?php
	echo _hs;
?>
<br />

<table class="datatable solidtable">
<tr class="tutor">
	<td>Enter words you want added to your custom dictionary that will<br />
	be used in addition to the system spell dictionaries. <font size="-1">(1 word per line.)</font></td>
</tr>
<tr class="field">
	<td><textarea tabindex="1" rows="10" cols="30" name="words"><?php echo $word_list; ?></textarea></td>
</tr>
<tr class="fieldaction">
	<td align="right"><input type="submit" name="submit" value="Add Words" tabindex="2" <?php echo $disabled; ?>/></td>
</tr>
</table>
</form>
<?php require($WWW_ROOT_DISK .'adm/footer.php'); ?>
