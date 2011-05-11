<?php
/**
* copyright            : (C) 2001-2011 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: footer.php 5075 2010-11-15 17:59:45Z naudefj $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; version 2 of the License.
**/

	if (!isset($FORUM_TITLE, $DATA_DIR)) die('Go away!');	// Hacking attempt?
	if (defined('shell_script') ) return;	// Command line execution.

	if (!defined('popup')) {	/* Normal pages only. */
?>
<br />
<div id="wikilink" style="text-align:right;">
<script type="text/javascript">
/* <![CDATA[ */
$(function() {

	// Collapsabile fieldsets.
	$(function(){
		$('legend').click(function(){
			$(this).siblings().slideToggle("fast");
			$(this).parent().toggleClass("collapsed");
		});
	});

	// Start TimeAgo plugin.
 	// $("time").timeago();

	// jQuery UI Buttons.
	$("button, input:submit, #button").button({
                icons: { primary: "ui-icon-gear", secondary: "ui-icon-triangle-1-s" }
    });

	// Make tables sortable.
	$('.resulttable').tablesorter();

	// Focus on the first input element.
	if (window.location.hash.length <= 0) {	// But not if the URL has an anchor.
		$(':text:visible:enabled:first').focus();
	}

	// Add context sensitive 'Help' links to the Wiki.
	var wikilink = $('H2').first().text();
	if (wikilink.length > 0) {
		$('#wikilink').append('[ <a href="http://cvs.prohost.org/index.php/'+ wikilink +'" title="Context sensitive help (FUDforum wiki)">Help</a> ]');
	}

	// Add code for dismissable DIV boxes.
	$('.dismiss').prepend('<span style="float:right;">&nbsp;[ <a href="javascript://" onclick="$(this).parents(\'.dismiss\').hide(\'slow\');" title="Dismiss!">X</a> ]</span>');

	// Open external links in new windows.
	$('a[href^="http://"]').attr({ target: "_blank", title: "Opens in a new window!" });
});
/* ]]> */
</script>
</div>
<?php } /* Normal & popup pages. */ ?>
<br />
</td>
</tr>
</table>
</body>
</html>
