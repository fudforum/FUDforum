INSERT INTO {SQL_TABLE_PREFIX}forum (cat_id, name, descr, date_created, thread_count, post_count, last_post_id, max_attach_size, max_file_attachments, view_order) VALUES(1, 'First Forum', 'Test forum for demonstration purposes. Please login and navigate to <font color="darkgreen">Administration</font> -> <font color="darkgreen">Category &amp; Forum Management</font> to create your own categories and forums.', {UNIX_TIMESTAMP}, 1, 1, 1, 1024, 5, 1);
INSERT INTO {SQL_TABLE_PREFIX}forum (cat_id, name, descr, date_created, thread_count, post_count, last_post_id, max_attach_size, max_file_attachments, view_order) VALUES(2, 'Members', 'This is a private forum for registered users.', {UNIX_TIMESTAMP}, 0, 0, 0, 1024, 5, 2);
INSERT INTO {SQL_TABLE_PREFIX}forum (cat_id, name, descr, date_created, thread_count, post_count, last_post_id, max_attach_size, max_file_attachments, view_order) VALUES(2, 'Staff', 'Private forum for staff (Administrators and Moderators).', {UNIX_TIMESTAMP}, 0, 0, 0, 1024, 5, 3);

INSERT INTO {SQL_TABLE_PREFIX}fc_view (c, f) VALUES(1, 1);
INSERT INTO {SQL_TABLE_PREFIX}fc_view (c, f) VALUES(2, 2);
INSERT INTO {SQL_TABLE_PREFIX}fc_view (c, f) VALUES(2, 3);

INSERT INTO {SQL_TABLE_PREFIX}groups (name, forum_id, groups_opt) VALUES('Global Anonymous Access', 0, 1|2|262144);
INSERT INTO {SQL_TABLE_PREFIX}groups (name, forum_id, groups_opt) VALUES('Global Registered Access', 0, 1|2|4|8|128|256|512|1024|16384|32768|262144);
INSERT INTO {SQL_TABLE_PREFIX}groups (name, forum_id, groups_opt) VALUES ('First Forum', 1, 1|2|4|8|16|32|64|128|256|512|1024|2048|4096|8192|16384|32768|262144);
INSERT INTO {SQL_TABLE_PREFIX}groups (name, forum_id, groups_opt) VALUES ('Members', 2, 1|2|4|8|16|32|64|128|256|512|1024|2048|4096|8192|16384|32768|262144);
INSERT INTO {SQL_TABLE_PREFIX}groups (name, forum_id, groups_opt) VALUES ('Staff', 3, 1|2|4|8|16|32|64|128|256|512|1024|2048|4096|8192|16384|32768|262144);

INSERT INTO {SQL_TABLE_PREFIX}group_resources (group_id, resource_id) VALUES(3, 1);
INSERT INTO {SQL_TABLE_PREFIX}group_resources (group_id, resource_id) VALUES(4, 2);
INSERT INTO {SQL_TABLE_PREFIX}group_resources (group_id, resource_id) VALUES(5, 3);

INSERT INTO {SQL_TABLE_PREFIX}group_members (user_id, group_id, group_members_opt) VALUES (0,          3, 1|2|262144|65536);
INSERT INTO {SQL_TABLE_PREFIX}group_members (user_id, group_id, group_members_opt) VALUES (2147483647, 3, 1|2|4|8|128|256|512|1024|16384|32768|262144|65536);
INSERT INTO {SQL_TABLE_PREFIX}group_members (user_id, group_id, group_members_opt) VALUES (0,          4, 0);
INSERT INTO {SQL_TABLE_PREFIX}group_members (user_id, group_id, group_members_opt) VALUES (2147483647, 4, 1|2|4|8|128|256|512|1024|16384|32768|262144|65536);
INSERT INTO {SQL_TABLE_PREFIX}group_members (user_id, group_id, group_members_opt) VALUES (0,          5, 0);
INSERT INTO {SQL_TABLE_PREFIX}group_members (user_id, group_id, group_members_opt) VALUES (2,          5, 1|2|4|8|128|256|512|1024|16384|32768|262144|65536);
INSERT INTO {SQL_TABLE_PREFIX}group_members (user_id, group_id, group_members_opt) VALUES (2147483647, 5, 0);

INSERT INTO {SQL_TABLE_PREFIX}group_cache (user_id, resource_id, group_id, group_cache_opt) VALUES (0,          1, 3, 1|2|262144);
INSERT INTO {SQL_TABLE_PREFIX}group_cache (user_id, resource_id, group_id, group_cache_opt) VALUES (2147483647, 1, 3, 1|2|4|8|128|256|512|1024|16384|32768|262144);
INSERT INTO {SQL_TABLE_PREFIX}group_cache (user_id, resource_id, group_id, group_cache_opt) VALUES (0,          2, 4, 0);
INSERT INTO {SQL_TABLE_PREFIX}group_cache (user_id, resource_id, group_id, group_cache_opt) VALUES (2147483647, 2, 4, 1|2|4|8|128|256|512|1024|16384|32768|262144);
INSERT INTO {SQL_TABLE_PREFIX}group_cache (user_id, resource_id, group_id, group_cache_opt) VALUES (0,          3, 4, 0);
INSERT INTO {SQL_TABLE_PREFIX}group_cache (user_id, resource_id, group_id, group_cache_opt) VALUES (2,          3, 4, 1|2|4|8|128|256|512|1024|16384|32768|262144);
INSERT INTO {SQL_TABLE_PREFIX}group_cache (user_id, resource_id, group_id, group_cache_opt) VALUES (2147483647, 3, 4, 0);

# Create additional thread view tables.
DROP TABLE {SQL_TABLE_PREFIX}tv_2;
CREATE TABLE {SQL_TABLE_PREFIX}tv_2
(
	thread_id	INT NOT NULL PRIMARY KEY,
	seq		INT NOT NULL,
	iss		INT NOT NULL
);
CREATE INDEX {SQL_TABLE_PREFIX}tv_2_seq ON {SQL_TABLE_PREFIX}tv_2 (seq);

DROP TABLE {SQL_TABLE_PREFIX}tv_3;
CREATE TABLE {SQL_TABLE_PREFIX}tv_3
(
	thread_id	INT NOT NULL PRIMARY KEY,
	seq		INT NOT NULL,
	iss		INT NOT NULL
);
CREATE INDEX {SQL_TABLE_PREFIX}tv_3_seq ON {SQL_TABLE_PREFIX}tv_3 (seq);
