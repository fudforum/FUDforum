<?php
/***************************************************************************
* copyright            : (C) 2001-2010 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id$
*
* This program is free software; you can redistribute it and/or modify it 
* under the terms of the GNU General Public License as published by the 
* Free Software Foundation; version 2 of the License. 
***************************************************************************/

	/* WWWBoard (Version 2.0 ALPHA 2.1) - FUDforum Conversion script - Brief Instructions:
	 *
	 * If you intend to run this script via the console, make sure to UNLOCK the 
	 * FUDforum 1st. I recommend running this script via the web unless the forum
	 * you are importing is very large.
	 */

	/* Specify the FULL path to the WWWBoard messages directory */
	$WWWB_DIR = '/path/to/messages';

/**** DO NOT EDIT BEYOND THIS POINT ****/
	
	set_time_limit(-1);
	ini_set('memory_limit', '128M');
	ini_set('display_errors', 1);
	// error_reporting(E_ALL);

	define('__WEB__', (isset($_SERVER['REMOTE_ADDR']) === FALSE ? 0 : 1));
	$start = 1;
	if ( isset($_GET['start']) ) {
		$start = (int)$_GET['start'];
	}
	print_msg('Start loading WWWBoard at message # '. $start);

	/* Prevent session initialization. */
	define('forum_debug', 1);
	unset($_SERVER['REMOTE_ADDR']);

	$gl = @include('./GLOBALS.php');
	if ($gl === FALSE) {
		print_msg('This script must be placed in FUDforum\'s main web directory.');
		exit;
	}

	if ($FUD_OPT_2 & 8388608 && !__WEB__) {
		print_msg('Since you are running conversion script via the console you must UNLOCK forum\'s files first.');
		exit;
	}

	if (!is_dir("$WWWB_DIR/") ) {
		print_msg('ERROR: Invalid WWWBoard messages directory ('. $WWWB_DIR .').');
		exit;	
	}

	if (!is_readable("$WWWB_DIR")) {
		print_msg('ERROR: Unable to read from WWWBoard messages directory ('. $WWWB_DIR .').');
		exit;	
	}

	/* Include all the necessary FUDforum includes. */
	fud_use('err.inc');
	fud_use('db.inc');
	fud_use('users.inc');
	fud_use('users_reg.inc');
	fud_use('replace.inc');
	fud_use('smiley.inc');
	fud_use('post_proc.inc');
	fud_use('wordwrap.inc');
	fud_use('cat.inc', true);
	fud_use('groups.inc');
	fud_use('imsg_edt.inc');
	fud_use('th.inc');
	fud_use('th_adm.inc');
	fud_use('rev_fmt.inc');
	fud_use('fileio.inc');
	fud_use('isearch.inc');
	fud_use('attach.inc');
	fud_use('ipoll.inc');
	fud_use('private.inc');
	fud_use('forum_adm.inc', true);
	fud_use('groups_adm.inc', true);
	fud_use('glob.inc', true);

	/* Get a list of all WWWBoard messages. */
	$files = array();
	$d = opendir("$WWWB_DIR");
	while (($f = readdir($d))) {
		if (preg_match('!\d+\.html?!i', $f)) {
			$files[ preg_replace('!\.html?$!i', '', $f) ] = $WWWB_DIR .'/'. $f;
		}
	}
	closedir($d);
	ksort($files, SORT_NUMERIC);

	/* Create a destination forum & category. */
	$_POST = array('cat_name' => 'WWWBoard', 'cat_cat_opt' => 3, 'cat_description' => 'Imported messages');
	$cat = new fud_cat;
	$cat->add(0);

	$_POST = array('frm_cat_id' => $cat->id, 'frm_name' => 'WWWBoard', 'frm_descr' => 'Your WWWBoard messages', 'frm_forum_opt' => 16);
	$frm = new fud_forum;
	$frm->add(0);

	rebuild_forum_cat_order();

	/* Begin the import process. */

	/* Common settings for all users. */
	$usr = new fud_user_reg;
	$usr->plaintext_passwd = rand();
	// $usr->users_opt = -1;
	$usr->users_opt = 4357110;
	$usr->lang = 'en';

	$m = new fud_msg_edit;
	$m->msg_opt = 0;

	foreach ($files as $k => $f) {
		print_msg('<hr>Loading message ['. $k .'] from file ['. $f .']');
		
		if ( filesize("$f") == 0 ) {
			print_msg('WARNING: Skip empty file...');
			continue;
		}

		/* Initialize message variables. */
		$msg = array('id' => $k);
		$msg['ipaddr'] = '0.0.0.0';
		$msg['body'] = '';

		/* Read message contents. */
		$data = file_get_contents($f);
		$data = preg_replace('/<\/p>/i', '', $data);
		
		/* Fetch subject. */
		$p = strpos($data, '<title>') + strlen('<title>');
		$msg['subject'] = substr($data, $p, (strpos($data, '</title>', $p) - $p));
		print_msg('SUBJECT=[' . $msg['subject'] . ']');

		/* Try to fetch the user. */
		// Ex: Posted by Fred (p114.vivendi.net) on August 21, 2000 at 05:30:14:<p>
		if (preg_match('!Posted by\s+<a href="mailto!i', $data)) {
			$p = stripos($data, 'Posted by ');
			$tmp = substr($data, $p, (stripos($data, ':<p>', $p) - $p));
			$x = preg_match('!Posted by\s+\<a href="mailto:([^"]+)"\>(.*)</a> [\(\[](.+)[\)\]] on (.*)!i', $tmp, $res);
			if ( $x == 0 ) {
				preg_match('!Posted by\s+\<a href="mailto:([^"]+)"\>(.*)</a> on (.*)!i', $tmp, $res);
				$res[4] = $res[3];
				$res[3] = '0.0.0.0';
			}
			$msg['email'] = $res[1];
			$msg['user'] = $res[2];
			$msg['ipaddr'] = gethostbyname($res[3]);
			$msg['time'] = normalize_date($res[4]);
		// Link is not an E-mail id.
		} elseif (($p = strpos($data, 'Posted by <a href="')) !== false) {
			$tmp = substr($data, $p, (stripos($data, ":<p>", $p) - $p));
			$x = preg_match('!Posted by \<a href="([^"]+)"\>(.*)</a> [\(\[](.+)[\)\]] on (.*)!', $tmp, $res);
			$msg['email'] = '';
			$msg['user'] = $res[2];
			$msg['time'] = normalize_date($res[4]);
		// No E-Mail or link.
		} elseif (($p = strpos($data, 'Posted by ')) !== false) {
			$tmp = substr($data, $p, (stripos($data, ':<p>', $p) - $p));
			$x = preg_match('!Posted by (.*) [\(\[](.+)[\)\]] on (.*)!i', $tmp, $res);
			if ( $x == 0 ) {
				preg_match('!Posted by (.*) on (.*)!i', $tmp, $res);
				$res[3] = $res[2];
				$res[2] = '0.0.0.0';
			}
			$msg['email'] = '';
			$msg['user'] = $res[1];
			$msg['ipaddr'] = gethostbyname($res[2]);
			$msg['time'] = normalize_date($res[3]);
		} else { /* We still need date of post and 'anon' user. */
			print_msg('ERROR: Unknown message format.');
			exit;
		}
		print_msg('USER=['. $msg['user'] .']');
		print_msg('IPADDR=['. $msg['ipaddr'] .']');
		print_msg('TIME=['. $msg['time'] .']');

		/* Some E-Mail cleanup. */
		$msg['email'] = str_replace("[at]", '@', $msg['email']);
		$msg['email'] = str_replace("[dot]", '.', $msg['email']);
		$msg['email'] = str_replace("_at_", '@', $msg['email']);
		$msg['email'] = str_replace("nospam.", '', $msg['email']);
		print_msg("EMAIL=[" . $msg['email'] . "]");

		/* Fetch reply info. */
		if (($p = strpos($data, 'In Reply to: <a href="')) !== false) {
			$p += strlen('In Reply to: <a href="');
			$msg['reply_to'] = (int) substr($data, $p, (strpos($data, '.', $p) - $p));
			print_msg("REPLYTO=[" . $msg['reply_to'] . "]");
		}

		/* Fetch message body. */
		$p = strpos($data, ':<p>', $p) + strlen(':<p>');
		$e = strpos($data, '<br><hr size=7 width=75%><p>', $p);
		if ( $e == false ) $e = stripos($data, '<br><hr size=5 width=90%><p>', $p);
		if ( $e == false ) $e = stripos($data, '<BR><HR WIDTH="90%" SIZE="7"><P>', $p);
		if ( $e == false ) $e = stripos($data, '<hr size=', $p);
		if ( $e == false ) $e = stripos($data, '<hr width=', $p);
		$msg['body'] = str_replace(array('<br>', '<p>'), array("\n", "\n\n"), strip_tags(substr($data, $p, ($e - $p)), '<a><br><p>'));
		if (strpos($msg['body'], '<a href="') !== false) {
			$msg['body'] = preg_replace('!<a href="([^"]+)">([^>]+)</a>!', '[url=\1]\2[/url]', $msg['body']);
		}
		$msg['body'] = html2bb($msg['body']);
		$msg['body'] = trim($msg['body']);
		fud_wordwrap($msg['body']);
		// print_msg('DATA=['. substr($data, $p, ($e - $p)) .']');

		/* Try to identify the user and if possible create a new user. */
		if (isset($msg['email'])) {
			$id = q_singleval("SELECT id FROM {$DBHOST_TBL_PREFIX}users WHERE email='".addslashes($msg['email'])."'");
			if (!$id) {
				$id = (int) q_singleval("SELECT id FROM {$DBHOST_TBL_PREFIX}users WHERE login='".addslashes($msg['user'])."'");
			}
			if (!$id) { /* Add new user to DB. */
				$usr->login = $usr->name = $msg['user'];
				$usr->email = $msg['email'];
				$id = $usr->add_user();
			}
		} else {
			$id = (int) q_singleval("SELECT id FROM {$DBHOST_TBL_PREFIX}users WHERE login='".addslashes($msg['user'])."'");
		}

		$m->subject = htmlspecialchars($msg['subject']);
		$m->ip_addr = $msg['ipaddr'];
		$m->poster_id = $id;
		$m->post_stamp = $msg['time'];
		$m->body = tags_to_html($msg['body'], 0);

		if (isset($msg['reply_to']) && isset($ml[$msg['reply_to']])) {
			$m->reply_to = $ml[$msg['reply_to']][0];
			$m->thread_id = $ml[$msg['reply_to']][1];
		} else {
			$m->reply_to = $m->thread_id = 0;
		}

		if ( $k >= $start ) {
			try {
				// add($forum, $message_threshold, $forum_opt, 64|4096, 0, $tdescr);
				$m->add($frm->id, 0, 2, 0);
			} catch (Exception $e) {
				print_msg('<h1>'. $e->getMessage() .'</h1>');
			}
			print_msg('Message was successfully added.');
		} else {
			$m->id = 0;
			$m->thread_id = 0;
			$m->id = (int) q_singleval("SELECT id FROM {$DBHOST_TBL_PREFIX}msg WHERE poster_id='".$m->poster_id."' and post_stamp=".$m->post_stamp);
			$m->thread_id = (int) q_singleval("SELECT thread_id FROM {$DBHOST_TBL_PREFIX}msg WHERE poster_id='".$m->poster_id."' and post_stamp=".$m->post_stamp);
			print_msg('Got m-id as '. $m->id );
			print_msg('Got m-id2 as '. $m->thread_id);
			if ( $m->id == 0 || $m->thread_id == 0 ) {
				continue;
			}
		}

		// print_msg("SET FUD REPLYTO=[" . $m->id . "]");
		// print_msg("SET FUD THREAD=[" . $m->thread_id . "]");
		$ml[$msg['id']] = array($m->id, $m->thread_id);
		sleep(0);
	}

	print_msg("<hr>Conversion Process Complete!");
	print_msg("");
	print_msg("To complete the conversion process run the consistency checker at: {$WWW_ROOT}adm/consist.php");
	print_msg("You will need to login using the administrator account from the forum you've just imported.");	
	print_msg("");
	print_msg("If you want the imported messages to be searcheable, rebuild the search index from the admin control panel.");

