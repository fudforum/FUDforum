<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: pdf.php.t,v 1.1 2003/05/20 08:56:46 hackie Exp $
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
	var $pdf, $pw, $ph, $pg_num, $pg_title, $hmargin, $wmargin, $y;

	function fud_pdf($author, $title, $subject, $page_type='letter', $hmargin=15, $wmargin=15)
	{
		$this->pdf = pdf_new();
		pdf_open_file($this->pdf, '');
		pdf_set_info($this->pdf, 'Author',	$author);
		pdf_set_info($this->pdf, 'Title',	$title);
		pdf_set_info($this->pdf, 'Creator',	$author);
		pdf_set_info($this->pdf, 'Subject',	$subject);

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
		pdf_setfont($this->pdf, pdf_findfont($this->pdf, 'Courier', 'host', FALSE), 12);
		pdf_set_text_pos($this->pdf, $this->wmargin, ($this->ph - $this->hmargin));
		$this->pg_title = $title;
	}

	function input_text($text)
	{
		$max_cpl = pdf_stringwidth($this->pdf, 'w');
		$max_cpl = floor(($this->pw - 2 * $this->wmargin) / $max_cpl);

		foreach ($text as $line) {
			if (strlen($line) > $max_cpl) {
				$parts = explode("\n", chunk_split($line, $max_cpl, "\n"));
			} else {
				$parts = array($line);
			}
			foreach ($parts as $p) {
				if (pdf_get_value($this->pdf, 'texty', 0) <= ($this->hmargin + 12)) {
					$this->end_page();
					$this->begin_page($this->pg_title);
				}
				pdf_continue_text($this->pdf, $p);
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
		pdf_setfont($this->pdf, pdf_findfont($this->pdf, 'Courier-Bold', 'host', FALSE), 20);
		pdf_continue_text($this->pdf, 'File Attachments');

		$this->draw_line();

		pdf_setfont($this->pdf, pdf_findfont($this->pdf, 'Helvetica', 'host', FALSE), 14);
		pdf_set_text_pos($this->pdf, $this->wmargin, $this->y - 3);
		foreach ($attch as $a) {
			$this->add_link($GLOBALS['WWW_ROOT'] . 'index.php?t=getfile&id='.$a['id'], $a['name']);
			pdf_show($this->pdf, ', downloaded '.$a['nd'].' times');
		}
	}

	function add_poll($name, $opts, $ttl_votes)
	{
		$font = pdf_findfont($this->pdf, 'Courier-Bold', 'host', FALSE);
		$this->y = pdf_get_value($this->pdf, 'texty', 0) - 3;

		pdf_set_text_pos($this->pdf, $this->wmargin, $this->y - 3);
		pdf_setfont($this->pdf, $font, 20);
		pdf_continue_text($this->pdf, $name);
		pdf_setfont($this->pdf, $font, 16);
		pdf_show($this->pdf, '(total votes: '.$ttl_votes.')');
		
		$this->draw_line();

		$ttl_w = round($this->pw * 0.66);
		$ttl_h = 20;
		$p1 = floor($ttl_w / 100);
		$this->y -= 10;

		pdf_setfont($this->pdf, pdf_findfont($this->pdf, 'Helvetica-Bold', 'host', FALSE), 14);

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
		pdf_moveto($this->pdf, $this->wmargin, $y);
		pdf_lineto($this->pdf, ($this->pw - $this->wmargin), $y);
		pdf_moveto($this->pdf, $this->wmargin, $y - 3);
		pdf_lineto($this->pdf, ($this->pw - $this->wmargin), $y - 3);
		pdf_stroke($this->pdf);

		pdf_set_text_pos($this->pdf, $this->wmargin, ($y - 5));

		pdf_setfont($this->pdf, pdf_findfont($this->pdf, 'Helvetica', 'host', FALSE), 14);
		pdf_continue_text($this->pdf, 'Subject: ' . $subject);
		pdf_continue_text($this->pdf, 'Posted by '.$author.' on '.date('r', $date));
		pdf_continue_text($this->pdf, 'URL: ');
		$url = $GLOBALS['WWW_ROOT'].'?t=rview&th='.$th.'&goto='.$id;
		$this->add_link($url, $url);

		$this->draw_line();

		pdf_set_text_pos($this->pdf, $this->wmargin, ($this->y - 3));
	}

	function end_message()
	{
		$y = pdf_get_value($this->pdf, 'texty', 0) - 3;
		pdf_moveto($this->pdf, $this->wmargin, $y);
		pdf_lineto($this->pdf, ($this->pw - $this->wmargin), $y);
		pdf_moveto($this->pdf, $this->wmargin, $y - 3);
		pdf_lineto($this->pdf, ($this->pw - $this->wmargin), $y - 3);
		pdf_stroke($this->pdf);

		pdf_set_text_pos($this->pdf, $this->wmargin, ($y - 20));
	}
}

function fatal_error()
{
	fud_use('cookies.inc');
	fud_use('users.inc');
	invl_inp_err();
}

	require('GLOBALS.php');
	require ($DATA_DIR . 'include/PDF.php');
	fud_use('err.inc');

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

	if ($PDF_ENABLED == 'N' || !extension_loaded('pdf')) {
		fud_use('cookies.inc');
		fud_use('users.inc');
		std_error('disabled');
	}

	if ($PHP_COMPRESSION_ENABLE == 'Y') {
		ob_start(array('ob_gzhandler', $PHP_COMPRESSION_LEVEL));
	}

/*{POST_HTML_PHP}*/

	$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
	$forum	= isset($_GET['frm']) ? (int)$_GET['frm'] : 0;
	$thread	= isset($_GET['th']) ? (int)$_GET['th'] : 0;
	$msg	= isset($_GET['msg']) ? (int)$_GET['msg'] : 0;

	if ($forum) {
		$lmt = ' AND f.id='.$forum;
	} else if ($thread) {
		$lmt = ' AND m.thread_id='.$thread;
	} else if ($msg) {
		$lmt = ' AND m.id='.$msg;
	} else {
		fatal_error();
	}

	if ($PDF_AUTH == 'Y') {
		if ($PDF_AUTH_ID) {
			$join = '	INNER JOIN {SQL_TABLE_PREFIX}group_cache g1 ON g1.user_id=2147483647 AND g1.resource_id=f.id
					LEFT JOIN {SQL_TABLE_PREFIX}group_cache g2 ON g2.user_id='.$PDF_AUTH_ID.' AND g2.resource_id=f.id
					LEFT JOIN {SQL_TABLE_PREFIX}mod mm ON mm.forum_id=f.id AND mm.user_id='.$PDF_AUTH_ID.' ';
			$lmt .= " AND (mm.id IS NOT NULL OR (CASE WHEN g2.id IS NOT NULL THEN g2.p_READ ELSE g1.p_READ END)='Y')";
		} else {
			$join = ' INNER JOIN {SQL_TABLE_PREFIX}group_cache g1 ON g1.user_id=0 AND g1.resource_id=f.id ';
			$lmt .= " AND g1.p_READ='Y'";
		}
	} else {
		$join = '';
	}

	if ($forum) {
		q_singleval('SELECT name FROM {SQL_TABLE_PREFIX}forum WHERE id='.$forum);
	}

	$data = uq('SELECT 
				m.id, m.thread_id, m.subject, m.post_stamp,
				m.attach_cnt, m.attach_cache, m.poll_cache,
				m.foff, m.length, m.file_id,
				(CASE WHEN u.alias IS NULL THEN \''.$ANON_NICK.'\' ELSE u.alias END) as alias,
				p.name AS poll_name, p.total_votes
			FROM {SQL_TABLE_PREFIX}msg m
			INNER JOIN {SQL_TABLE_PREFIX}thread t ON t.id=m.thread_id
			INNER JOIN {SQL_TABLE_PREFIX}forum f ON f.id=t.forum_id
			LEFT JOIN {SQL_TABLE_PREFIX}users u ON m.poster_id=u.id
			LEFT JOIN {SQL_TABLE_PREFIX}poll p ON m.poll_id=p.id
			'.$join.'
			WHERE
				m.approved=\'Y\' '.$lmt.' ORDER BY m.post_stamp, m.thread_id');

	if (!($o = db_rowobj($data))) {
		fatal_error();
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
		$msg_body = strip_tags(read_msg_body($o->foff, $o->length, $o->file_id));
		reverse_fmt($msg_body);
		$fpdf->input_text(explode("\n", $msg_body));

		/* handle attachments */
		if ($o->attach_cnt && $o->attach_cache) {
			$a = unserialize($o->attach_cache);
			if (is_array($a) && @count($a)) {
				foreach ($a as $i) {
					$attch[] = array('id' => $i[0], 'name' => $r[1], 'nd' => $r[3]);
				}
				$fpdf->add_attacments($attch);
			}
		}

		/* handle polls */
		if ($o->poll_name && $o->poll_cache) {
			$pc = @unserialize($o->poll_cache);
			if (is_array($pc) && count($pc)) {
				foreach ($pc as $opt) {
					$votes[] = array('name' => $opt[0], 'votes' => $opt[1]);
				}
				$fpdf->add_poll($o->poll_name, $votes, $o->total_votes);
			}
		}

		$fpdf->end_message();
	} while (($o = db_rowobj($data)));
	un_register_fps();

	$fpdf->end_page();
	pdf_close($fpdf->pdf);
	$pdf = pdf_get_buffer($fpdf->pdf);

	header('Content-type: application/pdf');
	header('Content-length: '.strlen($pdf));
	header('Content-disposition: inline; filename=FUDforum'.date('Ymd').'.pdf');
	echo $pdf;

	pdf_delete($fpdf->pdf);
?>