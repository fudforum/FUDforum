#!/usr/bin/php -q
<?php
/**
* copyright            : (C) 2001-2012 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id$
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; version 2 of the License.
**/

/** This is a PHP forked daemon.
 *  Standalone PHP binary must be compiled with --enable-sockets and --enable-pcntl
 */

// define('fud_debfud_debug', 1);

function send_command($cmd, $verbose=true)
{
	global $server;

	$cmd = trim($cmd);
	if (!empty($cmd)) {
		@fwrite($server['SOCKET'], $cmd ."\r\n");
	}

	if (defined('fud_debug')) $verbose=true;	// Force to true for debuging.
	if ($verbose) {
		echo '[SEND] '. $cmd ."\n";
	}
}

function sig_handler($signo)
{
	switch ($signo) {
	case SIGTERM:
	case SIGSTOP:
	case SIGKILL:
	case SIGINT:
		// Shut down.
		send_command('QUIT');
		sleep(1);
		exit;
		break;
	case SIGHUP:
		// Restart.
	default:
		// Handle all other signals.
	}
}

/* main */
	declare(ticks=1);

	@ini_set('memory_limit', '128M');
	@set_time_limit(0);
	define('no_session', 1);

	$pid = pcntl_fork();
	if ($pid == -1) {
		die('Could not fork!');
	} else if ($pid) {
		// We are the parent, exit.
		exit();
	} else {
		// We are the child.
	}

	// Detatch from the controlling terminal.
	if (posix_setsid() == -1) {
		die('Could not detach from terminal.');
	}

	// Setup signal handlers.
	@pcntl_signal(SIGTERM, 'sig_handler');
	@pcntl_signal(SIGSTOP, 'sig_handler');
	@pcntl_signal(SIGKILL, 'sig_handler');
	@pcntl_signal(SIGINT,  'sig_handler');
	@pcntl_signal(SIGHUP,  'sig_handler');

	// Load GLOBALS.php.
	if (strncmp($_SERVER['argv'][0], '.', 1)) {
		require (dirname($_SERVER['argv'][0]) .'/GLOBALS.php');
	} else {
		require (getcwd() .'/GLOBALS.php');
	}

	// Include DB driver.
	fud_use('err.inc');
	fud_use('db.inc');

	// Acquire lock to prevent concurrent bot runs.
	$lk = fopen($GLOBALS['TMP'] .'ircbot' , 'wb');
	if (!flock($lk, LOCK_EX|LOCK_NB)) {
		echo "IRCbot is already running. Exiting...\n";
		fclose($lk); 
		exit();
	}

	// Read config as defined by ircbot.plugin.
	if((@include_once $GLOBALS['PLUGIN_PATH'] .'/ircbot/ircbot.ini') === false) {
		die('Please configure ircbot.plugin before using this script.');
	}

	// Connect to IRC server.
	$server = array();
	$server['SOCKET'] = fsockopen($ini['IRCBOT_HOST'], $ini['IRCBOT_PORT'], $errno, $errstr, 2);
	if (!$server['SOCKET']) {
		die("IRC ERROR: $errstr ($errno)<br />");
	}

	// Login and join channel.
	if (!empty($ini['IRCBOT_NICK'])) {
		send_command('PASS NOPASS');
		send_command('NICK '. $ini['IRCBOT_NICK']);
		send_command('USER '. $ini['IRCBOT_NICK'] .' '. $ini['IRCBOT_HOST'] .' bla :'. $FORUM_TITLE);
	}
	if (!empty($ini['IRCBOT_CHANNEL'])) {
		send_command('JOIN '. $ini['IRCBOT_CHANNEL']); // Join the chanel.
	}

	// Play commands from ircbot.rc.
	if (file_exists(dirname($_SERVER['argv'][0]) .'/ircbot.rc')) {
		foreach (file(dirname($_SERVER['argv'][0]) .'/ircbot.rc') as $line) {
			send_command($line);
		}
	}

	// Announce ourselves.
	send_command('PRIVMSG '. $ini['IRCBOT_CHANNEL'] .' :I\'m your forum bot from '. $GLOBALS['WWW_ROOT']);
	send_command('PRIVMSG '. $ini['IRCBOT_CHANNEL'] .' :My job is to announce new forum topics and replies on this channel.');
	send_command('PRIVMSG '. $ini['IRCBOT_CHANNEL'] .' :For help enter "!help"');

	// While we are connected to the server.
	while(!feof($server['SOCKET'])) {
		// Get line of data from server.
		$line  = fgets($server['SOCKET'], 1024);
		$parts = explode(' ', $line);

		// Play ping-pong with the server to stay connected.
		if ($parts[0] == 'PING') {
			send_command('PONG '. $parts[1], false);
			$parts = null;
		}

		// Check if we have pending announcements.
		if (file_exists($GLOBALS['PLUGIN_PATH'] . 'ircbot/ircbot.pending')) {
			$anns = file($GLOBALS['PLUGIN_PATH'] . 'ircbot/ircbot.pending');
			foreach ($anns as $ann) {
				send_command('PRIVMSG '. $ini['IRCBOT_CHANNEL'] .' :'. $ann);
			}
			@unlink($GLOBALS['PLUGIN_PATH'] . 'ircbot/ircbot.pending');
		}

		// See if we received a command.
		if (isset($parts[3]) ) {
			$cmd = str_replace(array(chr(10), chr(13)), '', $parts[3]);
		} else {
			continue;
		}

		// Logging.
		if ($parts[1] == 'PRIVMSG') {
			$nick = $parts[2];
			// $nick = substr($nick, 0, strpos($nick, '!'));
			$msg  = implode(' ', array_slice($parts, 3));
			echo '['. date('H:i:s') .'] '. $nick .' '. $msg;
		}

		switch($cmd) {
		case ':!about':
			send_command('PRIVMSG '. $parts[2] .' :'. $GLOBALS['FORUM_TITLE']);
			send_command('PRIVMSG '. $parts[2] .' :'. $GLOBALS['FORUM_DESCR']);
			send_command('PRIVMSG '. $parts[2] .' :'. $GLOBALS['WWW_ROOT']);
		case ':!join':
			send_command('JOIN '. $parts[4]);
			break;
		case ':!part':
			send_command('PART '. $parts[4] .' :'. 'Bye');
			break;
		case ':!say':
			array_splice($parts, 0, 4);
			send_command('PRIVMSG '. $parts[2] .' :'. implode(' ', $parts));
			break;
		case ':!now':
		case ':!date':
		case ':!time':
			send_command('PRIVMSG '. $parts[2] .' :Date and time now is '. date('F j, Y, g:i a'));
			break;
		case ':!Hello':
		case ':!Hi':
			send_command('PRIVMSG '. $parts[2] .' : Hi ');
			break;
		case ':!help':
			send_command('PRIVMSG '. $parts[2] .' :Available commands:');
			send_command('PRIVMSG '. $parts[2] .' :!about -- more info about me');
			send_command('PRIVMSG '. $parts[2] .' :!exit -- terminate me');
			send_command('PRIVMSG '. $parts[2] .' :!now -- date and time now');
			send_command('PRIVMSG '. $parts[2] .' :!status -- current status of your forum');
			break;
		case ':!status':
			$stat = db_sab('SELECT * FROM '. $GLOBALS['DBHOST_TBL_PREFIX'] .'stats_cache');
			$user = q_singleval('SELECT alias FROM '. $GLOBALS['DBHOST_TBL_PREFIX'] .'users WHERE id = (SELECT MAX(id) FROM '. $GLOBALS['DBHOST_TBL_PREFIX'] .'users)');
			$subj = q_singleval('SELECT subject FROM '. $GLOBALS['DBHOST_TBL_PREFIX'] .'msg WHERE id = (SELECT MAX(id) FROM '. $GLOBALS['DBHOST_TBL_PREFIX'] .'msg)');
			send_command('PRIVMSG '. $parts[2] ." :There are {$stat->online_users_reg} members, {$stat->online_users_hidden} invisible members and {$stat->online_users_anon} guests visiting the board.");
			send_command('PRIVMSG '. $parts[2] ." :Most users ever online was {$stat->most_online} on ". strftime('%a, %d %B %Y %H:%M', $stat->most_online_time));
			send_command('PRIVMSG '. $parts[2] ." :We have {$stat->user_count} registered users.");
			send_command('PRIVMSG '. $parts[2] ." :The newest registered user is {$user}");
			send_command('PRIVMSG '. $parts[2] ." :Last message on the forum: {$subj}");
			break;
		case ':!exit':
		case ':!die':
		case ':!quit':
		case ':!shutdown':
			send_command('QUIT :Terminated by user request');
			break 2;
		}

		// Call IRC plugins.
		if (defined('plugins')) {
			plugin_call_hook('IRCCOMMAND', $parts);
		}

		flush();	// Force output.
	}

	fclose($lk);	// Release lock.
	echo "FUDbot shuts down.\n";
?>

