<?php
	$cn = pg_connect("user=pgsql dbname=pgsql");
	$r = pg_query($cn, "select relname from pg_class WHERE relkind='S'");
	while ( $obj = pg_fetch_object($r) ) {
		if ( substr($obj->relname, 0, 4) == "fud_" ) {
			if ( !pg_query("DROP SEQUENCE $obj->relname") ) {
				echo pg_error();
			}
			
		}
	}
	pg_free_result($r);
	
	$r = pg_query($cn, "select relname from pg_class WHERE relkind='r'");
	while ( $obj = pg_fetch_object($r) ) {
		if ( substr($obj->relname, 0, 4) == "fud_" ) {
			if ( !pg_query("DROP TABLE $obj->relname") ) {
				echo pg_error();
			}
			
		}
	}
	pg_free_result($r);
	
?>