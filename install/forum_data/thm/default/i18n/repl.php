<?php
function make_lang_arr($path)
{
	$eng = file($path);
	foreach ($eng as $v) {
		$key = strtok($v, ':');
		$lang[$key] = $v;
	}

	return $lang;
}
$repl2 = array(
	'status_line' => array('{VAR: inv_u}', '{VAR: st_obj->online_users_hidden}'),
	'was_moved_msg' => array('{VAR: obj->subject}', '{VAR: r[2]}'),
	'referals_refered_by' => array('{VAR: r_login}', '{VAR: p_user[1]}'),
	'remail_sent_conf' => array('VAR: thread->subject}', '{VAR: data->subject}'),
	'msg_update' => array('{VAR: thread->subject}', '{VAR: frm->subject}'),
	'private_msg_notify_body' => array('{VAR: this->subject}', '{VAR: m->subject}'),
	'register_welcome_msg' => array('{VAR: HTTP_POST_VARS[\'reg_plaintext_passwd\']}', '{VAR: _POST[\'reg_plaintext_passwd\']}'),
	'reset_newpass_msg' => array('{VAR: parr[\'passwd\']}', '{VAR: passwd}'),
	'showposts_update' => array('{VAR: u->login}', '{VAR: u_alias}'),
	'tree_update' => array('{VAR: thread->subject}', '{VAR: frm->subject}'),
	'ERR_emailconf_msg' => array('{DEF: _rsid}">click here', 'S={VAR: ses_id}">click here'),
	'msg_title' => array('{VAR: thread->subject}', '{VAR: frm->subject}'),
	'tree_title' => array('{VAR-HTML: frm->name}', '{VAR-HTML: frm->frm_name}'),
	'remail_email' => array('{VAR: thread->id}', '{VAR: data->id}'),
	'thr_exch_decl_reason' => array('{VAR: data->subject}', '{VAR-HTML: data->f2_name}'),
	'exch_decline_ttl' => array('{VAR: thr_name}', '{VAR: data->subject}'),
	'ignored_user_post' => array('{VAR: user_login}', '{VAR: obj->login}')
);

$repl3 = array(
	'status_line' => array('{VAR: annon}', '{VAR: st_obj->online_users_anon}'),	
	'was_moved_msg' => array('{VAR: d_frm_id}', '{VAR: r[11]}'),
	'private_msg_notify_body' => array('{VAR: GLOBALS["usr"]->login}', '{VAR: usr->login}'),
	'ERR_emailconf_msg' => array('{VAR: GLOBALS[\'usr\']->email}', '{VAR: usr_d->email}'),
	'remail_email' => array('{DEF: _rsid}', 'rid={DEF: _uid}')
);

$repl4 = array(
	'was_moved_msg' => array('VAR: name}', '{VAR: r[12]}'),
	'remail_email' => array('{VAR: u}', '{VAR: usr->alias}')
);

