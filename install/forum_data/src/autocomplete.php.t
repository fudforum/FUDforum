<?php
/**
* copyright            : (C) 2001-2019 Advanced Internet Designs Inc.
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

	/* User Lookup: Return DB values for AJAX autocomplete of fields. */
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

	/* Search: autocomplete topic titles. */
	if (!empty($_GET['search']) && !empty($_GET['term'])) {
		/* Only for logged in users. */
		if (!_uid) {
			std_error('access');
		}
		$term   = _esc($_GET['term'] .'%');

		$c = uq(q_limit('SELECT DISTINCT subject FROM {SQL_TABLE_PREFIX}thread t LEFT JOIN {SQL_TABLE_PREFIX}msg m on t.root_msg_id = m.id WHERE subject LIKE '. $term, 10));
		$rows = array();
		while ($r = db_rowarr($c)) {
			$rows[] = array('value' => $r[0]);
		}
		echo json_encode($rows);
		exit;
	}

	/* Registration: Check if supplied login/ e-mail address is in-use. */
	if (!empty($_GET['check']) && !empty($_GET['term'])) {
		$lookup = ($_GET['check'] == 'email') ? 'email' : 'login';
		$term   = $_GET['term'];

		if ($lookup == 'login' && strlen($term) < 2) {
			echo 0;  // UserID too short.
			exit;
		}
		if ($lookup == 'email' && !filter_var($term, FILTER_VALIDATE_EMAIL)) {
			echo 0;  // Invalid e-mail address.
			exit;
		}

		if (q_singleval('SELECT '. $lookup .' FROM {SQL_TABLE_PREFIX}users WHERE '. $lookup .' = '. _esc($term))) {
			echo 0;  // Not available - already taken.
		} else {
			echo 1;	 // Available for use.
		}
		exit;
	}

