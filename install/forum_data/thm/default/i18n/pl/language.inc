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

/* Plural form transformation for Polish messages.
 * Derived from http://svn.wikimedia.org/viewvc/mediawiki/trunk/phase3/languages/classes/LanguagePl.php
 */
function convertPlural( $count, $forms ) {
	if ( !count($forms) ) { return ''; }

	// Pads array to requested amound of forms by copying the last one.
	while (count($forms) < 3) {
		$forms[] = $forms[count($forms)-1];
	}

	$count = abs( $count );
	if ( $count == 1 )
		return $forms[0];     // singular
	switch ( $count % 10 ) {
		case 2:
		case 3:
		case 4:
			if ( $count / 10 % 10 != 1 )
				return $forms[1]; // plural
		default:
			return $forms[2];   // plural genitive
	}
}
