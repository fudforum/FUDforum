<?php
/***************************************************************************
* copyright            : (C) 2001-2004 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: rst.inc.t,v 1.7 2004/01/04 16:38:27 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; either version 2 of the License, or
* (at your option) any later version.
***************************************************************************/

/* needed by admuser.php, so that password resets can be sent in the appropriate languge */

$GLOBALS['register_conf_subject']       = '{TEMPLATE: rst_register_conf_subject}';
$GLOBALS['reset_newpass_title']         = '{TEMPLATE: rst_newpass_title}';
$GLOBALS['reset_confirmation']          = '{TEMPLATE: rst_confirmation}';
$GLOBALS['reset_reset']                 = '{TEMPLATE: rst_reset}';
?>