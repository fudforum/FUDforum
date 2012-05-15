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

/** PHP forked daemon
 * Standalone PHP binary must be compiled with --enable-sockets and --enable-pcntl
 */

function send_command($cmd, $verbose=true)
{
	global $server;
	$cmd = trim($cmd);
	@fwrite($server['SOCKET'], $cmd ."\r\n");
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
	@ini_set('memory_limit', '128M');
	@set_time_limit(0);
	define('no_session', 1);

	declare(ticks=1);

	$host    = 'chat.freenode.net';
	$port    = 6667;
	$nick    = 'fudbot';
	$ident   = 'fudbot';
	$channel = '#FUDforum';

	$pid = pcntl_fork();
	if ($pid == -1) {
		die('Could not fork!');
	} else if ($pid) {
		pcntl_wait($status);	// Protect against Zombie children.
		exit(); // we are the parent
	} else {
		// we are the child
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

	// Connect to IRC server.
	$server = array();
	$server['SOCKET'] = fsockopen($host, $port, $errno, $errstr, 2);
	if (!$server['SOCKET']) {
		die("IRC ERROR: $errstr ($errno)<br />");
	}

	// Load GLOBALS.php.
	if (strncmp($_SERVER['argv'][0], '.', 1)) {
		require (dirname($_SERVER['argv'][0]) .'/GLOBALS.php');
	} else {
		require (getcwd() .'/GLOBALS.php');
	}

	// Include DB driver.
	fud_use('err.inc');
	fud_use('db.inc');

	// Join channel.
	send_command('PASS NOPASS');
	send_command('NICK '. $nick);
	send_command('USER '. $ident .' '. $host .' bla :'. $FORUM_TITLE);
	send_command('JOIN '. $channel); // Join the chanel.

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
		if (file_exists($GLOBALS['PLUGIN_PATH'] .'irc.out')) {
			$anns = file('ircannounce.txt');
			foreach ($anns as $ann) {
				send_command('PRIVMSG '. $channel .' :'. $ann);
			}
			@unlink($GLOBALS['PLUGIN_PATH'] .'irc.out');
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
		case ':!join':
			send_command('JOIN '. $parts[4]);
			break;
		case ':!part':
			send_command('PART '. $parts[4] .' :'. 'Bye');
			break;
		case ':!say':
			array_splice($parts, 0, 4);
			send_command('PRIVMSG '. $channel .' :'. implode(' ', $parts));
			break;
		case ':!now':
		case ':!date':
		case ':!time':
			send_command('PRIVMSG '. $channel .' :Date and time now is '. date('F j, Y, g:i a'));
			break;
		case ':!help':
		case ':!status':
			$stat = db_sab('SELECT * FROM '. $GLOBALS['DBHOST_TBL_PREFIX'] .'stats_cache');
			$user = q_singleval('SELECT alias FROM '. $GLOBALS['DBHOST_TBL_PREFIX'] .'users WHERE id = (SELECT MAX(id) FROM '. $GLOBALS['DBHOST_TBL_PREFIX'] .'users)');
			$subj = q_singleval('SELECT subject FROM '. $GLOBALS['DBHOST_TBL_PREFIX'] .'msg WHERE id = (SELECT MAX(id) FROM '. $GLOBALS['DBHOST_TBL_PREFIX'] .'msg)');
			send_command('PRIVMSG '. $channel ." :$FORUM_TITLE");
			send_command('PRIVMSG '. $channel ." :There are {$stat->online_users_reg} members, {$stat->online_users_hidden} invisible members and {$stat->online_users_anon} guests visiting this board.");
			send_command('PRIVMSG '. $channel ." :Most users ever online was {$stat->most_online} on ". strftime('%a, %d %B %Y %H:%M', $stat->most_online_time));
			send_command('PRIVMSG '. $channel ." :We have {$stat->user_count} registered users.");
			send_command('PRIVMSG '. $channel ." :The newest registered user is {$user}");
			send_command('PRIVMSG '. $channel ." :Last message on the forum: {$subj}");
			break;
		case ':!exit':
		case ':!die':
		case ':!quit':
		case ':!shutdown':
			send_command('QUIT');
			exit;
		}

		// Call IRC plugins.
		if (defined('plugins')) {
			plugin_call_hook('IRCCOMMAND', $parts);
		}

		flush();	// Force output.
	}

	echo "FUDbot shuts down.\n";
?>
