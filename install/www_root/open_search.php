<?php
/***************************************************************************
* copyright            : (C) 2001-2006 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: open_search.php,v 1.1 2006/10/11 22:31:41 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it 
* under the terms of the GNU General Public License as published by the 
* Free Software Foundation; version 2 of the License.
***************************************************************************/

require "./GLOBALS.php";
header("Content-Type: text/xml; charset=UTF-8");
?>
<?xml version="1.0" encoding="UTF-8"?> 
<OpenSearchDescription xmlns="http://a9.com/-/spec/opensearch/1.1/"> 
	<ShortName><?php echo htmlentities($FORUM_TITLE); ?> Search</ShortName> 
	<Description>Search <?php echo htmlentities($FORUM_TITLE); ?> Messages</Description> 
	<Image width="16" height="16" type="image/vnd.microsoft.icon">/favicon.ico</Image> 
	<Url type="text/html" template="<?php echo $WWW_ROOT; ?>index.php?t=search&amp;srch={searchTerms}&amp;eld=all" method="get"/> 
</OpenSearchDescription>
