<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: imsg.inc.t,v 1.6 2003/03/31 11:29:59 hackie Exp $
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
	var $id, $thread_id, $poster_id, $reply_to, $ip_addr, $host_name, $post_stamp, $subject, $attach_cnt, $poll_id, 
	    $update_stamp, $icon, $approved, $show_sig, $updated_by, $smiley_disabled, $login, $length, $foff, $file_id,
	    $file_id_preview, $length_preview, $offset_preview, $body, $mlist_msg_id;
		
	function get_by_id($id)
	{
		qobj('SELECT {SQL_TABLE_PREFIX}msg.*,{SQL_TABLE_PREFIX}users.alias AS login FROM {SQL_TABLE_PREFIX}msg LEFT JOIN {SQL_TABLE_PREFIX}users ON {SQL_TABLE_PREFIX}msg.poster_id={SQL_TABLE_PREFIX}users.id WHERE {SQL_TABLE_PREFIX}msg.id='.$id, $this);
		if (!$this->id) {
			error_dialog('{TEMPLATE: imsg_err_message_title}', '{TEMPLATE: imsg_err_message_msg}', '', 'FATAL');
		}
		
		$this->body = read_msg_body($this->foff, $this->length, $this->file_id);
		un_register_fps();
	}
	
	function get_thread($th_id)
	{
		qobj('SELECT {SQL_TABLE_PREFIX}msg.* FROM {SQL_TABLE_PREFIX}msg INNER JOIN {SQL_TABLE_PREFIX}thread ON {SQL_TABLE_PREFIX}thread.root_msg_id={SQL_TABLE_PREFIX}msg.id WHERE {SQL_TABLE_PREFIX}thread.id='.$th_id, $this);
		
		$this->body = read_msg_body($this->foff, $this->length, $this->file_id);
		un_register_fps();
	}
}

if (defined('msg_edit') && !defined('_imsg_edit_inc_')) {
	fud_use('imsg_edt.inc');
}
?>