<?php

function create_archives($dir, $version, $upgrade, $comp, $convs='')
{
	global $TAR_BIN, $ZIP_BIN, $OUT_DIR;
	global $db;

	if (!$convs) {
		$name = "FUDforum_";
		if ($upgrade) {
			$name .= "upgrade_";
		}
		if ($comp) {
			$name .= "zl_";
		}
		$name .= $version;
	} else {
		$name = $convs;
	}

	/* gzip */
	shell_exec("{$TAR_BIN} zcvf {$OUT_DIR}{$name}.tar.gz {$dir} >/dev/null");
	
	/* bzip2 */
	shell_exec("{$TAR_BIN} ycvf {$OUT_DIR}{$name}.tar.bz2 {$dir} >/dev/null");
	
	/* zip */
	shell_exec("{$ZIP_BIN} {$OUT_DIR}{$name}.zip {$dir}/* >/dev/null");

	sqlite_query($db, "INSERT INTO fud_down_md5 (file_name, md5_checksum) VALUES('".sqlite_escape_string("{$name}.tar.gz")."', '".md5_file("{$OUT_DIR}{$name}.tar.gz")."')") or die(sqlite_error_string(sqlite_last_error($db)));
	sqlite_query($db, "INSERT INTO fud_down_md5 (file_name, md5_checksum) VALUES('".sqlite_escape_string("{$name}.tar.bz2")."', '".md5_file("{$OUT_DIR}{$name}.tar.bz2")."')") or die(sqlite_error_string(sqlite_last_error($db)));
	sqlite_query($db, "INSERT INTO fud_down_md5 (file_name, md5_checksum) VALUES('".sqlite_escape_string("{$name}.zip")."', '".md5_file("{$OUT_DIR}{$name}.zip")."')") or die(sqlite_error_string(sqlite_last_error($db)));

	return $name;
}

function get_zlib_support()
{
	$tmp = file_get_contents("./create_file_list");
	if (strpos($tmp, "extension_loaded('zlib')")) {
		return 1;
	} else {
		return 0;
	}
}

function tag_to_version($str)
{
	return str_replace('fud', '', str_replace('_', '-', $str));
}

