<?php
/***************************************************************************
* copyright            : (C) 2001-2004 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: cat.inc,v 1.15 2004/10/20 17:37:31 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; either version 2 of the License, or
* (at your option) any later version.
***************************************************************************/

class fud_cat
{
	var $id, $name, $description, $view_order, $cat_opt, $parent;

	function fud_cat()
	{
		foreach ($this as $k => $v) {
			if (isset($_POST['cat_'.$k])) {
				$this->{$k} = $_POST['cat_'.$k];
			}
		}
	}

	function add($pos)
	{
		if (!db_locked()) {
			$ll = 1;
			db_lock($GLOBALS['DBHOST_TBL_PREFIX'].'cat WRITE');
		}

		if ($pos == 'LAST') {
			$this->view_order = (int) q_singleval('SELECT MAX(view_order) FROM '.$GLOBALS['DBHOST_TBL_PREFIX'].'cat WHERE parent='.$this->parent) + 1;
		} else {
			$this->view_order = 1;
			q('UPDATE '.$GLOBALS['DBHOST_TBL_PREFIX'].'cat SET view_order=view_order+1 WHERE parent='.$this->parent);
		}

		$this->id = db_qid("INSERT INTO ".$GLOBALS['DBHOST_TBL_PREFIX']."cat (name, description, view_order, cat_opt, parent) VALUES ('".addslashes($this->name)."', '".addslashes($this->description)."', ".$this->view_order.", ".$this->cat_opt.", ".$this->parent.")");

		if (isset($ll)) {
			db_unlock();
		}

		return $this->id;
	}

	function sync($id)
	{
		q("UPDATE ".$GLOBALS['DBHOST_TBL_PREFIX']."cat SET parent=".$this->parent.", name='".addslashes($this->name)."',description='".addslashes($this->description)."', cat_opt=".$this->cat_opt." WHERE id=".$id);
		cat_rebuild_order();
	}
}

function cat_rebuild_order()
{
	$old_p = -1;
	$c = q('SELECT id, view_order, parent FROM '.$GLOBALS['DBHOST_TBL_PREFIX'].'cat ORDER BY parent, view_order');
	while ($r = db_rowarr($c)) {
		if ($r[2] != $old_p) {
			$i = 1;
			$old_p = $r[2];
		}
		if ($i != $r[1]) {
			q('UPDATE '.$GLOBALS['DBHOST_TBL_PREFIX'].'cat SET view_order='.$i.' WHERE id='.$r[0]);
		}
		++$i;
	}
	unset($c);
}

function cat_change_pos($oldp, $newp, $parent)
{
	if ($oldp == $newp || !$newp) {
		return;
	}

	db_lock($GLOBALS['DBHOST_TBL_PREFIX'].'cat WRITE');
	q('UPDATE '.$GLOBALS['DBHOST_TBL_PREFIX'].'cat SET view_order=2147483647 WHERE view_order='.$oldp.' AND parent='.$parent);
	if ($oldp < $newp) {
		q('UPDATE '.$GLOBALS['DBHOST_TBL_PREFIX'].'cat SET view_order=view_order-1 WHERE view_order<='.$newp.' AND view_order>'.$oldp.' AND parent='.$parent);
		$maxp = q_singleval('SELECT MAX(view_order) FROM '.$GLOBALS['DBHOST_TBL_PREFIX'].'cat WHERE view_order!=2147483647 AND parent='.$parent);
		if ($newp > $maxp) {
			$newp = $maxp + 1;
		}
	} else {
		q('UPDATE '.$GLOBALS['DBHOST_TBL_PREFIX'].'cat SET view_order=view_order+1 WHERE view_order<'.$oldp.' AND view_order>='.$newp.' AND parent='.$parent);
	}
	q('UPDATE '.$GLOBALS['DBHOST_TBL_PREFIX'].'cat SET view_order='.$newp.' WHERE view_order=2147483647 AND parent='.$parent);
	db_unlock();
}

function rebuild_forum_cat_order()
{
	$tbl =& $GLOBALS['DBHOST_TBL_PREFIX'];

	if (!db_locked()) {
		$ll = 1;
		db_lock($tbl.'cat c WRITE,'.$tbl.'fc_view WRITE,'.$tbl.'forum f WRITE');
	}

	/* we use left join to catch categories without any forums, but with sub-categories */
	$c = uq("SELECT c.id, f.id, c.parent, c.name FROM ".$tbl."cat c LEFT JOIN ".$tbl."forum f ON f.cat_id=c.id
			ORDER BY c.parent, c.view_order, f.view_order");
	$ol = array();
	while ($r = db_rowarr($c)) {
		if (!isset($ol[$r[2]])) {
			$ol[$r[2]] = array();
		}
		$ol[$r[2]][] = $r;
	}
	q("DELETE FROM ".$tbl."fc_view");
	
	$cat_cache = array();
	$lvl = array(0);
	$i = $l = 0;
	while (1) {
		while (list(,$v) = each($ol[$l])) {
			if (!isset($cat_cache[$v[0]])) {
				$cat_cache[$v[0]] = array($i, $v[3]);
				if ($v[2]) {
					if (!isset($cat_cache[$v[2]][2])) {
						$cat_cache[$v[2]][2] = array();
					}
					$cat_cache[$v[2]][2][] = $v[0];
				}
			}
			if ($v[1]) { // only insert when we have a category & forum
				q("INSERT INTO ".$tbl."fc_view (c, f, lvl) VALUES(".$v[0].",".$v[1].",".$i.")");
			}
			if (isset($ol[$v[0]])) {
				$lvl[] = $l;
				$l = $v[0];
				$i++;
			}
		}
		if ($i < 1) {
			break;
		}
		$l = array_pop($lvl);
		$i--;
	}

	/* create full child/parent associations */
	end($cat_cache);
	while (($v = prev($cat_cache))) {
		if (isset($v[2])) {
			$children = array();
			foreach ($v[2] as $c) {
				$children[] = $c;
				if (!empty($cat_cache[$c][2])) {
					$children = array_merge($children, $cat_cache[$c][2]);
				}
			}
			$cat_cache[key($cat_cache)][2] = $children;
		}
	}

	$cache_file = $GLOBALS['FORUM_SETTINGS_PATH'] . 'cat_cache.inc';
	$fp = fopen($cache_file.'.tmp', "w");
	fwrite($fp, '<?php $cat_cache = '.var_export($cat_cache, 1).'; ?>');
	fclose($fp);
	rename($cache_file.'.tmp', $cache_file);

	unset($ol);

	if (isset($ll)) {
		db_unlock();
	}
}
?>