<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: theme.inc,v 1.4 2003/06/03 16:45:13 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/
	
class fud_theme
{
	var $id, $name, $theme, $lang, $locale, $enabled, $pspell_lang, $t_default;

	function fud_theme()
	{
		foreach ($this as $k => $v) {
			if (isset($_POST['thm_'.$k])) {
				$this->{$k} = $_POST['thm_'.$k];
			}
		}	
	}

	function add()
	{
		if (!db_locked()) {
			$ll = 1;
			db_lock($GLOBALS['DBHOST_TBL_PREFIX'].'themes WRITE, '.$GLOBALS['DBHOST_TBL_PREFIX'].'users WRITE');
		}
		$this->t_default = yn($this->t_default);

		if ($this->t_default == 'Y') {
			q("UPDATE ".$GLOBALS['DBHOST_TBL_PREFIX']."themes SET t_default='N' WHERE t_default='Y'");
			$this->enabled = 'Y';
		}
		
		$this->id = db_qid("INSERT INTO ".$GLOBALS['DBHOST_TBL_PREFIX']."themes
				(name, theme, lang, locale, enabled, pspell_lang, t_default)
			VALUES
				('".addslashes($this->name)."', '".addslashes($this->theme)."', '".addslashes($this->lang)."', '".addslashes($this->locale)."', '".yn($this->enabled)."', ".strnull(addslashes($this->pspell_lang)).",'".$this->t_default."')");

		if ($this->t_default == 'Y') {
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
		$this->t_default = yn($this->t_default);

		if ($this->t_default == 'Y') {
			q("UPDATE ".$GLOBALS['DBHOST_TBL_PREFIX']."themes SET t_default='N' WHERE t_default='Y'");
			q('UPDATE '.$GLOBALS['DBHOST_TBL_PREFIX'].'users SET theme='.$id.' WHERE id=1');
			$this->enabled = 'Y';
		}

		q("UPDATE ".$GLOBALS['DBHOST_TBL_PREFIX']."themes SET 
			name='".addslashes($this->name)."', 
			theme='".addslashes($this->theme)."', 
			lang='".addslashes($this->lang)."', 
			locale='".addslashes($this->locale)."', 
			enabled='".yn($this->enabled)."',
			pspell_lang=".strnull(addslashes($this->pspell_lang)).",
			t_default='".$this->t_default."'
		WHERE id=".$id);

		if ($this->enabled != 'Y' && !q_singleval("SELECT id FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."themes WHERE enabled='Y'")) {
			q("UPDATE ".$GLOBALS['DBHOST_TBL_PREFIX']."themes SET enabled='Y' WHERE id=1");
			q('UPDATE '.$GLOBALS['DBHOST_TBL_PREFIX'].'users SET theme=1 WHERE theme='.$id);
		}
		if ($this->t_default != 'Y' && !q_singleval("SELECT id FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."themes WHERE t_default='Y'")) {
			q("UPDATE ".$GLOBALS['DBHOST_TBL_PREFIX']."themes SET t_default='Y' WHERE id=1");
			q('UPDATE '.$GLOBALS['DBHOST_TBL_PREFIX'].'users SET theme=1 WHERE theme=1');
		}
		
		if ($this->enabled != 'Y') {
			q('UPDATE '.$GLOBALS['DBHOST_TBL_PREFIX'].'users SET theme='.q_singleval("SELECT id FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."themes WHERE t_default='Y'").' WHERE theme='.$id);
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
		if (!($data = db_saq('SELECT t_default, name FROM '.$GLOBALS['DBHOST_TBL_PREFIX'].'themes WHERE id='.$id))) {
			db_unlock();
			return;
		}
		
		q('DELETE FROM '.$GLOBALS['DBHOST_TBL_PREFIX'].'themes WHERE id='.$id);
		if ($data[0] == 'Y') {
			if (!($new_default = q_singleval('SELECT id FROM '.$GLOBALS['DBHOST_TBL_PREFIX'].'themes WHERE enabled=\'Y\''))) {
				$new_default = 1;
			}
			q("UPDATE ".$GLOBALS['DBHOST_TBL_PREFIX']."themes SET enabled='Y', t_default='Y' WHERE id=".$new_default);
		} else {
			$new_default = q_singleval('SELECT id FROM '.$GLOBALS['DBHOST_TBL_PREFIX'].'themes WHERE enabled=\'Y\'');
		}

		q('UPDATE '.$GLOBALS['DBHOST_TBL_PREFIX'].'users SET theme='.$new_default.' WHERE theme='.$id);

		if (isset($ll)) {
			db_unlock();
		}

		cleandir($GLOBALS['WWW_ROOT_DISK'] . 'theme/' . $data[1]);
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

	$dp = opendir($dir);
	readdir($dp); readdir($dp);
	while ($f = readdir($dp)) {
		if ($f == 'GLOBALS.php' || $f == 'oldfrm_upgrade.php' || @is_link($dir . '/' .$f)) {
			continue;
		}

		if (@is_dir($dir . '/' . $f)) {
			cleandir($dir . '/' . $f);
		} else if (!@unlink($dir . '/' . $f)) {
			echo 'Couldn\'t remove (<b>'.$dir . '/' . $f.'</b>)<br>';
		}
	}

	closedir($dp);
	rmdir($dir);
}
?>