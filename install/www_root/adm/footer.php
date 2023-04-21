<?php
/**
* copyright            : (C) 2001-2023 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id$
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
<script>
jQuery(function() {

	// Collapsabile fieldsets.
	jQuery(function(){
		jQuery('legend').click(function(){
			jQuery(this).siblings().slideToggle("fast");
			jQuery(this).parent().toggleClass("collapsed");
		});
	});
	
	// Start TimeAgo plugin.
 	// jQuery("time").timeago();

	// jQuery UI Buttons.
	jQuery("button, input:submit, #button").button({
		icons: { primary: "ui-icon-gear", secondary: "ui-icon-triangle-1-s" }
	});

	<?php if (defined('__adm_rsidl')) { ?>
	// jQuery UI Sortable: Drag and drop to reorder items.
	jQuery(function() {
		jQuery("#sortable").sortable({
			opacity: 0.6, 
			cursor: 'move',
			update: function() {
				var order = jQuery("#sortable").sortable("serialize") + '&ajax=reorder&<?php echo __adm_rsidl ?>';
				jQuery.ajax({type: 'post', url: self.location, data: order,
					complete: function(request) { $('#dialogContent').html(request.responseText);
								      $('#dialogHolder').dialog({ autoOpen: true, title: 'Move request', modal: true});
								    },
				})
			}
		});
	});
	<?php } ?>

	// Make tables sortable.
	jQuery('.resulttable').tablesorter();

	// Implement jQuery keepAlive plugin from https://github.com/ocombe/jQuery-keepAlive 
	$.fn.keepAlive({url: "keepAlive.php", timer: 60000});  // 1 min

	// Focus on the first input element.
	if (window.location.hash.length <= 0) {	// But not if the URL has an anchor.
		jQuery(':text:visible:enabled:first').focus();
	}

	// Add context sensitive 'Help' links to the Wiki.
	var wikilink = jQuery('H2').first().text();
	if (wikilink.length > 0) {
		jQuery('#wikilink').append('[ <a href="http://cvs.prohost.org/index.php?title='+ wikilink +'" title="Context sensitive help (FUDforum wiki)">Help</a> ]');
	}

	// Add code for dismissable DIV boxes.
	jQuery('.dismiss').prepend('<span style="float:right;">&nbsp;[ <a href="javascript://" onclick="jQuery(this).parents(\'.dismiss\').hide(\'slow\');" title="Dismiss!">X</a> ]</span>');

	// Open external links in new windows.
	jQuery('a[href^="http://"], a[href^=https://]').attr({ target: "_blank", title: "Opens in a new window!" });
});
</script>
</div>
<?php } /* Normal & pop-up pages. */ ?>
<br />
</td>
</tr>
</table>
<?php if (defined('plugins')) plugin_call_hook('ACP_FOOTER'); ?>
</body>
</html>
