<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: ipfilter.inc.t,v 1.4 2002/12/05 20:56:04 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/


class fud_ip_filter
{
	var $id;
	var $ip;
	
	var $iplist;
	
	function mk_mask($ipaddr)
	{
		for ( $i=0; $i<4; $i++ ) $ip[$i] = 256;
		$seg = explode('.', $ipaddr);
		for ( $i=0; $i<count($seg); $i++ ) {
			if ( $seg[$i] == '*' ) 
				$ip[$i] = 256;
			else
				$ip[$i] = $seg[$i];
		}
		
		return $ip;
	}
	
	function is_blocked($ipaddr)
	{
		$ip = $this->mk_mask($ipaddr);		
		return bq("SELECT id FROM 
			{SQL_TABLE_PREFIX}ip_block 
				WHERE 
					ca=".$ip[0]." 
					AND 
					cb IN(".$ip[1].", 256) 
					AND cc IN(".$ip[2].", 256) 
					AND cd IN(".$ip[3].", 256) 
				LIMIT 1");
	}

}
?>