<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: pdf.php.t,v 1.8 2003/05/27 17:13:13 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

class fud_pdf
{
	var $pdf, $pw, $ph, $pg_num, $pg_title, $hmargin, $wmargin, $y, $fonts;

	function fud_pdf($author, $title, $subject, $page_type='letter', $hmargin=15, $wmargin=15)
	{
		$this->pdf = pdf_new();
		pdf_open_file($this->pdf, '');
		pdf_set_info($this->pdf, 'Author',	$author);
		pdf_set_info($this->pdf, 'Title',	$title);
		pdf_set_info($this->pdf, 'Creator',	$author);
		pdf_set_info($this->pdf, 'Subject',	$subject);
		pdf_set_value($this->pdf, 'compress', 	9);

		switch ($page_type) {
			case 'A0':
				$this->pw = 2380;
				$this->ph = 3368;
				break;
			case 'A1':
				$this->pw = 1684;
				$this->ph = 2380;
				break;
			case 'A2':
				$this->pw = 1190;
				$this->ph = 1684;
				break;
			case 'A3':
				$this->pw = 842;
				$this->ph = 1190;
				break;
			case 'A4':
				$this->pw = 595;
				$this->ph = 842;
				break;
			case 'A5':
				$this->pw = 421;
				$this->ph = 595;
				break;
			case 'A6':
				$this->pw = 297;
				$this->ph = 421;
				break;
			case 'B5':
				$this->pw = 501;
				$this->ph = 709;
				break;
			case 'letter':
			default:
				$this->pw = 612;
				$this->ph = 792;
				break;
			case 'legal':
				$this->pw = 612;
				$this->ph = 1008;
				break;
			case 'ledger':
				$this->pw = 1224;
				$this->ph = 792;
				break;
		}

		$this->hmargin = $hmargin;
		$this->wmargin = $wmargin;

		$fonts = array('Courier', 'Courier-Bold', 'Helvetica-Bold', 'Helvetica');
		foreach ($fonts as $f) {
			$this->fonts[$f] = pdf_findfont($this->pdf, $f, 'host', FALSE);
		}
	}

	function begin_page($title)
	{
		pdf_begin_page($this->pdf, $this->pw, $this->ph);
		pdf_setlinewidth($this->pdf, 1);
		$ttl = $title;
		if ($this->pg_num) {
			$this->pg_num++;
			$ttl .= ' #'. $this->pg_num;
		} else {
			$this->pg_num = 1;
		}
		pdf_add_outline($this->pdf, $ttl);
		pdf_setfont($this->pdf, $this->fonts['Courier'], 12);
		pdf_set_text_pos($this->pdf, $this->wmargin, ($this->ph - $this->hmargin));
		$this->pg_title = $title;
	}

	function input_text($text)
	{
		pdf_setfont($this->pdf, $this->fonts['Courier'], 12);

		$max_cpl = pdf_stringwidth($this->pdf, 'w');
		$max_cpl = floor(($this->pw - 2 * $this->wmargin) / $max_cpl);

		foreach ($text as $line) {
			if (strlen($line) > $max_cpl) {
				$parts = explode("\n", wordwrap($line, $max_cpl, "\n", 1));
				$line = $parts[0];
				unset($parts[0]);
			}
			if (pdf_get_value($this->pdf, 'texty', 0) <= ($this->hmargin + 12)) {
				$this->end_page();
				$this->begin_page($this->pg_title);
			}
			pdf_continue_text($this->pdf, $line);
			if (isset($parts) && count($parts)) {
				foreach ($parts as $p) {
					if (pdf_get_value($this->pdf, 'texty', 0) <= ($this->hmargin + 12)) {
						$this->end_page();
						$this->begin_page($this->pg_title);
					}
					pdf_continue_text($this->pdf, $p);
				}
				unset($parts);
			}
		}
	}

	function end_page()
	{
		pdf_end_page($this->pdf);
	}

	function finish()
	{
		pdf_close($this->pdf);
		pdf_delete($this->pdf);
	}

	function draw_line()
	{
		$this->y = pdf_get_value($this->pdf, 'texty', 0) - 3;
		pdf_moveto($this->pdf, $this->wmargin, $this->y);
		pdf_lineto($this->pdf, ($this->pw - $this->wmargin), $this->y);
		pdf_stroke($this->pdf);
	}

