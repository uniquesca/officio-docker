-- -------------------------------------------------------------------------------------------------------------------------
-- ID: 4
-- RELEASE: DM v2
-- PROBLEM: DM has migrations table name different from main Officio
-- SOLUTION: Rename the table
-- INSTRUCTIONS: Run query in the Backend DB
-- EXECUTED: not yet
-- -------------------------------------------------------------------------------------------------------------------------
ALTER TABLE schema_version RENAME phinx_log;