<?php
/**
* copyright            : (C) 2001-2009 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: language.inc,v 1.1 2009/08/26 17:57:07 frank Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; version 2 of the License.
**/

/* Plural form transformation for Lithuanian messages.
 * Derived from http://svn.wikimedia.org/viewvc/mediawiki/trunk/phase3/languages/classes/LanguageLt.php
 */
function convertPlural($count, $forms) {
	if ( !count($forms) ) { return ''; }

	//if no number with word, then use $form[0] for singular and $form[1] for plural or zero
	if( count($forms) === 2 ) return $count == 1 ? $forms[0] : $forms[1];

	// Pads array to requested amound of forms by copying the last one.
	while (count($forms) < 3) {
		$forms[] = $forms[count($forms)-1];
	}

	if ($count%10==1 && $count%100!=11) return $forms[0];
	if ($count%10>=2 && ($count%100<10 || $count%100>=20)) return $forms[1];
	return $forms[2];
}
