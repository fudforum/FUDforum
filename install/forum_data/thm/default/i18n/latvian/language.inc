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

/* Plural form transformation for Latvian messages.
 * Derived from http://svn.wikimedia.org/viewvc/mediawiki/trunk/phase3/languages/classes/LanguageLv.php
 */
function convertPlural($count, $forms) {
        if ( !count($forms) ) { return ''; }

	// Pads array to requested amound of forms by copying the last one.
	while (count($forms) < 2) {
		$forms[] = $forms[count($forms)-1];
	}

        return ( ( $count % 10 == 1 ) && ( $count % 100 != 11 ) ) ? $forms[0] : $forms[1];
}
