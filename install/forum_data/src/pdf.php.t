<?php
/**
* copyright            : (C) 2001-2004 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: pdf.php.t,v 1.33 2004/11/30 16:40:38 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; either version 2 of the License, or
* (at your option) any later version.
**/

	require('./GLOBALS.php');
	fud_use('err.inc');
	fud_use('fpdf.inc', true);

class fud_pdf extends FPDF
{
	function begin_page($title)
	{
		$this->AddPage();
		$this->Bookmark($title);
	}

	function input_text($text)
	{
		$this->SetFont('helvetica','',12);
		$this->Write(5, $text);
		$this->Ln(5);
	}

	function draw_line()
	{
		$this->Line($this->lMargin, $this->y, ($this->w - $this->rMargin), $this->y);
	}

	function add_link($url, $caption=0)
	{
		$this->SetTextColor(0,0,255);
		$this->Write(5, $caption ? $caption : $url, $url);
		$this->SetTextColor(0);
	}

	function add_attacments($attch)
	{
		$this->Ln(5);
		$this->SetFont('courier', '', 16);
		$this->Write(5, 'File Attachments');
		$this->Ln(5);
	
		$this->draw_line();

		$this->SetFont('', '', 14);

		$i = 0;
		foreach ($attch as $a) {
			$this->Write(5, ++$i . ') ');
			$this->add_link($GLOBALS['WWW_ROOT'] . 'index.php?t=getfile&id='.$a['id'], $a['name']);
			$this->Write(5, ', downloaded '.$a['nd'].' times');
			$this->Ln(5);
		}
	}

	function add_poll($name, $opts, $ttl_votes)
	{
		$this->Ln(6);
		$this->SetFont('courier', '', 16);
		$this->Write(5, $name);
		$this->SetFont('', '', 14);
		$this->Write(5, '(total votes: '.$ttl_votes.')');
		$this->Ln(6);
		$this->draw_line();
		$this->Ln(5);

		$p1 = ($this->w - $this->rMargin - $this->lMargin) * 0.6 / 100;

		// avoid /0 warnings and safe to do, since we'd be multiplying 0 since there are no votes
		if (!$ttl_votes) {
			$ttl_votes = 1;
		}

		$this->SetFont('helvetica', '', 14);

		foreach ($opts as $o) {
			$this->SetFillColor(52, 146, 40);
			$this->Cell((!$o['votes'] ? 1 : $p1 * (($o['votes'] / $ttl_votes) * 100)), 5, $o['name'] . "\t\t" . $o['votes'] . '/('.round(($o['votes'] / $ttl_votes) * 100).'%)', 1, 0, '', 1);
			$this->Ln(5);
		}
		$this->SetFillColor();
	}

	function message_header($subject, $author, $date, $id, $th)
	{
		$this->Rect($this->lMargin, $this->y, (int)($this->w - $this->lMargin - $this->lMargin), 1, 'F');
		$this->Ln(2);

		$this->SetFont('helvetica','',14);
		$this->Bookmark($subject, 1);
		$this->Write(5, 'Subject: ' . $subject);
		$this->Ln(5);
		
		$this->Write(5, 'Posted by ');
		$this->add_link($GLOBALS['WWW_ROOT'].'index.php?t=usrinfo&id='.$author[0], $author[1]);
		$this->Write(5, ' on '.gmdate('D, d M Y H:i:s \G\M\T', $date));
		$this->Ln(5);
		$this->add_link($GLOBALS['WWW_ROOT'].'index.php?t=rview&th='.$th.'&goto='.$id, 'View Forum Message');
		$this->Write(5, ' <> ');
		$this->add_link($GLOBALS['WWW_ROOT'].'index.php?t=post&reply_to='.$id, 'Reply to Message');
		$this->Ln(5);
		$this->draw_line();
		$this->Ln(3);
	}

	function end_message()
	{
		$this->Ln(3);
		$this->Rect($this->lMargin, $this->y, (int)($this->w - $this->lMargin - $this->lMargin), 1, 'F');
		$this->Ln(10);
	}

	function header()
	{
	
	}
	
	function footer()
	{
		$this->SetFont('courier', '', 10);
		$this->Ln(10);
		$this->draw_line();
		$this->Write(5, 'Page '.$this->page.' of {fnb} ---- Generated from ');
		$this->add_link($GLOBALS['WWW_ROOT'].'index.php', $GLOBALS['FORUM_TITLE']);
		$this->Write(5, ' by FUDforum '.$GLOBALS['FORUM_VERSION']);
	}
}


	/* this potentially can be a longer form to generate */
	@set_time_limit($PDF_MAX_CPU);

	/* before we go on, we need to do some very basic activation checks */
	if (!($FUD_OPT_1 & 1)) {
		fud_use('errmsg.inc');
		exit($DISABLED_REASON . __fud_ecore_adm_login_msg);
	}
	if (!$FORUM_TITLE && @file_exists($WWW_ROOT_DISK.'install.php')) {
		fud_use('errmsg.inc');
	        exit(__fud_e_install_script_present_error);
	}

