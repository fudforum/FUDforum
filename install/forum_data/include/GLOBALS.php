<?php
/***************************************************************************
*                                 GLOBALS.php
*                            -------------------
*   begin                : Tue Jan  8 00:20:19 UTC 2002
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
****************************************************************************
          
****************************************************************************
*
*	FUDforum Copyright (C) 2001,2002 Advanced Internet Designs Inc.
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

	$INCLUDE 		= "";
	$WWW_ROOT 		= "";
	$WWW_ROOT_DISK		= "";
	$DATA_DIR		= "";
	$ERROR_PATH 		= "";
	$MSG_STORE_DIR		= "";
	$TMP			= "";
	$FILE_STORE		= "";
	$FORUM_SETTINGS_PATH 	= "";

	$MOGRIFY_BIN		= "";
	
	$CUSTOM_AVATARS		= "ALL";	/* enum(OFF, BUILT, URL, UPLOAD, BUILT_URL, BUILT_UPLOAD, URL_UPLOAD, ALL) */
	$CUSTOM_AVATAR_MAX_SIZE = "10000";	/* bytes */
	$CUSTOM_AVATAR_MAX_DIM	= "64x64";	/* width x height (pixels) */
	$CUSTOM_AVATAR_APPOVAL  = "Y";		/* boolean */

	$COOKIE_PATH		= "";
	$COOKIE_DOMAIN		= "";
	$COOKIE_NAME		= "";
	$COOKIE_TIMEOUT 	= "604800";	/* seconds */
	$SESSION_TIMEOUT 	= "1800";	/* seconds */
	

	$DBHOST 		= "";
	$DBHOST_USER		= "";
	$DBHOST_PASSWORD		= "";
	$DBHOST_DBNAME		= ""; 
	$DBHOST_PERSIST		= "N";     	/* boolean */
	$DBHOST_TBL_PREFIX	= "fud2_";	/* do not modify this */

	$USE_SMTP		= "N";		/* boolean */
	$FUD_SMTP_SERVER	= "";
	$FUD_SMTP_TIMEOUT	= "10";		/* seconds */
	$FUD_SMTP_LOGIN		= "";
	$FUD_SMTP_PASS		= "";

	$ADMIN_EMAIL 		= "";

	$PM_ENABLED		= "Y";		/* boolean */
	$PRIVATE_ATTACHMENTS	= "5";		/* int */
	$PRIVATE_ATTACH_SIZE	= "1000000";	/* bytes */
	$PRIVATE_TAGS		= "ML";		/* toggle N:ML:HTML */
	$PRIVATE_MSG_SMILEY	= "Y";		/* boolean */
	$PRIVATE_IMAGES		= "Y";		/* boolean */
	$MAX_PMSG_FLDR_SIZE	= "300000";	/* bytes */

	$ALLOW_SIGS		= "Y";		/* boolean */
	$FORUM_CODE_SIG		= "ML";		/* toggle N:ML:HTML */
	$FORUM_SML_SIG		= "Y";		/* boolean */
	$FORUM_IMG_SIG		= "Y";		/* boolean */
	$FORUM_IMG_CNT_SIG	= "2";		/* int */
	
	$FORUM_ENABLED		= "Y";		/* boolean */

	$UNCONF_USER_EXPIRY	= "7";		/* days */
	$MOVED_THR_PTR_EXPIRY	= "3";		/* days */

	$USE_ALIASES		= "N";		/* boolean */
	$MULTI_HOST_LOGIN	= "N";		/* boolean */
	$MAX_SMILIES_SHOWN	= "15"; 	/* int */
	$ALLOW_REGISTRATION	= "Y";		/* boolean */
	$EMAIL_CONFIRMATION	= "Y";		/* boolean */
	$SPELL_CHECK_ENABLED	= "Y";		/* boolean */
	$PUBLIC_RESOLVE_HOST	= "N";		/* boolean */
	$ACTION_LIST_ENABLED	= "Y";		/* boolean */
	$DISABLED_REASON	= "Temporarily offline; please come back soon!";
	$COPPA			= "Y"; 		/* boolean */
	$POSTS_PER_PAGE 	= "40";
	$THREADS_PER_PAGE	= "40";
	$WORD_WRAP		= "60";
	$NOTIFY_FROM		= "";		/* email */
	$NOTIFY_WITH_BODY	= "N";		/* boolean */
	$ANON_NICK		= "Anonymous Coward";
	$FLOOD_CHECK_TIME	= "60";		/* seconds */
	$ALLOW_EMAIL		= "Y";		/* boolean */
	$SERVER_TZ		= "Canada/Eastern";
	$MEMBER_SEARCH_ENABLED	= "Y";		/* boolean */
	$FORUM_SEARCH		= "Y";		/* boolean */
	$MEMBERS_PER_PAGE	= "40";
	$POLLS_PER_PAGE		= "40";
	$THREAD_MSG_PAGER	= "5";
	$GENERAL_PAGER_COUNT	= "15";
	$SHOW_EDITED_BY		= "Y";		/* boolean */
	$EDITED_BY_MOD		= "Y";		/* boolean */
	$EDIT_TIME_LIMIT	= "0";
	$DISPLAY_IP		= "N";		/* boolean */
	$LOGEDIN_TIMEOUT	= "5";		/* minutes */
	$MAX_IMAGE_COUNT	= "10";
	$LOGEDIN_LIST		= "Y";		/* boolean */
	$PUBLIC_STATS		= "Y";		/* boolean */
	$FORUM_TITLE		= "";
	$SITE_HOME_PAGE		= "";
	$MAX_LOGIN_SHOW		= "25";
	$MAX_LOCATION_SHOW	= "25";
	$DEFAULT_THREAD_VIEW 	= "msg";    	/* toggle msg:tree */
	$SHOW_N_MODS		= "2";
	
	$FORUM_INFO		= "Y";		/* boolean */
	$ONLINE_OFFLINE_STATUS	= "Y";		/* boolean */

	$FILE_LOCK		= "Y";		/* boolean */
/* 
 * DO NOT EDIT FILE BEYOND THIS POINT UNLESS YOU KNOW WHAT YOU ARE DOING
 */
	
	$GLOBALS['__GLOBALS.INC__'] = $GLOBALS["INCLUDE"].'GLOBALS.php';
	include_once $GLOBALS["INCLUDE"].'core.inc';
?>