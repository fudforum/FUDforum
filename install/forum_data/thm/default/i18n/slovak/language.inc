<?php
/**
* copyright            : (C) 2001-2009 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: language.inc,v 1.1 2009/08/26 17:57:08 frank Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; version 2 of the License.
**/

/* Plural form transformation for Slovak messages.
 * Derived from http://svn.wikimedia.org/viewvc/mediawiki/trunk/phase3/languages/classes/LanguageSk.php
 */
function convertPlural( $count, $forms ) {
	if ( !count($forms) ) { return ''; }

	// Pads array to requested amound of forms by copying the last one.
	while (count($forms) < 3) {
		$forms[] = $forms[count($forms)-1];
	}

	if ( $count == 1 ) {
		$index = 0;
	} elseif ( $count == 2 || $count == 3 || $count == 4 ) {
		$index = 1;
	} else {
		$index = 2;
	}
	return $forms[$index];
}

