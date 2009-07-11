/* Add initial welcome message */
INSERT INTO {SQL_TABLE_PREFIX}msg (thread_id, poster_id, reply_to, ip_addr, host_name, post_stamp, update_stamp, updated_by, icon, subject, attach_cnt, poll_id, foff, length, file_id, offset_preview, length_preview, file_id_preview, attach_cache, poll_cache, mlist_msg_id, msg_opt, apr, flag_cc, flag_country) VALUES
(1, 2, 0, '127.0.0.1', NULL, 1237282100, 0, 0, NULL, 'Welcome to FUDforum', 0, 0, -1, 603, 1, -1, 0, 0, NULL, NULL, NULL, 1, 1, NULL, NULL);

INSERT INTO {SQL_TABLE_PREFIX}msg_store (data) VALUES ('Congratulations! You have successfully installed FUDforum and are well on your way to creating a vibrant community that you and your members can enjoy for years to come.<br />\r\n<br />\r\nTo get you started, please read the documentation on our <a href="http://cvs.prohost.org/" target="_blank">wiki</a> and report any problems on the support forum at <a href="http://fudforum.org" target="_blank">http://fudforum.org</a>. You are also welcome to join us on irc.freenode.net in the <i>FUDforum</i> channel.<br />\r\n<br />\r\nLogin and head over to the Admin Control Panel to start configuring your forum.<br />\r\n<br />\r\nThanks for using our software.<br />\r\n<br />\r\nEnjoy!<br />\r\nThe FUDforum team<br />\r\n');

INSERT INTO {SQL_TABLE_PREFIX}thread (forum_id, root_msg_id, last_post_date, replies, views, rating, n_rating, last_post_id, moved_to, orderexpiry, thread_opt, tdescr) VALUES (1, 1, 1237282100, 0, 1, 0, 0, 1, 0, 1000000000, 0, '');

INSERT INTO {SQL_TABLE_PREFIX}tv_1 (seq, thread_id, iss) VALUES (1, 1, 0);

INSERT INTO {SQL_TABLE_PREFIX}forum_read (forum_id, user_id, last_view) VALUES (1, 2, 0);

/* Subscribe to and bookmark the topic */
INSERT INTO {SQL_TABLE_PREFIX}thread_notify (user_id, thread_id) VALUES (2, 1);
INSERT INTO {SQL_TABLE_PREFIX}bookmarks (user_id, thread_id) VALUES (2, 1);
