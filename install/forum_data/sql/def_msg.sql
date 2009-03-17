INSERT INTO {SQL_TABLE_PREFIX}msg (id, thread_id, poster_id, reply_to, ip_addr, host_name, post_stamp, update_stamp, updated_by, icon, subject, attach_cnt, poll_id, foff, length, file_id, offset_preview, length_preview, file_id_preview, attach_cache, poll_cache, mlist_msg_id, msg_opt, apr, flag_cc, flag_country) VALUES
(1, 1, 2, 0, '127.0.0.1', NULL, 1237282100, 0, 0, NULL, 'Welcome to FUDforum', 0, 0, -1, 400, 4, -1, 0, 0, NULL, NULL, NULL, 1, 1, NULL, NULL);

INSERT INTO {SQL_TABLE_PREFIX}msg_store (id, data) VALUES (4, 'Congratulations! You have successfully installed FUDforum.<br />\r\n<br />\r\nPlease read the FUDforum documentation on our <a href="http://cvs.prohost.org/" target="_blank">wiki</a> and report any problems on the support forum at <a href="http://fudforum.org" target="_blank">http://fudforum.org</a>.<br />\r\n<br />\r\nEnjoy!<br />\r\nThe FUDforum team<br />\r\n');

INSERT INTO {SQL_TABLE_PREFIX}thread (id, forum_id, root_msg_id, last_post_date, replies, views, rating, n_rating, last_post_id, moved_to, orderexpiry, thread_opt, tdescr) VALUES (1, 1, 1, 1237282100, 0, 1, 0, 0, 1, 0, 1000000000, 0, '');

INSERT INTO {SQL_TABLE_PREFIX}tv_1 (id, seq, thread_id, iss) VALUES (1, 1, 1, 0);

INSERT INTO {SQL_TABLE_PREFIX}forum_read (id, forum_id, user_id, last_view) VALUES (1, 1, 2, 0);

UPDATE {SQL_TABLE_PREFIX}forum SET thread_count=1, post_count=1, last_post_id=1 WHERE id=1;
