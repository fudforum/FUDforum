<?php
/**
* copyright            : (C) 2001-2010 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id$
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; version 2 of the License.
**/

/* Plural form transformation for Macedonian messages.
 * Derived from http://svn.wikimedia.org/viewvc/mediawiki/trunk/phase3/languages/classes/LanguageMk.php
 */
function convertPlural($count, $forms) {
	if ( !count($forms) ) { return ''; }

	// Pads array to requested amound of forms by copying the last one.
	while (count($forms) < 2) {
		$forms[] = $forms[count($forms)-1];
	}

	if ( $count % 10 === 1 && $count % 100 !== 11 ) {
		return $forms[0];
	} else {
		return $forms[1];
	}
}
