<?
	$cn = pg_connect("user=pgsql dbname=pgsql");
	$r = pg_query($cn, "select relname from pg_class WHERE relkind='r'");
	while ( $obj = pg_fetch_object($r) ) {
		if ( substr($obj->relname, 0, 4) == "fud_" ) {
			$qry = "SELECT max(id) AS maxid FROM $obj->relname";
			if ( !($r2 = @pg_query($qry)) ) {
				continue;
			}
			$obj2 = pg_fetch_object($r2);
			if ( !$obj2->maxid ) continue;
			echo "setting ".$obj->relname."_id_seq to $obj2->maxid\n";
			$qry = "SELECT setval('".$obj->relname."_id_seq', $obj2->maxid)";
			if ( !pg_query($qry) ) {
				exit("can't set ( -- $qry -- )\n");
			}
		}
	}
	pg_free_result($r);
	
?>