<?php
/**
* copyright            : (C) 2001-2007 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: pdf.php.t,v 1.57 2007/01/01 18:23:46 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; version 2 of the License.
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

	function add_attacments($attch, $private=0)
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
			$this->add_link($GLOBALS['WWW_ROOT'] . 'index.php?t=getfile&id='.$a['id'] . ($private ? '&private=1' : ''), $a['name']);
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
		if ($th) {
			$this->Ln(5);
			$this->add_link($GLOBALS['WWW_ROOT'].'index.php?t=rview&th='.$th.'&goto='.$id.'#msg_'.$id, 'View Forum Message');
			$this->Write(5, ' <> ');
			$this->add_link($GLOBALS['WWW_ROOT'].'index.php?t=post&reply_to='.$id, 'Reply to Message');
		}
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
	$sel	= isset($_GET['sel']) ? (array)$_GET['sel'] : 0;

	if ($forum) {
		if (!($FUD_OPT_2 & 268435456)) {
			 std_error('disabled');		
		}

		if (!$page) {
			$page = 1;
		}

		if ($page) {
			if (!q_singleval('SELECT id FROM {SQL_TABLE_PREFIX}forum WHERE id='.$forum)) {
				invl_inp_err();
			}
			$lwi = q_singleval('SELECT seq FROM {SQL_TABLE_PREFIX}tv_'.$forum.' ORDER BY seq DESC LIMIT 1');
			if ($lwi === NULL || $lwi === FALSE) {
				invl_inp_err();
			}

			$join = 'FROM {SQL_TABLE_PREFIX}tv_'.$forum.' tv
				INNER JOIN {SQL_TABLE_PREFIX}thread t ON t.id=tv.thread_id
				INNER JOIN {SQL_TABLE_PREFIX}forum f ON f.id='.$forum.'
				INNER JOIN {SQL_TABLE_PREFIX}msg m ON m.thread_id=t.id
				LEFT JOIN {SQL_TABLE_PREFIX}users u ON m.poster_id=u.id
				LEFT JOIN {SQL_TABLE_PREFIX}poll p ON m.poll_id=p.id
			';
			$lmt = ' AND tv.seq BETWEEN '.($lwi - ($page * $THREADS_PER_PAGE) + 1).' AND '.($lwi - (($page - 1) * $THREADS_PER_PAGE));
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
	} else if ($sel) { /* PM handling */
		foreach ($sel as $k => $v) {
			if ($v = (int)$v) {
				$sel[$k] = $v;
			} else {
				unset($sel[$k]);
			}
		}
		if (!$sel || !q_singleval("SELECT count(*) FROM {SQL_TABLE_PREFIX}pmsg WHERE id IN(".implode(',', $sel).") AND duser_id="._uid)) {
			invl_inp_err();
		}
		fud_use('private.inc');
	} else {
		invl_inp_err();
	}

	if (_uid) {
		if (!$is_a) {
			$join .= '	INNER JOIN {SQL_TABLE_PREFIX}group_cache g1 ON g1.user_id=2147483647 AND g1.resource_id=f.id
					LEFT JOIN {SQL_TABLE_PREFIX}group_cache g2 ON g2.user_id='._uid.' AND g2.resource_id=f.id
					LEFT JOIN {SQL_TABLE_PREFIX}mod mm ON mm.forum_id=f.id AND mm.user_id='._uid.' ';
			$lmt .= " AND (mm.id IS NOT NULL OR (COALESCE(g2.group_cache_opt, g1.group_cache_opt) & 2) > 0)";
		}
	} else {
		$join .= ' INNER JOIN {SQL_TABLE_PREFIX}group_cache g1 ON g1.user_id=0 AND g1.resource_id=f.id ';
		$lmt .= " AND (g1.group_cache_opt & 2) > 0";
	}

	if ($forum) {
		$subject = q_singleval('SELECT name FROM {SQL_TABLE_PREFIX}forum WHERE id='.$forum);
	}

	if (!$sel) {
		$c = q('SELECT
				m.id, m.thread_id, m.subject, m.post_stamp,
				m.attach_cnt, m.attach_cache, m.poll_cache,
				m.foff, m.length, m.file_id, u.id AS uid,
				COALESCE(u.alias, \''.$ANON_NICK.'\') as alias,
				p.name AS poll_name, p.total_votes
			'.$join.'
			WHERE
				t.moved_to=0 AND m.apr=1 '.$lmt.' ORDER BY m.post_stamp, m.thread_id');
	} else {
		$c = q("SELECT p.*, u.alias, p.duser_id AS uid FROM {SQL_TABLE_PREFIX}pmsg p 
				LEFT JOIN {SQL_TABLE_PREFIX}users u ON p.ouser_id=u.id
				WHERE p.id IN(".implode(',', $sel).") AND p.duser_id="._uid);
	}

	if (!($o = db_rowobj($c))) {
		invl_inp_err();
	}

	if ($thread || $msg) {
		$subject = reverse_fmt($o->subject);
	} else if ($sel) {
		$subject = 'Private Message Archive';
	}

	$fpdf = new fud_pdf('FUDforum ' . $FORUM_VERSION, html_entity_decode($FORUM_TITLE), $subject, $PDF_PAGE, $PDF_WMARGIN, $PDF_HMARGIN);
	$fpdf->begin_page($subject);
	do {
		/* write message header */
		$fpdf->message_header(html_entity_decode($o->subject), array($o->uid, html_entity_decode($o->alias)), $o->post_stamp, $o->id, (isset($o->thread_id) ? $o->thread_id : 0));

		/* write message body */
		if (!$sel) {
			$body = read_msg_body($o->foff, $o->length, $o->file_id);
		} else {
			$body = read_pmsg_body($o->foff, $o->length);
		}

		$fpdf->input_text(html_entity_decode(strip_tags(post_to_smiley($body))));

		/* handle attachments */
		if ($o->attach_cnt) {
			if (!empty($o->attach_cache) && ($a = unserialize($o->attach_cache))) {
				$attch = array();
				foreach ($a as $i) {
					$attch[] = array('id' => $i[0], 'name' => $i[1], 'nd' => $i[3]);
				}
				$fpdf->add_attacments($attch);
			} else if ($sel) {
				$attch = array();
				$c2 = uq("SELECT id, original_name, dlcount FROM {SQL_TABLE_PREFIX}attach WHERE message_id={$o->id} AND attach_opt=1");
				while ($r2 = db_rowarr($c2)) {
					$attch[] = array('id' => $r2[0], 'name' => $r2[1], 'nd' => $r2[2]);
				}
				unset($c2);
				if ($attch) {
					$fpdf->add_attacments($attch, 1);
				}
			}
		}

		/* handle polls */
		if (!empty($o->poll_name) && $o->poll_cache && ($pc = unserialize($o->poll_cache))) {
			$votes = array();
			foreach ($pc as $opt) {
				$votes[] = array('name' => html_entity_decode(strip_tags(post_to_smiley($opt[0]))), 'votes' => $opt[1]);
			}
			$fpdf->add_poll(html_entity_decode($o->poll_name), $votes, $o->total_votes);
		}

		$fpdf->end_message();
	} while (($o = db_rowobj($c)));
	unset($c);

	$fpdf->Output('FUDforum'.date('Ymd').'.pdf', 'I');
?>