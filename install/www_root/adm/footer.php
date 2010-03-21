<?php
	if (!isset($FORUM_TITLE, $DATA_DIR)) die('Go away!');	// Hacking attempt?
	if (defined('shell_script') ) return;	// Command line execution.

	if (!defined('popup')) {	/* Normal pages only. */
?>
<br />
<div id="wikilink" style="text-align:right;">
<script type="text/javascript">
/* <![CDATA[ */
$(document).ready(function() {
	// Make tables sortable.
	$('.resulttable').tablesorter();

	// Focus on the first input element.
	$(':text:visible:enabled:first').focus();

	// Inject context sensitive 'Help' links to the Wiki.
	var wikilink = $('H2').first().text();
	if (wikilink.length > 0) {
		$('#wikilink').append('[ <a href="http://cvs.prohost.org/index.php/'+ wikilink +'" title="Context sensitive help (FUDforum wiki)">Help</a> ]');
	}
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
