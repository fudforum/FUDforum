<?php
/**
* copyright            : (C) 2001-2011 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id$
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; version 2 of the License.
**/

class fud_spider
{
	var	$id, $botname, $useragent, $bot_ip, $bot_opts;

	function __construct()
	{
		foreach ($this as $k => $v) {
			if (isset($_POST['spider_'. $k])) {
				$this->{$k} = $_POST['spider_'. $k];
			}
		}
	}

	function add()
	{
		$this->id = db_qid('INSERT INTO '. $GLOBALS['DBHOST_TBL_PREFIX'] .'spiders (
			botname,
			useragent,
			bot_ip,
			bot_opts
		) VALUES (
			'. ssn($this->botname) .',
			'. ssn($this->useragent) .',
			'. ssn($this->bot_ip) .',
			'. (int)$this->$bot_opts .'
		)');
		$this->rebuild_cache();
		return $this->id;
	}

	function sync($id)
	{
		q('UPDATE '. $GLOBALS['DBHOST_TBL_PREFIX'] .'spiders SET
			botname='.   ssn($this->botname) .',
			useragent='. ssn($this->useragent) .',
			bot_ip='.    ssn($this->bot_ip) .',
			bot_opts='.  (int)$this->bot_opts .'
		WHERE id='. (int)$id);
		$this->rebuild_cache();
	}

	static function delete($id)
	{
		q('DELETE FROM '. $GLOBALS['DBHOST_TBL_PREFIX'] .'spiders WHERE id = '. (int)$id);
		fud_spider::rebuild_cache();
	}

	static function rebuild_cache() {
		$spiders = NULL;

		// Only enabled bots.
		$c = uq('SELECT * FROM '. $GLOBALS['DBHOST_TBL_PREFIX'] .'spiders WHERE '. q_bitand('bot_opts', 1) .' != 1');
		while ($r = db_rowobj($c)) {
			$spiders[ $r->id ] = (array)$r;
		}
		unset($c);

		$fp = fopen($GLOBALS['TMP'] .'spider_cache', 'w');
		fwrite($fp, '<?php $spider_cache = '. var_export((array)$spiders, 1) .'; ?>');
		fclose($fp);

		if (!strncasecmp('win', PHP_OS, 3)) {
			@unlink($GLOBALS['FORUM_SETTINGS_PATH'] .'spider_cache');
		}
		rename($GLOBALS['TMP'] .'spider_cache', $GLOBALS['FORUM_SETTINGS_PATH'] .'spider_cache');
	}
}
?>