	function add_link($url, $caption)
	{
		$oh = pdf_get_value($this->pdf, 'texty', 0);
		pdf_show($this->pdf, $caption);
		$y = pdf_get_value($this->pdf, 'texty', 0);
		$w = pdf_get_value($this->pdf, 'textx', 0);
		$ow = pdf_get_value($this->pdf, 'textx', 0) - pdf_stringwidth($this->pdf, $caption);

		pdf_set_border_style($this->pdf, 'dashed', 0);
		pdf_add_weblink($this->pdf, $ow, $oh, $w, ($oh + 12), $url);
	}

	function add_attacments($attch)
	{
		pdf_setfont($this->pdf, $this->fonts['Courier-Bold'], 20);
		pdf_continue_text($this->pdf, 'File Attachments');

		$this->draw_line();

		pdf_setfont($this->pdf, $this->fonts['Helvetica'], 14);
		$y = $this->y - 3;
		$i = 0;
		foreach ($attch as $a) {
			pdf_set_text_pos($this->pdf, $this->wmargin, $y);
			pdf_continue_text($this->pdf, ++$i . ') ');
			$this->add_link($GLOBALS['WWW_ROOT'] . 'index.php?t=getfile&id='.$a['id'], $a['name']);
			pdf_show($this->pdf, ', downloaded '.$a['nd'].' times');
			$y -= 17;
		}
	}

	function add_poll($name, $opts, $ttl_votes)
	{
		$this->y = pdf_get_value($this->pdf, 'texty', 0) - 3;

		pdf_set_text_pos($this->pdf, $this->wmargin, $this->y - 3);
		pdf_setfont($this->pdf, $this->fonts['Courier-Bold'], 20);
		pdf_continue_text($this->pdf, $name);
		pdf_setfont($this->pdf, $this->fonts['Courier-Bold'], 16);
		pdf_show($this->pdf, '(total votes: '.$ttl_votes.')');
		
		$this->draw_line();

		$ttl_w = round($this->pw * 0.66);
		$ttl_h = 20;
		$p1 = floor($ttl_w / 100);
		$this->y -= 10;
		/* avoid /0 warnings and safe to do, since we'd be multiplying 0 since there are no votes */
		if (!$ttl_votes) {
			$ttl_votes = 1;
		}

		pdf_setfont($this->pdf, $this->fonts['Helvetica-Bold'], 14);

		foreach ($opts as $o) {
			$w1 = $p1 * (($o['votes'] / $ttl_votes) * 100);
			$h1 = $this->y - $ttl_h;

			pdf_setcolor($this->pdf, 'both', 'rgb', 0.92, 0.92, 0.92);
			pdf_rect($this->pdf, $this->wmargin, $h1, $w1, $ttl_h);
			pdf_fill_stroke($this->pdf);
			pdf_setcolor($this->pdf, 'both', 'rgb', 0, 0, 0);
			pdf_show_xy($this->pdf, $o['name'] . "\t\t" . $o['votes'] . '/('.round(($o['votes'] / $ttl_votes) * 100).'%)', $this->wmargin + 2, $h1 + 3);
			$this->y = $h1 - 10; 
		}
	}

	function message_header($subject, $author, $date, $id, $th)
	{
		$y = pdf_get_value($this->pdf, 'texty', 0) - 3;
		if ($y < 100) {
			$this->end_page();
			$this->begin_page($this->pg_title);
			$y = $this->ph - $this->hmargin;
		}
		pdf_moveto($this->pdf, $this->wmargin, $y);
		pdf_lineto($this->pdf, ($this->pw - $this->wmargin), $y);
		pdf_moveto($this->pdf, $this->wmargin, $y - 3);
		pdf_lineto($this->pdf, ($this->pw - $this->wmargin), $y - 3);
		pdf_stroke($this->pdf);

		pdf_set_text_pos($this->pdf, $this->wmargin, ($y - 5));

		pdf_setfont($this->pdf, $this->fonts['Helvetica'], 14);
		pdf_continue_text($this->pdf, 'Subject: ' . $subject);
		pdf_continue_text($this->pdf, 'Posted by '.$author.' on '.gmdate('D, d M Y H:i:s \G\M\T', $date));
		pdf_continue_text($this->pdf, 'URL: ');
		$url = $GLOBALS['WWW_ROOT'].'?t=rview&th='.$th.'&goto='.$id;
		$this->add_link($url, $url);

		$this->draw_line();

		pdf_set_text_pos($this->pdf, $this->wmargin, ($this->y - 3));
	}

