<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: help_index.php.t,v 1.3 2003/04/20 10:45:19 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

/*{PRE_HTML_PHP}*/
	
	$section = isset($_GET['section']) ? $_GET['section'] : '';
	switch ($section) {
		case 'usermaintance':
		case 'boardusage':
		case 'readingposting':
			$file = '{THEME_ROOT_DISK}/help/' . $section . '.hlp';
			$return_top = '{TEMPLATE: return_top}';
			break;
		default:
			$file = '{THEME_ROOT_DISK}/help/faq_index.hlp';
			$return_top = '';
	}
	
	ses_update_status($usr->sid, '{TEMPLATE: help_index_update}');
	$TITLE_EXTRA = ': {TEMPLATE: help_title}';

/*{POST_HTML_PHP}*/
	
	$str = file_get_contents($file);
	
	$tt_len = strlen('TOPIC_TITLE:');
	$th_len = strlen('TOPIC_HELP:');
	$help_section_data = '';
	while (($str = strstr($str, 'TOPIC_TITLE:')) !== FALSE) {
		$end_of = strpos($str, "\n");
		$topic_title = substr($str, $tt_len, $end_of-$tt_len);
		$str = strstr($str, 'TOPIC_HELP:');
		$str = substr($str, $th_len);
		$end_of_str = strstr($str, 'TOPIC_TITLE:');
		$topic_help = substr($str, 0, strlen($str)-strlen($end_of_str));
		$str = $end_of_str;
		$topic_help = str_replace('%_rsid%', _rsid, $topic_help);
		$help_section_data .= '{TEMPLATE: help_section}';
	}
/*{POST_PAGE_PHP_CODE}*/
?>
{TEMPLATE: HELP_PAGE}