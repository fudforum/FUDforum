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
	var	$id, $botname, $useragent, $theme, $bot_ip, $bot_opts;

	function __construct()
	{
		foreach ($this as $k => $v) {
			if (isset($_POST['spider_'. $k])) {
				$this->{$k} = $_POST['spider_'. $k];
			}
		}
	}

	/** Add a new spider. */
	function add()
	{
		// We need to create a FUDforum user for this bot.
		fud_use('users_reg.inc');
		if (get_id_by_login($this->botname)) {
			return -1;	// Cannot add user, already in DB.
		}
		$user = new fud_user_reg;
		$user->login     = $this->botname;
		$user->name      = $this->botname;
		$user->email     = $this->botname .'@fud_spiders';
		$user->theme     = (int)$this->theme;
		$user->users_opt = -1;
		$user_id = $user->add();

		// Set 'is_spider' for the user.
		q('UPDATE '. $GLOBALS['DBHOST_TBL_PREFIX'] .'users SET users_opt = '. q_bitand('users_opt', 1073741824) .' WHERE id = '. $user_id);

		// Tag the user as a spider.
		q('INSERT INTO '. $GLOBALS['DBHOST_TBL_PREFIX'] .'custom_tags (name, user_id) VALUES(\'Web crawler\', '. $user_id .')');

		// Insert spider definition.
		$this->id = db_qid('INSERT INTO '. $GLOBALS['DBHOST_TBL_PREFIX'] .'spiders (
			botname,
			useragent,
			bot_ip,
			user_id,
			theme,
			bot_opts
		) VALUES (
			'. ssn($this->botname) .',
			'. ssn($this->useragent) .',
			'. ssn($this->bot_ip) .',
			'. $user_id .',
			'. (int)$this->theme .',
			'. (int)$this->bot_opts .'
		)');

		fud_spider::rebuild_cache();
		return $this->id;
	}

	/** Change a spider. */
	function sync($id)
	{
		q('UPDATE '. $GLOBALS['DBHOST_TBL_PREFIX'] .'spiders SET
			botname='.   ssn($this->botname) .',
			useragent='. ssn($this->useragent) .',
			bot_ip='.    ssn($this->bot_ip) .',
			theme='.     (int)$this->theme .',
			bot_opts='.  (int)$this->bot_opts .'
		WHERE id='. (int)$id);

		// Sync theme with users table.
		$user_id = q_singleval('SELECT user_id FROM '. $GLOBALS['DBHOST_TBL_PREFIX'] .'spiders WHERE id = '. (int)$id);
		q('UPDATE '. $GLOBALS['DBHOST_TBL_PREFIX'] .'users SET theme = '. (int)$this->theme .' WHERE id = '. $user_id);

		fud_spider::rebuild_cache();
		return $id;
	}

	/** Remove a spider. */
	static function delete($id)
	{
		// Remove bot's base user.
		fud_use('users_reg.inc');
		$user_id = q_singleval('SELECT user_id FROM '. $GLOBALS['DBHOST_TBL_PREFIX'] .'spiders WHERE id = '. (int)$id);
		fud_user_reg::delete($user_id);

		// Remove bot definition.
		q('DELETE FROM '. $GLOBALS['DBHOST_TBL_PREFIX'] .'spiders WHERE id = '. (int)$id);

		fud_spider::rebuild_cache();
	}

	/** Build or rebuild the spider cache. */
	static function rebuild_cache() {
		$spiders = NULL;

		// Only for enabled bots, from the longest to the shortest.
		$c = uq('SELECT * FROM '. $GLOBALS['DBHOST_TBL_PREFIX'] .'spiders WHERE '. q_bitand('bot_opts', 1) .' != 1 ORDER BY LENGTH(useragent) DESC');
		while ($r = db_rowobj($c)) {
			$id = $r->id;

			// Unset what we don't need.
			unset($r->id);

			$spiders[ $id ] = (array)$r;
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
