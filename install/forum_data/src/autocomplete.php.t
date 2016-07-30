<?php
/**
* copyright            : (C) 2001-2016 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id$
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; version 2 of the License.
**/

/*{PRE_HTML_PHP}*/
        /* Validate request. */
	if ( !isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) != 'xmlhttprequest') {
		std_error('access');
	}

/*{POST_HTML_PHP}*/

	/* Return DB values for AJAX autocomplete of fields. */
	if (!empty($_GET['lookup']) && !empty($_GET['term'])) {
		/* Only for logged in users. */
		if (!_uid) {
			std_error('access');
		}

		$lookup = ($_GET['lookup'] == 'email') ? 'email' : 'alias';
		$term   = _esc($_GET['term'] .'%');

		$c = uq(q_limit('SELECT '. $lookup .' FROM {SQL_TABLE_PREFIX}users WHERE '. $lookup .' LIKE '. $term .' AND '. q_bitand('users_opt', 1073741824) .'= 0', 10));
		$rows = array();
		while ($r = db_rowarr($c)) {
			$rows[] = array('value' => $r[0]);
		}
		echo json_encode($rows);
		exit;
	}

	/* Check if supplied login/ e-mail address is in-use. */
	if (!empty($_GET['check']) && !empty($_GET['term'])) {
		$lookup = ($_GET['lookup'] == 'email') ? 'email' : 'login';
		$term   = _esc($_GET['term']);

		if (q_singleval('SELECT '. $lookup .' FROM {SQL_TABLE_PREFIX}users WHERE '. $lookup .' = '. $term)) {
			echo 0;  // Not available - already taken.
		} else {
			echo 1;	 // Available for use.
		}
		exit;
	}

