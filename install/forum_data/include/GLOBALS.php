<?php
/**
* copyright            : (C) 2001-2010 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id$
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; version 2 of the License.
**/

	$INCLUDE 		= '';
	$WWW_ROOT 		= '';
	$WWW_ROOT_DISK		= '';
	$DATA_DIR		= '';
	$ERROR_PATH 		= '';
	$MSG_STORE_DIR		= '';
	$TMP			= '';
	$FILE_STORE		= '';
	$FORUM_SETTINGS_PATH 	= '';
	$PLUGIN_PATH 		= '';

	$FUD_OPT_1		= 1743713471;
	$FUD_OPT_2		= 1769345087;
	$FUD_OPT_3		= 578813952;
	$FUD_OPT_4		= 3;

	$CUSTOM_AVATAR_MAX_SIZE = 10000;	/* bytes */
	$CUSTOM_AVATAR_MAX_DIM	= '64x64';	/* width x height (pixels) */

	$COOKIE_PATH		= '';
	$COOKIE_DOMAIN		= '';
	$COOKIE_NAME		= '';
	$COOKIE_TIMEOUT 	= 604800;	/* seconds */
	$SESSION_TIMEOUT 	= 1800;		/* seconds */

	$DBHOST 		= '';
	$DBHOST_USER		= '';
	$DBHOST_PASSWORD	= '';
	$DBHOST_DBNAME		= '';
	$DBHOST_TBL_PREFIX	= 'fud30_';		/* do not modify this */
	$DBHOST_DBTYPE		= '';

	$FUD_SMTP_SERVER	= '';
	$FUD_SMTP_PORT		= 25;
	$FUD_SMTP_TIMEOUT	= 10;		/* seconds */
	$FUD_SMTP_LOGIN		= '';
	$FUD_SMTP_PASS		= '';

	$ADMIN_EMAIL 		= '';

	$PRIVATE_ATTACHMENTS	= 5;		/* int */
	$PRIVATE_ATTACH_SIZE	= 1000000;	/* bytes */
	$MAX_PMSG_FLDR_SIZE	= 300000;	/* bytes */
	$MAX_PMSG_FLDR_SIZE_AD	= 1000000;	/* bytes */
	$MAX_PMSG_FLDR_SIZE_PM	= 1000000;	/* bytes */

	$FORUM_IMG_CNT_SIG	= 2;		/* int */
	$FORUM_SIG_ML		= 256;		/* int */

	$UNCONF_USER_EXPIRY	= 7;		/* days */
	$MOVED_THR_PTR_EXPIRY	= 3;		/* days */

	$MAX_SMILIES_SHOWN	= 15;		/* int */
	$DISABLED_REASON	= 'Temporarily offline; please come back soon!';
	$POSTS_PER_PAGE 	= 40;
	$THREADS_PER_PAGE	= 40;
	$WORD_WRAP		= 60;
	$NOTIFY_FROM		= '';		/* email */
	$ANON_NICK		= 'Anonymous';	/* coward */
	$FLOOD_CHECK_TIME	= 60;		/* seconds */
	$MOD_FIRST_N_POSTS	= 1;
	$POSTS_BEFORE_LINKS	= 0;
	$POST_MIN_LEN		= 3;
	$SERVER_TZ		= 'UTC';
	$SEARCH_CACHE_EXPIRY	= 172800;	/* seconds */
	$MEMBERS_PER_PAGE	= 40;
	$POLLS_PER_PAGE		= 40;
	$THREAD_MSG_PAGER	= 5;
	$GENERAL_PAGER_COUNT	= 15;
	$EDIT_TIME_LIMIT	= 0;
	$LOGEDIN_TIMEOUT	= 5;		/* minutes */
	$MAX_IMAGE_COUNT	= 10;
	$STATS_CACHE_AGE	= 600;		/* seconds */
	$FORUM_TITLE		= 'My forum, my way!';
	$FORUM_DESCR		= 'Fast Uncompromising Discussions. FUDforum will get your users talking.';
	$MAX_LOGIN_SHOW		= 25;
	$MAX_LOCATION_SHOW	= 25;
	$SHOW_N_MODS		= 2;

	$TREE_THREADS_MAX_DEPTH	= 15;
	$TREE_THREADS_MAX_SUBJ_LEN = 75;

	$REG_TIME_LIMIT		= 60;		/* seconds */
	$POST_ICONS_PER_ROW	= 9;		/* int */
	$MAX_LOGGEDIN_USERS	= 25;		/* int */
	$PHP_COMPRESSION_LEVEL	= 9;		/* int 1-9 */
	$PHP_CLI		= '';		/* Command line PHP exectable */
	$MNAV_MAX_DATE		= 31;		/* days */
	$MNAV_MAX_LEN		= 256;		/* characters */

	$FEED_MAX_N_RESULTS	= 20;		/* int */
	$FEED_AUTH_ID		= 0;		/* 0 - treat as anon user, >0 treat like specific forum user */
	$FEED_CACHE_AGE		= 3600;

	$PDF_PAGE		= 'letter';	/* string */
	$PDF_WMARGIN		= 15;		/* int */
	$PDF_HMARGIN		= 15;		/* int */
	$PDF_MAX_CPU		= 15;		/* seconds */

	$FUD_WHOIS_SERVER	= 'ws.arin.net';
	$MIN_TIME_BETWEEN_LOGIN = 10;		/* seconds */

/* DO NOT EDIT FILE BEYOND THIS POINT UNLESS YOU KNOW WHAT YOU ARE DOING */

	require($INCLUDE .'core.inc');
?>
