<?php
/**
* copyright            : (C) 2001-2004 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: tmp_view.php.t,v 1.10 2004/11/24 19:53:37 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; either version 2 of the License, or
* (at your option) any later version.
**/

	if (!empty($_GET['img'])) {
		$file = $TMP . basename($_GET['img']);
		if (@file_exists($file) && ($im = @getimagesize($file))) {
			header('Content-type: '.$im['mime']);
			fpassthru(fopen($file, 'rb'));
			exit;
		}
	}

	header('Content-type: image/gif');
	fpassthru(fopen($WWW_ROOT_DISK . 'blank.gif', 'rb'));
?>