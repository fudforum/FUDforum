<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: path_info.inc.t,v 1.3 2003/05/12 23:11:38 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

	$p = explode('/', $_SERVER['PATH_INFO']);
	if ($SESSION_USE_URL == 'Y') {
		$_GET['S'] = array_pop($p);
	}
	if ($TRACK_REFERRALS == 'Y') {
		$_GET['rid'] = array_pop($p);
	}
	$_SERVER['QUERY_STRING'] = $_SERVER['PATH_INFO'];

	if (count($p) < 2) {
		$_GET['t'] = count($p) ? $p[0] : 'index';
	} else {
		switch ($p[0]) {
			case 'm': /* goto specific message */
				$_GET['t'] = d_thread_view;
				$_GET['goto'] = $p[1];
				if (isset($p[2])) {
					$_GET['th'] = $p[2];
				}
				break;

			case 't': /* view thread */
				$_GET['t'] = d_thread_view;
				$_GET['th'] = $p[1];
				if (isset($p[2])) {
					$_GET['start'] = $p[2];	
				}
				break;

			case 'f': /* view forum */
				$_GET['t'] = t_thread_view;
				$_GET['frm_id'] = $p[1];
				if (isset($p[2])) {
					$_GET['start'] = $p[2];
				}
				break;

			case 'r':
				$_GET['t'] = 'post';
				if (isset($p[1])) {
					$_GET['reply_to'] = $p[1];
					if (isset($p[2])) {
						$_GET['quote'] = 'true';
					}
				}
				break;

			case 'e':
				$_GET['t'] = 'post';
				$_GET['msg_id'] = $p[1];
				break;

			case 'u': /* view user's info */
				$_GET['t'] = 'usrinfo';
				$_GET['id'] = $p[1];
				break;

			case 'i':
				$_GET['t'] = 'index';
				if (isset($p[1])) {
					$_GET['c'] = $p[1];
				}
				break;

			case 'fa':
				$_GET['t'] = 'getfile';
				$_GET['id'] = $p[1];
				if (isset($p[2])) {
					$_GET['private'] = 1;
				}
				break;

			case 'sp': /* show posts */
				$_GET['t'] = 'showposts';
				$_GET['id'] = $p[1];
				break;

			case 'l': /* login/logout */
				$_GET['t'] = 'login';
				if (isset($p[1])) {
					$_GET['logout'] = 1;
				}
				break;

			case 'st':
				$_GET['t'] = $p[1];
				$_GET['th'] = $p[2];
				$_GET['notify'] = $p[3];
				$_GET['opt'] = $p[4] ? 'on' : 'off';
				$_GET['start'] = $p[5];
				break;

			case 'sf':
				$_GET['t'] = $p[1];
				$_GET['frm_id'] = $p[2];
				$_GET[$p[3]] = 1;
				$_GET['start'] = $p[4];
				break;
			
			case 'sl':
				$_GET['t'] = 'subscribed';
				if (isset($p[2])) {
					$_GET['frm_id'] = $p[2];
				} else if (isset($p[3])) {
					$_GET['th'] = $p[3];
				}
				break;

			case 'pmm':
				$_GET['t'] = 'ppost';
				if (isset($p[1])) {
					$_GET[$p[1]] = $p[2];
				}
				break;

			case 'pmv':
				$_GET['t'] = 'pmsg_view';
				$_GET['id'] = $p[1];
				break;

			case 'pdm':
				$_GET['t'] = 'pmsg';
				$_GET['btn_delete'] = 1;
				$_GET['sel'] = $p[1];
				break;

			case 'pl': /* poll list */
				$_GET['t'] = 'polllist';
				if (isset($p[1])) {
					$_GET['uid'] = $p[1];
					if (isset($p[2])) {
						$_GET['start'] = $p[2];
						if (isset($p[3])) {
							$_GET['oby'] = 'ASC';
						}
					}
				}
				break;

			case 'm': /* member list */
				$_GET['t'] = 'finduser';
				if (isset($p[1])) {
					if ($p[1] == '1') { /* order by reg date */
						$_GET['pc'] = 1;
					} else if ($p[1] == '2') { /* order by login */
						$_GET['us'] = 1;
					} /* else order by date */
					if (isset($p[2])) {
						$_GET['start'] = $p[2];
					}
				}
				break;

			case 'h': /* help */
				$_GET['t'] = 'help_index';
				if (isset($p[1])) {
					$_GET['section'] = $p[1];
				}
				break;

			case 'cv': /* change thread view mode */
				$_GET['t'] = $p[1];
				$_GET['frm_id'] = $p[2];
				break;

			case 'mv': /* change message view mode */
				$_GET['t'] = $p[1];
				$_GET['th'] = $p[2];
				break;

			case 'rm': /* report message */
				$_GET['t'] = 'report';
				$_GET['msg_id'] = $p[1];
				break;

			case 'rl': /* list of reported messages */
				$_GET['t'] = 'reported';
				if (isset($p[1])) {
					$_GET['del'] = $p[1];
				}
				break;

			case 'd': /* delete thread/message */
				$_GET['t'] = 'mmod';
				$_GET['del'] = $p[1];
				if (isset($p[2])) {
					$_GET['th'] = $p[2];
				}
				break;

			case 'em': /* email forum member */
				$_GET['t'] = 'email';
				$_GET['toi'] = $p[1];
				break;

			case 'mar': /* mark all/forum read */
				$_GET['t'] = 'markread';
				if (isset($p[1])) {
					$_GET['id'] = $p[1];
				}
				break;

			case 'bl': /* buddy list */
				$_GET['t'] = 'buddy_list';
				if (isset($p[1])) {
					if ($p[2]) {
						$_GET['add'] = $p[1];
					} else {
						$_GET['del'] = $p[1];
					}
				}
				break;

			case 'il': /* ignore list */
				$_GET['t'] = 'ignore_list';
				if (isset($p[1])) {
					if ($p[2]) {
						$_GET['add'] = $p[1];
					} else {
						$_GET['del'] = $p[1];
					}
				}
				break;

			case 'lk': /* lock/unlock thread */
				$_GET['t'] = 'lock';
				$_GET['th'] = $p[1];
				$_GET[$p[2]] = 1;
				break;

			case 'st': /* split thread */
				$_GET['t'] = 'split_th';
				$_GET['th'] = $p[1];
				break;

			case 'ef': /* email to friend */
				$_GET['t'] = 'remail';
				$_GET['th'] = $p[1];
				break;

			case 'lr': /* list referers */
				$_GET['t'] = 'list_referers';
				if (isset($p[1])) {
					$_GET['start'] = $p[1];
				}
				break;

			case 'tttt': /* test case for testing avalibility of PATH_INFO */
				exit('tttt');
				break;

			default:
				$_GET['t'] = 'index';
				break;
		}
	}
?>