<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: imsg.inc.t,v 1.2 2002/06/18 18:26:09 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

	
class fud_msg
{
	var $id=NULL;
	var $thread_id=NULL;
	var $poster_id=NULL;
	var $reply_to=NULL;
	var $ip_addr=NULL;
	var $host_name=NULL;
	var $post_stamp=NULL;
	var $subject=NULL;
	var $attach_cnt=NULL;
	var $poll_id=NULL;
	var $update_stamp=NULL;
	var $icon=NULL;
	var $approved=NULL;
	var $show_sig=NULL;
	var $updated_by=NULL;
	var $smiley_disabled=NULL;
	var $login=NULL;
	var $length=NULL;
	var $offset=NULL;
	var $file_id=NULL;
	var $file_id_preview=NULL;
	var $length_preview=NULL;
	var $offset_preview=NULL;
	
	var $body;
		
	function get_by_id($id)
	{
		qobj("SELECT {SQL_TABLE_PREFIX}msg.*,{SQL_TABLE_PREFIX}users.login FROM {SQL_TABLE_PREFIX}msg LEFT JOIN {SQL_TABLE_PREFIX}users ON {SQL_TABLE_PREFIX}msg.poster_id={SQL_TABLE_PREFIX}users.id WHERE {SQL_TABLE_PREFIX}msg.id=".$id, $this);
		if( empty($this->id) ) error_dialog('{TEMPLATE: imsg_err_message_title}', '{TEMPLATE: imsg_err_message_msg}', '', 'FATAL');
		
		$this->body = read_msg_body($this->offset,$this->length, $this->file_id);
		un_register_fps();
	}
	
	function get_thread($th_id)
	{
		qobj("SELECT {SQL_TABLE_PREFIX}msg.* FROM {SQL_TABLE_PREFIX}msg INNER JOIN {SQL_TABLE_PREFIX}thread ON {SQL_TABLE_PREFIX}thread.root_msg_id={SQL_TABLE_PREFIX}msg.id WHERE {SQL_TABLE_PREFIX}thread.id=".$th_id, $this);
		
		
		$this->body = read_msg_body($this->offset,$this->length, $this->file_id);
		un_register_fps();
	}
}

if ( defined('msg_edit') && !defined("_imsg_edit_inc_") ) fud_use('imsg_edt.inc');
?>