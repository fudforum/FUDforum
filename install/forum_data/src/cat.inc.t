<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: cat.inc.t,v 1.1.1.1 2002/06/17 23:00:09 hackie Exp $
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
	var $id=NULL;
	var $name=NULL;
	var $description=NULL;
	var $allow_collapse=NULL;
	var $default_view=NULL;
	var $creation_date=NULL;
	var $view_order=NULL;
	
	var $cat_list;
	var $cur_cat;

	function get_cat($id)
	{
		QOBJ("SELECT * FROM {SQL_TABLE_PREFIX}cat WHERE id=".$id, $this);
		return $this->id;
	}
}

if ( defined('admin_form') && !defined("_cat_adm_inc_") ) fud_use('cat_adm.inc');
?>