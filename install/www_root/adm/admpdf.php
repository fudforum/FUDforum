<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: admpdf.php,v 1.3 2003/05/20 15:16:09 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

	require('GLOBALS.php');
	fud_use('adm.inc', true);
	fud_use('glob.inc', true);
	fud_use('widgets.inc', true);
	fud_use('draw_select_opt.inc');
	require($DATA_DIR . 'include/PDF.php');

function print_yn_field($descr, $help, $field)
{
	$str = !isset($GLOBALS[$field]) ? 'N' : $GLOBALS[$field];
	echo '<tr bgcolor="#bff8ff"><td>'.$descr.': <br><font size="-1">'.$help.'</font></td><td valign="top">'.create_select('CF_'.$field, "Yes\nNo", "Y\nN", $str).'</td></tr>';
}
	
function print_string_field($descr, $help, $field, $is_int=0)
{
	if (!isset($GLOBALS[$field])) {
		$str = !$is_int ? '' : '0';
	} else {
		$str = !$is_int ? htmlspecialchars($GLOBALS[$field]) : (int)$GLOBALS[$field];
	}
	echo '<tr bgcolor="#bff8ff"><td>'.$descr.': <br><font size="-1">'.$help.'</td><td valign="top"><input type="text" name="CF_'.$field.'" value="'.$str.'"></td></tr>';
}


	if (isset($_POST['form_posted'])) {
		foreach ($_POST as $k => $v) {
			if (strncmp($k, 'CF_', 3)) {
				continue;
			}
			$k = substr($k, 3);
			if (!isset($GLOBALS[$k]) || $GLOBALS[$k] != $v) {
				$ch_list[$k] = $v;
			}
		}
		if (isset($ch_list)) {
			change_global_settings($ch_list, 'PDF.php');
			/* put the settings 'live' so they can be seen on the form */
			foreach ($ch_list as $k => $v) {
				$GLOBALS[$k] = $v;
			}
		}
	}

	require($WWW_ROOT_DISK . 'adm/admpanel.php'); 

	/* bail out if pdf extension is not avaliable */
	if (!extension_loaded('pdf')) {
		echo 'Your PHP was not compiled with PDF extension support, therefor this feature is not avaliable.';
		require($WWW_ROOT_DISK . 'adm/admclose.html');
		exit;
	}

	$rdf_url = $WWW_ROOT . 'pdf.php';
?>
<h2>PDF Output Configuration</h2>
<form method="post" action="admpdf.php">
<table border=0 cellspacing=1 cellpadding=3>
<?php
	print_yn_field('PDF Output Enabled', 'Whether or not to enable PDF output', 'PDF_ENABLED');
	print_yn_field('Complete Forum Output', 'Whether or not to allow users to generate a PDF containing ALL the messages in a particular forum.', 'PDF_ALLOW_FULL');

	$opts = "A0\nA1\nA2\nA3\nA4\nA5\nA6\nB5\nletter\nlegal\nledger";
	$names = "A0: 2380 x 3368\nA1: 1684 x 2380\nA2: 1190 x 1684\nA3: 842 x 1190\nA4: 595 x 842\nA5: 421 x 595\nA6: 297 x 421\nB5: 501 x 709\nletter: 612 x 792\nlegal: 612 x 1008\nledger: 1224 x 792";

	$sel = create_select('CF_PDF_PAGE', $names, $opts, $PDF_PAGE);
	echo '<tr bgcolor="#bff8ff"><td>Page Dimensions: <br><font size="-1">The sizes are in points, each point is 1/72 of an inch.</font></td><td valign="top">'.$sel.'</td></tr>';

	print_string_field('Horizontal Margin', 'Number of pixels to reserve for white space at the right &amp; left side of the page.', 'PDF_WMARGIN');
	print_string_field('Vertical Margin', 'Number of pixels to reserve for white space at the top &amp; bottom of the page.', 'PDF_HMARGIN');
	print_string_field('Maximum CPU Time', 'PDF generation process can be quite intensive, this setting allows you to limit the number of seconds PHP script may spend trying to generate the pdf.', 'PDF_MAX_CPU');
?>
<tr bgcolor="#bff8ff"><td colspan=2 align=right><input type="submit" name="btn_submit" value="Change Settings"></td></tr>
</table>
<input type="hidden" name="form_posted" value="1">
</form>
<br>
<table border=0 cellspacing=1 cellpadding=3>
<tr><th><b>Quick PDF Tutorial</b></th></tr>
<tr><td bgcolor="#fffee5">
If enabled, this feature will allow forum visitors to generate PDF files based on the forum data for easy printing and other uses.<br />
This facility supports 3 data retrieval modes, messages, topics & entire forums.<br />
<b>Examples:</b>
<blockquote>
	<a href="<?php echo $rdf_url; ?>?frm=1"><?php echo $rdf_url; ?>?frm=1</a> will generate a pdf with all the messages from forum with an id of 1.<br />
	<a href="<?php echo $rdf_url; ?>?frm=1&page=3"><?php echo $rdf_url; ?>?frm=1&page=3</a> will generate a pdf with all the messages from forum with an id of 1, which can be found on page 3.<br />
	<a href="<?php echo $rdf_url; ?>?thread=1"><?php echo $rdf_url; ?>?thread=1</a> will generate a pdf with all the messages from topic with an id of 1.<br />
	<a href="<?php echo $rdf_url; ?>?msg=1"><?php echo $rdf_url; ?>?msg=1</a> will generate a pdf contaning a message with an id of 1.<br />
</blockquote>
<?php require($WWW_ROOT_DISK . 'adm/admclose.html'); ?>