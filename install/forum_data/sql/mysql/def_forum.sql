INSERT INTO {SQL_TABLE_PREFIX}forum (id, cat_id,name,date_created,max_attach_size) VALUES(1, 1,'TestForum',UNIX_TIMESTAMP(),1024);
INSERT INTO {SQL_TABLE_PREFIX}groups (id, name, res, res_id, p_VISIBLE, p_READ, p_POST, p_REPLY, p_EDIT, p_DEL, p_STICKY, p_POLL, p_FILE, p_VOTE, p_RATE, p_SPLIT, p_LOCK, p_MOVE, p_SML, p_IMG) VALUES (3, 'TestForum', 'forum', 1,  'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y');
INSERT INTO {SQL_TABLE_PREFIX}group_resources(group_id, resource_type, resource_id) VALUES(3, 'forum', 1);
INSERT INTO {SQL_TABLE_PREFIX}group_members (user_id, group_id,  up_VISIBLE, up_READ) VALUES (0, 3, 'Y', 'Y');
INSERT INTO {SQL_TABLE_PREFIX}group_members (user_id, group_id, up_VISIBLE, up_READ, up_POST, up_REPLY, up_POLL, up_FILE, up_VOTE, up_RATE, up_SML, up_IMG) VALUES (4294967295, 3, 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y');
INSERT INTO {SQL_TABLE_PREFIX}group_cache (user_id, resource_type, resource_id, group_id, p_VISIBLE, p_READ) VALUES (0, 'forum', 1, 3, 'Y', 'Y');
INSERT INTO {SQL_TABLE_PREFIX}group_cache (user_id, resource_type, resource_id, group_id, p_VISIBLE, p_READ, p_POST, p_REPLY, p_POLL, p_FILE, p_VOTE, p_RATE, p_SML, p_IMG) VALUES (4294967295, 'forum', 1, 3, 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y');
