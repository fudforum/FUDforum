<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: cat.inc,v 1.2 2003/04/24 13:58:41 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

class fud_cat
{
	var $id, $name, $description, $allow_collapse, $default_view, $view_order;

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
			$this->view_order = q_singleval('SELECT MAX(view_order)+1 FROM '.$GLOBALS['DBHOST_TBL_PREFIX'].'cat');
		} else {
			$this->view_order = 1;
			q('UPDATE '.$GLOBALS['DBHOST_TBL_PREFIX'].'cat SET view_order=view_order+1');
		}

		$this->id = db_qid("INSERT INTO ".$GLOBALS['DBHOST_TBL_PREFIX']."cat (name, description, allow_collapse, default_view, view_order) VALUES ('".addslashes($this->name)."','".addslashes($this->description)."','".yn($this->allow_collapse)."','".addslashes($this->default_view)."',".$this->view_order.")");

		if (isset($ll)) {
			db_unlock();
		}

		return $this->id;
	}

	function sync($id)
	{
		q("UPDATE ".$GLOBALS['DBHOST_TBL_PREFIX']."cat SET name='".addslashes($this->name)."',description='".addslashes($this->description)."',allow_collapse='".yn($this->allow_collapse)."',default_view='".addslashes($this->default_view)."' WHERE id=".$id);
	}
}

function cat_rebuild_order()
{
	$i = 1;
	$c = q('SELECT id, view_order FROM '.$GLOBALS['DBHOST_TBL_PREFIX'].'cat ORDER BY view_order');
	while ($r = db_rowarr($c)) {
		if ($i != $r[1]) {
			q('UPDATE '.$GLOBALS['DBHOST_TBL_PREFIX'].'cat SET view_order='.$i.' WHERE id='.$r[0]);
		}
		++$i;
	}
	qf($c);
}

function cat_change_pos($oldp, $newp)
{
	if ($oldp == $newp || !$newp) {
		return;
	}

	db_lock($GLOBALS['DBHOST_TBL_PREFIX'].'cat WRITE');
	q('UPDATE '.$GLOBALS['DBHOST_TBL_PREFIX'].'cat SET view_order=2147483647 WHERE view_order='.$oldp);
	if ($oldp < $newp) {
		q('UPDATE '.$GLOBALS['DBHOST_TBL_PREFIX'].'cat SET view_order=view_order-1 WHERE view_order<='.$newp.' AND view_order>'.$oldp);	
		$maxp = q_singleval('SELECT MAX(view_order) FROM '.$GLOBALS['DBHOST_TBL_PREFIX'].'cat WHERE view_order!=2147483647');
		if ($newp > $maxp) {
			$newp = $maxp + 1;
		}
	} else {
		q('UPDATE '.$GLOBALS['DBHOST_TBL_PREFIX'].'cat SET view_order=view_order+1 WHERE view_order<'.$oldp.' AND view_order>='.$newp);
	}
	q('UPDATE '.$GLOBALS['DBHOST_TBL_PREFIX'].'cat SET view_order='.$newp.' WHERE view_order=2147483647');
	db_unlock();
}
?>