$repl = array(
'download_counter' => array('{VAR: a_obj->dlcount}', '{VAR: v[3]}'),
'num_votes' => array('{VAR: total_votes}', '{VAR: obj->total_votes}'),
'post_count' => array('{VAR: u->posted_msg_count}', '{VAR: u_pcount}'),
'registered_on' => array('{DATE: u->join_date %a, %d %B %Y}', '{DATE: u_reg_date %a, %d %B %Y}'),
'show_posts_by' => array('{VAR: u->alias}', '{VAR: u_alias}'),
'status_line' => array('{VAR: reg_u}', '{VAR: st_obj->online_users_reg}'),
'user_counter' => array('{VAR: reg_users}', '{VAR: st_obj->user_count}'),
'vote' => array('{VAR: thread->vote_count()}', '{VAR: frm->n_rating}'),
'was_moved_msg' => array('{VAR: obj->root_msg_id}', '{VAR: r[15]}'),
'referals_refered_by' => array('{VAR: r_id}', '{VAR: p_user[0]}'),
'browsing_folder' => array('{VAR: folder}', '{VAR: folders[$folder_id]}'),
'register_email' => array(':', ''),
'remail_sent_conf' => array('{VAR: femail}', '{VAR-HTML: _POST[\'femail\']}'),
'ppost_quote_msg' => array('{VAR: msg_r->login}', '{VAR: msg_to_list}'),
'msg_update' => array('{VAR: thread->id}', '{VAR: frm->id}'),
'post_err_noannontopics_msg' => array('&amp;returnto={VAR-URL: returnto_d}', ''),
'post_err_noannonposts_msg' => array('&amp;returnto={VAR-URL: returnto_d}', ''),
'private_msg_notify_subj' => array('{VAR: this->subject}', '{VAR: m->subject}'),
'private_msg_notify_body' => array('{DATE: track_msg->post_stamp %a, %d %B %Y %H:%M}', '{DATE: m->post_stamp %a, %d %B %Y %H:%M}'),
'register_conf_msg' => array('{VAR: usr->conf_key}', '{VAR: uent->conf_key}'),
'register_welcome_msg' => array('{VAR: usr->login}', '{VAR: uent->login}'),
'reset_newpass_msg' => array('{VAR: parr[\'usr\']->login}', '{VAR: ui[1]}'),
'reset_login_notify' => array("\n", ".<br>\n"),
'showposts_update' => array('{VAR: u->id}', '{VAR: uid}'),
'tree_update' => array('{VAR: thread->id}', '{VAR: frm->id}'),
'ERR_disabled_url' => array('{VAR: HTTP_SERVER_VARS[\'HTTP_REFERER\']}', '{VAR: _SERVER[\'HTTP_REFERER\']}'),
'ERR_access_url' => array('{VAR: HTTP_SERVER_VARS[\'HTTP_REFERER\']}', '{VAR: _SERVER[\'HTTP_REFERER\']}'),
'ERR_emailconf_msg' => array('{VAR: GLOBALS[\'usr\']->email}', '{VAR: usr_d->email}'),
'ERR_user_url' => array('{VAR: HTTP_SERVER_VARS[\'HTTP_REFERER\']}', '{VAR: _SERVER[\'HTTP_REFERER\']}'),
'ERR_systemerr_url' => array('{GVAR: returnto_d}', ''),
'msg_title' => array('{VAR-HTML: frm->name}', '{VAR-HTML: frm->frm_name}'),
'tree_title' => array('{VAR: thread->subject}', '{VAR: frm->subject}'),
'remail_email' => array('{VAR: thread->subject}', '{VAR: data->subject}'),
'thr_exch_decl_reason' => array('{VAR: thr_name}', '{VAR-HTML: frm->name}'),
'exch_decline_ttl' => array('{VAR-HTML: frm->name}', '{VAR-HTML: data->f2_name}'),
'post_cur_attached' => array('{VAR: attach_count}', '{VAR: i}'),
'ignored_anon_post' => array('{VAR: user_login}', '{GVAR: ANON_NICK}'),
'buddy_list_bday' => array('{VAR: obj->login}', '{VAR: r[2]}')
);

$repl5 = array(
'register_icq' => 'register_icq:			ICQ
',
'buddy_search' => 'buddy_search:			Enter the login of the user you wish to add.
',
'single_msg_delete' => 'single_msg_delete:		You are about to <font color="#ff0000" size="4">DELETE</font> a message titled: <b>{VAR: data[3]}</b><p>
',
'thread_delete' => 'thread_delete:			You are about to <font color="#ff0000" size="4">DELETE</font> the <font color="#ff0000" size="4">ENTIRE TOPIC</font> titled: <b>{VAR: data[3]}</b><p>
');

function apply_replace($file, $repl, $repl2, $repl3, $repl4, $repl5)
{
	echo "checking: $file\n";
	$curl = make_lang_arr($file);

	foreach ($repl as $k => $v) {
		$curl[$k] = str_replace($v[0], $v[1], $curl[$k]);			
	}
	foreach ($repl2 as $k => $v) {
		$curl[$k] = str_replace($v[0], $v[1], $curl[$k]);			
	}
	foreach ($repl3 as $k => $v) {
		$curl[$k] = str_replace($v[0], $v[1], $curl[$k]);			
	}
	foreach ($repl4 as $k => $v) {
		$curl[$k] = str_replace($v[0], $v[1], $curl[$k]);			
	}
	$curl = array_merge($curl, $repl5);

	if (!($fp = fopen($file, 'w'))) {
		exit("can't open $file for writing\n");
	}
	fwrite($fp, implode('', $curl));
	fclose($fp);
}

	if (isset($_SERVER['argv'][1])) {
		apply_replace($_SERVER['argv'][1] . '/msg', $repl, $repl2, $repl3, $repl4, $repl5);
		exit;
	}

	$dp = opendir('.');
	readdir($dp); readdir($dp); 
	while ($de = readdir($dp)) {
		if (!is_dir($de) || $de == 'english' || !@file_exists($de . '/msg')) {
			continue;
		}
		apply_replace($de . '/msg', $repl, $repl2, $repl3, $repl4, $repl5);
	}
	closedir($dp);
?>