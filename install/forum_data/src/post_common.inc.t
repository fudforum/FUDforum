<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: post_common.inc.t,v 1.6 2003/05/28 15:07:16 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

function draw_post_smiley_cntrl()
{
	$c = uq('SELECT code, descr, img FROM {SQL_TABLE_PREFIX}smiley ORDER BY vieworder LIMIT '.$GLOBALS['MAX_SMILIES_SHOWN']);
	$data = '';
	while ($r = db_rowarr($c)) {
		$r[0] = ($a = strpos($r[0], '~')) ? substr($r[0], 0, $a) : $r[0];
		$data .= '{TEMPLATE: post_smiley_entry}';
	}
	qf($c);

	return ($data ? '{TEMPLATE: post_smilies}' : '');
}

function draw_post_icons($msg_icon)
{
	$tmp = $data = '';
	$allowed_ext = array('.jpg' => 1, '.png' => 1, '.jpeg' => 1, '.gif' => 1);
	$p = -1;
	$rl = (int) $GLOBALS['POST_ICONS_PER_ROW'];

	$none_checked = !$msg_icon ? ' checked' : '';

	if ($d = opendir($GLOBALS['WWW_ROOT_DISK'] . 'images/message_icons')) {
		readdir($d); readdir($d);
		while ($f = readdir($d)) {
			if (strlen($f) < 4 || !isset($allowed_ext[strtolower(strrchr($f, '.'))])) {
				continue;
			}
			if (++$p > $rl) {
				$data .= '{TEMPLATE: post_icon_row}';
				$tmp = ''; $p = 0;
			}
			$checked = $f == $msg_icon ? ' checked' : '';
			$tmp .= '{TEMPLATE: post_icon_entry}';
		}
		closedir($d);
		if ($tmp) {
			$data .= '{TEMPLATE: post_icon_row}';
		}
	}

	return ($data ? '{TEMPLATE: post_icons}' : '');
}

function draw_post_attachments($al, $max_as, $max_a, $attach_control_error)
{
	$attached_files = '';
	$i = 0;

	if (!empty($al) && count($al)) {
		$enc = base64_encode(@serialize($al));
		$c = uq('SELECT a.id,a.fsize,a.original_name,m.mime_hdr FROM {SQL_TABLE_PREFIX}attach a LEFT JOIN {SQL_TABLE_PREFIX}mime m ON a.mime_type=m.id WHERE a.id IN('.implode(',', $al).')');
		while ($r = db_rowarr($c)) {
			$sz = ( $r[1] < 100000 ) ? number_format($r[1]/1024,2).'KB' : number_format($r[1]/1048576,2).'MB';
			$insert_uploaded_image = strncasecmp('image/', $r[3], 6) ? '' : '{TEMPLATE: insert_uploaded_image}';
			$attached_files .= '{TEMPLATE: attached_file}';
			$i++;
		}
		qf($c);
	}

	if ($i) {
		$attachment_list = '{TEMPLATE: attachment_list}';
		$attached_status = '{TEMPLATE: attached_status}';
	} else {
		$attached_status = $attachment_list = '';
	}

	$upload_file = (($i + 1) <= $max_a) ? '{TEMPLATE: upload_file}' : '';

	include $GLOBALS['FORUM_SETTINGS_PATH'] . 'file_filter_regexp';
	if (!count($GLOBALS['__FUD_EXT_FILER__'])) {
		$allowed_extensions = '{TEMPLATE: post_proc_all_ext_allowed}';
	} else {
		$allowed_extensions = implode(' ', $GLOBALS['__FUD_EXT_FILER__']);
	}

	return '{TEMPLATE: file_attachments}';
}
?>