	function end_message()
	{
		$y = pdf_get_value($this->pdf, 'texty', 0) - 10;
		pdf_moveto($this->pdf, $this->wmargin, $y);
		pdf_lineto($this->pdf, ($this->pw - $this->wmargin), $y);
		pdf_moveto($this->pdf, $this->wmargin, $y - 3);
		pdf_lineto($this->pdf, ($this->pw - $this->wmargin), $y - 3);
		pdf_stroke($this->pdf);

		pdf_set_text_pos($this->pdf, $this->wmargin, ($y - 20));
	}
}

function post_to_smiley($text, $re)
{
	return ($re ? strtr($text, $re) : $text);
}

	require('./GLOBALS.php');
	require($DATA_DIR . 'include/PDF.php');
	fud_use('err.inc');

	/* this potentially can be a longer form to generate */
	set_time_limit($PDF_MAX_CPU);

	/* before we go on, we need to do some very basic activation checks */
	if ($FORUM_ENABLED != 'Y') {
		fud_use('cfg.inc', TRUE);
		fud_use('errmsg.inc');
		exit(cfg_dec($DISABLED_REASON) . __fud_ecore_adm_login_msg);
	}
	if (!$FORUM_TITLE && @file_exists($WWW_ROOT_DISK.'install.php')) {
		fud_use('errmsg.inc');
	        exit(__fud_e_install_script_present_error);
	}

