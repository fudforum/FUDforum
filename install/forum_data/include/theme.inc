<?php
/**
* copyright            : (C) 2001-2006 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: theme.inc,v 1.32 2006/09/19 14:37:55 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; version 2 of the License.
**/

class fud_theme
{
	var $id, $name, $theme, $lang, $locale, $pspell_lang, $theme_opt;

	function fud_theme()
	{
		foreach ($this as $k => $v) {
			if (isset($_POST['thm_'.$k])) {
				$this->{$k} = $_POST['thm_'.$k];
			}
		}
		$this->name = preg_replace('![^A-Za-z0-9_]!', '_', trim($this->name));
		$this->theme_opt = !empty($_POST['thm_enabled']) | (!empty($_POST['thm_t_default']) ? 2 : 0);
	}

	function add()
	{
		if (!db_locked()) {
			$ll = 1;
			db_lock($GLOBALS['DBHOST_TBL_PREFIX'].'themes WRITE, '.$GLOBALS['DBHOST_TBL_PREFIX'].'users WRITE');
		}

		if ($this->theme_opt & 2) {
			q("UPDATE ".$GLOBALS['DBHOST_TBL_PREFIX']."themes SET theme_opt=theme_opt & ~ 2 WHERE theme_opt>=2 AND (theme_opt & 2) > 0");
			$this->theme_opt |= 1;
		}

		if ($this->theme == 'path_info' || @file_exists($GLOBALS['DATA_DIR'].'thm/'.$this->theme.'/.path_info')) {
			$this->theme_opt |= 4;
		}

		$this->id = db_qid("INSERT INTO ".$GLOBALS['DBHOST_TBL_PREFIX']."themes
				(name, theme, lang, locale, theme_opt, pspell_lang)
			VALUES
				("._esc($this->name).", "._esc($this->theme).", "._esc($this->lang).", "._esc($this->locale).", ".$this->theme_opt.", ".ssn($this->pspell_lang).")");

		/* set anonymous user's theme to default */
		if ($this->theme_opt & 2) {
			q('UPDATE '.$GLOBALS['DBHOST_TBL_PREFIX'].'users SET theme='.$this->id.' WHERE id=1');
		}
		if (isset($ll)) {
			db_unlock();
		}
		return $this->id;
	}

	function sync($id)
	{
		if (!db_locked()) {
			$ll = 1;
			db_lock($GLOBALS['DBHOST_TBL_PREFIX'].'themes WRITE, '.$GLOBALS['DBHOST_TBL_PREFIX'].'users WRITE');
		}

		if ($this->theme_opt & 2) {
			q("UPDATE ".$GLOBALS['DBHOST_TBL_PREFIX']."themes SET theme_opt=theme_opt & ~ 2 WHERE theme_opt>=2 AND (theme_opt & 2) > 0");
			q('UPDATE '.$GLOBALS['DBHOST_TBL_PREFIX'].'users SET theme='.$id.' WHERE id=1');
			$this->theme_opt |= 2;
		}

		if ($this->theme == 'path_info' || @file_exists($GLOBALS['DATA_DIR'].'thm/'.$this->theme.'/.path_info')) {
			$this->theme_opt |= 4;
		}

		q("UPDATE ".$GLOBALS['DBHOST_TBL_PREFIX']."themes SET
			name="._esc($this->name).",
			theme="._esc($this->theme).",
			lang="._esc($this->lang).",
			locale="._esc($this->locale).",
			theme_opt=".$this->theme_opt.",
			pspell_lang=".ssn($this->pspell_lang)."
		WHERE id=".$id);

		if (!($this->theme_opt & 1) && !q_singleval("SELECT id FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."themes WHERE (theme_opt & 1) > 0")) {
			q("UPDATE ".$GLOBALS['DBHOST_TBL_PREFIX']."themes SET theme_opt=theme_opt|1 WHERE id=1");
			q('UPDATE '.$GLOBALS['DBHOST_TBL_PREFIX'].'users SET theme=1 WHERE theme='.$id);
		}
		if (!($this->theme_opt & 2) && !q_singleval("SELECT id FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."themes WHERE theme_opt>=2 AND (theme_opt & 2) > 0")) {
			q("UPDATE ".$GLOBALS['DBHOST_TBL_PREFIX']."themes SET theme_opt=theme_opt|3 WHERE id=1");
			q('UPDATE '.$GLOBALS['DBHOST_TBL_PREFIX'].'users SET theme=1 WHERE theme=1');
		}

		if (!($this->theme_opt & 1)) {
			q('UPDATE '.$GLOBALS['DBHOST_TBL_PREFIX'].'users SET theme='.q_singleval("SELECT id FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."themes WHERE theme_opt>=2 AND (theme_opt & 2) > 0").' WHERE theme='.$id);
		}

		if (isset($ll)) {
			db_unlock();
		}
	}

	function get($id)
	{
		qobj("SELECT * FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."themes WHERE id=".$id, $this);
	}

	function delete($id)
	{
		if (!db_locked()) {
			$ll = 1;
			db_lock($GLOBALS['DBHOST_TBL_PREFIX'].'themes WRITE, '.$GLOBALS['DBHOST_TBL_PREFIX'].'users WRITE');
		}
		if (!($data = db_saq('SELECT theme_opt, name FROM '.$GLOBALS['DBHOST_TBL_PREFIX'].'themes WHERE id='.$id))) {
			db_unlock();
			return;
		}

		q('DELETE FROM '.$GLOBALS['DBHOST_TBL_PREFIX'].'themes WHERE id='.$id);
		if ($data[0] & 2) {
			if (!($new_default = q_singleval('SELECT id FROM '.$GLOBALS['DBHOST_TBL_PREFIX'].'themes WHERE (theme_opt & 1) > 0'))) {
				$new_default = 1;
			}
			q("UPDATE ".$GLOBALS['DBHOST_TBL_PREFIX']."themes SET theme_opt=theme_opt|3 WHERE id=".$new_default);
		} else {
			$new_default = q_singleval('SELECT id FROM '.$GLOBALS['DBHOST_TBL_PREFIX'].'themes WHERE theme_opt>=2 AND (theme_opt & 2) > 0');
		}

		q('UPDATE '.$GLOBALS['DBHOST_TBL_PREFIX'].'users SET theme='.$new_default.' WHERE theme='.$id);

		if (isset($ll)) {
			db_unlock();
		}
		if ($data[1]) {
			cleandir($GLOBALS['WWW_ROOT_DISK'] . 'theme/' . $data[1]);
			cleandir($GLOBALS['INCLUDE'] . 'theme/' . $data[1]);
		}
	}
}

function cleandir($dir)
{
	/* this is needed for various 'lame' systems like FreeBSOD */
	$dir = preg_replace('!/*$!', '', $dir);

	if (!@is_dir($dir)) {
		echo 'Couldn\'t delete "'.$dir.'", directory does not exist<br>';
		return;
	}

	$dirs = array(realpath($dir));

	while (list(,$v) = each($dirs)) {
		if (!($files = glob($v.'/*', GLOB_NOSORT))) {
			continue;
		}
		foreach ($files as $file) {
			if (strpos($file, 'GLOBALS.php') !== false || strpos($file, 'oldfrm_upgrade.php') !== false) {
				continue;
			}
		
			if (is_dir($file) && !is_link($file)) {
				$dirs[] = $file;
			} else if (!unlink($file)) {
				echo '<b>Could not delete file "'.$file.'"<br>';
			}
		}
	}

	foreach (array_reverse($dirs) as $dir) {
		if (!rmdir($dir)) {
			echo '<b>Could not delete directory "'.$dir.'"<br>';
		}
	}
}
?>