<?
	$cn = pg_connect("user=pgsql dbname=pgsql");
	$r = pg_query($cn, "select relname from pg_class WHERE relkind='r'");
	while ( $obj = pg_fetch_object($r) ) {
		if ( substr($obj->relname, 0, 4) == "fud_" ) {
			echo "Vacuuming: $obj->relname\n";
			pg_query("VACUUM ANALYZE $obj->relname");
		}
	}
	pg_free_result($r);
	
?>