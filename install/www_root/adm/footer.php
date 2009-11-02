<?php
	if (!isset($FORUM_TITLE, $DATA_DIR)) die('Go away!');	// Hacking attempt?
	if (defined('shell_script') ) return;	// Command line execution.
?>
<br />
<div style="text-align:right;">
<script type="text/javascript">
/* <![CDATA[ */
var wikilink = document.getElementsByTagName('H2').item(0).firstChild.data;
if (wikilink.length > 0) {
	document.write('[ <a href="http://cvs.prohost.org/index.php/'+
	wikilink+
	'" title="Context sensitive help (FUDforum wiki)">Help</a> ]');
}
/* ]]> */
</script>
</div>
</td>
</tr>
</table>
</body>
</html>