/* Define functions. */

function print_msg($msg)
{
	if (__WEB__) {
		echo $msg ."<br />\n";	
	} else {
		echo $msg ."\n";
	}
}

function normalize_date($datestr) {
	if (($timestamp = strtotime($datestr)) === false) {
		preg_match('!([^ ]+) (.+), (.+) at (.+)!i', $datestr, $dat);
		// Fix bad Y2K years. Ex. 19101 should be 2001.
		if ((int)$dat[3] >= 19100) {
			$dat[3] = $dat[3] - 17100;
		}
		$timestamp = strtotime($dat[2] .' '. $dat[1] .' '. $dat[3] . $dat[4]);
	}
	return $timestamp;
}

function html2bb($html2bbtxt) {
	// Pre-formatted text.
	$pre = array();$i=0;
	while ($pre_str = stristr($html2bbtxt,'<pre>')) {
		$pre_str = substr($pre_str,0,strpos(strtolower($pre_str),'</pre>')+6);
		$html2bbtxt = str_replace($pre_str, "***pre_string***$i", $html2bbtxt);
		$pre_str = str_replace("\r\n","\n",$pre_str);
		$pre[$i] = str_replace("&#124;","|",$pre_str);
		$i++;
	}

	$html2bbtxt = str_replace('[', '***^***', $html2bbtxt);
	$html2bbtxt = str_replace(']', '**@^@**', $html2bbtxt);

	$html2bbtxt = str_replace('<b>', '[b]', $html2bbtxt);
	$html2bbtxt = str_replace('<B>', '[b]', $html2bbtxt);
	$html2bbtxt = str_replace('<strong>', '[b]', $html2bbtxt);
	$html2bbtxt = str_replace('<STRONG>', '[b]', $html2bbtxt);
	$html2bbtxt = str_replace('</b>', '[/b]', $html2bbtxt);
	$html2bbtxt = str_replace('</B>', '[/b]', $html2bbtxt);
	$html2bbtxt = str_replace('</strong>', '[/b]', $html2bbtxt);
	$html2bbtxt = str_replace('</STRONG>', '[/b]', $html2bbtxt);
	$html2bbtxt = str_replace('<i>', '[i]', $html2bbtxt);
	$html2bbtxt = str_replace('<I>', '[i]', $html2bbtxt);
	$html2bbtxt = str_replace('<em>', '[i]', $html2bbtxt);
	$html2bbtxt = str_replace('<EM>', '[i]', $html2bbtxt);
	$html2bbtxt = str_replace('</i>', '[/i]', $html2bbtxt);
	$html2bbtxt = str_replace('</I>', '[/i]', $html2bbtxt);
	$html2bbtxt = str_replace('</em>', '[/i]', $html2bbtxt);
	$html2bbtxt = str_replace('</EM>', '[/i]', $html2bbtxt);
	$html2bbtxt = str_replace('<sub>', '[sub]', $html2bbtxt);
	$html2bbtxt = str_replace('<SUB>', '[sub]', $html2bbtxt);
	$html2bbtxt = str_replace('</sub>', '[/sub]', $html2bbtxt);
	$html2bbtxt = str_replace('</SUB>', '[/sub]', $html2bbtxt);
	$html2bbtxt = str_replace('<sup>', '[sup]', $html2bbtxt);
	$html2bbtxt = str_replace('<SUP>', '[sup]', $html2bbtxt);
	$html2bbtxt = str_replace('</sup>', '[/sup]', $html2bbtxt);
	$html2bbtxt = str_replace('</SUP>', '[/sup]', $html2bbtxt);
	$html2bbtxt = str_replace('<tt>', '[i]', $html2bbtxt);
	$html2bbtxt = str_replace('<TT>', '[i]', $html2bbtxt);
	$html2bbtxt = str_replace('</tt>', '[/i]', $html2bbtxt);
	$html2bbtxt = str_replace('</TT>', '[/i]', $html2bbtxt);
	$html2bbtxt = str_replace('<u>', '[u]', $html2bbtxt);
	$html2bbtxt = str_replace('<U>', '[u]', $html2bbtxt);
	$html2bbtxt = str_replace('</u>', '[/u]', $html2bbtxt);
	$html2bbtxt = str_replace('</U>', '[/u]', $html2bbtxt);
	$html2bbtxt = str_replace('<big>', '[sup]', $html2bbtxt);
	$html2bbtxt = str_replace('</big>', '[/sup]', $html2bbtxt);
	$html2bbtxt = str_replace('<small>', '[sub]', $html2bbtxt);
	$html2bbtxt = str_replace('</small>', '[/sub]', $html2bbtxt);

	// Tables.
	$html2bbtxt = preg_replace("/<table([^>]*)>/is", "\n", $html2bbtxt);
	$html2bbtxt = preg_replace("/<\/table([^>]*)>/is", "\n", $html2bbtxt);
	$html2bbtxt = preg_replace("/<tbody([^>]*)>/is", '', $html2bbtxt);
	$html2bbtxt = preg_replace("/<\/tbody([^>]*)>/is", '', $html2bbtxt);
	$html2bbtxt = preg_replace("/<td([^>]*)>/is", ' - ', $html2bbtxt);
	$html2bbtxt = preg_replace("/<\/td([^>]*)>/is", ' - ', $html2bbtxt);
	$html2bbtxt = preg_replace("/<th([^>]*)>/is", ' - ', $html2bbtxt);
	$html2bbtxt = preg_replace("/<\/th([^>]*)>/is", ' - ', $html2bbtxt);
	$html2bbtxt = preg_replace("/<tr([^>]*)>/is", "\n", $html2bbtxt);
	$html2bbtxt = preg_replace("/<\/tr([^>]*)>/is", "\n", $html2bbtxt);

	$html2bbtxt = str_replace('<li>', "[*]", $html2bbtxt);
	$html2bbtxt = str_replace('<LI>', "[*]", $html2bbtxt);
	$html2bbtxt = str_replace('</li>', '', $html2bbtxt);
	$html2bbtxt = str_replace('</LI>', '', $html2bbtxt);
	$html2bbtxt = str_replace('<ul>', '[list]', $html2bbtxt);
	$html2bbtxt = str_replace('<UL>', '[list]', $html2bbtxt);
	$html2bbtxt = str_replace('</ul>', '[/list]', $html2bbtxt);
	$html2bbtxt = str_replace('</UL>', '[/list]', $html2bbtxt);

	// More stuff.
	$html2bbtxt = str_replace('<img border="0" src="', '[img]', $html2bbtxt);
	$html2bbtxt = str_replace('" alt="an image">', '[/img]', $html2bbtxt);
	$html2bbtxt = preg_replace("/<a href=([^>]+)>/is", "[url=\$1]", $html2bbtxt);
	$html2bbtxt = preg_replace("/<A HREF=([^>]+)>/is", "[url=\$1]", $html2bbtxt);
	$html2bbtxt = preg_replace("/<\/a>/is", "[/url]", $html2bbtxt);
	$html2bbtxt = preg_replace("/<\/A>/is", "[/url]", $html2bbtxt);

	$html2bbtxt = preg_replace("/<hr([^>]*)>/is", "\n----------------------------------------------------------------------\n", $html2bbtxt);
	$html2bbtxt = str_replace('<blockquote>', '[pre]', $html2bbtxt);
	$html2bbtxt = str_replace('</blockquote>', '[/pre]', $html2bbtxt);

	$html2bbtxt = preg_replace("/<font color=\"([^>\"]*?)\">(.*?)<\/font>/is", "[color=\\1]\\2[/color]", $html2bbtxt);
	$html2bbtxt = preg_replace("/<font color='([^>']*?)'>(.*?)<\/font>/is", "[color=\\1]\\2[/color]", $html2bbtxt);
	$html2bbtxt = preg_replace("/<font size=\"([^>\"]*?)\">(.*?)<\/font>/is", "[size=\\1]\\2[/size]", $html2bbtxt);
	$html2bbtxt = preg_replace("/<font size='([^>']*?)'>(.*?)<\/font>/is", "[size=\\1]\\2[/size]", $html2bbtxt);
	$html2bbtxt = preg_replace("/<font face=\"([^>\"]*?)\">(.*?)<\/font>/is", "[font=\\1]\\2[/font]", $html2bbtxt);
	$html2bbtxt = preg_replace("/<font face='([^>']*?)'>(.*?)<\/font>/is", "[font=\\1]\\2[/font]", $html2bbtxt);
	$html2bbtxt = preg_replace("/<h1([^>]*?)>(.*?)<\/h1>/is", "[size=7]\\2[/size]", $html2bbtxt);
	$html2bbtxt = preg_replace("/<h2([^>]*?)>(.*?)<\/h2>/is", "[size=6]\\2[/size]", $html2bbtxt);
	$html2bbtxt = preg_replace("/<h3([^>]*?)>(.*?)<\/h3>/is", "[size=5]\\2[/size]", $html2bbtxt);
	$html2bbtxt = preg_replace("/<h4([^>]*?)>(.*?)<\/h4>/is", "[size=4]\\2[/size]", $html2bbtxt);
	$html2bbtxt = preg_replace("/<h5([^>]*?)>(.*?)<\/h5>/is", "[size=3]\\2[/size]", $html2bbtxt);
	$html2bbtxt = preg_replace("/<h6([^>]*?)>(.*?)<\/h6>/is", "[size=2]\\2[/size]", $html2bbtxt);
	$html2bbtxt = preg_replace("/<h7([^>]*?)>(.*?)<\/h7>/is", "[size=1]\\2[/size]", $html2bbtxt);

	$html2bbtxt = preg_replace("/<code([^>]*?)>(.*?)<\/code>/is", "[i]\\2[/i]", $html2bbtxt);

	$html2bbtxt = preg_replace("/<font[^>.]*>/is", "", $html2bbtxt);
	$html2bbtxt = preg_replace("/<\/font[^>.]*>/is", "", $html2bbtxt);
	$html2bbtxt = preg_replace("/<span[^>.]*>/is", "", $html2bbtxt);
	$html2bbtxt = preg_replace("/<\/span[^>.]*>/is", "", $html2bbtxt);
    
	// The hypertext entities (ditto).
	$html2bbtxt = str_replace('&nbsp;', '[sp]', $html2bbtxt);
	$html2bbtxt = str_replace('&plusmn;', '±', $html2bbtxt);
	$html2bbtxt = str_replace('&deg;', '°', $html2bbtxt);
	$html2bbtxt = str_replace('&lt;', '[<]', $html2bbtxt);
	$html2bbtxt = str_replace('&gt;', '[>]', $html2bbtxt);
	$html2bbtxt = str_replace('&copy;', '©', $html2bbtxt);
	$html2bbtxt = str_replace('&reg;', '®', $html2bbtxt);
	$html2bbtxt = str_replace('&hellip;', '...', $html2bbtxt);
	$html2bbtxt = str_replace('&#124;', '|', $html2bbtxt);

	$html2bbtxt = str_replace('***^***', '[[', $html2bbtxt);
	$html2bbtxt = str_replace('**@^@**', ']]', $html2bbtxt);

	$cp = count($pre)-1; // It all hinges on simple arithmetic.
	for($i=0;$i <= $cp;$i++) {
		$html2bbtxt = str_replace("***pre_string***$i", '[code]'.substr($pre[$i],5,-6).'[/code]', $html2bbtxt);
	}

	return ($html2bbtxt);
}

function send_email() {
	print_msg('Send mock E-mail.');
}

?>
