<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: rview.php.t,v 1.7 2003/04/10 17:37:00 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/	

	if (isset($_GET['th']) || isset($_GET['goto'])) {
		$_GET['t'] = d_thread_view;
	} else if (isset($_GET['frm_id'])) {
		$_GET['t'] = t_thread_view;
	} else {
		$_GET['t'] = 'index';
	}

	require($GLOBALS['DATA_DIR'] . fud_theme . $_GET['t'] . '.php');
?>