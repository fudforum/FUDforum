INSERT INTO {SQL_TABLE_PREFIX}cat VALUES (1,'Test Category','Just a test category','Y','OPEN',1);
SELECT setval('{SQL_TABLE_PREFIX}cat_id_seq', 1);
