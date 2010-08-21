/* Add initial welcome message. */
INSERT INTO {SQL_TABLE_PREFIX}msg (thread_id, poster_id, reply_to, ip_addr, host_name, post_stamp, update_stamp, updated_by, icon, subject, attach_cnt, poll_id, foff, length, file_id, offset_preview, length_preview, file_id_preview, attach_cache, poll_cache, mlist_msg_id, msg_opt, apr) 
VALUES (1, 2, 0, '127.0.0.1', NULL, {UNIX_TIMESTAMP}, 0, 0, NULL, 'Welcome to FUDforum', 0, 0 /*1*/, -1, 459, 1, -1, 0, 0, NULL, NULL /*'a:2:{i:1;a:2:{i:0;s:3:"Yes";i:1;s:1:"0";}i:2;a:2:{i:0;s:2:"No";i:1;s:1:"0";}}' */, NULL, 1, 1);

INSERT INTO {SQL_TABLE_PREFIX}msg_store (data)
VALUES ('<b>Congratulations!</b> You have successfully installed FUDforum and are well on your way to creating a vibrant community that you and your members can enjoy for years to come.<br />\r\n<br />\r\nTo get you started, please read the documentation on our <a href="http://cvs.prohost.org/" target="_blank">wiki</a> and report any problems on the support forum at <a href="http://fudforum.org" target="_blank">http://fudforum.org</a>. You are also welcome to join us on <i>irc.freenode.net</i> in the <i>FUDforum</i> channel.<br />\r\n<br />\r\nLogin and head over to the Admin Control Panel to start configuring your forum.<br />\r\n<br />\r\nThanks for using our software.<br />\r\n<br />\r\nEnjoy!<br />\r\nThe FUDforum team<br />\r\n');

INSERT INTO {SQL_TABLE_PREFIX}thread (forum_id, root_msg_id, last_post_date, replies, views, rating, n_rating, last_post_id, moved_to, orderexpiry, thread_opt, tdescr)
VALUES (1, 1, {UNIX_TIMESTAMP}, 0, 1, 5, 1, 1, 0, 1000000000, 4, 'Welcome, welcome, welcome!');

INSERT INTO {SQL_TABLE_PREFIX}tv_1 (seq, thread_id, iss) VALUES (1, 1, 0);

INSERT INTO {SQL_TABLE_PREFIX}forum_read (forum_id, user_id, last_view) VALUES (1, 2, 0);

/* Subscribe to and bookmark the topic. */
INSERT INTO {SQL_TABLE_PREFIX}thread_notify (user_id, thread_id) VALUES (2, 1);
INSERT INTO {SQL_TABLE_PREFIX}bookmarks (user_id, thread_id) VALUES (2, 1);

/* Give it a 5-star rating. */
INSERT INTO {SQL_TABLE_PREFIX}thread_rate_track (thread_id, user_id, stamp, rating) VALUES (1, 2, {UNIX_TIMESTAMP}, 5);

/* Add poll to it. */
/* INSERT INTO {SQL_TABLE_PREFIX}poll (name, owner, creation_date, forum_id) VALUES ('Do you like what you see?', 2, {UNIX_TIMESTAMP}, 1); */
/* INSERT INTO {SQL_TABLE_PREFIX}poll_opt (poll_id, name) VALUES (1, 'Yes'); */
/* INSERT INTO {SQL_TABLE_PREFIX}poll_opt (poll_id, name) VALUES (1, 'No'); */
