<?php
/**
* copyright            : (C) 2001-2009 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id$
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; version 2 of the License.
**/

/* Plural form transformation for Egyptian Spoken Arabic messages.
 * Derived from http://svn.wikimedia.org/viewvc/mediawiki/trunk/phase3/languages/classes/LanguageAr.php
 */
function convertPlural($count, $forms) {
	if ( !count($forms) ) { return ''; }

	// Pads array to requested amound of forms by copying the last one.
	while (count($forms) < 6) {
		$forms[] = $forms[count($forms)-1];
	}

	if ($count == 0) {
		$index = 0;
	} elseif ($count == 1) {
		$index = 1;
	} elseif ($count == 2) {
		$index = 2;
	} elseif ($count % 100 >= 3 && $count % 100 <= 10) {
		$index = 3;
	} elseif ($count % 100 >= 11 && $count % 100 <= 99) {
		$index = 4;
	} else {
		$index = 5;
	}
	return $forms[$index];
}