function version_to_name($str)
{
	preg_match('!([-0-9_]+)(RC([0-9])+)?([a-z]+)?!', $str, $res);
	$name = "FUDforum v" . str_replace('-', '.', $res[1]);
	if (isset($res[3])) {
		$name .= " Release Candidate #".$res[3];
		if (isset($res[4])) {
			switch ($res[4]) {
				case 'a':
					$name .= " Alpha";
					break;
				case 'b':
					$name .= " Beta";
					break;
			}
		}
	}

	return $name;		
}

	$CVS_BIN = "/usr/local/bin/cvs";
	$TAR_BIN = "/bin/tar";
	$ZIP_BIN = "/usr/bin/zip";
	$PHP_BIN = "/usr/local/bin/php";
	$RM_BIN	 = "/bin/rm";

	$CVS_PTH = ":pserver:anonymous@asuka.prohost.org:/forum21";

	$OUT_DIR = "";
	$DB_DIR  = "";
	$TMP_DIR = "/tmp/";

	$CONV_SCRIPTS = array(
'yabbTOfud2' 	=> array('Yabb', '2.3.X'),
'yabbdcTOfud2' 	=> array('Yabb DC', '2.3.X'),
'phorumTOfud2'	=> array('Phorum', '2.3.X'),
'phpBB2TOfud2'	=> array('phpBB2', '2.3.X'),
'openbbTOfud2'	=> array('OpenBB', '2.3.X'),
'vb2TOfud2'	=> array('VBulletin 2', '2.3.X'),
'ikonTOfud2'	=> array('IkonBoard (mysql)', '2.3.X'),
'wbbTOfud2'	=> array('WoltLab Bulletin Board', '2.3.X'),
'xmbTOfud2'	=> array('XMB 1.8+', '2.5.0+')
);

	/* parse tags */
	$data = shell_exec("{$CVS_BIN} -d {$CVS_PTH} log -h install.php");
	preg_match_all("!\t(fud[A-Za-z0-9_]+): !", $data, $res);
	foreach ($res[1] as $r) {
		$tags[] = trim($r);
	}

	$dir = getcwd();
	chdir($TMP_DIR);

	$nt = 0;
	$db = sqlite_open("{$DB_DIR}cvs.sqlite", 0666);
	
	/* determine which tags are new */
	foreach ($tags as $tag) {
		if (!sqlite_single_query($db, "SELECT id FROM fud_down WHERE tag='".sqlite_escape_string($tag)."'")) {
echo "TAG $tag\n";
			/* create install script */
			shell_exec("{$RM_BIN} -rf ./fud21_install");
			shell_exec("{$CVS_BIN} -z9 -d {$CVS_PTH} co -r{$tag} fud21_install >/dev/null 2>&1");
			chdir("./fud21_install");

			/* get release date */
			if (!($release_date = @filemtime("./install/forum_data/include/core.inc"))) {
				$release_date = filemtime("./install/forum_data/src/core.inc.t");
			}

			/* determine if this version supports zlib compression */
			$is_zlib = get_zlib_support();

			/* generate necessary variables */
			$version = tag_to_version($tag);
			$name = version_to_name($version);
			$is_beta = (strpos($tag, 'RC') === FALSE) ? 0 : 1;

			/* create distribution directory */
			mkdir("FUDforum2", 0755);
			rename("./COPYING", 		"./FUDforum2/COPYING");
			rename("./CREDITS", 		"./FUDforum2/CREDITS");
			rename("./README", 		"./FUDforum2/README");
			if (@file_exists("./uninstall.php")) {
				rename("./uninstall.php", 	"./FUDforum2/uninstall.php");
			}

			if ($is_zlib) {
				copy("./install.php", "./FUDforum2/install.php");
				shell_exec("{$PHP_BIN} -q create_file_list install >> FUDforum2/install.php");
				
				create_archives("FUDforum2/", $version, 0, 1);
				unlink("./FUDforum2/install.php");
			}
			rename("./install.php", 	"./FUDforum2/install.php");
			shell_exec("{$PHP_BIN} -q create_file_list install >> FUDforum2/install.php");

			$ar = create_archives("FUDforum2/", $version, 0, 0);

			sqlite_query($db, "INSERT INTO fud_down
						(release_date, name, is_zlib, is_beta, ar_name, tag)
					VALUES 
						({$release_date}, '".sqlite_escape_string($name)."', {$is_zlib}, 
						{$is_beta}, '".sqlite_escape_string($ar)."', 
						'".sqlite_escape_string($tag)."')") or die(sqlite_error_string(sqlite_last_error($db)));

			/* handle upgrade script */
			shell_exec("{$RM_BIN} -rf ./fud21_upgrade");
			shell_exec("{$CVS_BIN} -z9 -d {$CVS_PTH} co -r{$tag} fud21_upgrade >/dev/null 2>&1");
			chdir("./fud21_upgrade");
			
			/* create distribution directory */
			mkdir("FUDforum2", 0755);
			rename("./COPYING", 		"./FUDforum2/COPYING");
			rename("./CREDITS", 		"./FUDforum2/CREDITS");
			rename("./UPGRADE_README", 	"./FUDforum2/UPGRADE_README");

			if ($is_zlib) {
				copy("./upgrade.php", "./FUDforum2/upgrade.php");
				shell_exec("{$PHP_BIN} -q create_file_list install >> FUDforum2/upgrade.php");
				
				create_archives("FUDforum2/", $version, 1, 1);
				unlink("./FUDforum2/upgrade.php");
			}
			rename("./upgrade.php", 	"./FUDforum2/upgrade.php");
			shell_exec("{$PHP_BIN} -q create_file_list install >> FUDforum2/install.php");

			create_archives("FUDforum2/", $version, 1, 0);
			
			chdir($TMP_DIR);		
			++$nt;
		}
	}

	/* cleanup */
	shell_exec("{$RM_BIN} -rf ./fud21_install ./fud21_upgrade");

	if ($nt) {
		echo "Imported {$nt} tags\n";

		/* make sure that the dates of the releases are correct */
		chdir($dir);
		$dt = $tags2 = array();
		$data = shell_exec("{$CVS_BIN} -d {$CVS_PTH} log -h upgrade.php");
		preg_match_all("!\t(fud[A-Za-z0-9_]+): ([0-9.]+)!", $data, $res2);
		foreach ($res2[1] as $k=> $r) {
			$tags2[trim($res2[2][$k])] = trim($r);
		}

		$data = explode('----------------------------', shell_exec("{$CVS_BIN} -d {$CVS_PTH} log -N upgrade.php"));
		foreach ($data as $ent) {
			if (preg_match("!revision ([0-9.]+)\s+date: ([^;]+);!", $ent, $m)) {
				if (isset($tags2[$m[1]])) {
					$dt[$tags2[$m[1]]] = strtotime($m[2]);
				}
			}
		}

		foreach ($dt as $k => $v) {
			sqlite_query($db, "UPDATE fud_down SET release_date={$v} WHERE tag='".sqlite_escape_string($k)."'");
		}
		unset($data, $dt, $tags2);

		/* handle conversion scripts */
		foreach ($CONV_SCRIPTS as $c => $d) {
			shell_exec("{$RM_BIN} -rf ./{$c}");
			shell_exec("{$CVS_BIN} -z9 -d {$CVS_PTH} co {$c} >/dev/null 2>&1");
			sqlite_query($db, "DELETE FROM fud_down_md5 WHERE file_name LIKE '".sqlite_escape_string($c)."%'");
			create_archives("{$c}/", 0, 0, 0, $c);

			/* new conversion script */
			if (!sqlite_single_query($db, "SELECT id FROM fud_conv WHERE tag='".sqlite_escape_string($c)."'")) {
				sqlite_query($db, "INSERT INTO fud_conv (name, ar_name, tag, conv_version) VALUES(
						'".sqlite_escape_string($d[0])." Conversion Script',
						'".sqlite_escape_string($c)."',
						'".sqlite_escape_string($c)."',
						'".sqlite_escape_string($d[1])."'
					)") or die(sqlite_error_string(sqlite_last_error($db)));

				echo "Added {$c} conversion script\n";
			}
			shell_exec("{$RM_BIN} -rf ./{$c}");
		}
	} else {
		echo "!!!WARNING!!!\nNothing to do, forget to tag a release? ;)\n";
	}

	sqlite_query($db, 'VACUUM fud_down; VACUUM fud_down_md5; VACUUM fud_conv');
	sqlite_close($db);

/*

// 1.2.8 -> 2.0.2
sqlite_query($db, "INSERT INTO fud_down_md5 (file_name, md5_checksum) VALUES('FUDforum2_oldupgrade_20020613.tar.gz', 'bae4b9112a1416bbcada025b34efae4a')") or die(sqlite_error_string(sqlite_last_error($db)));
sqlite_query($db, "INSERT INTO fud_down_md5 (file_name, md5_checksum) VALUES('FUDforum2_oldupgrade_20020613.tar.bz2', '52f2b051270c20de01ef31b9c12c14a4')") or die(sqlite_error_string(sqlite_last_error($db)));
sqlite_query($db, "INSERT INTO fud_down_md5 (file_name, md5_checksum) VALUES('FUDforum2_oldupgrade_20020613.zip', 'eca6fe3abb6d80b77f4e2d26c5a51849')") or die(sqlite_error_string(sqlite_last_error($db)));
sqlite_query($db, "INSERT INTO fud_down (release_date, name, is_zlib, is_beta, ar_name, tag) VALUES (1023980698, 'FUDforum 1.2.8 -> 2.0.2 Conversion Script', 0, 0, 'FUDforum2_oldupgrade_20020613', 'NONE')") or die(sqlite_error_string(sqlite_last_error($db)));

// 1.0.X -> 1.2.8
sqlite_query($db, "INSERT INTO fud_down_md5 (file_name, md5_checksum) VALUES('update-v1.2.8.php.tar.gz', '2e148a1df95c08804c58c6c817ce2f57')") or die(sqlite_error_string(sqlite_last_error($db)));
sqlite_query($db, "INSERT INTO fud_down_md5 (file_name, md5_checksum) VALUES('update-v1.2.8.php.tar.bz2', '674884c32f12f874a00b356d5f759a8f')") or die(sqlite_error_string(sqlite_last_error($db)));
sqlite_query($db, "INSERT INTO fud_down_md5 (file_name, md5_checksum) VALUES('update-v1.2.8.php.zip', '2e433bc7cddcec056e019552778fe430')") or die(sqlite_error_string(sqlite_last_error($db)));
sqlite_query($db, "INSERT INTO fud_down (release_date, name, is_zlib, is_beta, ar_name, tag) VALUES (1020439248, 'FUDforum 1.0.X -> 1.2.8 Conversion Script', 0, 0, 'update-v1.2.8.php', 'NONE2')") or die(sqlite_error_string(sqlite_last_error($db)));

// conversion stuff
sqlite_query($db, "CREATE TABLE fud_conv (
					id		INTEGER PRIMARY KEY,
					name		VARCHAR(230),
					ar_name		VARCHAR(230),
					tag		VARCHAR(230),
					conv_version	VARCHAR(230))");


sqlite_query($db, "DELETE FROM fud_down; DELETE FROM fud_down_md5");

sqlite_query($db, 'DROP TABLE fud_down; DROP TABLE fud_down_md5');
sqlite_query($db, 'CREATE TABLE fud_down ( 
					id		INTEGER PRIMARY KEY,
					release_date	BIGINT,
					name		VARCHAR(230),
					is_zlib		INTEGER DEFAULT 1,
					is_beta		INTEGER DEFAULT 0,
					ar_name		VARCHAR(230),
					tag		VARCHAR(50) NOT NULL)');

sqlite_query($db, 'CREATE INDEX fud_down_tag_idx ON fud_down(tag)');

sqlite_query($db, 'CREATE TABLE fud_down_md5 (
	file_name 	VARCHAR(230),
	md5_checksum	CHAR(32))');

sqlite_query($db, 'CREATE INDEX fud_down_md5_idx ON fud_down_md5(md5_checksum)');
*/	


?>