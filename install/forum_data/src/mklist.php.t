<?php
/**
* copyright            : (C) 2001-2004 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: mklist.php.t,v 1.15 2004/12/03 19:41:48 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; either version 2 of the License, or
* (at your option) any later version.
**/

	define('plain_form', 1);

/*{PRE_HTML_PHP}*/

	if (!empty($_POST['opt_list'])) {
		foreach ((array)$_POST['opt_list'] as $k => $v) {
			if (!is_numeric($k)) {
				unset($_POST['opt_list'][$k]);
			}
		}
	} else {
		$_POST['opt_list'] = array();
	}

	/* remove list entry */
	if (isset($_POST['del'])) {
		unset($_POST['opt_list'][$_POST['del']]);
	}

	/* append list entry */
	if (isset($_POST['btn_submit'], $_POST['opt'])) {
		$_POST['opt_list'][] = $_POST['opt'];
	}

	if (isset($_POST['go'])) {
		if (empty($_POST['opt_list'])) {
			exit('<html><script>window.close();</script></html>');
		}
		list($list_tag, $list_type) = explode(':', trim($_POST['tp']), 2);

		$tag = '[LIST TYPE='.$list_type.']\n';
		foreach ($_POST['opt_list'] as $o) {
			$tag .= '[*]'.addslashes($o).'\n';
		}
		$tag .= '[/LIST]';

		echo '<html><script>';
		readfile('lib.js');
		echo "\n\n".'insertParentTag(\''.$tag.'\', \' \'); window.close();</script></html>';

		exit;
	}

/*{POST_HTML_PHP}*/

	$tp_select_data = tmpl_draw_select_opt("OL:1\nOL:a\nUL:square\nUL:disc\nUL:circle", "{TEMPLATE: mklist_numerical}\n{TEMPLATE: mklist_aplha}\n{TEMPLATE: mklist_square}\n{TEMPLATE: mklist_disc}\n{TEMPLATE: mklist_circle}", (isset($_POST['tp']) ? $_POST['tp'] : (isset($_GET['tp']) ? $_GET['tp'] : '')), '{TEMPLATE: sel_opt}', '{TEMPLATE: sel_opt_selected}');
	if (!empty($_POST['opt_list'])) {
		list($list_tag, $list_type) = explode(':', trim($_POST['tp']), 2);
		$list_entry_data = '';
		foreach ($_POST['opt_list'] as $k => $op) {
			$list_entry_data .= '{TEMPLATE: list_entry}';
		}
		$list_sample = '{TEMPLATE: list_sample}';
	} else {
		$list_sample = '';
	}

/*{POST_PAGE_PHP_CODE}*/
?>
{TEMPLATE: MKLIST_PAGE}