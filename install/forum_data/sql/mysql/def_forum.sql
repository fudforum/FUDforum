INSERT INTO {SQL_TABLE_PREFIX}forum (id, cat_id, name, date_created, max_attach_size) VALUES(1, 1, 'TestForum', UNIX_TIMESTAMP(), 1024);
INSERT INTO {SQL_TABLE_PREFIX}groups (id, name, forum_id, groups_opt) VALUES (3, 'TestForum', 1, 1|2|4|8|16|32|64|128|256|512|1024|2048|4096|8192|16384|32768)
INSERT INTO {SQL_TABLE_PREFIX}group_resources(group_id, resource_id) VALUES(3, 1);
INSERT INTO {SQL_TABLE_PREFIX}group_members (user_id, group_id, group_members_opt) VALUES (0, 3, 1|2);
INSERT INTO {SQL_TABLE_PREFIX}group_members (user_id, group_id, group_members_opt) VALUES (2147483647, 3, 1|2|4|8|128|256|512|1024|16384|32768);
INSERT INTO {SQL_TABLE_PREFIX}group_cache (user_id, resource_id, group_id, group_cache_opt) VALUES (0, 1, 3, 1|2);
INSERT INTO {SQL_TABLE_PREFIX}group_cache (user_id, resource_id, group_id, group_cache_opt) VALUES (2147483647, 1, 3, 1|2|4|8|128|256|512|1024|16384|32768);
