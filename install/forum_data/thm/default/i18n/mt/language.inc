<?php
/**
* copyright            : (C) 2001-2010 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: language.inc 4898 2010-01-25 21:30:30Z naudefj $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; version 2 of the License.
**/

/* Plural form transformation for Maltese (Malti) messages.
 * Derived from http://svn.wikimedia.org/viewvc/mediawiki/trunk/phase3/languages/classes/LanguageMt.php
 */
function convertPlural($count, $forms) {
	if ( !count($forms) ) { return ''; }

	// Pads array to requested amound of forms by copying the last one.
	while (count($forms) < 4) {
		$forms[] = $forms[count($forms)-1];
	}

	if ($count == 1) {
		$index = 0;
	} elseif ( $count == 0 || ( $count % 100 > 1 && $count % 100 < 11 ) ) {
		$index = 1;
	} elseif ( $count % 100 > 10 && $count % 100 < 20 ) {
		$index = 2;
	} else {
		$index = 3;
	}
	return $forms[$index];
}