/*{PRE_HTML_PHP}*/
/*{POST_HTML_PHP}*/

	if (!($FUD_OPT_2 & 134217728)) {
		std_error('disabled');
	}

	if ($FUD_OPT_2 & 16384) {
		ob_start(array('ob_gzhandler', $PHP_COMPRESSION_LEVEL));
	}

	$forum	= isset($_GET['frm']) ? (int)$_GET['frm'] : 0;
	$thread	= isset($_GET['th']) ? (int)$_GET['th'] : 0;
	$msg	= isset($_GET['msg']) ? (int)$_GET['msg'] : 0;
	$page	= isset($_GET['page']) ? (int)$_GET['page'] : 0;

	if ($forum) {
		if (!($FUD_OPT_2 & 268435456) && !$page) {
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

	$re = array();
	$c = uq('SELECT code, '.__FUD_SQL_CONCAT__.'(\'images/smiley_icons/\', img), descr FROM {SQL_TABLE_PREFIX}smiley');
	while ($r = db_rowarr($c)) {
		$im = '<img src="'.$r[1].'" border=0 alt="'.$r[2].'">';
		$re[$im] = (($p = strpos($r[0], '~')) !== false) ? substr($r[0], 0, $p) : $r[0];
	}

	if (_uid) {
		if (!$is_a) {
			$join .= '	INNER JOIN {SQL_TABLE_PREFIX}group_cache g1 ON g1.user_id=2147483647 AND g1.resource_id=f.id
					LEFT JOIN {SQL_TABLE_PREFIX}group_cache g2 ON g2.user_id='._uid.' AND g2.resource_id=f.id
					LEFT JOIN {SQL_TABLE_PREFIX}mod mm ON mm.forum_id=f.id AND mm.user_id='._uid.' ';
			$lmt .= " AND (mm.id IS NOT NULL OR ((CASE WHEN g2.id IS NOT NULL THEN g2.group_cache_opt ELSE g1.group_cache_opt END) & 2) > 0)";
		}
	} else {
		$join .= ' INNER JOIN {SQL_TABLE_PREFIX}group_cache g1 ON g1.user_id=0 AND g1.resource_id=f.id ';
		$lmt .= " AND (g1.group_cache_opt & 2) > 0";
	}

	if ($forum) {
		$subject = q_singleval('SELECT name FROM {SQL_TABLE_PREFIX}forum WHERE id='.$forum);
	}

	$c = uq('SELECT
				m.id, m.thread_id, m.subject, m.post_stamp,
				m.attach_cnt, m.attach_cache, m.poll_cache,
				m.foff, m.length, m.file_id, u.id AS uid,
				(CASE WHEN u.alias IS NULL THEN \''.$ANON_NICK.'\' ELSE u.alias END) as alias,
				p.name AS poll_name, p.total_votes
			'.$join.'
			WHERE
				t.moved_to=0 AND m.apr=1 '.$lmt.' ORDER BY m.post_stamp, m.thread_id');

	if (!($o = db_rowobj($c))) {
		invl_inp_err();
	}

	if ($thread || $msg) {
		$subject = $o->subject;
	}

	$fpdf = new fud_pdf('FUDforum ' . $FORUM_VERSION, $FORUM_TITLE, $subject, $PDF_PAGE, $PDF_WMARGIN, $PDF_HMARGIN);
	$fpdf->begin_page($subject);
	do {
		/* write message header */
		reverse_fmt($o->alias);
		reverse_fmt($o->subject);
		$fpdf->message_header($o->subject, array($o->uid, $o->alias), $o->post_stamp, $o->id, $o->thread_id);

		/* write message body */
		$msg_body = strip_tags(post_to_smiley(read_msg_body($o->foff, $o->length, $o->file_id), $re));
		reverse_fmt($msg_body);
		$fpdf->input_text($msg_body);

		/* handle attachments */
		if ($o->attach_cnt && $o->attach_cache && ($a = unserialize($o->attach_cache))) {
			$attch = array();
			foreach ($a as $i) {
				$attch[] = array('id' => $i[0], 'name' => $i[1], 'nd' => $i[3]);
			}
			$fpdf->add_attacments($attch);
		}

		/* handle polls */
		if ($o->poll_name && $o->poll_cache && ($pc = unserialize($o->poll_cache))) {
			$votes = array();
			reverse_fmt($o->poll_name);
			foreach ($pc as $opt) {
				$opt[0] = strip_tags(post_to_smiley($opt[0], $re));
				reverse_fmt($opt[0]);
				$votes[] = array('name' => $opt[0], 'votes' => $opt[1]);
			}
			$fpdf->add_poll($o->poll_name, $votes, $o->total_votes);
		}

		$fpdf->end_message();
	} while (($o = db_rowobj($c)));

	$fpdf->Output('FUDforum'.date('Ymd').'.pdf', 'I');
?>