/*{PRE_HTML_PHP}*/
/*{POST_HTML_PHP}*/

	if ($PDF_ENABLED == 'N' || !extension_loaded('pdf')) {
		std_error('disabled');
	}

	if ($PHP_COMPRESSION_ENABLE == 'Y') {
		ob_start(array('ob_gzhandler', $PHP_COMPRESSION_LEVEL));
	}

	$forum	= isset($_GET['frm']) ? (int)$_GET['frm'] : 0;
	$thread	= isset($_GET['th']) ? (int)$_GET['th'] : 0;
	$msg	= isset($_GET['msg']) ? (int)$_GET['msg'] : 0;
	$page	= isset($_GET['page']) ? (int)$_GET['page'] : 0;

	if ($forum) {
		if ($PDF_ALLOW_FULL != 'Y' && !$page) {
			$page = 1;
		}

		if ($page) {
			$join = 'FROM {SQL_TABLE_PREFIX}thread_view tv 
				INNER JOIN {SQL_TABLE_PREFIX}thread t ON t.id=tv.thread_id
				INNER JOIN {SQL_TABLE_PREFIX}forum f ON f.id='.$forum.'
				INNER JOIN {SQL_TABLE_PREFIX}msg m ON m.thread_id=t.id
				LEFT JOIN {SQL_TABLE_PREFIX}users u ON m.poster_id=u.id
				LEFT JOIN {SQL_TABLE_PREFIX}poll p ON m.poll_id=p.id
			';
			$lmt = ' AND tv.forum_id='.$forum.' AND tv.page='.$page;
		} else {
			$join = 'FROM {SQL_TABLE_PREFIX}forum f
				INNER JOIN {SQL_TABLE_PREFIX}thread t ON t.forum_id=f.id
				INNER JOIN {SQL_TABLE_PREFIX}msg m ON m.thread_id=t.id
				LEFT JOIN {SQL_TABLE_PREFIX}users u ON m.poster_id=u.id
				LEFT JOIN {SQL_TABLE_PREFIX}poll p ON m.poll_id=p.id
			';
			$lmt = ' AND f.id='.$forum;
		}
	} else if ($thread) {
		$join = 'FROM {SQL_TABLE_PREFIX}msg m 
				INNER JOIN {SQL_TABLE_PREFIX}thread t ON t.id=m.thread_id
				INNER JOIN {SQL_TABLE_PREFIX}forum f ON f.id=t.forum_id
				LEFT JOIN {SQL_TABLE_PREFIX}users u ON m.poster_id=u.id
				LEFT JOIN {SQL_TABLE_PREFIX}poll p ON m.poll_id=p.id
			';
		$lmt = ' AND m.thread_id='.$thread;
	} else if ($msg) {
		$lmt = ' AND m.id='.$msg;
		$join = 'FROM {SQL_TABLE_PREFIX}msg m 
				INNER JOIN {SQL_TABLE_PREFIX}thread t ON t.id=m.thread_id
				INNER JOIN {SQL_TABLE_PREFIX}forum f ON f.id=t.forum_id
				LEFT JOIN {SQL_TABLE_PREFIX}users u ON m.poster_id=u.id
				LEFT JOIN {SQL_TABLE_PREFIX}poll p ON m.poll_id=p.id
			';
	} else {
		invl_inp_err();
	}

	$c = uq('SELECT code, '.__FUD_SQL_CONCAT__.'(\'images/smiley_icons/\', img), descr FROM {SQL_TABLE_PREFIX}smiley');
	while ($r = db_rowarr($c)) {
		$im = '<img src="'.$r[1].'" border=0 alt="'.$r[2].'">';
		$re[$im] = (($p = strpos($r[0], '~')) !== FALSE) ? substr($r[0], 0, $p) : $r[0];
	}
	qf($c);
	if (!isset($re)) {
		$re = NULL;
	}

	if (_uid) {
		if ($usr->is_mod != 'A') {
			$join .= '	INNER JOIN {SQL_TABLE_PREFIX}group_cache g1 ON g1.user_id=2147483647 AND g1.resource_id=f.id
					LEFT JOIN {SQL_TABLE_PREFIX}group_cache g2 ON g2.user_id='._uid.' AND g2.resource_id=f.id
					LEFT JOIN {SQL_TABLE_PREFIX}mod mm ON mm.forum_id=f.id AND mm.user_id='._uid.' ';
			$lmt .= " AND (mm.id IS NOT NULL OR (CASE WHEN g2.id IS NOT NULL THEN g2.p_READ ELSE g1.p_READ END)='Y')";
		}
	} else {
		$join .= ' INNER JOIN {SQL_TABLE_PREFIX}group_cache g1 ON g1.user_id=0 AND g1.resource_id=f.id ';
		$lmt .= " AND g1.p_READ='Y'";
	}

	if ($forum) {
		$subject = q_singleval('SELECT name FROM {SQL_TABLE_PREFIX}forum WHERE id='.$forum);
	}

	$c = uq('SELECT 
				m.id, m.thread_id, m.subject, m.post_stamp,
				m.attach_cnt, m.attach_cache, m.poll_cache,
				m.foff, m.length, m.file_id,
				(CASE WHEN u.alias IS NULL THEN \''.$ANON_NICK.'\' ELSE u.alias END) as alias,
				p.name AS poll_name, p.total_votes
			'.$join.'
			WHERE
				m.approved=\'Y\' '.$lmt.' ORDER BY m.post_stamp, m.thread_id');

	if (!($o = db_rowobj($c))) {
		invl_inp_err();
	}

	if ($thread || $msg) {
		$subject = $o->subject;
	}

	$fpdf = new fud_pdf('FUDforum ' . $GLOBALS['FORUM_VERSION'], $FORUM_TITLE, $subject, $PDF_PAGE, $PDF_WMARGIN, $PDF_HMARGIN);
	$fpdf->begin_page($subject);
	do {
		/* write message header */
		reverse_fmt($o->alias);
		reverse_fmt($o->subject);
		$fpdf->message_header($o->subject, $o->alias, $o->post_stamp, $o->id, $o->thread_id);

		/* write message body */
		$msg_body = strip_tags(post_to_smiley(read_msg_body($o->foff, $o->length, $o->file_id), $re));
		reverse_fmt($msg_body);
		$fpdf->input_text(explode("\n", $msg_body));

		/* handle attachments */
		if ($o->attach_cnt && $o->attach_cache) {
			$a = unserialize($o->attach_cache);
			if (is_array($a) && @count($a)) {
				foreach ($a as $i) {
					$attch[] = array('id' => $i[0], 'name' => $i[1], 'nd' => $i[3]);
				}
				$fpdf->add_attacments($attch);
			}
		}

		/* handle polls */
		if ($o->poll_name && $o->poll_cache) {
			$pc = @unserialize($o->poll_cache);
			if (is_array($pc) && count($pc)) {
				reverse_fmt($o->poll_name);
				foreach ($pc as $opt) {
					$opt[0] = strip_tags(post_to_smiley($opt[0], $re));
					reverse_fmt($opt[0]);
					$votes[] = array('name' => $opt[0], 'votes' => $opt[1]);
				}
				$fpdf->add_poll($o->poll_name, $votes, $o->total_votes);
			}
		}

		$fpdf->end_message();
	} while (($o = db_rowobj($c)));
	un_register_fps();
	qf($c);

	$fpdf->end_page();
	pdf_close($fpdf->pdf);
	$pdf = pdf_get_buffer($fpdf->pdf);

	header('Content-type: application/pdf');
	header('Content-length: '.strlen($pdf));
	header('Content-disposition: inline; filename=FUDforum'.date('Ymd').'.pdf');
	echo $pdf;

	pdf_delete($fpdf->pdf);
?>